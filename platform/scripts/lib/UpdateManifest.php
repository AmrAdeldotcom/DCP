<?php
declare(strict_types=1);

/**
 * Shared DCP updater manifest contract. The detached RSA-SHA256 signature is
 * verified over the exact downloaded JSON bytes before any field is trusted.
 */
final class DcpUpdateManifest
{
    public const SCHEMA = 3;
    public const KEY_ID = 'dcp-alpha10-rsa3072-20261124';
    public const REPOSITORY = 'AmrAdeldotcom/DCP';
    public const PUBLIC_KEY_SHA256 = 'bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67';

    /** @return array{manifest:array<string,mixed>,strategy:string} */
    public static function inspect(
        string $manifestBytes,
        string $signatureBytes,
        string $publicKeyPath,
        string $platform,
        string $architecture,
        string $currentVersion,
        int $currentRevision,
        ?string $expectedPublicKeySha256 = null
    ): array {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('OpenSSL is required to verify DCP update manifests.');
        }
        if (!is_file($publicKeyPath) || !is_readable($publicKeyPath)) {
            throw new RuntimeException('The pinned DCP update public key is missing.');
        }
        $expectedFingerprint = strtolower($expectedPublicKeySha256 ?? self::PUBLIC_KEY_SHA256);
        $actualFingerprint = hash_file('sha256', $publicKeyPath);
        if (!is_string($actualFingerprint) || !hash_equals($expectedFingerprint, strtolower($actualFingerprint))) {
            throw new RuntimeException('The pinned DCP update public key fingerprint does not match.');
        }
        $publicKey = openssl_pkey_get_public((string)file_get_contents($publicKeyPath));
        if ($publicKey === false) {
            throw new RuntimeException('The pinned DCP update public key is invalid.');
        }
        try {
            $verified = openssl_verify($manifestBytes, $signatureBytes, $publicKey, OPENSSL_ALGO_SHA256);
        } finally {
            openssl_free_key($publicKey);
        }
        if ($verified !== 1) {
            throw new RuntimeException('DCP update manifest signature verification failed.');
        }

        try {
            $manifest = json_decode($manifestBytes, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('DCP update manifest is not valid JSON.', 0, $error);
        }
        if (!is_array($manifest) || array_is_list($manifest)) {
            throw new RuntimeException('DCP update manifest root must be an object.');
        }
        self::validate($manifest, $platform, $architecture);

        $base = $manifest['delta']['base'] ?? null;
        $useDelta = is_array($base)
            && hash_equals((string)$base['version'], ltrim($currentVersion, 'v'))
            && (int)$base['package_revision'] === $currentRevision;

        return ['manifest' => $manifest, 'strategy' => $useDelta ? 'delta' : 'full'];
    }

    /** @param array<string,mixed> $manifest */
    public static function validate(array $manifest, string $platform, string $architecture): void
    {
        if (($manifest['schema'] ?? null) !== self::SCHEMA) {
            throw new RuntimeException('Unsupported DCP update manifest schema.');
        }
        $version = (string)($manifest['version'] ?? '');
        $tag = (string)($manifest['release_tag'] ?? '');
        if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:-[0-9A-Za-z.-]+)?$/', $version)
            || $tag !== 'v' . $version) {
            throw new RuntimeException('DCP update version and release tag do not match.');
        }
        if (!is_int($manifest['package_revision'] ?? null) || $manifest['package_revision'] < 1) {
            throw new RuntimeException('DCP update package revision must be positive.');
        }
        if (($manifest['platform'] ?? null) !== $platform || ($manifest['architecture'] ?? null) !== $architecture) {
            throw new RuntimeException('DCP update package does not match this platform and architecture.');
        }
        if (!preg_match('/^[a-f0-9]{40}$/i', (string)($manifest['commit'] ?? ''))) {
            throw new RuntimeException('DCP update commit SHA is invalid.');
        }
        if (($manifest['key_id'] ?? null) !== self::KEY_ID) {
            throw new RuntimeException('DCP update signing key ID is not trusted.');
        }

        $expectedFull = self::expectedFullAsset($platform, $architecture);
        self::validateAsset($manifest['full'] ?? null, $tag, $expectedFull);

        if (isset($manifest['delta']) && $manifest['delta'] !== null) {
            $delta = $manifest['delta'];
            if (!is_array($delta) || !isset($delta['base']) || !is_array($delta['base'])) {
                throw new RuntimeException('DCP delta base metadata is missing.');
            }
            $baseVersion = (string)($delta['base']['version'] ?? '');
            $baseTag = (string)($delta['base']['release_tag'] ?? '');
            $baseRevision = $delta['base']['package_revision'] ?? null;
            if (!preg_match('/^\\d+\\.\\d+\\.\\d+(?:-[0-9A-Za-z.-]+)?$/', $baseVersion)
                || $baseTag !== 'v' . $baseVersion || !is_int($baseRevision) || $baseRevision < 0) {
                throw new RuntimeException('DCP delta base version/revision is invalid.');
            }
            $expectedDelta = self::expectedDeltaAsset($platform, $architecture);
            self::validateAsset($delta['asset'] ?? null, $tag, $expectedDelta);
            $files = $delta['files'] ?? null;
            $deleted = $delta['deleted'] ?? null;
            if (!is_array($files) || !array_is_list($files) || count($files) > 5000
                || !is_array($deleted) || !array_is_list($deleted) || count($deleted) > 5000) {
                throw new RuntimeException('DCP delta file/deletion lists are invalid.');
            }
            $seen = [];
            foreach ($files as $file) {
                if (!is_array($file)) {
                    throw new RuntimeException('DCP delta file entry must be an object.');
                }
                $path = (string)($file['path'] ?? '');
                self::validateUpdatePath($path);
                if (isset($seen[$path])) {
                    throw new RuntimeException('DCP delta contains duplicate paths.');
                }
                $seen[$path] = true;
                if (!is_int($file['size'] ?? null) || $file['size'] < 0
                    || !preg_match('/^[a-f0-9]{64}$/i', (string)($file['sha256'] ?? ''))) {
                    throw new RuntimeException('DCP delta file size or SHA-256 is invalid: ' . $path);
                }
            }
            foreach ($deleted as $path) {
                if (!is_string($path)) {
                    throw new RuntimeException('DCP delta deletion entry must be a path string.');
                }
                self::validateUpdatePath($path);
                if (isset($seen[$path])) {
                    throw new RuntimeException('DCP delta cannot both replace and delete the same path.');
                }
                $seen[$path] = true;
            }
        }
    }

    /** @param mixed $asset */
    private static function validateAsset(mixed $asset, string $tag, string $expectedName): void
    {
        if (!is_array($asset)) {
            throw new RuntimeException('DCP update asset metadata is missing.');
        }
        $name = (string)($asset['name'] ?? '');
        $url = (string)($asset['url'] ?? '');
        $size = $asset['size'] ?? null;
        $hash = (string)($asset['sha256'] ?? '');
        if ($name !== $expectedName || !is_int($size) || $size < 1
            || !preg_match('/^[a-f0-9]{64}$/i', $hash)) {
            throw new RuntimeException('DCP update asset name, size or SHA-256 is invalid.');
        }
        $expectedPath = '/' . self::REPOSITORY . '/releases/download/' . $tag . '/' . rawurlencode($name);
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || strtolower((string)($parts['host'] ?? '')) !== 'github.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])
            || ($parts['path'] ?? '') !== $expectedPath) {
            throw new RuntimeException('DCP update asset URL is not the exact official GitHub release asset.');
        }
    }

    private static function expectedFullAsset(string $platform, string $architecture): string
    {
        return match ($platform . ':' . $architecture) {
            'windows:x64' => 'DCP-Windows-x64.zip',
            'linux:x64' => 'DCP-Linux-x64.tar.gz',
            'macos:x64' => 'DCP-macOS-Intel.zip',
            'macos:arm64' => 'DCP-macOS-AppleSilicon.zip',
            default => throw new RuntimeException('Unsupported DCP update platform/architecture.')
        };
    }

    private static function expectedDeltaAsset(string $platform, string $architecture): string
    {
        return match ($platform . ':' . $architecture) {
            'windows:x64' => 'DCP-Windows-x64-delta.zip',
            'linux:x64' => 'DCP-Linux-x64-delta.tar.gz',
            'macos:x64' => 'DCP-macOS-Intel-delta.zip',
            'macos:arm64' => 'DCP-macOS-AppleSilicon-delta.zip',
            default => throw new RuntimeException('Unsupported DCP update platform/architecture.')
        };
    }

    public static function validateUpdatePath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')
            || str_starts_with($path, '/') || str_contains($path, ':')
            || preg_match('/[\\x00-\\x1f\\x7f]/', $path)) {
            throw new RuntimeException('Unsafe DCP update path.');
        }
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Unsafe DCP update path traversal.');
            }
        }
        $normalized = strtolower($path);
        if (str_starts_with($normalized, 'dcp-userdata/') || $normalized === 'dcp-userdata'
            || preg_match('#^platform/(?:data|storage)(?:/|$)#', $normalized)
            || preg_match('#(^|/)\\.env(?:$|\\.)#', $normalized)) {
            throw new RuntimeException('DCP update manifests may not modify user data or environment secrets.');
        }
    }

    /** @param array<string,mixed> $asset */
    public static function verifyAssetFile(string $path, array $asset): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Downloaded DCP update asset is missing.');
        }
        $size = filesize($path);
        if ($size === false || $size !== (int)$asset['size']) {
            throw new RuntimeException('Downloaded DCP update asset size mismatch.');
        }
        $hash = hash_file('sha256', $path);
        if (!is_string($hash) || !hash_equals(strtolower((string)$asset['sha256']), strtolower($hash))) {
            throw new RuntimeException('Downloaded DCP update asset SHA-256 mismatch.');
        }
    }
}
