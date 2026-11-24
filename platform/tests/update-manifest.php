<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/lib/UpdateManifest.php';

function manifest_ok(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "PASS: {$message}\n";
}

function manifest_rejected(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        manifest_ok(true, $message);
        return;
    }
    manifest_ok(false, $message);
}

$root = sys_get_temp_dir() . '/dcp-update-manifest-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);
try {
    $private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    if ($private === false) {
        throw new RuntimeException('Could not create the ephemeral manifest test key.');
    }
    openssl_pkey_export($private, $privatePem);
    $details = openssl_pkey_get_details($private);
    $publicPem = (string)($details['key'] ?? '');
    if ($publicPem === '') {
        throw new RuntimeException('Could not export the ephemeral manifest test public key.');
    }
    $publicPath = $root . '/test-public.pem';
    file_put_contents($publicPath, $publicPem);
    manifest_ok(hash_file('sha256', dirname(__DIR__) . '/update/release-public.pem') === DcpUpdateManifest::PUBLIC_KEY_SHA256, 'production public-key fingerprint is pinned in the verifier');
    manifest_rejected(
        static fn() => DcpUpdateManifest::inspect('{}', '', dirname(__DIR__) . '/update/release-public.pem', 'linux', 'x64', '1.0.0-alpha.9', 0),
        'an unpinned public-key file is rejected before manifest parsing'
    );
    manifest_rejected(
        static fn() => DcpUpdateManifest::inspect('{}', '', $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0),
        'a test key cannot replace the pinned production key by default'
    );

    $fullBytes = 'full-package-test-bytes';
    $deltaBytes = 'delta-package-test-bytes';
    $fullPath = $root . '/full-package.bin';
    file_put_contents($fullPath, $fullBytes);
    $tag = 'v1.0.0-alpha.10';
    $manifest = [
        'schema' => DcpUpdateManifest::SCHEMA,
        'version' => '1.0.0-alpha.10',
        'release_tag' => $tag,
        'package_revision' => 1,
        'platform' => 'linux',
        'architecture' => 'x64',
        'commit' => str_repeat('a', 40),
        'key_id' => DcpUpdateManifest::KEY_ID,
        'full' => [
            'name' => 'DCP-Linux-x64.tar.gz',
            'url' => 'https://github.com/AmrAdeldotcom/DCP/releases/download/' . $tag . '/DCP-Linux-x64.tar.gz',
            'size' => strlen($fullBytes),
            'sha256' => hash('sha256', $fullBytes),
            'deleted' => ['platform/docs/old-note.md'],
        ],
        'delta' => [
            'base' => ['version' => '1.0.0-alpha.9', 'release_tag' => 'v1.0.0-alpha.9', 'package_revision' => 0],
            'asset' => [
                'name' => 'DCP-Linux-x64-delta.tar.gz',
                'url' => 'https://github.com/AmrAdeldotcom/DCP/releases/download/' . $tag . '/DCP-Linux-x64-delta.tar.gz',
                'size' => strlen($deltaBytes),
                'sha256' => hash('sha256', $deltaBytes),
            ],
            'files' => [['path' => 'platform/app/Core/Database.php', 'size' => 18, 'sha256' => hash('sha256', 'database-source')]],
            'deleted' => ['platform/docs/old-note.md'],
        ],
    ];

    $sign = static function (array $payload) use ($privatePem): array {
        $bytes = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false || !openssl_sign($bytes, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign manifest fixture.');
        }
        openssl_free_key($key);
        return [$bytes, $signature];
    };

    [$bytes, $signature] = $sign($manifest);
    $result = DcpUpdateManifest::inspect($bytes, $signature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0, hash_file('sha256', $publicPath));
    manifest_ok($result['strategy'] === 'delta', 'exact version and package revision select the delta update');
    manifest_ok($result['manifest']['full']['name'] === 'DCP-Linux-x64.tar.gz', 'signed manifest exposes the expected full-package fallback');

    $fallback = DcpUpdateManifest::inspect($bytes, $signature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 1, hash_file('sha256', $publicPath));
    manifest_ok($fallback['strategy'] === 'full', 'base revision mismatch selects the full package');

    manifest_rejected(
        static fn() => DcpUpdateManifest::inspect($bytes . ' ', $signature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0, hash_file('sha256', $publicPath)),
        'manifest byte tampering is rejected by detached signature verification'
    );

    $wrongPlatform = $manifest;
    $wrongPlatform['platform'] = 'windows';
    [$badBytes, $badSignature] = $sign($wrongPlatform);
    manifest_rejected(
        static fn() => DcpUpdateManifest::inspect($badBytes, $badSignature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0, hash_file('sha256', $publicPath)),
        'a correctly signed manifest for another platform is rejected'
    );

    foreach (['platform/../escape.txt', 'DCP-UserData/Linux/data/dcp.sqlite', 'platform/storage/keys/master.key', 'platform/config/.env.local'] as $unsafePath) {
        $unsafe = $manifest;
        $unsafe['delta']['files'][0]['path'] = $unsafePath;
        [$unsafeBytes, $unsafeSignature] = $sign($unsafe);
        manifest_rejected(
            static fn() => DcpUpdateManifest::inspect($unsafeBytes, $unsafeSignature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0, hash_file('sha256', $publicPath)),
            'unsafe update path is rejected: ' . $unsafePath
        );
    }

    $unsafeFullDeletion = $manifest;
    $unsafeFullDeletion['full']['deleted'] = ['DCP-UserData/Linux/data/dcp.sqlite'];
    [$fullDeleteBytes, $fullDeleteSignature] = $sign($unsafeFullDeletion);
    manifest_rejected(
        static fn() => DcpUpdateManifest::inspect($fullDeleteBytes, $fullDeleteSignature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0, hash_file('sha256', $publicPath)),
        'full-package fallback cannot delete user data'
    );

    $wrongOrigin = $manifest;
    $wrongOrigin['full']['url'] = 'https://example.invalid/DCP-Linux-x64.tar.gz';
    [$originBytes, $originSignature] = $sign($wrongOrigin);
    manifest_rejected(
        static fn() => DcpUpdateManifest::inspect($originBytes, $originSignature, $publicPath, 'linux', 'x64', '1.0.0-alpha.9', 0, hash_file('sha256', $publicPath)),
        'non-GitHub package origins are rejected'
    );

    DcpUpdateManifest::verifyAssetFile($fullPath, $manifest['full']);
    manifest_ok(true, 'full-package size and SHA-256 verification passes');
    file_put_contents($fullPath, 'tampered');
    manifest_rejected(
        static fn() => DcpUpdateManifest::verifyAssetFile($fullPath, $manifest['full']),
        'full-package byte tampering is rejected'
    );

    echo "ALL UPDATE MANIFEST TESTS PASSED\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
}
