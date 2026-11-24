<?php
declare(strict_types=1);

require_once __DIR__ . '/../scripts/update-macos.php';

$assertions = 0;
function checkMacUpdate(bool $condition, string $message): void {
    global $assertions;
    $assertions++;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . PHP_EOL;
}
function rejectMacUpdate(callable $callback, string $message): void {
    $rejected = false;
    try { $callback(); } catch (RuntimeException) { $rejected = true; }
    checkMacUpdate($rejected, $message);
}
function makeMacTransactionFixture(string $base, string $name): array {
    $install = $base . '/' . $name . '/install';
    $stage = $base . '/' . $name . '/stage';
    $work = $base . '/' . $name . '/work';
    foreach ([$install . '/DCP.app/Contents', $install . '/platform/app', $install . '/DCP-UserData/macOS/data',
              $install . '/DCP-UserData/macOS/storage/keys', $stage . '/DCP.app/Contents',
              $stage . '/platform/app', $work] as $directory) {
        if (!is_dir($directory)) mkdir($directory, 0700, true);
    }
    file_put_contents($install . '/DCP.app/Contents/Info.plist', 'old app metadata');
    file_put_contents($install . '/platform/app/index.php', 'old application');
    file_put_contents($install . '/platform/app/removed.php', 'old file to delete');
    file_put_contents($stage . '/DCP.app/Contents/Info.plist', 'new app metadata');
    file_put_contents($stage . '/platform/app/index.php', 'new application');
    file_put_contents($stage . '/platform/app/new.php', 'new application file');
    file_put_contents($install . '/DCP-UserData/macOS/data/dcp.sqlite', 'real database fixture');
    file_put_contents($install . '/DCP-UserData/macOS/data/dcp.sqlite-wal', 'real wal fixture');
    file_put_contents($install . '/DCP-UserData/macOS/data/dcp.sqlite-shm', 'real shm fixture');
    file_put_contents($install . '/DCP-UserData/macOS/storage/keys/master.key', 'real encryption key fixture');
    $records = [];
    foreach (['DCP.app/Contents/Info.plist', 'platform/app/index.php', 'platform/app/new.php'] as $relative) {
        $file = $stage . '/' . $relative;
        $records[] = ['path' => $relative, 'size' => filesize($file), 'sha256' => hash_file('sha256', $file)];
    }
    return [
        'install' => $install,
        'stage' => $stage,
        'work' => $work,
        'package' => ['stage' => $stage, 'manifest' => ['files' => $records, 'deleted' => ['platform/app/removed.php']]],
    ];
}

function makeMacZip(string $root, string $name, array $files): string {
    $source = $root . '/' . $name . '-source';
    mkdir($source, 0700, true);
    foreach ($files as $relative => $contents) {
        $path = $source . '/' . $relative;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $contents);
    }
    $zip = $root . '/' . $name . '.zip';
    $cwd = getcwd();
    chdir($source);
    exec('/usr/bin/zip -q -r ' . escapeshellarg($zip) . ' .', $output, $status);
    chdir($cwd);
    if ($status !== 0 || !is_file($zip)) throw new RuntimeException('Could not create ZIP test fixture.');
    return $zip;
}

$root = sys_get_temp_dir() . '/dcp-macos-update-tests-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
try {
    DcpMacOSUpdater::validateUpdatePath('DCP.app/Contents/Info.plist');
    checkMacUpdate(true, 'application bundle paths are accepted');
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateUpdatePath('../escape.php'), 'path traversal is rejected');
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateUpdatePath('DCP-UserData/macOS/data/dcp.sqlite'), 'user data is protected');
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateUpdatePath('platform/storage/keys/master.key'), 'platform storage is protected');
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateUpdatePath('platform/app/.env.local'), 'environment secrets are protected');
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateUpdatePath('platform/app\\index.php'), 'backslash path aliases are rejected');

    checkMacUpdate(DcpMacOSUpdater::compareVersions('1.0.0-alpha.10', '1.0.0-alpha.9') > 0, 'alpha.10 sorts after alpha.9');
    checkMacUpdate(DcpMacOSUpdater::compareVersions('1.0.0', '1.0.0-alpha.10') > 0, 'stable release sorts after prerelease');
    checkMacUpdate(DcpMacOSUpdater::compareVersions('1.0.0-alpha.9', '1.0.0-alpha.10') < 0, 'older prerelease does not trigger downgrade');

    $private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($private === false) throw new RuntimeException('Could not create ephemeral RSA test key.');
    $details = openssl_pkey_get_details($private);
    $public = $details['key'];
    $bytes = 'DCP signed macOS update manifest fixture';
    openssl_sign($bytes, $signature, $private, OPENSSL_ALGO_SHA256);
    checkMacUpdate(DcpMacOSUpdater::verifyDetachedSignature($bytes, $signature, $public), 'RSA-SHA256 detached manifest signature verifies');
    checkMacUpdate(!DcpMacOSUpdater::verifyDetachedSignature($bytes . 'tampered', $signature, $public), 'tampered manifest bytes are rejected');

    $architecture = in_array(strtolower(php_uname('m')), ['arm64', 'aarch64'], true) ? 'arm64' : 'x64';
    $fullName = $architecture === 'arm64' ? 'DCP-macOS-AppleSilicon.zip' : 'DCP-macOS-Intel.zip';
    $deltaName = $architecture === 'arm64' ? 'DCP-macOS-AppleSilicon-delta.zip' : 'DCP-macOS-Intel-delta.zip';
    $tag = 'v1.0.0-alpha.10';
    $fullUrl = 'https://github.com/AmrAdeldotcom/DCP/releases/download/' . $tag . '/' . $fullName;
    $deltaUrl = 'https://github.com/AmrAdeldotcom/DCP/releases/download/' . $tag . '/' . $deltaName;
    $manifest = [
        'schema' => 3,
        'key_id' => 'dcp-alpha10-rsa3072-20261124',
        'platform' => 'macos',
        'architecture' => $architecture,
        'version' => '1.0.0-alpha.10',
        'release_tag' => $tag,
        'package_revision' => 1,
        'commit' => str_repeat('a', 40),
        'full' => ['name' => $fullName, 'url' => $fullUrl, 'size' => 1024, 'sha256' => str_repeat('a', 64), 'deleted' => ['platform/app/old.php']],
        'delta' => [
            'base' => ['version' => '1.0.0-alpha.9', 'release_tag' => 'v1.0.0-alpha.9', 'package_revision' => 0],
            'asset' => ['name' => $deltaName, 'url' => $deltaUrl, 'size' => 512, 'sha256' => str_repeat('b', 64)],
            'files' => [['path' => 'platform/app/new.php', 'size' => 12, 'sha256' => str_repeat('c', 64)]],
            'deleted' => ['platform/app/old.php'],
        ],
    ];
    DcpMacOSUpdater::validateManifest($manifest, 'macos', $architecture, $tag, [$fullName => $fullUrl]);
    checkMacUpdate(true, 'schema-3 macOS manifest accepts signed full fallback and optional delta asset');
    $unsafeFull = $manifest;
    $unsafeFull['full']['deleted'] = ['DCP-UserData/macOS/data/dcp.sqlite'];
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateManifest($unsafeFull, 'macos', $architecture, $tag, [$fullName => $fullUrl]), 'full-package manifest cannot delete user data');
    $unsafeDelta = $manifest;
    $unsafeDelta['delta']['files'][0]['path'] = '../escape.php';
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateManifest($unsafeDelta, 'macos', $architecture, $tag, [$fullName => $fullUrl]), 'delta manifest rejects traversal paths');
    rejectMacUpdate(fn() => DcpMacOSUpdater::validateManifest($manifest, 'macos', $architecture === 'arm64' ? 'x64' : 'arm64', $tag, [$fullName => $fullUrl]), 'signed macOS manifest cannot be replayed across architectures');

    $fullZip = makeMacZip($root, 'full', [
        'DCP.app/Contents/Info.plist' => 'app bundle',
        'platform/app/index.php' => '<?php echo "ok";',
        'DCP-UserData/macOS/data/dcp.sqlite' => 'must not be extracted',
    ]);
    $fullStage = $root . '/full-stage';
    $files = DcpMacOSUpdater::extractZipSafely($fullZip, $fullStage, true);
    checkMacUpdate(is_file($fullStage . '/DCP.app/Contents/Info.plist'), 'full ZIP extracts application bundle files');
    checkMacUpdate(!file_exists($fullStage . '/DCP-UserData'), 'full ZIP skips packaged user data');
    checkMacUpdate(count($files) === 2, 'full ZIP file inventory excludes protected user data');

    $deltaZip = makeMacZip($root, 'delta-user', ['DCP-UserData/macOS/data/dcp.sqlite' => 'bad']);
    rejectMacUpdate(fn() => DcpMacOSUpdater::extractZipSafely($deltaZip, $root . '/delta-user-stage', false), 'delta ZIP containing user data is rejected');

    $protectedZip = makeMacZip($root, 'protected', ['platform/storage/keys/master.key' => 'bad']);
    rejectMacUpdate(fn() => DcpMacOSUpdater::extractZipSafely($protectedZip, $root . '/protected-stage', true), 'ZIP protected storage entry is rejected');

    $traversalZip = $root . '/traversal.zip';
    $python = 'import zipfile,sys; z=zipfile.ZipFile(sys.argv[1],"w"); z.writestr("../escape.txt","bad"); z.close()';
    exec('python3 -c ' . escapeshellarg($python) . ' ' . escapeshellarg($traversalZip), $output, $status);
    if ($status !== 0) throw new RuntimeException('Could not create traversal ZIP fixture.');
    rejectMacUpdate(fn() => DcpMacOSUpdater::extractZipSafely($traversalZip, $root . '/traversal-stage', true), 'ZIP path traversal is rejected');

    $successFixture = makeMacTransactionFixture($root, 'transaction-success');
    putenv('DCP_INSTALL_ROOT=' . $successFixture['install']);
    $successBackup = DcpMacOSUpdater::applyTransaction($successFixture['install'], $successFixture['package'], $successFixture['work'], false, static fn(): bool => true);
    checkMacUpdate(file_get_contents($successFixture['install'] . '/DCP.app/Contents/Info.plist') === 'new app metadata', 'transaction replaces the app bundle payload');
    checkMacUpdate(file_get_contents($successFixture['install'] . '/platform/app/index.php') === 'new application', 'transaction replaces package-owned platform files');
    checkMacUpdate(is_file($successFixture['install'] . '/platform/app/new.php'), 'transaction installs new package-owned files');
    checkMacUpdate(!file_exists($successFixture['install'] . '/platform/app/removed.php'), 'transaction applies signed deletion list');
    checkMacUpdate(file_get_contents($successFixture['install'] . '/DCP-UserData/macOS/data/dcp.sqlite') === 'real database fixture' &&
        file_get_contents($successFixture['install'] . '/DCP-UserData/macOS/data/dcp.sqlite-wal') === 'real wal fixture' &&
        file_get_contents($successFixture['install'] . '/DCP-UserData/macOS/storage/keys/master.key') === 'real encryption key fixture',
        'successful transaction preserves SQLite, WAL and master.key');
    checkMacUpdate(file_get_contents($successBackup . '/files/DCP.app/Contents/Info.plist') === 'old app metadata', 'successful transaction retains the previous app bundle files in its rollback snapshot');
    $successJournal = json_decode((string)file_get_contents($successBackup . '/journal.json'), true);
    checkMacUpdate(is_array($successJournal) && ($successJournal['status'] ?? '') === 'committed' && ($successJournal['health_status'] ?? '') === 'passed', 'successful transaction marks its durable journal healthy');

    $rollbackFixture = makeMacTransactionFixture($root, 'transaction-rollback');
    putenv('DCP_INSTALL_ROOT=' . $rollbackFixture['install']);
    $rolledBack = false;
    try {
        DcpMacOSUpdater::applyTransaction($rollbackFixture['install'], $rollbackFixture['package'], $rollbackFixture['work'], false, static fn(): bool => false);
    } catch (RuntimeException) { $rolledBack = true; }
    checkMacUpdate($rolledBack, 'failed post-update health check triggers transactional rollback');
    checkMacUpdate(file_get_contents($rollbackFixture['install'] . '/DCP.app/Contents/Info.plist') === 'old app metadata', 'rollback restores the complete previous app bundle');
    checkMacUpdate(file_get_contents($rollbackFixture['install'] . '/platform/app/index.php') === 'old application' &&
        !file_exists($rollbackFixture['install'] . '/platform/app/new.php') &&
        file_get_contents($rollbackFixture['install'] . '/platform/app/removed.php') === 'old file to delete',
        'rollback restores replaced files, removes new files and restores deleted files');
    checkMacUpdate(file_get_contents($rollbackFixture['install'] . '/DCP-UserData/macOS/data/dcp.sqlite') === 'real database fixture' &&
        file_get_contents($rollbackFixture['install'] . '/DCP-UserData/macOS/storage/keys/master.key') === 'real encryption key fixture',
        'rollback preserves SQLite and master.key');

    $crashFixture = makeMacTransactionFixture($root, 'transaction-crash-recovery');
    $backupRoot = $crashFixture['install'] . '/DCP-UserData/macOS/storage/updates/backups/20261124T170000Z-crash';
    mkdir($backupRoot . '/files/DCP.app/Contents', 0700, true);
    mkdir($backupRoot . '/files/platform/app', 0700, true);
    copy($crashFixture['install'] . '/DCP.app/Contents/Info.plist', $backupRoot . '/files/DCP.app/Contents/Info.plist');
    copy($crashFixture['install'] . '/platform/app/index.php', $backupRoot . '/files/platform/app/index.php');
    copy($crashFixture['install'] . '/platform/app/removed.php', $backupRoot . '/files/platform/app/removed.php');
    file_put_contents($crashFixture['install'] . '/DCP.app/Contents/Info.plist', 'partially updated app');
    file_put_contents($crashFixture['install'] . '/platform/app/index.php', 'partially updated platform');
    file_put_contents($crashFixture['install'] . '/platform/app/new.php', 'partially installed file');
    unlink($crashFixture['install'] . '/platform/app/removed.php');
    $crashJournal = [
        'schema' => 1,
        'id' => basename($backupRoot),
        'strategy' => 'delta',
        'status' => 'applying',
        'health_status' => 'pending',
        'created_at' => gmdate(DATE_ATOM),
        'operations' => [
            ['path' => 'DCP.app/Contents/Info.plist', 'existed' => true, 'mode' => 0644, 'applied' => true],
            ['path' => 'platform/app/index.php', 'existed' => true, 'mode' => 0644, 'applied' => true],
            ['path' => 'platform/app/new.php', 'existed' => false, 'mode' => 0644, 'applied' => true],
            ['path' => 'platform/app/removed.php', 'existed' => true, 'mode' => 0644, 'applied' => true],
        ],
    ];
    file_put_contents($backupRoot . '/journal.json', json_encode($crashJournal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    putenv('DCP_INSTALL_ROOT=' . $crashFixture['install']);
    $recovered = DcpMacOSUpdater::recoverInterrupted($crashFixture['install'], 0, false);
    checkMacUpdate($recovered === 1, 'startup recovery detects and replays an interrupted macOS transaction journal');
    checkMacUpdate(file_get_contents($crashFixture['install'] . '/DCP.app/Contents/Info.plist') === 'old app metadata' &&
        file_get_contents($crashFixture['install'] . '/platform/app/index.php') === 'old application' &&
        !file_exists($crashFixture['install'] . '/platform/app/new.php') &&
        file_get_contents($crashFixture['install'] . '/platform/app/removed.php') === 'old file to delete',
        'crash recovery restores old app and platform files and removes partial additions');
    $recoveredJournal = json_decode((string)file_get_contents($backupRoot . '/journal.json'), true);
    checkMacUpdate(is_array($recoveredJournal) && ($recoveredJournal['status'] ?? '') === 'rolled_back',
        'crash recovery durably marks the interrupted journal rolled back');
    checkMacUpdate(file_get_contents($crashFixture['install'] . '/DCP-UserData/macOS/data/dcp.sqlite') === 'real database fixture' &&
        file_get_contents($crashFixture['install'] . '/DCP-UserData/macOS/storage/keys/master.key') === 'real encryption key fixture',
        'crash recovery leaves the user database and encryption key untouched');
    $recoverScript = realpath(__DIR__ . '/../scripts/update-macos.php');
    $recoverCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($recoverScript) . ' --recover --root ' . escapeshellarg($crashFixture['install']);
    exec($recoverCommand . ' 2>&1', $recoverOutput, $recoverStatus);
    checkMacUpdate($recoverStatus === 0, 'macOS updater CLI accepts --recover when no interrupted transaction remains');
    $lockPath = $crashFixture['install'] . '/DCP-UserData/macOS/storage/updates/updater.lock';
    $lockHandle = fopen($lockPath, 'c');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) throw new RuntimeException('Could not acquire updater lock test fixture.');
    $busyRecovery = DcpMacOSUpdater::recoverInterrupted($crashFixture['install'], 0, false);
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    checkMacUpdate($busyRecovery === -2, 'startup recovery refuses to race an active updater lock');

    $processFixture = $root . '/process-ownership';
    $fakeApp = $processFixture . '/DCP.app/Contents/MacOS/DCP';
    $processStorage = $processFixture . '/DCP-UserData/macOS/storage';
    mkdir(dirname($fakeApp), 0700, true);
    mkdir($processStorage, 0700, true);
    $fakeCommand = '/usr/bin/nohup /bin/bash -c ' . escapeshellarg('exec -a "$1" /bin/sleep 60') .
        ' dcp-test ' . escapeshellarg($fakeApp) . ' >/dev/null 2>&1 & echo $!';
    exec($fakeCommand, $fakePidOutput, $fakePidStatus);
    $fakePid = (int)trim((string)($fakePidOutput[0] ?? '0'));
    if ($fakePidStatus !== 0 || $fakePid < 2) throw new RuntimeException('Could not start isolated DCP process fixture.');
    usleep(200000);
    DcpMacOSUpdater::stopCurrentApp($processFixture);
    $fakeStillRunning = is_string(shell_exec('/bin/ps -p ' . $fakePid . ' -o pid=')) &&
        trim((string)shell_exec('/bin/ps -p ' . $fakePid . ' -o pid=')) !== '';
    checkMacUpdate(!$fakeStillRunning, 'rollback process guard stops only the exact DCP.app executable even without runtime-pid.txt');

    $aliasRoot = $root . '/process-alias';
    $aliasApp = $aliasRoot . '/DCP.app/Contents/MacOS/DCP';
    mkdir(dirname($aliasApp), 0700, true);
    mkdir($aliasRoot . '/platform/public', 0700, true);
    mkdir($aliasRoot . '/DCP-UserData/macOS/storage', 0700, true);
    file_put_contents($aliasApp, 'isolated DCP path alias fixture');
    $canonicalAliasRoot = realpath($aliasRoot);
    $aliasPidOutput = [];
    exec('/usr/bin/nohup /bin/bash -c ' . escapeshellarg('exec -a "$1" /bin/sleep 3') .
        ' dcp-alias-test ' . escapeshellarg($aliasApp) . ' >/dev/null 2>&1 & echo $!', $aliasPidOutput, $aliasPidStatus);
    $aliasPid = (int)trim((string)($aliasPidOutput[0] ?? '0'));
    if ($aliasPidStatus !== 0 || $aliasPid < 2) throw new RuntimeException('Could not start DCP path-alias process fixture.');
    $matchMethod = new ReflectionMethod(DcpMacOSUpdater::class, 'processMatchesDcpApp');
    $matchMethod->setAccessible(true);
    checkMacUpdate($canonicalAliasRoot !== false && $canonicalAliasRoot !== $aliasRoot &&
        $matchMethod->invoke(null, $aliasPid, $aliasRoot), 'process identity accepts the lexical /var installation path and its canonical /private/var path');
    $guardMethod = new ReflectionMethod(DcpMacOSUpdater::class, 'assertNoRunningDcpProcesses');
    $guardMethod->setAccessible(true);
    rejectMacUpdate(fn() => $guardMethod->invoke(null, $aliasRoot, 0), 'process guard refuses a live DCP app launched through a symlinked install path');
    $recoverAliasCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($recoverScript) .
        ' --recover --root ' . escapeshellarg($aliasRoot) . ' --pid ' . $aliasPid;
    exec($recoverAliasCommand . ' 2>&1', $aliasRecoveryOutput, $aliasRecoveryStatus);
    checkMacUpdate($aliasRecoveryStatus === 0, 'CLI recovery preserves the lexical install path when validating the current DCP PID');
    $waitMethod = new ReflectionMethod(DcpMacOSUpdater::class, 'waitForAppExit');
    $waitMethod->setAccessible(true);
    $waitStarted = microtime(true);
    $waitMethod->invoke(null, $aliasPid, $aliasRoot);
    checkMacUpdate(microtime(true) - $waitStarted >= 1.0, 'updater waits for the exact DCP process before package mutation through a symlinked path');

    $unrelatedCommand = '/usr/bin/nohup /bin/sleep 60 >/dev/null 2>&1 & echo $!';
    exec($unrelatedCommand, $unrelatedPidOutput, $unrelatedPidStatus);
    $unrelatedPid = (int)trim((string)($unrelatedPidOutput[0] ?? '0'));
    if ($unrelatedPidStatus !== 0 || $unrelatedPid < 2) throw new RuntimeException('Could not start unrelated process fixture.');
    file_put_contents($processStorage . '/runtime-pid.txt', (string)$unrelatedPid);
    DcpMacOSUpdater::stopCurrentApp($processFixture);
    $unrelatedStillRunning = trim((string)shell_exec('/bin/ps -p ' . $unrelatedPid . ' -o pid=')) !== '';
    checkMacUpdate($unrelatedStillRunning, 'a stale DCP PID file never terminates an unrelated process');
    if (function_exists('posix_kill')) @posix_kill($unrelatedPid, SIGTERM);

    echo 'ALL MACOS UPDATE CONTRACT TESTS PASSED (' . $assertions . ' assertions)' . PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) @rmdir($item->getPathname());
        else @unlink($item->getPathname());
    }
    @rmdir($root);
}
