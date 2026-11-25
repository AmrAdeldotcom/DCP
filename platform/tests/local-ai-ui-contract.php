<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$service = file_get_contents(__DIR__ . '/../app/Services/LocalAIService.php');
$manager = file_get_contents(__DIR__ . '/../app/Services/CustomModelManager.php');
$css = file_get_contents(__DIR__ . '/../public/assets/app.css');
$linuxWrapper = file_get_contents(__DIR__ . '/../wrappers/linux/dcp-webview.c');
if (!is_string($page) || !is_string($js) || !is_string($api) || !is_string($service) || !is_string($manager) || !is_string($css) || !is_string($linuxWrapper)) {
    $message="FAIL: Local AI UI contract sources could not be read.\n";if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,$message);exit(1);}throw new RuntimeException(trim($message));
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
    'Windows process identity binds listener port, PID and canonical executable path' => str_contains($service, 'Get-NetTCPConnection -LocalPort $Port') && str_contains($service, '[System.IO.Path]::GetFullPath([string]$p.ExecutablePath)') && str_contains($service, '[System.StringComparison]::OrdinalIgnoreCase'),
    'Windows runner tolerates normal native stderr but propagates non-zero exit' => str_contains($service, "\$ErrorActionPreference = 'Continue'") && str_contains($service, 'if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }'),
    'slow GGUF startup gets a 60-second readiness window and cleans up on failure' => str_contains($service, '$attempt<120') && str_contains($service, "\$this->stop('llama.cpp')") && str_contains($service, 'within 60 seconds'),
    'Windows llama.cpp launcher and Ollama conversion use argv-based PowerShell invocation' => str_contains($service, 'runWindowsPowerShell') && str_contains($manager, 'runWindowsPowerShell'),
    'chat workspace groups Tasks, Local AI, APIs and Devices into context tabs' => str_contains($page, 'data-chat-context-tab="tasks"') && str_contains($page, 'data-chat-context-tab="local-ai"') && str_contains($page, 'data-chat-context-tab="apis"') && str_contains($page, 'data-chat-context-tab="devices"'),
    'chat context tabs render local engines, API providers and paired devices' => str_contains($js, 'function renderChatContextPanels()') && str_contains($js, 'function setChatContext(tab)') && str_contains($js, 'state.cloudStatus=d') && str_contains($page, 'id="chat-context-devices"'),
    'chat context tabs use compact responsive panel styling' => str_contains($css, '.chat-context-tabs') && str_contains($css, '.chat-context-panel.active') && str_contains($css, '@media(max-width:720px)'),
    'Linux WebView reserves a private dynamic port instead of colliding with Ollama port 18765' => str_contains($linuxWrapper, 'static guint available_port(void)') && str_contains($linuxWrapper, 'address.sin_port=0') && !str_contains($linuxWrapper, '18765'),
    'Qwen3 llama.cpp starts with thinking disabled so chat returns user-facing content' => str_contains($service, "'--chat-template-kwargs','{\"enable_thinking\":false}'") && str_contains($service, '$isQwen3'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$passed) $failed++;
}
echo $failed === 0 ? "ALL LOCAL AI UI CONTRACT CHECKS PASSED\n" : "$failed LOCAL AI UI CONTRACT CHECK(S) FAILED\n";
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit($failed===0?0:1);if($failed!==0)throw new RuntimeException($failed.' Local AI UI contract check(s) failed.');
