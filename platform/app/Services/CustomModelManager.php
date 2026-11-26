<?php
declare(strict_types=1);

trait CustomModelManager
{
    private function customModelDirectory(): string
    {
        return rtrim($this->llamaCacheDirectory(), '/\\') . DIRECTORY_SEPARATOR . 'custom';
    }

    private function customModelManifestPath(): string
    {
        return $this->customModelDirectory() . '/models.json';
    }

    private function legacyCustomModelDirectory(): string
    {
        return $this->storage . '/models/gguf';
    }

    private function migrateLegacyCustomModels(): void
    {
        $legacyDir = $this->legacyCustomModelDirectory();
        $legacyManifest = $legacyDir . '/models.json';
        $targetManifest = $this->customModelManifestPath();
        if (is_file($targetManifest) || is_link($targetManifest) || !is_file($legacyManifest)) return;
        if (is_link($legacyManifest) || is_link($legacyDir)) throw new RuntimeException('Legacy GGUF model storage cannot contain symlinks.');
        $models = json_decode((string)file_get_contents($legacyManifest), true);
        if (!is_array($models) || !array_is_list($models)) throw new RuntimeException('Legacy GGUF model manifest is invalid.');
        $targetDir = $this->customModelDirectory();
        if (!is_dir($targetDir) && !mkdir($targetDir, 0700, true) && !is_dir($targetDir)) throw new RuntimeException('Could not create the Downloads-backed custom GGUF directory.');
        $legacyPrefix = rtrim(realpath($legacyDir) ?: $legacyDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        foreach ($models as $entry) {
            if (!is_array($entry)) throw new RuntimeException('Legacy GGUF model entry is invalid.');
            $file = (string)($entry['file'] ?? '');
            $sha = strtolower((string)($entry['sha256'] ?? ''));
            if ($file === '' || basename($file) !== $file || preg_match('/^[a-f0-9]{64}$/', $sha) !== 1) throw new RuntimeException('Legacy GGUF model path or checksum is invalid.');
            $source = $legacyDir . DIRECTORY_SEPARATOR . $file;
            if (is_link($source)) throw new RuntimeException('Legacy GGUF model file cannot be a symlink.');
            $sourceReal = realpath($source);
            if ($sourceReal === false || !str_starts_with($sourceReal, $legacyPrefix) || !is_file($sourceReal) || !hash_equals($sha, (string)hash_file('sha256', $sourceReal))) throw new RuntimeException('Legacy GGUF model failed its SHA-256 verification.');
            $target = $targetDir . DIRECTORY_SEPARATOR . $file;
            if (is_link($target)) throw new RuntimeException('Downloads GGUF destination cannot be a symlink.');
            if (is_file($target)) {
                if (!hash_equals($sha, (string)hash_file('sha256', $target))) throw new RuntimeException('A different GGUF already exists in Downloads; legacy model was not overwritten.');
                continue;
            }
            $tmp = $target . '.migration-' . bin2hex(random_bytes(4));
            if (!copy($sourceReal, $tmp) || !hash_equals($sha, (string)hash_file('sha256', $tmp))) { @unlink($tmp); throw new RuntimeException('Could not verify the copied legacy GGUF in Downloads.'); }
            @chmod($tmp, 0600);
            if (!rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Could not finalize the copied legacy GGUF in Downloads.'); }
            @chmod($target, 0600);
        }
        // Keep the old private-storage files untouched as a rollback copy.
        $this->writeCustomModels($models);
    }

    private function readCustomModels(): array
    {
        $this->migrateLegacyCustomModels();
        $file = $this->customModelManifestPath();
        if (is_link($file)) throw new RuntimeException('Custom GGUF model manifest cannot be a symlink.');
        if (!is_file($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data) || !array_is_list($data)) throw new RuntimeException('Custom GGUF model manifest is invalid.');
        return $data;
    }

    private function writeCustomModels(array $models): void
    {
        $dir = $this->customModelDirectory();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Could not create the private GGUF model directory.');
        if (is_link($dir)) throw new RuntimeException('The custom GGUF model directory cannot be a symlink.');
        $file = $this->customModelManifestPath();
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode(array_values($models), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) throw new RuntimeException('Could not write the custom GGUF model manifest.');
        @chmod($tmp, 0600);
        if (!rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('Could not atomically update the custom GGUF model manifest.'); }
        @chmod($file, 0600);
    }

    private function customModelSlug(string $name): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-._'));
        if ($slug === '') $slug = 'custom-model';
        return substr($slug, 0, 40);
    }

    private function customOllamaName(string $name): string
    {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,63}(?::[a-z0-9][a-z0-9._-]{0,63})?$/', $name)) {
            throw new InvalidArgumentException('Ollama model names may contain only lowercase letters, numbers, dots, underscores, hyphens and one optional tag.');
        }
        return $name;
    }

    private function customModelById(string $id, array $models): array
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,63}$/', $id)) throw new InvalidArgumentException('Invalid custom model id.');
        foreach ($models as $model) if (($model['id'] ?? '') === $id) return $model;
        throw new RuntimeException('Custom GGUF model was not found.');
    }

    private function customPidRunning(int $pid, string $expectedModel = ''): bool
    {
        if ($pid < 1) return false;
        $binary = $this->binary('ollama');
        if ($binary === '') return false;

        if (PHP_OS_FAMILY === 'Windows') {
            $ps = '$p=Get-CimInstance Win32_Process -Filter "ProcessId = ' . $pid . '"; if($p){[pscustomobject]@{Path=$p.ExecutablePath;CommandLine=$p.CommandLine}|ConvertTo-Json -Compress}';
            $raw = $this->runWindowsPowerShell(['-Command', $ps], 3);
            $info = json_decode($raw, true);
            if (!is_array($info)) return false;
            $actual = strtolower(str_replace('/', chr(92), (string)($info['Path'] ?? '')));
            $expected = strtolower(str_replace('/', chr(92), (string)(realpath($binary) ?: $binary)));
            if ($actual === '' || $actual !== $expected) return false;
            $commandLine = (string)($info['CommandLine'] ?? '');
        } else {
            $commandLine = $this->run('ps -p ' . (int)$pid . ' -o command= 2>/dev/null', 3);
            $expected = (string)(realpath($binary) ?: $binary);
            if ($commandLine === '' || !str_starts_with($commandLine, $expected . ' ')) return false;
        }

        if (preg_match('/(?:^|\s)create(?:\s|$)/i', $commandLine) !== 1) return false;
        if ($expectedModel !== '') {
            $modelPattern = '/(?:^|\s)create\s+["]?' . preg_quote($expectedModel, '/') . '(?::latest)?(?=["\s]|$)/i';
            if (preg_match($modelPattern, $commandLine) !== 1) return false;
        }
        return true;
    }

    public function customModels(): array
    {
        $models = $this->readCustomModels();
        $ollama = $this->ollama();
        $installed = array_map(static fn($m) => (string)($m['id'] ?? ''), $ollama['models']);
        foreach ($models as &$model) {
            // Give the supplied Qwen3 starter a stable catalogue identity even when
            // older uploads were named after PHP's temporary upload file.
            if (hash_equals('ac2d97712095a558e31573f62f466a3f9d93990898b0ec79d7c974c1780d524a', strtolower((string)($model['sha256'] ?? '')))) {
                $model['name'] = 'Qwen3 0.6B · GGUF Q4_K_M';
            }
            $name = (string)($model['ollama_name'] ?? '');
            $model['ollama_installed'] = $name !== '' && (in_array($name, $installed, true) || in_array($name . ':latest', $installed, true));
            if ($model['ollama_installed']) {
                $model['ollama_status'] = 'ready';
                $model['ollama_pid'] = 0;
                $model['ollama_started_at'] = null;
            } elseif (($model['ollama_status'] ?? '') === 'creating' && !$this->customPidRunning((int)($model['ollama_pid'] ?? 0), $name)) {
                // Ollama can finish `create` before /api/tags exposes its new manifest.
                // Keep the UI in its verifying state briefly instead of reporting a false failure.
                $startedAt = strtotime((string)($model['ollama_started_at'] ?? $model['created_at'] ?? ''));
                if ($startedAt === false || time() - $startedAt > 120) $model['ollama_status'] = 'failed';
            }
            $model['path'] = null;
        }
        unset($model);
        return $models;
    }

    public function importGguf(string $sourcePath, string $displayName = '', string $originalFilename = ''): array
    {
        @set_time_limit(0);
        $source = realpath($sourcePath);
        if ($source === false || !is_file($source) || is_link($sourcePath) || is_link($source)) throw new InvalidArgumentException('Choose an existing regular GGUF file, not a symlink.');
        if (strtolower(pathinfo($originalFilename !== '' ? $originalFilename : $source, PATHINFO_EXTENSION)) !== 'gguf') throw new InvalidArgumentException('Custom model import accepts .gguf files only.');
        $size = filesize($source);
        if ($size === false || $size < 24 || $size > 21474836480) throw new InvalidArgumentException('GGUF file size must be between 24 bytes and 20 GB.');
        $handle = fopen($source, 'rb');
        if ($handle === false) throw new RuntimeException('Could not read the selected GGUF file.');
        $header = fread($handle, 24);
        fclose($handle);
        if (!is_string($header) || strlen($header) !== 24 || substr($header, 0, 4) !== 'GGUF') throw new InvalidArgumentException('The selected file does not contain a valid GGUF header.');
        $headerFields = unpack('Vversion/Ptensor_count/Pmetadata_count', substr($header, 4));
        if (!is_array($headerFields) || !in_array((int)$headerFields['version'], [2, 3], true) || (int)$headerFields['tensor_count'] < 1 || (int)$headerFields['tensor_count'] > 10000000 || (int)$headerFields['metadata_count'] > 100000) throw new InvalidArgumentException('The GGUF header version or metadata counts are invalid.');

        $sha = hash_file('sha256', $source);
        if (!is_string($sha)) throw new RuntimeException('Could not calculate the GGUF SHA-256 checksum.');
        $fallbackName = $originalFilename !== '' ? pathinfo($originalFilename, PATHINFO_FILENAME) : pathinfo($source, PATHINFO_FILENAME);
        $baseName = trim($displayName) !== '' ? trim($displayName) : $fallbackName;
        $slug = $this->customModelSlug($baseName);
        $id = $slug . '-' . substr($sha, 0, 12);
        $models = $this->readCustomModels();
        foreach ($models as $existing) if (($existing['id'] ?? '') === $id) throw new RuntimeException('This GGUF file is already imported.');
        $dir = $this->customModelDirectory();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Could not create the private GGUF model directory.');
        if (is_link($dir)) throw new RuntimeException('The custom GGUF model directory cannot be a symlink.');
        $targetName = $id . '.gguf';
        $target = $dir . DIRECTORY_SEPARATOR . $targetName;
        if (file_exists($target) || is_link($target)) throw new RuntimeException('A file already exists at the custom model destination.');
        $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
        if (!copy($source, $tmp)) throw new RuntimeException('Could not copy the GGUF file into the Downloads model directory.');
        if (filesize($tmp) !== $size || !hash_equals($sha, (string)hash_file('sha256', $tmp))) {
            @unlink($tmp);
            throw new RuntimeException('Imported GGUF failed size or SHA-256 verification.');
        }
        @chmod($tmp, 0600);
        if (!rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Could not finalize the imported GGUF file.'); }
        @chmod($target, 0600);
        $entry = [
            'id' => $id,
            'name' => $baseName,
            'file' => $targetName,
            'size' => (int)$size,
            'sha256' => $sha,
            'created_at' => date(DATE_ATOM),
            'ollama_name' => '',
            'ollama_status' => 'not-created',
            'ollama_pid' => 0,
            'ollama_started_at' => null,
        ];
        $models[] = $entry;
        $this->writeCustomModels($models);
        return $entry;
    }

    public function createOllamaFromGguf(string $id, string $ollamaName = ''): array
    {
        $models = $this->readCustomModels();
        $entry = $this->customModelById($id, $models);
        $name = $this->customOllamaName($ollamaName !== '' ? $ollamaName : 'dcp-' . $id);
        $status = $this->ollama();
        if (!$status['online']) throw new RuntimeException('Start Ollama before converting a custom GGUF model.');
        $existing = array_map(static fn($m) => (string)($m['id'] ?? ''), $status['models']);
        if (in_array($name, $existing, true) || in_array($name . ':latest', $existing, true)) {
            foreach ($models as &$model) if ($model['id'] === $id) { $model['ollama_name'] = $name; $model['ollama_status'] = 'ready'; $model['ollama_pid'] = 0; $model['ollama_started_at'] = null; }
            unset($model);
            $this->writeCustomModels($models);
            return ['ok' => true, 'already_installed' => true, 'id' => $id, 'ollama_name' => $name];
        }
        $dir = $this->customModelDirectory();
        $file = (string)($entry['file'] ?? '');
        if ($file === '' || basename($file) !== $file) throw new RuntimeException('Custom GGUF manifest contains an unsafe file path.');
        $candidate = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_link($candidate)) throw new RuntimeException('Imported GGUF file cannot be a symlink.');
        $path = realpath($candidate);
        $prefix = rtrim(realpath($dir) ?: $dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($path === false || !str_starts_with($path, $prefix) || !is_file($path)) throw new RuntimeException('Imported GGUF file is missing or outside the Downloads model directory.');
        $modelfile = $dir . DIRECTORY_SEPARATOR . $id . '.Modelfile';
        $modelPath = str_replace('\\', '/', $path);
        $modelfileText = 'FROM "' . str_replace('"', '\\"', $modelPath) . '"' . PHP_EOL;
        if (file_put_contents($modelfile, $modelfileText, LOCK_EX) === false) throw new RuntimeException('Could not create the Ollama Modelfile.');
        @chmod($modelfile, 0600);
        $bin = $this->binary('ollama');
        if ($bin === '') throw new RuntimeException('Ollama executable could not be found.');
        $logDir = $this->storage . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0700, true);
        $log = $logDir . '/ollama-create-' . $id . '.log';
        if (PHP_OS_FAMILY === 'Windows') {
            $script = $this->storage . '/runtime/start-ollama-create-' . $id . '.ps1';
            if (!is_dir(dirname($script))) mkdir(dirname($script), 0700, true);
            $exe = str_replace("'", "''", $bin);
            $modelName = str_replace("'", "''", $name);
            $fileArg = str_replace("'", "''", $modelfile);
            $logPath = str_replace("'", "''", $log . '.out');
            $errorPath = str_replace("'", "''", $log . '.err');
            $body = "\$ErrorActionPreference = 'Stop'\n\$modelName = '$modelName'\n\$fileArg = '$fileArg'\n\$arguments = 'create ' + \$modelName + ' -f \"' + \$fileArg + '\"'\n\$p = Start-Process -FilePath '$exe' -ArgumentList \$arguments -PassThru -WindowStyle Hidden -RedirectStandardOutput '$logPath' -RedirectStandardError '$errorPath'\nWrite-Output \$p.Id\n";
            file_put_contents($script, $body);
            $pid = (int)$this->runWindowsPowerShell(['-File', $script], 8);
        } else {
            $command = 'nohup ' . escapeshellarg($bin) . ' create ' . escapeshellarg($name) . ' -f ' . escapeshellarg($modelfile) . ' > ' . escapeshellarg($log) . ' 2>&1 < /dev/null & echo $!';
            $pid = (int)$this->run($command, 5);
        }
        if ($pid < 1) throw new RuntimeException('Could not start the Ollama GGUF conversion process.');
        foreach ($models as &$model) if ($model['id'] === $id) { $model['ollama_name'] = $name; $model['ollama_status'] = 'creating'; $model['ollama_pid'] = $pid; $model['ollama_started_at'] = date(DATE_ATOM); $model['ollama_log'] = basename($log); }
        unset($model);
        $this->writeCustomModels($models);
        return ['ok' => true, 'started' => true, 'id' => $id, 'ollama_name' => $name, 'pid' => $pid, 'message' => 'Ollama model creation started.'];
    }

    public function deleteCustomModel(string $id): array
    {
        $models = $this->readCustomModels();
        $entry = $this->customModelById($id, $models);
        if (($entry['ollama_status'] ?? '') === 'creating' && $this->customPidRunning((int)($entry['ollama_pid'] ?? 0), (string)($entry['ollama_name'] ?? ''))) {
            throw new RuntimeException('Wait for Ollama model creation to finish before deleting this GGUF.');
        }
        $name = (string)($entry['ollama_name'] ?? '');
        if ($name !== '') {
            $status = $this->ollama();
            if (!$status['online']) throw new RuntimeException('Start Ollama before deleting a linked custom model.');
            $installed = array_map(static fn($m) => (string)($m['id'] ?? ''), $status['models']);
            if (in_array($name, $installed, true) || in_array($name . ':latest', $installed, true)) {
                $ch = curl_init('http://127.0.0.1:11434/api/delete');
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['name' => $name]), CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 60]);
                $raw = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                if ($raw === false || $code < 200 || $code >= 300) throw new RuntimeException('Could not remove the linked Ollama model: ' . $error);
            }
        }
        $dir = $this->customModelDirectory();
        $file = (string)($entry['file'] ?? '');
        if ($file === '' || basename($file) !== $file) throw new RuntimeException('Custom GGUF manifest contains an unsafe file path.');
        $candidate = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_link($candidate)) throw new RuntimeException('Imported GGUF file cannot be a symlink.');
        $path = realpath($candidate);
        $prefix = rtrim(realpath($dir) ?: $dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($path === false || !str_starts_with($path, $prefix) || !is_file($path)) throw new RuntimeException('Imported GGUF file is missing or outside the Downloads model directory.');
        if (!unlink($path)) throw new RuntimeException('Could not delete the imported GGUF file.');
        $modelfile = $dir . DIRECTORY_SEPARATOR . $id . '.Modelfile';
        if (is_file($modelfile) && !is_link($modelfile)) @unlink($modelfile);
        $models = array_values(array_filter($models, static fn($m) => ($m['id'] ?? '') !== $id));
        $this->writeCustomModels($models);
        return ['ok' => true, 'deleted' => true, 'id' => $id];
    }
}
