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
    mkdir($root . '/DCP-UserData/Linux/data', 0700, true);
    mkdir($root . '/DCP-UserData/Linux/storage', 0700, true);
    $updater = new DcpLinuxUpdater($root);
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
    linux_health_ok(is_file($root . '/DCP-UserData/Linux/data/dcp.sqlite'), 'post-update HTTP health check boots the packaged API and initializes the isolated SQLite store');
    linux_health_ok(is_file($root . '/DCP-UserData/Linux/storage/keys/master.key'), 'post-update HTTP health check initializes the isolated encryption key');
    linux_health_ok(is_file($work . '/health-check.log'), 'post-update HTTP health check records a local diagnostic log');
    echo "ALL LINUX UPDATER HEALTH TESTS PASSED" . PHP_EOL;
} finally {
    linux_health_remove($root);
}
