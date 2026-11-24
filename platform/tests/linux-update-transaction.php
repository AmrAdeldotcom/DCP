<?php
declare(strict_types=1);
require_once __DIR__ . '/../scripts/lib/UpdateManifest.php';
require_once __DIR__ . '/../scripts/lib/LinuxUpdateTransaction.php';

function linux_update_ok(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function linux_update_rm(string $path): void {
    if (!file_exists($path) && !is_link($path)) return;
    if (is_dir($path) && !is_link($path)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        rmdir($path);
    } else unlink($path);
}
$root = sys_get_temp_dir() . '/dcp-linux-updater-' . bin2hex(random_bytes(5));
$install = $root . '/install';
$user = $install . '/DCP-UserData/Linux';
$stage = $root . '/delta-stage';
mkdir($install . '/platform/app', 0700, true);
mkdir($user . '/data', 0700, true);
mkdir($user . '/storage/keys', 0700, true);
mkdir($stage . '/platform/app', 0700, true);
try {
    file_put_contents($install . '/platform/app/old.php', 'old-version');
    file_put_contents($install . '/platform/app/remove.php', 'obsolete');
    file_put_contents($install . '/DCP-UserData/Linux/data/dcp.sqlite', 'sqlite-must-survive');
    file_put_contents($install . '/DCP-UserData/Linux/storage/keys/master.key', 'master-key-must-survive');
    file_put_contents($stage . '/platform/app/new.php', '<?php echo "new";');
    chmod($stage . '/platform/app/new.php', 0755);
    $manifestFiles = [[
        'path' => 'platform/app/new.php',
        'size' => filesize($stage . '/platform/app/new.php'),
        'sha256' => hash_file('sha256', $stage . '/platform/app/new.php'),
    ]];
    $updater = new DcpLinuxUpdateTransaction($install, $user);
    $result = $updater->applyDelta($stage, $manifestFiles, ['platform/app/old.php', 'platform/app/remove.php']);
    linux_update_ok(($result['ok'] ?? false) && ($result['strategy'] ?? '') === 'delta', 'delta update commits as a transaction');
    linux_update_ok(file_get_contents($install . '/platform/app/new.php') === '<?php echo "new";', 'delta installs the verified replacement file');
    linux_update_ok((fileperms($install . '/platform/app/new.php') & 0111) !== 0, 'delta preserves executable permission bits');
    linux_update_ok(!file_exists($install . '/platform/app/old.php') && !file_exists($install . '/platform/app/remove.php'), 'delta applies signed deletions');
    linux_update_ok(file_get_contents($user . '/data/dcp.sqlite') === 'sqlite-must-survive', 'SQLite user data remains byte-identical');
    linux_update_ok(file_get_contents($user . '/storage/keys/master.key') === 'master-key-must-survive', 'master.key remains byte-identical');
    $rollback = $updater->rollbackLatest();
    linux_update_ok(($rollback['ok'] ?? false) && file_get_contents($install . '/platform/app/old.php') === 'old-version', 'latest transaction can be rolled back');
    linux_update_ok(file_get_contents($install . '/platform/app/remove.php') === 'obsolete' && !file_exists($install . '/platform/app/new.php'), 'rollback restores deleted files and removes newly added files');

    $badStage = $root . '/bad-stage';
    mkdir($badStage . '/platform/app', 0700, true);
    file_put_contents($badStage . '/platform/app/tampered.php', 'tampered');
    $rejected = false;
    try {
        $updater->applyDelta($badStage, [[
            'path' => 'platform/app/tampered.php',
            'size' => 8,
            'sha256' => hash('sha256', 'expected'),
        ]], []);
    } catch (Throwable) { $rejected = true; }
    linux_update_ok($rejected && !file_exists($install . '/platform/app/tampered.php'), 'delta file hash mismatch is rejected before installation mutation');

    $unsafeStage = $root . '/unsafe-stage';
    mkdir($unsafeStage . '/platform', 0700, true);
    file_put_contents($unsafeStage . '/escape.php', 'unsafe');
    $unsafe = false;
    try {
        $updater->applyDelta($unsafeStage, [[
            'path' => '../escape.php',
            'size' => 6,
            'sha256' => hash('sha256', 'unsafe'),
        ]], []);
    } catch (Throwable) { $unsafe = true; }
    linux_update_ok($unsafe, 'path traversal in signed delta metadata is rejected');

    mkdir($install . '/platform/storage/keys', 0700, true);
    mkdir($install . '/platform/data', 0700, true);
    file_put_contents($install . '/platform/storage/keys/legacy-master.key', 'legacy-key-must-survive');
    file_put_contents($install . '/platform/data/legacy.sqlite', 'legacy-db-must-survive');
    $fullStage = $root . '/full-stage';
    mkdir($fullStage . '/platform/app', 0700, true);
    mkdir($fullStage . '/DCP-UserData/Linux/data', 0700, true);
    file_put_contents($fullStage . '/platform/app/full.php', 'full-package-version');
    file_put_contents($fullStage . '/DCP-UserData/Linux/data/dcp.sqlite', 'attacker-data-must-not-copy');
    $fullResult = $updater->applyFullPackage($fullStage);
    linux_update_ok(($fullResult['strategy'] ?? '') === 'full' && file_get_contents($install . '/platform/app/full.php') === 'full-package-version', 'full package fallback replaces the application file set');
    linux_update_ok(file_get_contents($user . '/data/dcp.sqlite') === 'sqlite-must-survive', 'full package fallback never replaces DCP-UserData');
    linux_update_ok(!file_exists($install . '/platform/app/old.php') && !file_exists($install . '/platform/app/remove.php'), 'full package fallback removes obsolete application files');
    linux_update_ok(file_get_contents($install . '/platform/storage/keys/legacy-master.key') === 'legacy-key-must-survive' && file_get_contents($install . '/platform/data/legacy.sqlite') === 'legacy-db-must-survive', 'full package fallback preserves legacy platform storage and data directories');

    $symlinkInstall = $root . '/symlink-install';
    $symlinkUser = $symlinkInstall . '/DCP-UserData/Linux';
    mkdir($symlinkInstall . '/platform/app', 0700, true);
    mkdir($symlinkUser . '/data', 0700, true);
    mkdir($symlinkUser . '/storage', 0700, true);
    $outside = $root . '/outside-backups'; mkdir($outside, 0700, true);
    symlink($outside, $symlinkUser . '/storage/updates');
    $symlinkStage = $root . '/symlink-stage'; mkdir($symlinkStage . '/platform/app', 0700, true);
    file_put_contents($symlinkStage . '/platform/app/never-installed.php', 'must not escape');
    $symlinkRejected = false;
    try {
        (new DcpLinuxUpdateTransaction($symlinkInstall, $symlinkUser))->applyDelta($symlinkStage, [[
            'path' => 'platform/app/never-installed.php',
            'size' => filesize($symlinkStage . '/platform/app/never-installed.php'),
            'sha256' => hash_file('sha256', $symlinkStage . '/platform/app/never-installed.php'),
        ]], []);
    } catch (Throwable) { $symlinkRejected = true; }
    linux_update_ok($symlinkRejected && !file_exists($symlinkInstall . '/platform/app/never-installed.php') && count(scandir($outside)) === 2, 'symlinked user-data backup directory is rejected without writing outside the install');
    echo "ALL LINUX UPDATE TRANSACTION TESTS PASSED" . PHP_EOL;
} finally {
    linux_update_rm($root);
}
