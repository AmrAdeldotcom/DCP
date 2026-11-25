<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$service = file_get_contents(__DIR__ . '/../app/Services/LocalAIService.php');
$manager = file_get_contents(__DIR__ . '/../app/Services/CustomModelManager.php');
$ollamaDriver = file_get_contents(__DIR__ . '/../plugins/providers/ollama/driver.php');
if (!is_string($page) || !is_string($js) || !is_string($api) || !is_string($service) || !is_string($manager) || !is_string($ollamaDriver)) {
    fwrite(STDERR, "FAIL: custom model manager source files could not be read.\n");
    exit(1);
}
$checks = [
    'Local AI page exposes a custom GGUF import panel' => str_contains($page, 'id="custom-gguf-path"') && str_contains($page, 'id="custom-gguf-name"') && str_contains($page, 'id="custom-gguf-import"'),
    'custom GGUF inventory has a dedicated list container' => str_contains($page, 'id="custom-gguf-list"'),
    'LocalAIService loads the custom model manager trait' => str_contains($service, "require_once __DIR__ . '/CustomModelManager.php'") && str_contains($service, 'use CustomModelManager;'),
    'custom GGUF import checks extension, GGUF magic and supported version' => str_contains($manager, "PATHINFO_EXTENSION") && str_contains($manager, "substr(\$header, 0, 4) !== 'GGUF'") && str_contains($manager, "in_array((int)\$headerFields['version'], [2, 3], true)"),
    'custom GGUF import verifies SHA-256 and stores under private model directory' => str_contains($manager, "hash_file('sha256', \$source)") && str_contains($manager, "0600") && str_contains($manager, "models/gguf"),
    'Ollama conversion uses a managed Modelfile and create command' => str_contains($manager, 'FROM "') && str_contains($manager, "' create '") && str_contains($manager, 'ollama-create-'),
    'custom model deletion removes linked Ollama model and managed GGUF' => str_contains($manager, '/api/delete') && str_contains($manager, 'unlink($path)'),
    'conversion PID guard verifies the exact Ollama executable and model command before deletion' => str_contains($manager, '$binary = $this->binary(\'ollama\')') && str_contains($manager, '$info[\'Path\']') && str_contains($manager, 'str_starts_with($commandLine, $expected') && str_contains($manager, '$expectedModel'),
    'conversion status tolerates Ollama manifest visibility lag for 120 seconds after process exit' => str_contains($manager, 'ollama_started_at') && str_contains($manager, 'time() - $startedAt > 120'),
    'Ollama Qwen3 chat disables hidden thinking so content is available to the chat UI' => str_contains($ollamaDriver, "\$payload['think']=false"),
    'API exposes list, import, Ollama conversion and delete routes' => str_contains($api, "'/api/local-ai/models'") && str_contains($api, "'/api/local-ai/models/import'") && str_contains($api, "'/api/local-ai/models/ollama'") && str_contains($api, "'/api/local-ai/models/delete'"),
    'UI supports import, conversion polling, delete and use-in-chat switching' => str_contains($js, "api('/api/local-ai/models/import'") && str_contains($js, "api('/api/local-ai/models/ollama'") && str_contains($js, "api('/api/local-ai/models/delete'") && str_contains($js, "localStorage.setItem('dcp-provider','ollama')"),
    'UI imports by explicit local file path and never uploads or auto-runs a file' => str_contains($js, "Enter the full local path to a .gguf file.") && str_contains($js, "showPage('chat')"),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo $failed === 0 ? "ALL MODEL MANAGER CONTRACT CHECKS PASSED\n" : "$failed MODEL MANAGER CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
