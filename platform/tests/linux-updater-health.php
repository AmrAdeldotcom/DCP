<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/update-linux.php';

function linux_health_ok(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function linux_health_remove(string $path): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isLink() || $item->isFile()) @unlink($item->getPathname());
        elseif ($item->isDir()) @rmdir($item->getPathname());
    }
    @rmdir($path);
}

$root = sys_get_temp_dir() . '/dcp-linux-health-' . bin2hex(random_bytes(5));
$work = $root . '/health-work';
mkdir($root, 0700, true);
mkdir($work, 0700, true);
try {
    symlink(realpath(dirname(__DIR__)), $root . '/platform');
    file_put_contents($root . '/DCP', "\x7fELF" . str_repeat("\0", 64));
    chmod($root . '/DCP', 0755);
    mkdir($root . '/DCP-UserData/Linux/data', 0700, true);
    mkdir($root . '/DCP-UserData/Linux/storage', 0700, true);
    $updater = new DcpLinuxUpdater($root);
    $offlineUpdater = new DcpLinuxUpdater($root, static function (string $url, int $maxBytes): string {
        throw new DcpUpdateNetworkException('Simulated GitHub connection timeout.');
    });
    $offlineResult = $offlineUpdater->check();
    linux_health_ok(($offlineResult['ok'] ?? false) === true && ($offlineResult['available'] ?? true) === false && ($offlineResult['retryable'] ?? false) === true, 'temporary GitHub outage is reported as retryable without failing the packaged updater health check');
    linux_health_ok(str_contains((string)($offlineResult['message'] ?? ''), 'DCP remains available'), 'offline update check explicitly preserves normal DCP availability');
    $assetCheck = new ReflectionMethod(DcpLinuxUpdater::class, 'assertManifestAssetsPresent');
    $assetCheck->setAccessible(true);
    $fullUrl = 'https://github.com/AmrAdeldotcom/DCP/releases/download/v1.0.0-alpha.10/DCP-Linux-x64.tar.gz';
    $deltaUrl = 'https://github.com/AmrAdeldotcom/DCP/releases/download/v1.0.0-alpha.10/DCP-Linux-x64-delta.tar.gz';
    $signedAssets = [
        'full' => ['name' => 'DCP-Linux-x64.tar.gz', 'url' => $fullUrl],
        'delta' => ['asset' => ['name' => 'DCP-Linux-x64-delta.tar.gz', 'url' => $deltaUrl]],
    ];
    linux_health_ok($assetCheck->invoke($updater, $signedAssets, ['DCP-Linux-x64.tar.gz' => $fullUrl]) === false, 'missing optional delta asset selects the signed full-package fallback');
    linux_health_ok($assetCheck->invoke($updater, $signedAssets, ['DCP-Linux-x64.tar.gz' => $fullUrl, 'DCP-Linux-x64-delta.tar.gz' => $deltaUrl]) === true, 'exact official delta asset enables the delta strategy');
    $badDeltaOriginRejected = false;
    try { $assetCheck->invoke($updater, $signedAssets, ['DCP-Linux-x64.tar.gz' => $fullUrl, 'DCP-Linux-x64-delta.tar.gz' => 'https://example.invalid/delta.tar.gz']); }
    catch (Throwable) { $badDeltaOriginRejected = true; }
    linux_health_ok($badDeltaOriginRejected, 'a delta asset with a non-release URL is rejected instead of silently falling back');
    $dbPath = $root . '/DCP-UserData/Linux/data/dcp.sqlite';
    $keyDir = $root . '/DCP-UserData/Linux/storage/keys';
    mkdir($keyDir, 0700, true);
    $keyPath = $keyDir . '/master.key';
    file_put_contents($dbPath, 'original-sqlite-bytes');
    file_put_contents($keyPath, 'original-master-key');
    $snapshotMethod = new ReflectionMethod(DcpLinuxUpdater::class, 'snapshotCriticalData');
    $snapshotMethod->setAccessible(true);
    $snapshot = $snapshotMethod->invoke($updater, $work);
    file_put_contents($dbPath, 'changed-sqlite-bytes');
    file_put_contents($keyPath, 'changed-master-key');
    $restoreMethod = new ReflectionMethod(DcpLinuxUpdater::class, 'restoreCriticalData');
    $restoreMethod->setAccessible(true);
    $restoreMethod->invoke($updater, $snapshot);
    linux_health_ok(file_get_contents($dbPath) === 'original-sqlite-bytes' && file_get_contents($keyPath) === 'original-master-key', 'critical SQLite and master.key snapshots restore byte-identically');
    unlink($dbPath); unlink($keyPath);
    $method = new ReflectionMethod(DcpLinuxUpdater::class, 'healthCheck');
    $method->setAccessible(true);
    $method->invoke($updater, $work);
    linux_health_ok(!file_exists($root . '/DCP-UserData/Linux/data/dcp.sqlite') && !file_exists($root . '/DCP-UserData/Linux/storage/keys/master.key'), 'post-update health check does not create or mutate the real user database or master key');
    linux_health_ok(is_file($work . '/health-user-data/data/dcp.sqlite'), 'post-update HTTP health check boots the API using disposable isolated SQLite data');
    linux_health_ok(is_file($work . '/health-user-data/storage/keys/master.key'), 'post-update HTTP health check creates its encryption key only inside disposable data');
    linux_health_ok(is_file($work . '/health-check.log'), 'post-update HTTP health check records a local diagnostic log');

    $router = realpath($root . '/platform/public/router.php');
    $public = dirname((string)$router);
    $server = proc_open([PHP_BINARY, '-S', '127.0.0.1:0', '-t', $public, $router], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $serverPipes, $root);
    if (!is_resource($server)) throw new RuntimeException('Could not start process-guard fixture.');
    try {
        $guard = new ReflectionMethod(DcpLinuxUpdater::class, 'assertNoRunningDcpProcesses');
        $guard->setAccessible(true);
        $blocked = false;
        try { $guard->invoke($updater); } catch (ReflectionException|Throwable) { $blocked = true; }
        $state = proc_get_status($server);
        linux_health_ok($blocked && !empty($state['running']), 'updater refuses to modify files while the packaged DCP PHP server is running and does not kill it');
    } finally {
        proc_terminate($server);
        foreach ($serverPipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($server);
    }
    $updaterSource = file_get_contents(__DIR__ . '/../scripts/update-linux.php');
    $builderSource = file_get_contents(__DIR__ . '/../scripts/build-linux.sh');
    $watchStart = strpos($updaterSource, "if (" . '$mode' . " === '--watch')");
    $watchBlock = $watchStart === false ? '' : substr($updaterSource, $watchStart);
    linux_health_ok(method_exists(DcpLinuxUpdater::class, 'download') && str_contains($updaterSource, "if (" . '$mode' . " === '--download')"), 'Linux updater exposes a CLI operation to download and verify a signed package before apply');
    linux_health_ok(str_contains($watchBlock, '$updater->download()') && str_contains($watchBlock, '$updater->apply()') && str_contains($watchBlock, 'sleep(30)') && str_contains($watchBlock, 'sleep(300)'), 'background watcher waits for app startup, then downloads signed updates every five minutes and applies only through the transactional updater');
    linux_health_ok(str_contains($watchBlock, 'exit(0)') && str_contains($watchBlock, 'exiting after successful apply'), 'watcher exits after applying an update so it cannot keep running stale updater code or a replaced PHP runtime');
    linux_health_ok(str_contains($builderSource, 'update-linux.php" --watch') && str_contains($builderSource, 'update-watch.pid'), 'packaged Linux launcher starts one per-install background update watcher');
    linux_health_ok(str_contains($builderSource, '${DCP_DISABLE_UPDATE_WATCH:-0}'), 'Linux launcher safely defaults the optional watcher flag under set -u');
    echo "ALL LINUX UPDATER HEALTH TESTS PASSED" . PHP_EOL;
} finally {
    linux_health_remove($root);
}
