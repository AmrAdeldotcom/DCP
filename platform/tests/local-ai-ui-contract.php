<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$service = file_get_contents(__DIR__ . '/../app/Services/LocalAIService.php');
if (!is_string($page) || !is_string($js) || !is_string($api) || !is_string($service)) {
    fwrite(STDERR, "FAIL: Local AI UI contract sources could not be read.\n");
    exit(1);
}
$checks = [
    'Local AI page exposes model cards and engine status controls' => str_contains($page, 'id="page-local-ai"') && str_contains($page, 'id="local-ai-models"') && str_contains($page, 'id="llama-engine-status"'),
    'Qwen3 0.6B card exposes Run with llama.cpp' => str_contains($js, "['qwen3:0.6b','qwen3:1.7b','qwen3:4b','llama3.2:3b'].includes(m.id)") && str_contains($js, 'data-llama-model='),
    'Run with llama.cpp uses the managed start API and selected model id' => str_contains($js, "runLocalAIAction('/api/local-ai/llama/start'") && str_contains($js, 'llama.dataset.llamaModel'),
    'UI explains that a verified cached GGUF is reused' => str_contains($js, 'reuse a verified local model cache when available'),
    'successful Local AI actions refresh the engine status' => str_contains($js, 'setTimeout(loadLocalAI,1800)'),
    'API starts the requested model and saves its OpenAI-compatible provider' => str_contains($api, "'/api/local-ai/llama/start'") && str_contains($api, '$this->localAI->startLlama($model)') && str_contains($api, "'base_url'=>(string)" . '$result' . "['base_url']") && str_contains($api, "'model'=>" . '$model'),
    'provider selection switches chat to the newly created local provider' => str_contains($js, "localStorage.setItem('dcp-provider',d.provider.id)") && str_contains($js, 'loadProviders(true)'),
    'cached Qwen3 GGUF launches directly without forcing a network download' => str_contains($service, 'llamaModelArguments') && str_contains($service, "return ['-m'," . '$modelFile' . "]") && str_contains($service, 'return ' . '$model' . "==='qwen3:0.6b'?2048"),
    'Windows process identity binds listener port, PID and executable' => str_contains($service, 'Get-NetTCPConnection -LocalPort $Port') && str_contains($service, '$p.ExecutablePath -ieq $Executable'),
    'Windows runner tolerates normal native stderr but propagates non-zero exit' => str_contains($service, "\$ErrorActionPreference = 'Continue'") && str_contains($service, 'if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$passed) $failed++;
}
echo $failed === 0 ? "ALL LOCAL AI UI CONTRACT CHECKS PASSED\n" : "$failed LOCAL AI UI CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
