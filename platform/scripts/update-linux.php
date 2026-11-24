<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/UpdateManifest.php';
require_once __DIR__ . '/lib/LinuxUpdateTransaction.php';

const DCP_RELEASE_REPOSITORY = 'AmrAdeldotcom/DCP';
const DCP_LINUX_MANIFEST = 'DCP-Linux-update.json';
const DCP_LINUX_SIGNATURE = 'DCP-Linux-update.json.sig';
const DCP_RELEASE_REQUIRED = [
    'DCP-Windows-x64.zip',
    'DCP-macOS-Intel.zip',
    'DCP-macOS-AppleSilicon.zip',
    'DCP-Linux-x64.tar.gz',
    'DCP-Windows-update.json',
    'DCP-Windows-update.json.sig',
    'DCP-Linux-update.json',
    'DCP-Linux-update.json.sig',
    'DCP-macOS-Intel-update.json',
    'DCP-macOS-Intel-update.json.sig',
    'DCP-macOS-AppleSilicon-update.json',
    'DCP-macOS-AppleSilicon-update.json.sig',
];

final class DcpLinuxUpdater
{
    private string $root;
    private string $userData;
    private string $publicKey;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || !is_dir($real)) {
            throw new RuntimeException('DCP installation root is missing.');
        }
        $this->root = rtrim($real, DIRECTORY_SEPARATOR);
        $this->userData = $this->root . '/DCP-UserData/Linux';
        $this->publicKey = $this->root . '/platform/update/release-public.pem';
        if (!is_dir($this->userData) || !is_file($this->root . '/platform/version.json')) {
            throw new RuntimeException('This is not a complete portable DCP Linux installation.');
        }
        if (!extension_loaded('curl') || !extension_loaded('openssl')) {
            throw new RuntimeException('DCP updater requires PHP cURL and OpenSSL extensions.');
        }
    }

    public function check(): array
    {
        $versionData = $this->installedVersion();
        $release = $this->latestCompleteRelease();
        if ($release === null) {
            return ['ok' => true, 'available' => false, 'message' => 'No complete synchronized signed DCP release is available.'];
        }
        $tag = (string)($release['tag_name'] ?? '');
        if (!preg_match('/^v\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $tag)) {
            throw new RuntimeException('GitHub returned an invalid DCP release tag.');
        }
        $assets = $this->releaseAssets($release);
        $manifestBytes = $this->downloadToString($assets[DCP_LINUX_MANIFEST]);
        $signatureBytes = $this->downloadToString($assets[DCP_LINUX_SIGNATURE]);
        $inspection = DcpUpdateManifest::inspect(
            $manifestBytes,
            $signatureBytes,
            $this->publicKey,
            'linux',
            'x64',
            (string)$versionData['version'],
            (int)$versionData['packageRevision']
        );
        $manifest = $inspection['manifest'];
        if (($manifest['release_tag'] ?? '') !== $tag) {
            throw new RuntimeException('Signed Linux manifest does not belong to the selected GitHub release.');
        }
        $latestVersion = (string)$manifest['version'];
        $latestRevision = (int)$manifest['package_revision'];
        $this->verifySynchronizedManifests($assets, $tag, $manifest);
        $isNewer = version_compare($latestVersion, (string)$versionData['version'], '>')
            || ($latestVersion === (string)$versionData['version'] && $latestRevision > (int)$versionData['packageRevision']);
        if (!$isNewer) {
            return [
                'ok' => true,
                'available' => false,
                'version' => $versionData['version'],
                'packageRevision' => $versionData['packageRevision'],
                'message' => 'DCP is up to date.',
            ];
        }
        return [
            'ok' => true,
            'available' => true,
            'version' => $latestVersion,
            'packageRevision' => $latestRevision,
            'releaseTag' => $tag,
            'strategy' => $inspection['strategy'],
            'manifest' => $manifest,
            'manifestBytes' => $manifestBytes,
            'signatureBytes' => $signatureBytes,
            'message' => 'A verified DCP update is available.',
        ];
    }

    public function apply(): array
    {
        $check = $this->check();
        if (empty($check['available'])) {
            return $check;
        }
        $manifest = $check['manifest'];
        $strategy = (string)$check['strategy'];
        $asset = $strategy === 'delta' ? $manifest['delta']['asset'] : $manifest['full'];
        $work = sys_get_temp_dir() . '/dcp-linux-update-' . bin2hex(random_bytes(8));
        $archive = $work . '/' . basename((string)$asset['name']);
        $stage = $work . '/payload';
        if (!mkdir($work, 0700, true)) {
            throw new RuntimeException('Could not create a private DCP update staging directory.');
        }
        $criticalSnapshot = [];
        try {
            $criticalSnapshot = $this->snapshotCriticalData($work);
            $this->downloadToFile((string)$asset['url'], $archive, (int)$asset['size']);
            DcpUpdateManifest::verifyAssetFile($archive, $asset);
            $this->extractArchive($archive, $stage, $strategy === 'full');
            $transaction = new DcpLinuxUpdateTransaction($this->root, $this->userData);
            if ($strategy === 'delta') {
                $result = $transaction->applyDelta(
                    $stage,
                    $manifest['delta']['files'],
                    $manifest['delta']['deleted']
                );
            } else {
                $result = $transaction->applyFullPackage($stage);
            }
            try {
                $this->healthCheck($work);
                $result['healthCheck'] = 'passed';
            } catch (Throwable $healthError) {
                $rollbackFailure = null;
                try { $transaction->rollbackLatest(); } catch (Throwable $error) { $rollbackFailure = $error; }
                try { $this->restoreCriticalData($criticalSnapshot); } catch (Throwable $error) {
                    throw new RuntimeException('Post-update health check failed and critical user-data restoration also failed: ' . $error->getMessage(), 0, $healthError);
                }
                if ($rollbackFailure !== null) {
                    throw new RuntimeException('Post-update health check failed; critical user data was restored, but application rollback failed: ' . $rollbackFailure->getMessage(), 0, $healthError);
                }
                throw new RuntimeException('Post-update health check failed; previous application files and critical SQLite/key files were restored: ' . $healthError->getMessage(), 0, $healthError);
            }
            $result['version'] = $manifest['version'];
            $result['packageRevision'] = $manifest['package_revision'];
            $result['releaseTag'] = $manifest['release_tag'];
            $result['message'] = 'Verified DCP package update applied. Launch DCP again to start the updated version.';
            return $result;
        } finally {
            $this->removeTree($work);
        }
    }

    private function installedVersion(): array
    {
        $data = json_decode((string)file_get_contents($this->root . '/platform/version.json'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['version'] ?? null) || !is_int($data['packageRevision'] ?? null)) {
            throw new RuntimeException('Installed DCP version metadata is invalid.');
        }
        return ['version' => ltrim($data['version'], 'v'), 'packageRevision' => $data['packageRevision']];
    }

    private function latestCompleteRelease(): ?array
    {
        $url = 'https://api.github.com/repos/' . DCP_RELEASE_REPOSITORY . '/releases?per_page=30';
        $bytes = $this->request($url, 4 * 1024 * 1024);
        $releases = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($releases) || !array_is_list($releases)) {
            throw new RuntimeException('GitHub releases response is invalid.');
        }
        foreach ($releases as $release) {
            if (!is_array($release) || !empty($release['draft']) || !is_array($release['assets'] ?? null)) {
                continue;
            }
            $names = [];
            foreach ($release['assets'] as $asset) {
                if (is_array($asset) && is_string($asset['name'] ?? null)) {
                    $names[$asset['name']] = (string)($asset['browser_download_url'] ?? '');
                }
            }
            if (count(array_diff(DCP_RELEASE_REQUIRED, array_keys($names))) === 0) {
                return $release;
            }
        }
        return null;
    }

    private function releaseAssets(array $release): array
    {
        $tag = (string)$release['tag_name'];
        $assets = [];
        foreach ($release['assets'] as $asset) {
            if (!is_array($asset) || !is_string($asset['name'] ?? null)) continue;
            $name = $asset['name'];
            if (!in_array($name, DCP_RELEASE_REQUIRED, true)) continue;
            $url = (string)($asset['browser_download_url'] ?? '');
            $expected = 'https://github.com/' . DCP_RELEASE_REPOSITORY . '/releases/download/' . $tag . '/' . rawurlencode($name);
            if (!hash_equals($expected, $url)) {
                throw new RuntimeException('A synchronized release asset has an unexpected URL: ' . $name);
            }
            $assets[$name] = $url;
        }
        foreach ([DCP_LINUX_MANIFEST, DCP_LINUX_SIGNATURE] as $required) {
            if (!isset($assets[$required])) throw new RuntimeException('Signed Linux update metadata is incomplete.');
        }
        return $assets;
    }


    private function verifySynchronizedManifests(array $assets, string $tag, array $linuxManifest): void
    {
        $platforms = [
            'DCP-Windows-update.json' => ['windows', 'x64'],
            'DCP-macOS-Intel-update.json' => ['macos', 'x64'],
            'DCP-macOS-AppleSilicon-update.json' => ['macos', 'arm64'],
        ];
        foreach ($platforms as $name => [$platform, $architecture]) {
            $signatureName = $name . '.sig';
            $bytes = $this->downloadToString($assets[$name]);
            $signature = $this->downloadToString($assets[$signatureName]);
            $inspection = DcpUpdateManifest::inspect(
                $bytes,
                $signature,
                $this->publicKey,
                $platform,
                $architecture,
                (string)$linuxManifest['version'],
                (int)$linuxManifest['package_revision']
            );
            $other = $inspection['manifest'];
            foreach (['version', 'release_tag', 'package_revision', 'commit'] as $field) {
                if (($other[$field] ?? null) !== ($linuxManifest[$field] ?? null)) {
                    throw new RuntimeException('Signed release manifests are not synchronized across platforms: ' . $name . ' (' . $field . ' mismatch).');
                }
            }
            if (($other['key_id'] ?? null) !== ($linuxManifest['key_id'] ?? null)) {
                throw new RuntimeException('Signed release manifests do not share the same signing key identifier.');
            }
        }
    }

    private function downloadToString(string $url): string
    {
        return $this->request($url, 2 * 1024 * 1024);
    }

    private function request(string $url, int $maxBytes): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || (strtolower((string)($parts['host'] ?? '')) !== 'api.github.com' && strtolower((string)($parts['host'] ?? '')) !== 'github.com')) {
            throw new RuntimeException('DCP update metadata must be fetched over HTTPS from GitHub.');
        }
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('Could not initialize HTTPS request.');
        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'DCP-Linux-Updater/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $result = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($result === false || $status !== 200) {
            throw new RuntimeException('GitHub update request failed (' . $status . '): ' . ($error ?: 'HTTP error'));
        }
        return $body;
    }

    private function downloadToFile(string $url, string $destination, int $maxBytes): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || strtolower((string)($parts['host'] ?? '')) !== 'github.com') {
            throw new RuntimeException('Update package URL is not an official HTTPS GitHub URL.');
        }
        $handle = fopen($destination, 'xb');
        if ($handle === false) throw new RuntimeException('Could not create private update archive file.');
        $written = 0;
        $curl = curl_init($url);
        if ($curl === false) { fclose($handle); throw new RuntimeException('Could not initialize package download.'); }
        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 900,
            CURLOPT_USERAGENT => 'DCP-Linux-Updater/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function ($curl, float $downloadTotal, float $downloaded) use ($maxBytes, &$written): int {
                $written = (int)$downloaded;
                return $downloaded > $maxBytes ? 1 : 0;
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if ($ok === false || $status !== 200 || $written > $maxBytes) {
            @unlink($destination);
            throw new RuntimeException('DCP package download failed or exceeded its signed size: ' . ($error ?: 'HTTP ' . $status));
        }
    }

    private function extractArchive(string $archive, string $stage, bool $full): void
    {
        $command = [
            'python3',
            $this->root . '/platform/scripts/extract-update-tar.py',
            $archive,
            $stage,
        ];
        if ($full) $command[] = '--full-package';
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        if (!is_resource($process)) throw new RuntimeException('Could not start the safe Linux archive extractor.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) throw new RuntimeException('DCP update archive extraction was rejected: ' . trim((string)$stderr . ' ' . (string)$stdout));
    }


    private function snapshotCriticalData(string $work): array
    {
        $dataRoot = $this->userData . '/data';
        $storageRoot = $this->userData . '/storage';
        $paths = [
            $dataRoot . '/dcp.sqlite',
            $dataRoot . '/dcp.sqlite-wal',
            $dataRoot . '/dcp.sqlite-shm',
            $dataRoot . '/dcp.sqlite-journal',
            $storageRoot . '/keys/master.key',
        ];
        $snapshot = [];
        foreach ($paths as $index => $path) {
            $this->assertSafeUserDataPath($path);
            $saved = $work . '/protected-data/' . $index . '.bin';
            if (file_exists($path) && !is_file($path)) throw new RuntimeException('Critical DCP user-data path is not a regular file.');
            $exists = is_file($path);
            if ($exists) {
                if (!is_readable($path) || !is_dir(dirname($saved)) && !mkdir(dirname($saved), 0700, true)) {
                    throw new RuntimeException('Could not prepare the protected-data update snapshot.');
                }
                if (!copy($path, $saved)) throw new RuntimeException('Could not snapshot a critical DCP user-data file.');
            }
            $snapshot[] = ['path' => $path, 'saved' => $saved, 'exists' => $exists, 'mode' => $exists ? (fileperms($path) & 0777) : 0600];
        }
        return $snapshot;
    }

    private function restoreCriticalData(array $snapshot): void
    {
        foreach ($snapshot as $entry) {
            $path = (string)$entry['path'];
            $this->assertSafeUserDataPath($path);
            if (empty($entry['exists'])) {
                if (is_file($path) && !unlink($path)) throw new RuntimeException('Could not remove newly created critical user-data file.');
                continue;
            }
            $this->ensureSafeUserDataDirectory(dirname($path));
            $temporary = $path . '.dcp-restore-' . bin2hex(random_bytes(5));
            if (!copy((string)$entry['saved'], $temporary)) throw new RuntimeException('Could not restore critical DCP user data.');
            chmod($temporary, (int)$entry['mode']);
            if (!rename($temporary, $path)) { @unlink($temporary); throw new RuntimeException('Could not atomically restore critical DCP user data.'); }
        }
    }


    private function assertSafeUserDataPath(string $path): void
    {
        if (is_link($this->userData)) throw new RuntimeException('DCP user-data root may not be a symbolic link.');
        $root = realpath($this->userData);
        if ($root === false || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Critical DCP data path is outside the protected user-data root.');
        }
        $relative = substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
        $cursor = $root;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $part) {
            $cursor .= DIRECTORY_SEPARATOR . $part;
            if (is_link($cursor)) throw new RuntimeException('Critical DCP user-data path contains a symbolic link.');
            if (file_exists($cursor) && $cursor !== $path && !is_dir($cursor)) {
                throw new RuntimeException('Critical DCP user-data parent is not a directory.');
            }
        }
    }

    private function ensureSafeUserDataDirectory(string $directory): void
    {
        if (is_link($this->userData)) throw new RuntimeException('DCP user-data root may not be a symbolic link.');
        $root = realpath($this->userData);
        if ($root === false || !str_starts_with($directory, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('DCP user-data directory is outside the protected root.');
        }
        $relative = substr($directory, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
        $cursor = $root;
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $part) {
            $cursor .= DIRECTORY_SEPARATOR . $part;
            if (is_link($cursor)) throw new RuntimeException('Critical DCP user-data directory contains a symbolic link.');
            if (file_exists($cursor)) {
                if (!is_dir($cursor)) throw new RuntimeException('Critical DCP user-data directory path is occupied by a file.');
                continue;
            }
            if (!mkdir($cursor, 0700) && !is_dir($cursor)) throw new RuntimeException('Could not recreate the critical user-data directory.');
            chmod($cursor, 0700);
        }
    }

    private function healthCheck(string $work): void
    {
        $verify = proc_open([PHP_BINARY, $this->root . '/platform/scripts/verify-runtime.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        if (!is_resource($verify)) throw new RuntimeException('Could not run the post-update PHP runtime verification.');
        fclose($pipes[0]); $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $verifyCode = proc_close($verify);
        if ($verifyCode !== 0) throw new RuntimeException('Updated PHP runtime verification failed: ' . trim((string)$stdout . ' ' . (string)$stderr));

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) throw new RuntimeException('Could not reserve a local health-check port: ' . $errstr);
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $port = (int)substr((string)$address, strrpos((string)$address, ':') + 1);
        $log = $work . '/health-check.log';
        $environment = getenv();
        if (!is_array($environment)) $environment = [];
        $environment['DCP_MODE'] = 'desktop';
        $environment['DCP_USER_DATA_DIR'] = $this->userData;
        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $this->root . '/platform/public', $this->root . '/platform/public/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $serverPipes,
            $this->root . '/platform',
            $environment
        );
        if (!is_resource($server)) throw new RuntimeException('Could not start the temporary DCP health-check server.');
        $healthy = false;
        try {
            for ($attempt = 0; $attempt < 30; $attempt++) {
                $status = curl_init('http://127.0.0.1:' . $port . '/api/status');
                curl_setopt_array($status, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 3, CURLOPT_HTTPHEADER => ['Connection: close']]);
                $body = curl_exec($status);
                $code = (int)curl_getinfo($status, CURLINFO_RESPONSE_CODE);
                curl_close($status);
                if ($body !== false && $code === 200) {
                    $json = json_decode($body, true);
                    if (is_array($json) && ($json['ok'] ?? false) === true) { $healthy = true; break; }
                }
                $state = proc_get_status($server);
                if (empty($state['running'])) break;
                usleep(200000);
            }
        } finally {
            proc_terminate($server);
            proc_close($server);
        }
        if (!$healthy) {
            $details = is_file($log) ? substr((string)file_get_contents($log), -3000) : '';
            throw new RuntimeException('Updated DCP /api/status did not become healthy. ' . $details);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) return;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($path);
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
try {
    $root = getenv('DCP_INSTALL_ROOT') ?: dirname(__DIR__, 2);
    $updater = new DcpLinuxUpdater($root);
    $mode = $argv[1] ?? '--check';
    if ($mode === '--check') {
        $result = $updater->check();
        unset($result['manifest'], $result['manifestBytes'], $result['signatureBytes']);
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }
    if ($mode === '--apply') {
        $result = $updater->apply();
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }
    throw new InvalidArgumentException('Usage: php update-linux.php --check|--apply');
} catch (Throwable $error) {
    fwrite(STDERR, 'DCP Linux updater: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
}
