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
mkdir($user . '/storage/logs', 0700, true);
mkdir($user . '/settings', 0700, true);
mkdir($user . '/workspaces/demo', 0700, true);
mkdir($user . '/models', 0700, true);
mkdir($stage . '/platform/app', 0700, true);
try {
    file_put_contents($install . '/platform/app/old.php', 'old-version');
    file_put_contents($install . '/platform/app/remove.php', 'obsolete');
    file_put_contents($install . '/DCP-UserData/Linux/data/dcp.sqlite', 'sqlite-must-survive');
    file_put_contents($install . '/DCP-UserData/Linux/storage/keys/master.key', 'master-key-must-survive');
    file_put_contents($user . '/storage/logs/updater.log', 'keep-log');
    file_put_contents($user . '/settings/settings.json', '{"keep":true}');
    file_put_contents($user . '/workspaces/demo/project.txt', 'keep-workspace');
    file_put_contents($user . '/models/model.gguf', 'keep-model');
    $protectedHashes = [];
    foreach ([$user . '/data/dcp.sqlite', $user . '/storage/keys/master.key', $user . '/storage/logs/updater.log', $user . '/settings/settings.json', $user . '/workspaces/demo/project.txt', $user . '/models/model.gguf'] as $protectedPath) {
        $protectedHashes[$protectedPath] = hash_file('sha256', $protectedPath);
    }
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
    foreach ($protectedHashes as $protectedPath => $expectedHash) {
        linux_update_ok(hash_file('sha256', $protectedPath) === $expectedHash, 'user data preserved: ' . basename($protectedPath));
    }
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
    file_put_contents($install . '/operator-notes.txt', 'user-created install-root file');
    $fullStage = $root . '/full-stage';
    mkdir($fullStage . '/platform/app', 0700, true);
    mkdir($fullStage . '/DCP-UserData/Linux/data', 0700, true);
    file_put_contents($fullStage . '/platform/app/full.php', 'full-package-version');
    file_put_contents($fullStage . '/DCP-UserData/Linux/data/dcp.sqlite', 'attacker-data-must-not-copy');
    $fullResult = $updater->applyFullPackage($fullStage, ['platform/app/old.php', 'platform/app/remove.php']);
    linux_update_ok(($fullResult['strategy'] ?? '') === 'full' && file_get_contents($install . '/platform/app/full.php') === 'full-package-version', 'full package fallback replaces the application file set');
    linux_update_ok(file_get_contents($user . '/data/dcp.sqlite') === 'sqlite-must-survive', 'full package fallback never replaces DCP-UserData');
    linux_update_ok(!file_exists($install . '/platform/app/old.php') && !file_exists($install . '/platform/app/remove.php'), 'full package fallback applies only the signed obsolete-file deletion list');
    linux_update_ok(file_get_contents($install . '/operator-notes.txt') === 'user-created install-root file', 'full package fallback preserves unrecognized installation-root files');
    linux_update_ok(file_get_contents($install . '/platform/storage/keys/legacy-master.key') === 'legacy-key-must-survive' && file_get_contents($install . '/platform/data/legacy.sqlite') === 'legacy-db-must-survive', 'full package fallback preserves legacy platform storage and data directories');
    $healthy = $updater->markLatestHealthy();
    linux_update_ok(($healthy['ok'] ?? false) === true, 'committed update is marked healthy only after explicit health verification');

    // Simulate power loss after a replacement has been renamed but before the
    // transaction can mark itself committed. Recovery must restore the journal.
    $crashId = gmdate('Ymd\THis\Z') . '-crash-test';
    $crashBackup = $user . '/storage/updates/backups/' . $crashId;
    mkdir($crashBackup . '/files/platform/app', 0700, true);
    file_put_contents($install . '/platform/app/crash.php', 'partially-applied');
    file_put_contents($crashBackup . '/files/platform/app/crash.php', 'before-crash');
    file_put_contents($install . '/platform/app/crash-new.php', 'partially-created');
    file_put_contents($install . '/platform/app/.dcp-update-crash-temp', 'partial-temp');
    $crashJournal = [
        'schema' => 1, 'id' => $crashId, 'strategy' => 'delta', 'status' => 'applying',
        'operations' => [
            ['path' => 'platform/app/crash.php', 'existed' => true, 'mode' => 0644, 'applied' => true, 'temporary' => 'platform/app/.dcp-update-crash-temp'],
            ['path' => 'platform/app/crash-new.php', 'existed' => false, 'mode' => 0644, 'applied' => true],
        ],
    ];
    file_put_contents($crashBackup . '/journal.json', json_encode($crashJournal, JSON_THROW_ON_ERROR));
    linux_update_ok($updater->hasIncompleteTransactions(), 'interrupted update journal is detected');
    $recovery = $updater->recoverInterruptedTransactions();
    linux_update_ok(($recovery['recovered'] ?? 0) === 1, 'interrupted transaction is automatically recovered');
    linux_update_ok(file_get_contents($install . '/platform/app/crash.php') === 'before-crash' && !file_exists($install . '/platform/app/crash-new.php'), 'crash recovery restores originals and removes incomplete new files');
    linux_update_ok(!file_exists($install . '/platform/app/.dcp-update-crash-temp'), 'crash recovery removes incomplete same-directory temporary files');

    // Simulate a process crash after file application committed but before the
    // post-update health gate could mark the snapshot healthy.
    $pendingId = gmdate('Ymd\THis\Z') . '-health-pending';
    $pendingBackup = $user . '/storage/updates/backups/' . $pendingId;
    mkdir($pendingBackup . '/files/platform/app', 0700, true);
    file_put_contents($install . '/platform/app/pending-health.php', 'new-unhealthy');
    file_put_contents($pendingBackup . '/files/platform/app/pending-health.php', 'old-healthy');
    file_put_contents($pendingBackup . '/journal.json', json_encode([
        'schema' => 1, 'id' => $pendingId, 'strategy' => 'delta', 'status' => 'committed', 'health_status' => 'pending',
        'operations' => [['path' => 'platform/app/pending-health.php', 'existed' => true, 'mode' => 0644, 'applied' => true]],
    ], JSON_THROW_ON_ERROR));
    linux_update_ok($updater->hasIncompleteTransactions(), 'committed update awaiting health confirmation is treated as incomplete');
    $healthRecovery = $updater->recoverInterruptedTransactions();
    linux_update_ok(($healthRecovery['recovered'] ?? 0) === 1 && file_get_contents($install . '/platform/app/pending-health.php') === 'old-healthy', 'startup recovery rolls back a committed update whose health check was interrupted');

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
