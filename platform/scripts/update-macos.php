<?php
declare(strict_types=1);

/**
 * Signed, transactional macOS package updater.
 *
 * This script is launched by the native DCP.app wrapper. It never writes into
 * DCP-UserData, platform/data, platform/storage or .env files. It uses the
 * pinned RSA public key, schema-3 release manifests, exact-base delta packages
 * and a signed full-package fallback.
 */
final class DcpMacOSUpdater
{
    private const REPOSITORY = 'AmrAdeldotcom/DCP';
    private const KEY_ID = 'dcp-alpha10-rsa3072-20261124';
    private const PUBLIC_KEY_SHA256 = 'bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67';
    private const MAX_ARCHIVE_BYTES = 2147483648;
    private const MAX_FILE_BYTES = 536870912;
    private const MAX_FILES = 200000;
    private const REQUIRED_ASSETS = [
        'DCP-Windows-x64.zip',
        'DCP-Linux-x64.tar.gz',
        'DCP-macOS-Intel.zip',
        'DCP-macOS-AppleSilicon.zip',
        'DCP-Windows-update.json',
        'DCP-Windows-update.json.sig',
        'DCP-Linux-update.json',
        'DCP-Linux-update.json.sig',
        'DCP-macOS-Intel-update.json',
        'DCP-macOS-Intel-update.json.sig',
        'DCP-macOS-AppleSilicon-update.json',
        'DCP-macOS-AppleSilicon-update.json.sig',
    ];

    private static function log(string $message): void
    {
        $root = getenv('DCP_INSTALL_ROOT') ?: '';
        $userData = $root !== '' ? $root . '/DCP-UserData/macOS/storage/logs' : sys_get_temp_dir();
        if (!is_dir($userData)) @mkdir($userData, 0700, true);
        @file_put_contents($userData . '/updater.log', '[' . gmdate('c') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function validateUpdatePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') ||
            str_contains($path, ':') || preg_match('/[\x00-\x1f\x7f]/', $path) ||
            preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new RuntimeException('Unsafe DCP update path: ' . $path);
        }
        $parts = explode('/', $path);
        if (in_array('', $parts, true) || in_array('.', $parts, true)) {
            throw new RuntimeException('Unsafe DCP update path segment: ' . $path);
        }
        $lower = strtolower($path);
        if ($lower === 'dcp-userdata' || str_starts_with($lower, 'dcp-userdata/') ||
            preg_match('#^platform/(data|storage)(/|$)#', $lower) ||
            preg_match('#(^|/)\.env($|\.)#', $lower)) {
            throw new RuntimeException('DCP update path attempts to modify protected user data: ' . $path);
        }
    }

    private static function validateArchivePath(string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') ||
            str_contains($path, ':') || preg_match('/[\x00-\x1f\x7f]/', $path) ||
            preg_match('#(^|/)\.\.(/|$)#', $path)) {
            throw new RuntimeException('Unsafe path in DCP ZIP archive: ' . $path);
        }
        $parts = explode('/', $path);
        if (in_array('', $parts, true) || in_array('.', $parts, true)) {
            throw new RuntimeException('Unsafe path segment in DCP ZIP archive: ' . $path);
        }
    }

    private static function isUserDataPath(string $path): bool
    {
        $lower = strtolower($path);
        return $lower === 'dcp-userdata' || str_starts_with($lower, 'dcp-userdata/');
    }

    private static function officialAssetUrl(string $tag, string $name): string
    {
        return 'https://github.com/' . self::REPOSITORY . '/releases/download/' . rawurlencode($tag) . '/' . rawurlencode($name);
    }

    private static function fullAssetName(string $platform, string $architecture): string
    {
        return match ($platform . '/' . $architecture) {
            'windows/x64' => 'DCP-Windows-x64.zip',
            'linux/x64' => 'DCP-Linux-x64.tar.gz',
            'macos/x64' => 'DCP-macOS-Intel.zip',
            'macos/arm64' => 'DCP-macOS-AppleSilicon.zip',
            default => throw new RuntimeException('Unsupported DCP platform/architecture.'),
        };
    }

    private static function deltaAssetName(string $platform, string $architecture): string
    {
        return match ($platform . '/' . $architecture) {
            'windows/x64' => 'DCP-Windows-x64-delta.zip',
            'linux/x64' => 'DCP-Linux-x64-delta.tar.gz',
            'macos/x64' => 'DCP-macOS-Intel-delta.zip',
            'macos/arm64' => 'DCP-macOS-AppleSilicon-delta.zip',
            default => throw new RuntimeException('Unsupported DCP platform/architecture.'),
        };
    }

    private static function curlBytes(string $url, int $maxBytes): string
    {
        $curl = curl_init($url);
        if ($curl === false) throw new RuntimeException('Could not initialize HTTPS request.');
        $body = '';
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'DCP-macOS-Updater/2.0',
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($ok === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('HTTPS request failed (' . $status . '): ' . $error);
        }
        return $body;
    }

    private static function downloadAsset(array $asset, string $destination): void
    {
        $size = (int)$asset['size'];
        if ($size < 1 || $size > self::MAX_ARCHIVE_BYTES) throw new RuntimeException('Signed DCP package size is outside the allowed limit.');
        $handle = fopen($destination, 'xb');
        if ($handle === false) throw new RuntimeException('Could not create DCP package download file.');
        $hash = hash_init('sha256');
        $written = 0;
        $curl = curl_init((string)$asset['url']);
        if ($curl === false) { fclose($handle); throw new RuntimeException('Could not initialize package download.'); }
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 1200,
            CURLOPT_USERAGENT => 'DCP-macOS-Updater/2.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use ($handle, $hash, &$written, $size): int {
                $length = strlen($chunk);
                if ($written + $length > $size || $written + $length > self::MAX_ARCHIVE_BYTES) return 0;
                $count = fwrite($handle, $chunk);
                if ($count !== $length) return 0;
                hash_update($hash, $chunk);
                $written += $length;
                return $length;
            },
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fflush($handle);
        fclose($handle);
        $digest = hash_final($hash);
        if ($ok === false || $status < 200 || $status >= 300 || $written !== $size ||
            !hash_equals(strtolower((string)$asset['sha256']), strtolower($digest))) {
            @unlink($destination);
            throw new RuntimeException('DCP package download failed signed size/SHA-256 verification: ' . $error);
        }
    }

    private static function validatePathList(mixed $paths): array
    {
        if (!is_array($paths) || !array_is_list($paths) || count($paths) > 5000) {
            throw new RuntimeException('DCP update deletion list is invalid.');
        }
        $seen = [];
        foreach ($paths as $path) {
            if (!is_string($path)) throw new RuntimeException('DCP update deletion entry must be a path string.');
            self::validateUpdatePath($path);
            $key = strtolower($path);
            if (isset($seen[$key])) throw new RuntimeException('DCP update deletion list contains duplicate paths.');
            $seen[$key] = true;
        }
        return $paths;
    }

    private static function validateAsset(mixed $asset, string $expectedName, string $tag, array $releaseAssets, bool $required): void
    {
        if (!is_array($asset) || ($asset['name'] ?? null) !== $expectedName ||
            !is_string($asset['url'] ?? null) || $asset['url'] !== self::officialAssetUrl($tag, $expectedName) ||
            !is_int($asset['size'] ?? null) || $asset['size'] < 1 ||
            !is_string($asset['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/i', $asset['sha256'])) {
            throw new RuntimeException('DCP signed asset metadata is invalid: ' . $expectedName);
        }
        if ($required && ($releaseAssets[$expectedName] ?? null) !== $asset['url']) {
            throw new RuntimeException('Required DCP release asset is missing: ' . $expectedName);
        }
    }

    public static function validateManifest(array $manifest, string $platform, string $architecture, string $tag, array $releaseAssets): void
    {
        $version = (string)($manifest['version'] ?? '');
        if (($manifest['schema'] ?? null) !== 3 ||
            !preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) ||
            ($manifest['release_tag'] ?? null) !== $tag || $tag !== 'v' . $version ||
            !is_int($manifest['package_revision'] ?? null) || $manifest['package_revision'] < 1 ||
            ($manifest['platform'] ?? null) !== $platform ||
            ($manifest['architecture'] ?? null) !== $architecture ||
            !preg_match('/^[a-f0-9]{40}$/i', (string)($manifest['commit'] ?? '')) ||
            ($manifest['key_id'] ?? null) !== self::KEY_ID) {
            throw new RuntimeException('DCP signed manifest metadata is invalid.');
        }
        $fullName = self::fullAssetName($platform, $architecture);
        self::validateAsset($manifest['full'] ?? null, $fullName, $tag, $releaseAssets, true);
        $manifest['full']['deleted'] = self::validatePathList($manifest['full']['deleted'] ?? null);

        $delta = $manifest['delta'] ?? null;
        if ($delta === null) return;
        if (!is_array($delta) || !is_array($delta['base'] ?? null)) throw new RuntimeException('DCP delta manifest metadata is invalid.');
        $baseVersion = (string)($delta['base']['version'] ?? '');
        if (!preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $baseVersion) ||
            ($delta['base']['release_tag'] ?? null) !== 'v' . $baseVersion ||
            !is_int($delta['base']['package_revision'] ?? null) || $delta['base']['package_revision'] < 0) {
            throw new RuntimeException('DCP delta base metadata is invalid.');
        }
        self::validateAsset($delta['asset'] ?? null, self::deltaAssetName($platform, $architecture), $tag, $releaseAssets, false);
        $files = $delta['files'] ?? null;
        if (!is_array($files) || !array_is_list($files) || count($files) > 5000) throw new RuntimeException('DCP delta file list is invalid.');
        $seen = [];
        foreach ($files as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null) || !is_int($file['size'] ?? null) ||
                $file['size'] < 0 || !is_string($file['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/i', $file['sha256'])) {
                throw new RuntimeException('DCP delta file metadata is invalid.');
            }
            self::validateUpdatePath($file['path']);
            $key = strtolower($file['path']);
            if (isset($seen[$key])) throw new RuntimeException('DCP delta contains duplicate paths.');
            $seen[$key] = true;
        }
        $deleted = self::validatePathList($delta['deleted'] ?? null);
        foreach ($deleted as $path) {
            if (isset($seen[strtolower($path)])) throw new RuntimeException('DCP delta both replaces and deletes the same path.');
        }
    }

    public static function compareVersions(string $left, string $right): int
    {
        $parse = static function (string $value): array {
            $value = ltrim(explode('+', trim($value))[0], 'v');
            if (!preg_match('/^(\d+\.\d+\.\d+)(?:-([0-9A-Za-z.-]+))?$/', $value, $match)) {
                throw new RuntimeException('Invalid DCP semantic version: ' . $value);
            }
            return [array_map('intval', explode('.', $match[1])), $match[2] ?? ''];
        };
        [$aCore, $aPre] = $parse($left);
        [$bCore, $bPre] = $parse($right);
        for ($i = 0; $i < 3; $i++) if ($aCore[$i] !== $bCore[$i]) return $aCore[$i] <=> $bCore[$i];
        if ($aPre === '') return $bPre === '' ? 0 : 1;
        if ($bPre === '') return -1;
        $aParts = explode('.', $aPre); $bParts = explode('.', $bPre);
        for ($i = 0; $i < min(count($aParts), count($bParts)); $i++) {
            $aNumeric = ctype_digit($aParts[$i]); $bNumeric = ctype_digit($bParts[$i]);
            if ($aNumeric && $bNumeric) $cmp = (int)$aParts[$i] <=> (int)$bParts[$i];
            elseif ($aNumeric) $cmp = -1;
            elseif ($bNumeric) $cmp = 1;
            else $cmp = strcmp($aParts[$i], $bParts[$i]);
            if ($cmp !== 0) return $cmp;
        }
        return count($aParts) <=> count($bParts);
    }

    public static function verifyDetachedSignature(string $bytes, string $signature, OpenSSLAsymmetricKey|string $publicKey): bool
    {
        return openssl_verify($bytes, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function verifyRelease(string $root): array
    {
        $publicPath = $root . '/platform/update/release-public.pem';
        if (!is_file($publicPath) || !hash_equals(self::PUBLIC_KEY_SHA256, hash_file('sha256', $publicPath))) {
            throw new RuntimeException('Pinned DCP release public key is missing or has changed.');
        }
        $publicKey = openssl_pkey_get_public((string)file_get_contents($publicPath));
        if ($publicKey === false) throw new RuntimeException('Could not load the pinned DCP release public key.');

        $releases = json_decode(self::curlBytes('https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=30', 4 * 1024 * 1024), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($releases)) throw new RuntimeException('GitHub release list is invalid.');
        $release = null;
        foreach ($releases as $candidate) {
            if (!is_array($candidate) || !empty($candidate['draft'])) continue;
            $names = [];
            foreach (($candidate['assets'] ?? []) as $asset) if (is_array($asset) && is_string($asset['name'] ?? null)) $names[$asset['name']] = true;
            if (array_diff(self::REQUIRED_ASSETS, array_keys($names)) === []) { $release = $candidate; break; }
        }
        if ($release === null) return [];
        $tag = (string)($release['tag_name'] ?? '');
        if (!preg_match('/^v\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $tag)) throw new RuntimeException('GitHub release tag is invalid.');
        $releaseAssets = [];
        foreach (($release['assets'] ?? []) as $asset) {
            $name = (string)($asset['name'] ?? '');
            if (!str_starts_with($name, 'DCP-')) continue;
            $url = (string)($asset['browser_download_url'] ?? '');
            if ($url !== self::officialAssetUrl($tag, $name)) throw new RuntimeException('Release asset URL is not official: ' . $name);
            $releaseAssets[$name] = $url;
        }

        $manifests = [
            ['DCP-Windows-update.json', 'windows', 'x64'],
            ['DCP-Linux-update.json', 'linux', 'x64'],
            ['DCP-macOS-Intel-update.json', 'macos', 'x64'],
            ['DCP-macOS-AppleSilicon-update.json', 'macos', 'arm64'],
        ];
        $reference = null;
        $macManifest = null;
        foreach ($manifests as [$name, $platform, $architecture]) {
            $bytes = self::curlBytes($releaseAssets[$name], 2 * 1024 * 1024);
            $signature = self::curlBytes($releaseAssets[$name . '.sig'], 16 * 1024);
            if (!self::verifyDetachedSignature($bytes, $signature, $publicKey)) {
                throw new RuntimeException('Detached signature verification failed for ' . $name . '.');
            }
            $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)) throw new RuntimeException('Signed DCP manifest is not a JSON object.');
            self::validateManifest($manifest, $platform, $architecture, $tag, $releaseAssets);
            $sync = [
                'version' => $manifest['version'],
                'release_tag' => $manifest['release_tag'],
                'package_revision' => $manifest['package_revision'],
                'commit' => $manifest['commit'],
                'key_id' => $manifest['key_id'],
            ];
            if ($reference !== null && $sync !== $reference) throw new RuntimeException('Signed DCP manifests are not synchronized across platforms.');
            $reference = $sync;
            if ($platform === 'macos' && $architecture === self::macArchitecture()) $macManifest = $manifest;
        }
        if ($macManifest === null) throw new RuntimeException('No signed macOS manifest matches this Mac architecture.');
        $delta = $macManifest['delta'] ?? null;
        $deltaAvailable = is_array($delta) && isset($releaseAssets[$delta['asset']['name'] ?? '']);
        return ['tag' => $tag, 'manifest' => $macManifest, 'assets' => $releaseAssets, 'delta_available' => $deltaAvailable];
    }

    private static function macArchitecture(): string
    {
        $machine = strtolower(php_uname('m'));
        return in_array($machine, ['arm64', 'aarch64'], true) ? 'arm64' : 'x64';
    }

    private static function checkUpdate(string $root, string $currentVersion, int $currentRevision): array
    {
        $release = self::verifyRelease($root);
        if ($release === []) return ['available' => false, 'message' => 'No complete synchronized signed DCP release is available.'];
        $manifest = $release['manifest'];
        $latestVersion = (string)$manifest['version'];
        $latestRevision = (int)$manifest['package_revision'];
        if (self::compareVersions($latestVersion, $currentVersion) < 0 ||
            (self::compareVersions($latestVersion, $currentVersion) === 0 && $latestRevision <= $currentRevision)) {
            return ['available' => false, 'message' => 'DCP is already current.'];
        }
        $failurePath = $root . '/DCP-UserData/macOS/storage/updates/last-failed-release.json';
        if (is_file($failurePath)) {
            $failure = json_decode((string)file_get_contents($failurePath), true);
            if (is_array($failure) && ($failure['tag'] ?? null) === $release['tag'] &&
                is_int($failure['time'] ?? null) && $failure['time'] > time() - 3600) {
                return ['available' => false, 'message' => 'The same signed release failed recently; automatic retry is temporarily paused.'];
            }
        }
        $delta = $manifest['delta'] ?? null;
        $useDelta = is_array($delta) && $release['delta_available'] &&
            ($delta['base']['version'] ?? null) === $currentVersion &&
            (int)($delta['base']['package_revision'] ?? -1) === $currentRevision;
        return [
            'available' => true,
            'version' => $latestVersion,
            'release_tag' => $release['tag'],
            'package_revision' => $latestRevision,
            'strategy' => $useDelta ? 'delta' : 'full',
            'message' => 'A verified DCP update is available.',
        ];
    }

    public static function extractZipSafely(string $archivePath, string $destination, bool $fullPackage): array
    {
        if (!class_exists(PharData::class)) throw new RuntimeException('The managed PHP Phar extension is required for safe ZIP extraction.');
        $archiveReal = realpath($archivePath);
        if ($archiveReal === false) throw new RuntimeException('DCP ZIP archive path is missing.');
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) throw new RuntimeException('Could not create DCP staging directory.');
        $root = realpath($destination);
        if ($root === false) throw new RuntimeException('DCP staging directory could not be resolved.');
        $prefix = 'phar://' . $archiveReal . '/';
        $archive = new PharData($archiveReal);
        $seen = [];
        $files = [];
        $totalBytes = 0;
        $iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            if (!$entry instanceof SplFileInfo) continue;
            $entryPath = $entry->getPathName();
            if (!str_starts_with($entryPath, $prefix)) throw new RuntimeException('DCP ZIP entry escaped the archive namespace.');
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($entryPath, strlen($prefix)));
            self::validateArchivePath($relative);
            $userData = self::isUserDataPath($relative);
            if ($userData) {
                if ($fullPackage) continue;
                throw new RuntimeException('DCP delta archive contains user data.');
            }
            self::validateUpdatePath($relative);
            $key = strtolower($relative);
            if (isset($seen[$key])) throw new RuntimeException('DCP ZIP contains duplicate or case-colliding entries: ' . $relative);
            $seen[$key] = true;
            $target = $root . '/' . $relative;
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) throw new RuntimeException('Could not create DCP staging subdirectory.');
            if ($entry->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) throw new RuntimeException('Could not create DCP package directory.');
                continue;
            }
            $size = $entry->getSize();
            if ($size < 0 || $size > self::MAX_FILE_BYTES) throw new RuntimeException('DCP ZIP entry exceeds per-file size limit: ' . $relative);
            $totalBytes += $size;
            if (count($files) >= self::MAX_FILES || $totalBytes > self::MAX_ARCHIVE_BYTES) throw new RuntimeException('DCP ZIP exceeds extraction limits.');
            $input = fopen($entryPath, 'rb');
            $output = fopen($target, 'xb');
            if ($input === false || $output === false) throw new RuntimeException('Could not safely extract DCP ZIP entry: ' . $relative);
            $copied = stream_copy_to_stream($input, $output);
            fclose($input); fflush($output); fclose($output);
            if ($copied !== $size) throw new RuntimeException('DCP ZIP extracted size mismatch: ' . $relative);
            $mode = $entry->getPerms();
            @chmod($target, (($mode & 0111) !== 0) ? 0755 : 0644);
            $files[] = ['path' => $relative, 'size' => $size, 'sha256' => hash_file('sha256', $target)];
        }
        usort($files, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));
        if ($files === []) throw new RuntimeException('DCP ZIP package contains no application files.');
        return $files;
    }

    private static function verifyDeltaPayload(array $actual, array $expected): void
    {
        if (count($actual) !== count($expected)) throw new RuntimeException('DCP delta ZIP does not contain the exact signed file set.');
        $map = [];
        foreach ($expected as $file) $map[strtolower($file['path'])] = $file;
        foreach ($actual as $file) {
            $key = strtolower($file['path']);
            if (!isset($map[$key]) || (int)$map[$key]['size'] !== (int)$file['size'] ||
                !hash_equals(strtolower((string)$map[$key]['sha256']), strtolower((string)$file['sha256']))) {
                throw new RuntimeException('DCP delta ZIP content differs from the signed manifest: ' . $file['path']);
            }
        }
    }

    private static function downloadAndStage(string $root, array $release, string $currentVersion, int $currentRevision): array
    {
        $manifest = $release['manifest'];
        $delta = $manifest['delta'] ?? null;
        $useDelta = is_array($delta) && $release['delta_available'] &&
            ($delta['base']['version'] ?? null) === $currentVersion &&
            (int)($delta['base']['package_revision'] ?? -1) === $currentRevision;
        $strategy = $useDelta ? 'delta' : 'full';
        $asset = $useDelta ? $delta['asset'] : $manifest['full'];
        $workRoot = sys_get_temp_dir() . '/DCP-update-' . bin2hex(random_bytes(8));
        if (!mkdir($workRoot, 0700, true)) throw new RuntimeException('Could not create DCP update workspace.');
        try {
            $archivePath = $workRoot . '/' . $asset['name'];
            self::downloadAsset($asset, $archivePath);
            $stage = $workRoot . '/payload';
            $files = self::extractZipSafely($archivePath, $stage, $strategy === 'full');
            if ($useDelta) self::verifyDeltaPayload($files, $delta['files']);
            $deleted = $useDelta ? self::validatePathList($delta['deleted']) : self::validatePathList($manifest['full']['deleted']);
            $actualPaths = [];
            foreach ($files as $file) $actualPaths[strtolower($file['path'])] = true;
            foreach ($deleted as $deletedPath) {
                if (isset($actualPaths[strtolower($deletedPath)])) {
                    throw new RuntimeException('DCP package both installs and deletes the same path: ' . $deletedPath);
                }
            }
            $normalized = [
                'schema' => 2,
                'version' => $manifest['version'],
                'package_revision' => $manifest['package_revision'],
                'release_tag' => $manifest['release_tag'],
                'files' => $files,
                'deleted' => $deleted,
            ];
            file_put_contents($workRoot . '/update-manifest.json', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
            return ['work_root' => $workRoot, 'stage' => $stage, 'manifest' => $normalized, 'strategy' => $strategy];
        } catch (Throwable $error) {
            self::removeTree($workRoot);
            throw $error;
        }
    }

    private static function removeTree(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) return;
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->isFile()) @unlink($item->getPathname());
            elseif ($item->isDir()) @rmdir($item->getPathname());
        }
        @rmdir($path);
    }

    private static function assertNoSymlinkPath(string $root, string $relative): string
    {
        $rootReal = realpath($root);
        if ($rootReal === false || is_link($root)) throw new RuntimeException('DCP installation root is missing or is a symlink.');
        $cursor = $rootReal;
        foreach (explode('/', $relative) as $part) {
            $cursor .= '/' . $part;
            if (file_exists($cursor) || is_link($cursor)) {
                if (is_link($cursor)) throw new RuntimeException('DCP update refuses to follow a symlink: ' . $relative);
            }
        }
        $target = $rootReal . '/' . $relative;
        if (!str_starts_with($target, $rootReal . '/')) throw new RuntimeException('DCP update path escaped installation root.');
        return $target;
    }

    private static function copyTree(string $source, string $destination): void
    {
        if (is_link($source)) throw new RuntimeException('DCP app bundle contains a symlink and cannot be backed up safely.');
        if (is_file($source)) {
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true) && !is_dir(dirname($destination))) throw new RuntimeException('Could not create DCP backup directory.');
            if (!copy($source, $destination)) throw new RuntimeException('Could not back up DCP application file.');
            @chmod($destination, fileperms($source) & 0777);
            return;
        }
        if (!is_dir($source)) throw new RuntimeException('DCP application bundle is missing.');
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) throw new RuntimeException('Could not create DCP app backup directory.');
        foreach (new DirectoryIterator($source) as $entry) {
            if ($entry->isDot()) continue;
            self::copyTree($entry->getPathname(), $destination . '/' . $entry->getFilename());
        }
    }

    private static function snapshotCriticalData(string $root, string $backupRoot): array
    {
        $userData = $root . '/DCP-UserData/macOS';
        $paths = [
            'DCP-UserData/macOS/data/dcp.sqlite',
            'DCP-UserData/macOS/data/dcp.sqlite-wal',
            'DCP-UserData/macOS/data/dcp.sqlite-shm',
            'DCP-UserData/macOS/data/dcp.sqlite-journal',
            'DCP-UserData/macOS/storage/keys/master.key',
        ];
        $snapshots = [];
        $criticalRoot = $backupRoot . '/critical-data';
        foreach ($paths as $index => $relative) {
            $target = self::assertNoSymlinkPath($root, $relative);
            $exists = file_exists($target);
            $saved = $criticalRoot . '/' . $index . '.bin';
            if ($exists) {
                if (!is_file($target)) throw new RuntimeException('Critical DCP user-data path is not a regular file: ' . $relative);
                if (!is_dir($criticalRoot) && !mkdir($criticalRoot, 0700, true) && !is_dir($criticalRoot)) throw new RuntimeException('Could not create critical-data backup directory.');
                if (!copy($target, $saved)) throw new RuntimeException('Could not snapshot critical DCP user data: ' . $relative);
                @chmod($saved, 0600);
            }
            $snapshots[] = ['path' => $relative, 'saved' => $saved, 'exists' => $exists];
        }
        return $snapshots;
    }

    private static function restoreCriticalData(string $root, array $snapshots): void
    {
        foreach ($snapshots as $snapshot) {
            $target = self::assertNoSymlinkPath($root, $snapshot['path']);
            if ($snapshot['exists']) {
                if (!is_file($snapshot['saved'])) throw new RuntimeException('Critical-data rollback snapshot is missing: ' . $snapshot['path']);
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true) && !is_dir(dirname($target))) throw new RuntimeException('Could not restore critical user-data directory.');
                $temporary = $target . '.dcp-restore-' . bin2hex(random_bytes(4));
                if (!copy($snapshot['saved'], $temporary) || !rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('Could not restore critical DCP user data: ' . $snapshot['path']);
                }
                @chmod($target, 0600);
            } else {
                if (is_file($target) && !unlink($target)) throw new RuntimeException('Could not remove newly created critical user-data file: ' . $snapshot['path']);
            }
        }
    }

    public static function applyTransaction(string $root, array $package, string $workRoot, bool $nativeChecks = true, ?callable $healthCheck = null): string
    {
        $root = realpath($root) ?: throw new RuntimeException('DCP installation root is missing.');
        $userData = $root . '/DCP-UserData/macOS';
        $backupRelative = 'DCP-UserData/macOS/storage/updates/backups';
        $backupParent = self::assertNoSymlinkPath($root, $backupRelative);
        if (!is_dir($backupParent) && !mkdir($backupParent, 0700, true) && !is_dir($backupParent)) throw new RuntimeException('Could not create DCP rollback storage.');
        if (is_link($backupParent)) throw new RuntimeException('DCP rollback storage must not be a symlink.');
        $backupRoot = $backupParent . '/' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(3));
        if (!mkdir($backupRoot, 0700, true)) throw new RuntimeException('Could not create DCP rollback snapshot.');
        $appPath = $root . '/DCP.app';
        $appBackup = $backupRoot . '/DCP.app';
        $appBackedUp = false;
        $applied = [];
        $mutationStarted = false;
        $critical = [];
        try {
            $critical = self::snapshotCriticalData($root, $backupRoot);
            self::copyTree($appPath, $appBackup);
            $appBackedUp = true;
            $stage = $package['stage'];
            foreach ($package['manifest']['files'] as $file) {
                $relative = (string)$file['path'];
                self::validateUpdatePath($relative);
                $source = $stage . '/' . $relative;
                $target = self::assertNoSymlinkPath($root, $relative);
                if (!is_file($source)) throw new RuntimeException('Staged DCP update file is missing: ' . $relative);
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) throw new RuntimeException('Could not create DCP update target directory.');
                $exists = file_exists($target);
                if ($exists && !is_file($target)) throw new RuntimeException('DCP update target is not a regular file: ' . $relative);
                if ($exists && !str_starts_with($relative, 'DCP.app/')) {
                    $backup = $backupRoot . '/files/' . $relative;
                    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0700, true) && !is_dir(dirname($backup))) throw new RuntimeException('Could not create DCP file backup directory.');
                    if (!copy($target, $backup)) throw new RuntimeException('Could not back up DCP file: ' . $relative);
                }
                $applied[] = ['path' => $relative, 'exists' => $exists];
                $mutationStarted = true;
                $temporary = $target . '.dcp-update-' . bin2hex(random_bytes(4));
                if (!copy($source, $temporary) || !rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('Could not atomically replace DCP file: ' . $relative);
                }
                @chmod($target, fileperms($source) & 0777);
            }
            foreach ($package['manifest']['deleted'] as $relative) {
                self::validateUpdatePath((string)$relative);
                $target = self::assertNoSymlinkPath($root, (string)$relative);
                if (!file_exists($target)) continue;
                if (!is_file($target)) throw new RuntimeException('DCP deletion target is not a regular file: ' . $relative);
                if (!str_starts_with((string)$relative, 'DCP.app/')) {
                    $backup = $backupRoot . '/files/' . $relative;
                    if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0700, true) && !is_dir(dirname($backup))) throw new RuntimeException('Could not create DCP deletion backup directory.');
                    if (!copy($target, $backup)) throw new RuntimeException('Could not back up deleted DCP file: ' . $relative);
                }
                $applied[] = ['path' => (string)$relative, 'exists' => true];
                $mutationStarted = true;
                if (!unlink($target)) throw new RuntimeException('Could not remove obsolete DCP file: ' . $relative);
            }

            if ($nativeChecks) {
                self::runCommand(['/usr/bin/codesign', '--force', '--deep', '--sign', '-', $appPath]);
                self::runCommand(['/usr/bin/codesign', '--verify', '--deep', '--strict', $appPath]);
                @unlink(self::assertNoSymlinkPath($root, 'DCP-UserData/macOS/storage/runtime-port.txt'));
                @unlink(self::assertNoSymlinkPath($root, 'DCP-UserData/macOS/storage/runtime-pid.txt'));
                self::launchApp($appPath);
                if (!self::waitForHealthyApi($root, 40, 500000)) throw new RuntimeException('Updated DCP /api/status did not become healthy.');
            } elseif ($healthCheck !== null && !$healthCheck()) {
                throw new RuntimeException('Injected DCP post-update health check failure.');
            }
            self::pruneBackups($backupParent, 2);
            return $backupRoot;
        } catch (Throwable $error) {
            try {
                if ($nativeChecks) self::stopCurrentApp($root);
                $entries = array_reverse($applied);
                foreach ($entries as $operation) {
                    $relative = $operation['path'];
                    if (str_starts_with($relative, 'DCP.app/')) continue;
                    $target = self::assertNoSymlinkPath($root, $relative);
                    $backup = $backupRoot . '/files/' . $relative;
                    if ($operation['exists']) {
                        if (!is_file($backup)) throw new RuntimeException('DCP rollback file is missing: ' . $relative);
                        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) throw new RuntimeException('Could not create rollback target directory.');
                        $temporary = $target . '.dcp-rollback-' . bin2hex(random_bytes(4));
                        if (!copy($backup, $temporary) || !rename($temporary, $target)) {
                            @unlink($temporary);
                            throw new RuntimeException('Could not restore DCP file: ' . $relative);
                        }
                    } elseif (is_file($target) && !unlink($target)) {
                        throw new RuntimeException('Could not remove newly installed DCP file: ' . $relative);
                    }
                }
                if ($appBackedUp) {
                    self::removeTree($appPath);
                    self::copyTree($appBackup, $appPath);
                }
                self::restoreCriticalData($root, $critical);
                if ($nativeChecks) {
                    self::runCommand(['/usr/bin/codesign', '--force', '--deep', '--sign', '-', $appPath]);
                    self::runCommand(['/usr/bin/codesign', '--verify', '--deep', '--strict', $appPath]);
                }
                self::pruneBackups($backupParent, 2);
                self::log('Update rolled back successfully: ' . $error->getMessage());
            } catch (Throwable $rollbackError) {
                self::log('CRITICAL: rollback failed; recovery snapshot retained at ' . $backupRoot . ': ' . $rollbackError->getMessage());
                throw new RuntimeException('DCP update failed and rollback needs manual recovery. Snapshot: ' . $backupRoot, 99, $error);
            }
            throw $error;
        }
    }

    private static function runCommand(array $arguments): void
    {
        $command = implode(' ', array_map('escapeshellarg', $arguments));
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0) throw new RuntimeException('DCP update command failed: ' . implode("\n", $output));
    }

    private static function launchApp(string $appPath): void
    {
        $command = '/usr/bin/open -a ' . escapeshellarg($appPath) . ' >/dev/null 2>&1 &';
        exec($command);
    }

    private static function stopCurrentApp(string $root): void
    {
        $pidFile = self::assertNoSymlinkPath($root, 'DCP-UserData/macOS/storage/runtime-pid.txt');
        $pid = is_file($pidFile) ? (int)trim((string)file_get_contents($pidFile)) : 0;
        if ($pid > 1 && $pid !== getmypid()) {
            $expectedExecutable = $root . '/DCP.app/Contents/MacOS/DCP';
            $command = shell_exec('/bin/ps -p ' . $pid . ' -o command=');
            if (!is_string($command) || !str_contains($command, $expectedExecutable)) return;
            @posix_kill($pid, SIGTERM);
            usleep(500000);
            if (@posix_kill($pid, 0)) @posix_kill($pid, SIGKILL);
        }
    }

    private static function waitForHealthyApi(string $root, int $attempts, int $sleepMicros): bool
    {
        $portFile = self::assertNoSymlinkPath($root, 'DCP-UserData/macOS/storage/runtime-port.txt');
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if (is_file($portFile)) {
                $port = (int)trim((string)file_get_contents($portFile));
                if ($port >= 1 && $port <= 65535) {
                    $curl = curl_init('http://127.0.0.1:' . $port . '/api/status');
                    if ($curl !== false) {
                        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 2]);
                        $body = curl_exec($curl);
                        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                        curl_close($curl);
                        if (is_string($body) && $status === 200) {
                            $json = json_decode($body, true);
                            if (is_array($json) && ($json['ok'] ?? false) === true) return true;
                        }
                    }
                }
            }
            usleep($sleepMicros);
        }
        return false;
    }

    private static function pruneBackups(string $backupParent, int $keep): void
    {
        $directories = glob($backupParent . '/*', GLOB_ONLYDIR) ?: [];
        rsort($directories, SORT_STRING);
        foreach (array_slice($directories, $keep) as $old) self::removeTree($old);
    }

    private static function apply(string $root, string $currentVersion, int $currentRevision, int $pid): void
    {
        if ($pid > 1 && $pid !== getmypid()) {
            $deadline = time() + 120;
            while (@posix_kill($pid, 0) && time() < $deadline) sleep(1);
            if (@posix_kill($pid, 0)) throw new RuntimeException('Timed out waiting for the previous DCP.app process to exit.');
        }
        $userData = $root . '/DCP-UserData/macOS';
        @unlink(self::assertNoSymlinkPath($root, 'DCP-UserData/macOS/storage/runtime-port.txt'));
        @unlink(self::assertNoSymlinkPath($root, 'DCP-UserData/macOS/storage/runtime-pid.txt'));
        $release = self::verifyRelease($root);
        if ($release === []) { self::launchApp($root . '/DCP.app'); return; }
        $manifest = $release['manifest'];
        $latestVersion = (string)$manifest['version'];
        $latestRevision = (int)$manifest['package_revision'];
        if (self::compareVersions($latestVersion, $currentVersion) < 0 ||
            (self::compareVersions($latestVersion, $currentVersion) === 0 && $latestRevision <= $currentRevision)) {
            self::launchApp($root . '/DCP.app');
            return;
        }
        $package = null;
        try {
            $package = self::downloadAndStage($root, $release, $currentVersion, $currentRevision);
            self::applyTransaction($root, $package, $package['work_root']);
            @unlink($userData . '/storage/updates/last-failed-release.json');
            self::log('Update installed: ' . $latestVersion . ' revision ' . $latestRevision . ' using ' . $package['strategy'] . '.');
        } catch (Throwable $error) {
            $failureDirectory = $userData . '/storage/updates';
            if (!is_dir($failureDirectory)) @mkdir($failureDirectory, 0700, true);
            @file_put_contents($failureDirectory . '/last-failed-release.json', json_encode(['tag' => $release['tag'], 'time' => time()], JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX);
            self::log('Update failed: ' . $error->getMessage());
            throw $error;
        } finally {
            if (is_array($package) && isset($package['work_root'])) self::removeTree($package['work_root']);
        }
    }

    public static function main(array $argv): int
    {
        $options = getopt('', ['check', 'apply', 'root:', 'version:', 'revision:', 'pid:']);
        $root = realpath((string)($options['root'] ?? '')) ?: '';
        $version = (string)($options['version'] ?? '0.0.0');
        $revision = (int)($options['revision'] ?? 0);
        if ($root === '' || !is_dir($root . '/platform') || !is_dir($root . '/DCP.app')) {
            fwrite(STDERR, "DCP installation root is invalid.\n");
            return 2;
        }
        putenv('DCP_INSTALL_ROOT=' . $root);
        try {
            if (isset($options['check'])) {
                $result = self::checkUpdate($root, $version, $revision);
                echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
                return 0;
            }
            if (isset($options['apply'])) {
                self::apply($root, $version, $revision, (int)($options['pid'] ?? 0));
                return 0;
            }
            fwrite(STDERR, "Use --check or --apply.\n");
            return 2;
        } catch (Throwable $error) {
            self::log($error->getMessage());
            fwrite(STDERR, 'DCP macOS updater: ' . $error->getMessage() . PHP_EOL);
            if (isset($options['apply']) && $error->getCode() !== 99) self::launchApp($root . '/DCP.app');
            return 1;
        }
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(DcpMacOSUpdater::main($argv));
}
