<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/UpdateManifest.php';
require_once __DIR__ . '/lib/LinuxUpdateTransaction.php';
require_once __DIR__ . '/lib/GitHubReleaseFeed.php';

const DCP_RELEASE_REPOSITORY = 'AmrAdeldotcom/DCP';
const DCP_LINUX_MANIFEST = 'DCP-Linux-update-v3.json';
const DCP_LINUX_SIGNATURE = 'DCP-Linux-update-v3.json.sig';
const DCP_RELEASE_OPTIONAL = [
    'DCP-Windows-x64-delta.zip',
    'DCP-Linux-x64-delta.tar.gz',
    'DCP-macOS-Intel-delta.zip',
    'DCP-macOS-AppleSilicon-delta.zip',
];
const DCP_RELEASE_REQUIRED = [
    'DCP-Windows-x64.zip',
    'DCP-macOS-Intel.zip',
    'DCP-macOS-AppleSilicon.zip',
    'DCP-Linux-x64.tar.gz',
    'DCP-Windows-update-v3.json',
    'DCP-Windows-update-v3.json.sig',
    'DCP-Linux-update-v3.json',
    'DCP-Linux-update-v3.json.sig',
    'DCP-macOS-Intel-update-v3.json',
    'DCP-macOS-Intel-update-v3.json.sig',
    'DCP-macOS-AppleSilicon-update-v3.json',
    'DCP-macOS-AppleSilicon-update-v3.json.sig',
];

final class DcpLinuxUpdater
{
    private string $root;
    private string $userData;
    private string $publicKey;
    private ?string $releaseDiscoveryNote = null;

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
        $lock = $this->acquireUpdateLock();
        try {
            $this->recoverInterruptedTransactions();
            return $this->checkUnlocked();
        } finally {
            $this->releaseUpdateLock($lock);
        }
    }

    private function checkUnlocked(): array
    {
        $versionData = $this->installedVersion();
        $release = $this->latestCompleteRelease();
        if ($release === null) {
            return [
                'ok' => true,
                'available' => false,
                'retryable' => $this->releaseDiscoveryNote !== null,
                'message' => $this->releaseDiscoveryNote ?? 'No complete synchronized signed DCP release is available.',
            ];
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
        $deltaAssetAvailable = $this->assertManifestAssetsPresent($manifest, $assets);
        if ($inspection['strategy'] === 'delta' && !$deltaAssetAvailable) {
            $inspection['strategy'] = 'full';
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

    public function download(): array
    {
        $lock = $this->acquireUpdateLock();
        try {
            $check = $this->checkUnlocked();
            if (empty($check['available'])) return $check;
            $manifest = $check['manifest'];
            $strategy = (string)$check['strategy'];
            $asset = $strategy === 'delta' ? $manifest['delta']['asset'] : $manifest['full'];
            $pendingRoot = $this->userData . '/storage/updates/pending/' . hash('sha256', (string)$manifest['release_tag']);
            $this->ensureSafeUserDataDirectory($pendingRoot);
            $archive = $pendingRoot . '/' . basename((string)$asset['name']);
            if (is_link($archive)) throw new RuntimeException('Cached DCP update package may not be a symbolic link.');
            $downloaded = false;
            if (is_file($archive)) {
                try {
                    DcpUpdateManifest::verifyAssetFile($archive, $asset);
                } catch (Throwable) {
                    if (!unlink($archive)) throw new RuntimeException('Could not discard a damaged cached DCP update package.');
                }
            }
            if (!is_file($archive)) {
                $this->downloadToFile((string)$asset['url'], $archive, (int)$asset['size']);
                DcpUpdateManifest::verifyAssetFile($archive, $asset);
                $downloaded = true;
            }
            return [
                'ok' => true,
                'available' => true,
                'downloaded' => true,
                'newDownload' => $downloaded,
                'version' => $manifest['version'],
                'packageRevision' => $manifest['package_revision'],
                'releaseTag' => $manifest['release_tag'],
                'strategy' => $strategy,
                'message' => 'The signed DCP update package is downloaded and verified; it will be applied when DCP is closed.',
            ];
        } finally {
            $this->releaseUpdateLock($lock);
        }
    }

    public function apply(): array
    {
        $lock = $this->acquireUpdateLock();
        try {
            $this->assertNoRunningDcpProcesses();
            $this->recoverInterruptedTransactions();
            $check = $this->checkUnlocked();
            if (empty($check['available'])) {
                return $check;
            }
            $manifest = $check['manifest'];
            $strategy = (string)$check['strategy'];
            $asset = $strategy === 'delta' ? $manifest['delta']['asset'] : $manifest['full'];
            $work = sys_get_temp_dir() . '/dcp-linux-update-' . bin2hex(random_bytes(8));
            $pendingRoot = $this->userData . '/storage/updates/pending/' . hash('sha256', (string)$manifest['release_tag']);
            $this->ensureSafeUserDataDirectory($pendingRoot);
            $archive = $pendingRoot . '/' . basename((string)$asset['name']);
            if (is_link($archive)) throw new RuntimeException('Cached DCP update package may not be a symbolic link.');
            $stage = $work . '/payload';
            if (!mkdir($work, 0700, true)) {
                throw new RuntimeException('Could not create a private DCP update staging directory.');
            }
            $criticalSnapshot = [];
            try {
                $criticalSnapshot = $this->snapshotCriticalData($work);
                if (is_file($archive)) {
                    try { DcpUpdateManifest::verifyAssetFile($archive, $asset); }
                    catch (Throwable) { @unlink($archive); }
                }
                if (!is_file($archive)) {
                    $this->downloadToFile((string)$asset['url'], $archive, (int)$asset['size']);
                    DcpUpdateManifest::verifyAssetFile($archive, $asset);
                }
                $this->extractArchive($archive, $stage, $strategy === 'full');
                $transaction = new DcpLinuxUpdateTransaction($this->root, $this->userData);
                if ($strategy === 'delta') {
                    $result = $transaction->applyDelta(
                        $stage,
                        $manifest['delta']['files'],
                        $manifest['delta']['deleted']
                    );
                } else {
                    $result = $transaction->applyFullPackage($stage, $manifest['full']['deleted']);
                }
                try {
                    $this->healthCheck($work);
                    $appliedVersion = $this->installedVersion();
                    if ($appliedVersion['version'] !== (string)$manifest['version']
                        || $appliedVersion['packageRevision'] !== (int)$manifest['package_revision']) {
                        throw new RuntimeException('Installed version metadata does not match the signed update manifest.');
                    }
                    $transaction->markLatestHealthy();
                    $this->removeTree($pendingRoot);
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
        } finally {
            $this->releaseUpdateLock($lock);
        }
    }

    private function acquireUpdateLock()
    {
        $updates = $this->userData . '/storage/updates';
        $this->ensureSafeUserDataDirectory($updates);
        $path = $updates . '/updater.lock';
        if (is_link($path) || (file_exists($path) && !is_file($path))) {
            throw new RuntimeException('DCP updater lock path is not a regular file.');
        }
        $handle = fopen($path, 'c+b');
        if ($handle === false) throw new RuntimeException('Could not open the DCP updater lock.');
        chmod($path, 0600);
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            throw new RuntimeException('Another DCP update or recovery operation is already running.');
        }
        return $handle;
    }

    private function releaseUpdateLock($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function recoverInterruptedTransactions(): void
    {
        $transaction = new DcpLinuxUpdateTransaction($this->root, $this->userData);
        if (!$transaction->hasIncompleteTransactions()) return;
        $this->assertNoRunningDcpProcesses();
        $result = $transaction->recoverInterruptedTransactions();
        if (($result['recovered'] ?? 0) < 1) {
            throw new RuntimeException('An interrupted DCP update was detected but could not be recovered.');
        }
    }

    private function assertNoRunningDcpProcesses(): void
    {
        $binary = realpath($this->root . '/DCP');
        $router = realpath($this->root . '/platform/public/router.php') ?: $this->root . '/platform/public/router.php';
        $pipes = [];
        $process = proc_open(['ps', '-axo', 'pid=,args='], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not inspect running processes; refusing to update while process state is unknown.');
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 || !is_string($stdout)) {
            throw new RuntimeException('Could not inspect running processes; refusing to update: ' . trim((string)$stderr));
        }
        $matchesPath = static function (string $arguments, string $path): bool {
            return preg_match('/(?:^|\\s)' . preg_quote($path, '/') . '(?:\\s|$)/', $arguments) === 1;
        };
        $found = [];
        foreach (preg_split('/\\R/', $stdout) ?: [] as $line) {
            if (!preg_match('/^\\s*(\\d+)\\s+(.*)$/', $line, $match)) continue;
            $pid = (int)$match[1];
            if ($pid < 1 || $pid === getmypid()) continue;
            $arguments = $match[2];
            $isDcpBinary = $binary !== false && $matchesPath($arguments, $binary);
            $isDcpPhpServer = $matchesPath($arguments, $router);
            if ($isDcpBinary || $isDcpPhpServer) $found[] = $pid;
        }
        if ($found !== []) {
            throw new RuntimeException('DCP must be closed before updating. Running DCP process IDs: ' . implode(', ', $found) . '. No process was stopped.');
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
        $feed = $this->request('https://github.com/' . DCP_RELEASE_REPOSITORY . '/releases.atom', 2 * 1024 * 1024);
        $tags = DcpGitHubReleaseFeed::tags($feed, DCP_RELEASE_REPOSITORY);
        foreach ($tags as $index => $tag) {
            $manifestUrl = DcpGitHubReleaseFeed::assetUrl(DCP_RELEASE_REPOSITORY, $tag, DCP_LINUX_MANIFEST);
            $signatureUrl = DcpGitHubReleaseFeed::assetUrl(DCP_RELEASE_REPOSITORY, $tag, DCP_LINUX_SIGNATURE);
            if (!$this->assetExists($manifestUrl) || !$this->assetExists($signatureUrl)) {
                // Releases are published atomically. If the newest published tag
                // has no schema-3 manifest, older tags cannot contain a newer update.
                if ($index === 0) return null;
                continue;
            }

            $assets = [];
            foreach (array_merge(DCP_RELEASE_REQUIRED, DCP_RELEASE_OPTIONAL) as $name) {
                $url = DcpGitHubReleaseFeed::assetUrl(DCP_RELEASE_REPOSITORY, $tag, $name);
                if (in_array($name, DCP_RELEASE_OPTIONAL, true) && !$this->assetExists($url)) {
                    continue;
                }
                $assets[] = ['name' => $name, 'browser_download_url' => $url];
            }
            return ['tag_name' => $tag, 'assets' => $assets];
        }
        return null;
    }

    private function assetExists(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' ||
            strtolower((string)($parts['host'] ?? '')) !== 'github.com') {
            throw new RuntimeException('DCP release asset URL is not an official HTTPS GitHub URL.');
        }
        $curl = curl_init($url);
        if ($curl === false) return false;
        curl_setopt_array($curl, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'DCP-Linux-Updater/1.0',
            CURLOPT_HTTPHEADER => ['Accept: */*'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return $status >= 200 && $status < 300;
    }

    private function releaseAssets(array $release): array
    {
        $tag = (string)$release['tag_name'];
        $assets = [];
        foreach ($release['assets'] as $asset) {
            if (!is_array($asset) || !is_string($asset['name'] ?? null)) continue;
            $name = $asset['name'];
            if (!in_array($name, array_merge(DCP_RELEASE_REQUIRED, DCP_RELEASE_OPTIONAL), true)) continue;
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


    private function assertManifestAssetsPresent(array $manifest, array $assets): bool
    {
        $full = $manifest['full'] ?? null;
        if (!is_array($full)) throw new RuntimeException('A signed update manifest contains no full-package fallback.');
        $fullName = (string)($full['name'] ?? '');
        if ($fullName === '' || !isset($assets[$fullName]) || !hash_equals((string)$assets[$fullName], (string)($full['url'] ?? ''))) {
            throw new RuntimeException('A signed update manifest references a full package absent from the selected GitHub release.');
        }
        $delta = $manifest['delta'] ?? null;
        if (!is_array($delta)) return false;
        $asset = $delta['asset'] ?? null;
        if (!is_array($asset)) throw new RuntimeException('A signed delta manifest contains incomplete asset metadata.');
        $name = (string)($asset['name'] ?? '');
        if ($name === '' || !isset($assets[$name])) return false;
        if (!hash_equals((string)$assets[$name], (string)($asset['url'] ?? ''))) {
            throw new RuntimeException('A signed delta manifest asset URL does not match the selected GitHub release.');
        }
        return true;
    }

    private function verifySynchronizedManifests(array $assets, string $tag, array $linuxManifest): void
    {
        $platforms = [
            'DCP-Windows-update-v3.json' => ['windows', 'x64'],
            'DCP-macOS-Intel-update-v3.json' => ['macos', 'x64'],
            'DCP-macOS-AppleSilicon-update-v3.json' => ['macos', 'arm64'],
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
            $this->assertManifestAssetsPresent($other, $assets);
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
        $headers = ['Accept: */*'];
        $token = trim((string)(getenv('DCP_GITHUB_API_TOKEN') ?: getenv('DCP_GITHUB_TOKEN') ?: getenv('GITHUB_TOKEN') ?: ''));
        if (strtolower((string)($parts['host'] ?? '')) === 'api.github.com' && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'DCP-Linux-Updater/1.0',
            CURLOPT_HTTPHEADER => $headers,
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
            throw new RuntimeException('GitHub update request failed (' . $status . '): ' . ($error ?: 'HTTP error'), $status);
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
        $nativeApp = $this->root . '/DCP';
        $nativeHandle = is_file($nativeApp) ? fopen($nativeApp, 'rb') : false;
        $nativeMagic = $nativeHandle === false ? '' : fread($nativeHandle, 4);
        if (is_resource($nativeHandle)) fclose($nativeHandle);
        if (!is_executable($nativeApp) || $nativeMagic !== "\x7fELF") {
            throw new RuntimeException('Updated native DCP Linux executable is missing, non-executable or not an ELF binary.');
        }
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
        $healthUserData = $work . '/health-user-data';
        if (!mkdir($healthUserData, 0700, true)) throw new RuntimeException('Could not create isolated health-check user data.');
        $environment['DCP_MODE'] = 'desktop';
        $environment['DCP_USER_DATA_DIR'] = $healthUserData;
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
    if ($mode === '--download') {
        $result = $updater->download();
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }
    if ($mode === '--apply') {
        $result = $updater->apply();
        echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit(0);
    }
    if ($mode === '--watch') {
        $logPath = dirname(__DIR__, 2) . '/DCP-UserData/Linux/storage/logs/updater.log';
        $log = static function (string $message) use ($logPath): void {
            @file_put_contents($logPath, '[' . gmdate('c') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        };
        $log('Background release watcher started; polling GitHub every 300 seconds.');
        while (true) {
            try {
                $check = $updater->download();
                if (!empty($check['available'])) {
                    $tag = (string)($check['releaseTag'] ?? 'unknown');
                    $log('Verified update detected: ' . $tag . ' (' . ($check['strategy'] ?? 'full') . '). Waiting for DCP to close before transactional apply.');
                    try {
                        $result = $updater->apply();
                        $log('Background update applied successfully: ' . json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        if (function_exists('exec') && function_exists('shell_exec') && trim((string)shell_exec('command -v notify-send 2>/dev/null')) !== '') {
                            exec('notify-send ' . escapeshellarg('DCP updated') . ' ' . escapeshellarg('Installed ' . $tag . '. Reopen DCP to use the new version.') . ' >/dev/null 2>&1 &');
                        }
                    } catch (Throwable $error) {
                        $log('Update remains pending until DCP exits: ' . $error->getMessage());
                    }
                }
            } catch (Throwable $error) {
                $log('Release watcher check failed; will retry: ' . $error->getMessage());
            }
            sleep(300);
        }
    }
    throw new InvalidArgumentException('Usage: php update-linux.php --check|--download|--apply|--watch');
} catch (Throwable $error) {
    fwrite(STDERR, 'DCP Linux updater: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
}
