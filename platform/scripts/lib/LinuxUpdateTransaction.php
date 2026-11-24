<?php
declare(strict_types=1);

/**
 * File-level transactional updater for the portable Linux package.
 * User data is outside the mutable package set and is never copied or replaced.
 */
final class DcpLinuxUpdateTransaction
{
    public function __construct(
        private readonly string $installRoot,
        private readonly string $userDataRoot
    ) {}

    /** @param list<array{path:string,size:int,sha256:string}> $files @param list<string> $deleted */
    public function applyDelta(string $stageRoot, array $files, array $deleted): array
    {
        $stage = $this->resolveDirectory($stageRoot, 'staging directory');
        $expected = [];
        foreach ($files as $entry) {
            if (!is_array($entry) || !isset($entry['path'], $entry['size'], $entry['sha256'])) {
                throw new RuntimeException('Malformed signed delta file entry.');
            }
            $path = (string)$entry['path'];
            DcpUpdateManifest::validateUpdatePath($path);
            if (isset($expected[$path])) {
                throw new RuntimeException('Duplicate signed delta path: ' . $path);
            }
            $source = $this->stageFile($stage, $path);
            $size = filesize($source);
            $hash = hash_file('sha256', $source);
            if ($size === false || $size !== (int)$entry['size']
                || !is_string($hash) || !hash_equals(strtolower((string)$entry['sha256']), strtolower($hash))) {
                throw new RuntimeException('Staged delta file does not match its signed metadata: ' . $path);
            }
            $expected[$path] = ['source' => $source, 'mode' => fileperms($source) & 0777];
        }
        $this->assertStageContainsOnly($stage, array_keys($expected));
        $deletions = [];
        foreach ($deleted as $path) {
            if (!is_string($path)) {
                throw new RuntimeException('Malformed signed delta deletion path.');
            }
            DcpUpdateManifest::validateUpdatePath($path);
            if (isset($expected[$path]) || isset($deletions[$path])) {
                throw new RuntimeException('Conflicting or duplicate signed delta path: ' . $path);
            }
            $deletions[$path] = true;
        }
        return $this->applyOperations($expected, array_keys($deletions), 'delta');
    }

    /** Apply a verified complete package; package hash/signature must be checked by caller first. */
    public function applyFullPackage(string $stageRoot, array $deletedPaths = []): array
    {
        $stage = $this->resolveDirectory($stageRoot, 'full package staging directory');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($stage) + 1);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $lowerRelative = strtolower($relative);
            if ($relative === 'DCP-UserData' || str_starts_with($relative, 'DCP-UserData/')
                || preg_match('#^platform/(?:data|storage)(?:/|$)#', $lowerRelative)
                || preg_match('#(^|/)\.env(?:$|\.(?!example$))#', $lowerRelative)) {
                continue;
            }
            DcpUpdateManifest::validateUpdatePath($relative);
            if ($item->isLink()) {
                throw new RuntimeException('Full package contains a symbolic link: ' . $relative);
            }
            if ($item->isDir()) {
                continue;
            }
            if (!$item->isFile()) {
                throw new RuntimeException('Full package contains a non-regular entry: ' . $relative);
            }
            $hash = hash_file('sha256', $item->getPathname());
            $size = filesize($item->getPathname());
            if (!is_string($hash) || $size === false) {
                throw new RuntimeException('Could not inspect full package file: ' . $relative);
            }
            $files[$relative] = ['source' => $item->getPathname(), 'mode' => fileperms($item->getPathname()) & 0777];
        }
        $this->assertStageContainsOnly($stage, array_keys($files), true);
        $deleted = [];
        foreach ($deletedPaths as $path) {
            if (!is_string($path)) throw new RuntimeException('Malformed signed full-package deletion path.');
            DcpUpdateManifest::validateUpdatePath($path);
            if (isset($files[$path]) || isset($deleted[$path])) throw new RuntimeException('Conflicting or duplicate full-package path: ' . $path);
            $deleted[$path] = true;
        }
        return $this->applyOperations($files, array_keys($deleted), 'full');
    }

    /** Return whether a previous process was interrupted during file replacement. */
    public function hasIncompleteTransactions(): bool
    {
        $backupRoot = $this->backupRoot();
        if (is_link($backupRoot)) throw new RuntimeException('DCP update backup root may not be a symbolic link.');
        if (!is_dir($backupRoot)) return false;
        foreach (glob($backupRoot . '/20*', GLOB_ONLYDIR) ?: [] as $snapshot) {
            if (is_link($snapshot)) throw new RuntimeException('DCP update snapshot may not be a symbolic link.');
            $journalPath = $snapshot . '/journal.json';
            if (!is_file($journalPath) || is_link($journalPath)) continue;
            $journal = json_decode((string)file_get_contents($journalPath), true);
            if (is_array($journal) && (($journal['status'] ?? '') === 'applying'
                || (($journal['status'] ?? '') === 'committed' && ($journal['health_status'] ?? 'pending') === 'pending'))) return true;
        }
        return false;
    }

    /** Recover any transaction interrupted by a crash or power loss. */
    public function recoverInterruptedTransactions(): array
    {
        $backupRoot = $this->backupRoot();
        if (is_link($backupRoot)) throw new RuntimeException('DCP update backup root may not be a symbolic link.');
        if (!is_dir($backupRoot)) return ['recovered' => 0];
        $recovered = 0;
        foreach (glob($backupRoot . '/20*', GLOB_ONLYDIR) ?: [] as $snapshot) {
            if (is_link($snapshot)) throw new RuntimeException('DCP update snapshot may not be a symbolic link.');
            $journalPath = $snapshot . '/journal.json';
            if (!is_file($journalPath) || is_link($journalPath)) continue;
            $journal = json_decode((string)file_get_contents($journalPath), true);
            if (!is_array($journal)) continue;
            $applying = ($journal['status'] ?? '') === 'applying';
            $healthPending = ($journal['status'] ?? '') === 'committed' && ($journal['health_status'] ?? 'pending') === 'pending';
            if (!$applying && !$healthPending) continue;
            $this->restoreJournal($snapshot, $journal);
            $journal['status'] = 'rolled_back';
            $journal['recovery'] = $applying ? 'interrupted_transaction' : 'health_check_interrupted';
            $journal['rolled_back_at'] = gmdate(DATE_ATOM);
            $this->writeJournal($snapshot, $journal);
            $recovered++;
        }
        return ['recovered' => $recovered];
    }

    /** Mark the latest committed package healthy only after the updater's HTTP check passes. */
    public function markLatestHealthy(): array
    {
        $backupRoot = $this->backupRoot();
        if (is_link($backupRoot) || !is_dir($backupRoot)) throw new RuntimeException('DCP update journal directory is missing or unsafe.');
        $snapshots = glob($backupRoot . '/20*', GLOB_ONLYDIR) ?: [];
        usort($snapshots, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach ($snapshots as $snapshot) {
            if (is_link($snapshot)) throw new RuntimeException('DCP update snapshot may not be a symbolic link.');
            $journalPath = $snapshot . '/journal.json';
            if (!is_file($journalPath) || is_link($journalPath)) continue;
            $journal = json_decode((string)file_get_contents($journalPath), true);
            if (!is_array($journal) || ($journal['status'] ?? '') !== 'committed') continue;
            if (($journal['health_status'] ?? 'pending') !== 'pending') continue;
            $journal['health_status'] = 'passed';
            $journal['health_checked_at'] = gmdate(DATE_ATOM);
            $this->writeJournal($snapshot, $journal);
            return ['ok' => true, 'snapshot' => basename($snapshot)];
        }
        throw new RuntimeException('No pending DCP update transaction could be marked healthy.');
    }

    /** Restore the most recent successful transaction. */
    public function rollbackLatest(): array
    {
        $backupRoot = $this->backupRoot();
        if (is_link($backupRoot) || !is_dir($backupRoot)) {
            throw new RuntimeException('No safe DCP update rollback snapshot exists.');
        }
        $snapshots = glob($backupRoot . '/20*', GLOB_ONLYDIR) ?: [];
        usort($snapshots, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach ($snapshots as $snapshot) {
            $journalPath = $snapshot . '/journal.json';
            if (!is_file($journalPath)) continue;
            $journal = json_decode((string)file_get_contents($journalPath), true);
            if (!is_array($journal) || ($journal['status'] ?? '') !== 'committed') continue;
            $this->restoreJournal($snapshot, $journal);
            $journal['status'] = 'rolled_back';
            $journal['rolled_back_at'] = gmdate(DATE_ATOM);
            $this->writeJournal($snapshot, $journal);
            return ['ok' => true, 'rolled_back' => basename($snapshot)];
        }
        throw new RuntimeException('No committed DCP update snapshot can be restored.');
    }

    /** @param array<string,array{source:string,mode:int}> $files @param list<string> $deleted */
    private function applyOperations(array $files, array $deleted, string $strategy): array
    {
        $install = $this->resolveDirectory($this->installRoot, 'installation directory');
        $userData = $this->resolveDirectory($this->userDataRoot, 'user-data directory');
        if (!str_starts_with($userData . DIRECTORY_SEPARATOR, $install . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('DCP user data must remain inside the selected installation root.');
        }
        $backupBase = $this->backupRoot();
        $this->ensureDirectory($backupBase, 0700);
        $id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
        $backup = $backupBase . '/' . $id;
        $this->ensureDirectory($backup . '/files', 0700);
        $journal = ['schema' => 1, 'id' => $id, 'strategy' => $strategy, 'status' => 'applying', 'health_status' => 'pending', 'created_at' => gmdate(DATE_ATOM), 'operations' => []];
        $createdDirectories = [];
        try {
            foreach ($files as $relative => $entry) {
                $target = $this->safeTarget($install, $relative, $createdDirectories);
                $this->recordOriginal($target, $backup, $relative, $journal);
                $temporaryName = '.dcp-update-' . bin2hex(random_bytes(6));
                $temporaryRelative = dirname($relative) === '.' ? $temporaryName : dirname($relative) . '/' . $temporaryName;
                $journal['operations'][count($journal['operations']) - 1]['temporary'] = $temporaryRelative;
                $this->writeJournal($backup, $journal);
                $temporary = dirname($target) . '/' . $temporaryName;
                if (!copy($entry['source'], $temporary)) {
                    throw new RuntimeException('Could not stage updated file: ' . $relative);
                }
                chmod($temporary, $entry['mode'] & 0777);
                if (!rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('Could not atomically replace updated file: ' . $relative);
                }
                $this->writeJournal($backup, $journal);
            }
            foreach ($deleted as $relative) {
                $target = $this->safeTarget($install, $relative, $createdDirectories, false);
                if (!file_exists($target)) continue;
                $this->recordOriginal($target, $backup, $relative, $journal);
                if (!unlink($target)) {
                    throw new RuntimeException('Could not remove obsolete package file: ' . $relative);
                }
                $this->writeJournal($backup, $journal);
            }
            $journal['status'] = 'committed';
            $journal['completed_at'] = gmdate(DATE_ATOM);
            $this->writeJournal($backup, $journal);
            $this->pruneBackups($backupBase, 2);
            return ['ok' => true, 'updated' => true, 'strategy' => $strategy, 'backup' => $id, 'files' => count($files), 'deleted' => count($deleted)];
        } catch (Throwable $error) {
            try {
                $this->restoreJournal($backup, $journal);
                foreach (array_reverse($createdDirectories) as $directory) {
                    if (is_dir($directory)) @rmdir($directory);
                }
                $journal['status'] = 'rolled_back';
                $journal['error'] = $error->getMessage();
                $this->writeJournal($backup, $journal);
            } catch (Throwable $rollbackError) {
                throw new RuntimeException('DCP update failed and automatic rollback also failed: ' . $rollbackError->getMessage(), 0, $error);
            }
            throw new RuntimeException('DCP update failed; original package files were restored: ' . $error->getMessage(), 0, $error);
        }
    }

    private function restoreJournal(string $backup, array $journal): void
    {
        $install = $this->resolveDirectory($this->installRoot, 'installation directory');
        foreach (array_reverse($journal['operations'] ?? []) as $operation) {
            if (empty($operation['applied'])) continue;
            $relative = (string)$operation['path'];
            DcpUpdateManifest::validateUpdatePath($relative);
            $ignored = [];
            $target = $this->safeTarget($install, $relative, $ignored, false);
            $saved = $backup . '/files/' . $relative;
            if (!empty($operation['existed']) && is_file($saved)) {
                $this->ensureDirectory(dirname($target), 0755);
                $temporary = dirname($target) . '/.dcp-rollback-' . bin2hex(random_bytes(6));
                if (!copy($saved, $temporary)) throw new RuntimeException('Could not restore rollback file: ' . $relative);
                chmod($temporary, (int)$operation['mode']);
                if (!rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('Could not atomically restore rollback file: ' . $relative);
                }
            } elseif (is_file($target)) {
                if (!unlink($target)) throw new RuntimeException('Could not remove newly installed file during rollback: ' . $relative);
            }
            $temporaryRelative = $operation['temporary'] ?? null;
            if (is_string($temporaryRelative) && $temporaryRelative !== '') {
                DcpUpdateManifest::validateUpdatePath($temporaryRelative);
                $temporaryPath = $this->safeTarget($install, $temporaryRelative, $ignored, false);
                if (is_link($temporaryPath)) {
                    if (!unlink($temporaryPath)) throw new RuntimeException('Could not remove an interrupted update temporary symlink.');
                } elseif (is_file($temporaryPath) && !unlink($temporaryPath)) {
                    throw new RuntimeException('Could not remove an interrupted update temporary file: ' . $temporaryRelative);
                }
            }
        }
    }

    private function recordOriginal(string $target, string $backup, string $relative, array &$journal): void
    {
        $exists = file_exists($target);
        $mode = 0644;
        if ($exists) {
            if (is_link($target) || !is_file($target)) throw new RuntimeException('Refusing to replace a non-regular installed path: ' . $relative);
            $mode = fileperms($target) & 0777;
            $saved = $backup . '/files/' . $relative;
            $this->ensureDirectory(dirname($saved), 0700);
            if (!copy($target, $saved)) throw new RuntimeException('Could not back up installed file before update: ' . $relative);
            chmod($saved, $mode);
        }
        // Mark the operation recoverable before the filesystem mutation. If power
        // is lost between rename/unlink and the next journal write, startup recovery
        // will still restore the saved original (or remove a newly created file).
        $journal['operations'][] = ['path' => $relative, 'existed' => $exists, 'mode' => $mode, 'applied' => true];
        $this->writeJournal($backup, $journal);
    }

    private function safeTarget(string $install, string $relative, array &$createdDirectories, bool $createParents = true): string
    {
        DcpUpdateManifest::validateUpdatePath($relative);
        $segments = explode('/', $relative);
        $cursor = $install;
        array_pop($segments);
        foreach ($segments as $segment) {
            $cursor .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($cursor)) throw new RuntimeException('Refusing to traverse a symbolic link in the installation tree.');
            if (file_exists($cursor) && !is_dir($cursor)) throw new RuntimeException('An update parent path is not a directory: ' . $relative);
            if (!file_exists($cursor) && $createParents) {
                if (!mkdir($cursor, 0755) && !is_dir($cursor)) throw new RuntimeException('Could not create update directory: ' . $cursor);
                $createdDirectories[] = $cursor;
            }
        }
        $target = $install . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, explode('/', $relative));
        if (is_link($target)) throw new RuntimeException('Refusing to replace a symbolic link: ' . $relative);
        if (file_exists($target) && !is_file($target)) throw new RuntimeException('Update target is not a regular file: ' . $relative);
        return $target;
    }

    private function stageFile(string $stage, string $relative): string
    {
        $path = $stage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($path);
        if ($real === false || !str_starts_with($real, $stage . DIRECTORY_SEPARATOR) || is_link($path) || !is_file($real)) {
            throw new RuntimeException('Staged update file is missing or unsafe: ' . $relative);
        }
        return $real;
    }

    private function assertStageContainsOnly(string $stage, array $expected, bool $allowUserData = false): void
    {
        $allowed = array_fill_keys($expected, true);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) throw new RuntimeException('Staging tree contains a symbolic link.');
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($stage) + 1));
            if ($allowUserData && ($relative === 'DCP-UserData' || str_starts_with($relative, 'DCP-UserData/'))) continue;
            if ($item->isDir()) continue;
            if (!$item->isFile() || !isset($allowed[$relative])) {
                throw new RuntimeException('Staging tree contains an unlisted or non-regular file: ' . $relative);
            }
        }
        if (count($allowed) !== count($expected)) throw new RuntimeException('Staging file list is inconsistent.');
    }

    private function backupRoot(): string
    {
        $userData = realpath($this->userDataRoot);
        if ($userData === false || !is_dir($userData) || is_link($this->userDataRoot)) {
            throw new RuntimeException('DCP user-data directory is missing or unsafe.');
        }
        return rtrim($userData, DIRECTORY_SEPARATOR) . '/storage/updates/backups';
    }

    private function resolveDirectory(string $path, string $label): string
    {
        if (is_link($path)) throw new RuntimeException('DCP ' . $label . ' may not be a symbolic link.');
        $real = realpath($path);
        if ($real === false || !is_dir($real)) throw new RuntimeException('DCP ' . $label . ' is missing.');
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function ensureDirectory(string $path, int $mode): void
    {
        $absolute = str_starts_with($path, DIRECTORY_SEPARATOR);
        $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
        $cursor = $absolute ? DIRECTORY_SEPARATOR : '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $cursor = $cursor === DIRECTORY_SEPARATOR ? $cursor . $part : ($cursor === '' ? $part : $cursor . DIRECTORY_SEPARATOR . $part);
            if (is_link($cursor)) throw new RuntimeException('DCP update directory path contains a symbolic link.');
            if (file_exists($cursor)) {
                if (!is_dir($cursor)) throw new RuntimeException('DCP update directory path is occupied by a non-directory.');
                continue;
            }
            if (!mkdir($cursor, $mode) && !is_dir($cursor)) throw new RuntimeException('Could not create DCP update directory.');
            chmod($cursor, $mode);
        }
    }

    private function writeJournal(string $backup, array $journal): void
    {
        $path = $backup . '/journal.json';
        $temporary = $path . '.tmp';
        $bytes = json_encode($journal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $handle = fopen($temporary, 'wb');
        if ($handle === false) throw new RuntimeException('Could not open the DCP update journal for writing.');
        try {
            chmod($temporary, 0600);
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if ($written === false || $written === 0) throw new RuntimeException('Could not write the complete DCP update journal.');
                $offset += $written;
            }
            if (!fflush($handle)) throw new RuntimeException('Could not flush the DCP update journal.');
            if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('Could not sync the DCP update journal to disk.');
        } finally {
            fclose($handle);
        }
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Could not atomically publish the DCP update journal.');
        }
        chmod($path, 0600);
    }

    private function pruneBackups(string $root, int $keep): void
    {
        $snapshots = glob($root . '/20*', GLOB_ONLYDIR) ?: [];
        usort($snapshots, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        foreach (array_slice($snapshots, $keep) as $old) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($old);
        }
    }
}
