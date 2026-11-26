<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$service = file_get_contents(__DIR__ . '/../app/Services/LocalAIService.php');
$manager = file_get_contents(__DIR__ . '/../app/Services/CustomModelManager.php');
$css = file_get_contents(__DIR__ . '/../public/assets/app.css');
$designFixes = file_get_contents(__DIR__ . '/../public/assets/design-system-fixes.css');
$linuxWrapper = file_get_contents(__DIR__ . '/../wrappers/linux/dcp-webview.c');
$windowsWrapper = file_get_contents(__DIR__ . '/../wrappers/windows/Program.cs');
if (!is_string($page) || !is_string($js) || !is_string($api) || !is_string($service) || !is_string($manager) || !is_string($css) || !is_string($designFixes) || !is_string($linuxWrapper) || !is_string($windowsWrapper)) {
    $message="FAIL: Local AI UI contract sources could not be read.\n";if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,$message);exit(1);}throw new RuntimeException(trim($message));
}
$checks = [
    'Local AI page exposes model cards and engine status controls' => str_contains($page, 'id="page-local-ai"') && str_contains($page, 'id="local-ai-models"') && str_contains($page, 'id="llama-engine-status"'),
    'Qwen3 0.6B card exposes Run with llama.cpp' => str_contains($js, "['qwen3:0.6b','qwen3:1.7b','qwen3:4b','llama3.2:3b'].includes(m.id)") && str_contains($js, 'data-llama-model='),
    'Run with llama.cpp uses the managed start API and selected model id' => str_contains($js, "runLocalAIAction('/api/local-ai/llama/start'") && str_contains($js, 'llama.dataset.llamaModel'),
    'UI explains that a verified cached GGUF is reused' => str_contains($js, 'reuse a verified local model cache when available'),
    'successful Local AI actions refresh the engine status' => str_contains($js, 'setTimeout(loadLocalAI,1800)'),
    'API starts the requested model and saves its OpenAI-compatible provider' => str_contains($api, "'/api/local-ai/llama/start'") && str_contains($api, '$this->localAI->startLlama($model)') && str_contains($api, "'base_url'=>(string)" . '$result' . "['base_url']") && str_contains($api, "'model'=>" . '$model'),
    'local llama provider updates in place and exposes a friendly Qwen3 model id' => str_contains($api, 'llama.cpp (Local)') && str_contains($api, 'Qwen3 0.6B · GGUF Q4_K_M') && str_contains($api, "existing['id']??''"),
    'local llama model list is served from its saved model id without probing a stopped port' => strpos($api, '$provider=$this->provider->get($id)') < strpos($api, '$models=$this->chat->models($id)'),
    'provider selection switches chat to the newly created local provider' => str_contains($js, "localStorage.setItem('dcp-provider',d.provider.id)") && str_contains($js, 'loadProviders(true)'),
    'Chat defaults to an online llama.cpp provider when Ollama is offline' => str_contains($js, '(!state.localAI||engines.llama_cpp?.online&&!engines.ollama?.online)') && str_contains($js, 'await loadCloudStatus();await loadProviders(true)'),
    'llama.cpp uses an 8192-token context window required by the DCP system prompt' => str_contains($service, 'private function llamaContextSize') && str_contains($service, 'return 8192;'),
    'llama.cpp sets a finite generation budget for local models' => str_contains($service, 'private function llamaPredictTokens') && str_contains($service, "'-n'" ) && str_contains($service, '?512:1024;'),
    'Windows process identity binds listener port, PID and canonical executable path' => str_contains($service, 'Get-NetTCPConnection -LocalPort $Port') && str_contains($service, '[System.IO.Path]::GetFullPath([string]$p.ExecutablePath)') && str_contains($service, '[System.StringComparison]::OrdinalIgnoreCase'),
    'Windows runner tolerates normal native stderr but propagates non-zero exit' => str_contains($service, "\$ErrorActionPreference = 'Continue'") && str_contains($service, 'if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }'),
    'slow GGUF startup gets a 60-second readiness window and cleans up on failure' => str_contains($service, '$attempt<120') && str_contains($service, "\$this->stop('llama.cpp')") && str_contains($service, 'within 60 seconds'),
    'Windows llama.cpp launcher and Ollama conversion use argv-based PowerShell invocation' => str_contains($service, 'runWindowsPowerShell') && str_contains($manager, 'runWindowsPowerShell'),
    'chat workspace exposes bottom AI and Tasks tabs plus online device tabs' => str_contains($page, 'data-chat-context-tab="tasks"') && str_contains($page, 'data-chat-context-tab="local-ai"') && str_contains($page, 'id="chat-device-tabs"') && str_contains($page, 'No Device Online'),
    'AI context groups local engines and API providers while devices use named tabs' => str_contains($js, 'function renderChatContextPanels()') && str_contains($js, 'function renderChatDeviceTabs()') && str_contains($js, 'function setChatContext(tab)') && str_contains($page, 'id="chat-context-apis"') && str_contains($page, 'id="chat-context-devices"'),
    'chat context tabs use compact responsive panel styling' => str_contains($designFixes, '.messages{order:1') && str_contains($designFixes, '.composer-wrap{order:2') && str_contains($designFixes, '.chat-context-tabs{order:4') && str_contains($designFixes, '@media(max-width:720px)'),
    'Linux WebView checks the fixed DCP port and never silently selects a random port' => str_contains($linuxWrapper, '#define DCP_LOCAL_PORT 60776') && str_contains($linuxWrapper, 'dcp_port=DCP_LOCAL_PORT') && str_contains($linuxWrapper, 'dcp_port_available()'),
    'Qwen3 uses the native reasoning-off switch on Windows and template kwargs on Unix' => str_contains($service, "'--reasoning','off'") && str_contains($service, "'--chat-template-kwargs','{\"enable_thinking\":false}'") && str_contains($service, '$isQwen3'),
    'chat send button and Enter shortcut dispatch through the active AI/Tasks composer mode' => str_contains($js, "$('#chat-send').addEventListener('click',()=>state.chatContextTab==='tasks'?queueTaskFromComposer():sendChat())") && str_contains($js, "state.chatContextTab==='tasks'?queueTaskFromComposer():sendChat()"),
    'hamburger toggles persistent collapsed navigation' => str_contains($js, "sidebarToggle?.addEventListener('click'") && str_contains($css, '.sidebar-collapsed .sidebar'),
    'chat dock sits below the composer and supports device scrolling' => str_contains($page, 'id="chat-device-scroll-left"') && str_contains($page, 'id="chat-device-scroll-right"') && str_contains($designFixes, '.chat-context-tabs{order:4'),
    'chat workspace expands to the available main-window height without a blank footer gap' => str_contains($js, "content.classList.toggle('chat-page-active',name==='chat')") && str_contains($designFixes, '.content.chat-page-active{display:flex') && str_contains($designFixes, '#page-chat.active{display:flex') && str_contains($designFixes, '#page-chat .chat-layout{flex:1 1 auto'),
    'cloud heartbeat polling is rate-limited to one consolidated request per minute' => str_contains($js, 'setInterval(()=>loadCloudStatus(),60000)') && !str_contains($js, 'setInterval(()=>loadCloudStatus(),10000'),
    'static asset cache keys include the build number so VM updates cannot keep stale chat assets' => str_contains($page, '__DCP_ASSET_VERSION__') && str_contains($page, '$assetVersion=$version.') && str_contains($page, "\$this->config['build']??'1'"),
    'DCP fixed port registry has seven unique valid private ports' => (function(){ $p=json_decode((string)file_get_contents(__DIR__.'/../config/ports.json'),true);$v=array_column($p['ports']??[],'tcp');return count($v)===7&&count(array_unique($v))===7&&min($v)>=49152&&max($v)<=65535&&$v[0]===60776; })(),
    'Windows and macOS wrappers use fixed DCP port' => str_contains(file_get_contents(__DIR__.'/../wrappers/windows/Program.cs'),'const int dcpPort = 60776') && str_contains(file_get_contents(__DIR__.'/../wrappers/macos/main.m'),'const NSInteger dcpPort=60776'),
    'Windows wrapper reuses the existing legacy DCP profile without moving SQLite, keys or model caches' => str_contains($windowsWrapper, 'LocalApplicationData') && str_contains($windowsWrapper, '"DCP", "DCP-UserData", "Windows"') && str_contains($windowsWrapper, 'HasExistingUserData') && str_contains($windowsWrapper, 'dcp.sqlite') && str_contains($windowsWrapper, 'master.key') && str_contains($windowsWrapper, 'no database, key or model files were moved'),
    'Windows wrapper prefers the existing C drive DCP profile before creating a fresh package-local identity' => str_contains($windowsWrapper, '"DCP", "DCP-UserData", "Windows"') && str_contains($windowsWrapper, 'installLegacy') && str_contains($windowsWrapper, 'Reusing existing C:'),
    'Local AI engine discovery uses saved OS paths without a bundled runtime' => str_contains($service, 'enginePathFile') && str_contains($service, 'rememberEnginePath') && !str_contains(substr($service, strpos($service, 'private function binary('), strpos($service, 'private function llamaBinary(')-strpos($service, 'private function binary(')), 'dcp-env/engines'),
    'Ollama can be started and stopped as an OS-level service' => str_contains($service, 'public function stop(string $engine)') && str_contains($js, "runLocalAIAction('/api/local-ai/stop',{engine:'ollama'}"),
    'DC and DSH agent controls are backed by the agent action API' => str_contains(file_get_contents(__DIR__.'/../app/Services/AgentService.php'), 'function action') && str_contains($api, "'/api/agents/action'") && str_contains($js, "'/api/agents/action'"),
    'Agent Install opens a combined dependency checklist and progress modal before execution' => str_contains($js, 'function agentInstallModal(agent)') && str_contains($js, 'agent-install-list') && str_contains($js, 'agent-install-progress') && str_contains($js, "if(action==='install')") && str_contains($js, "action:'install'") ,
    'Agent install modal displays live installer output and only marks completion after verified status' => str_contains($js, 'DOWNLOAD_PROGRESS') || str_contains($js, 'pctMatch') && str_contains($js, 'a.installed&&!a.installing') && str_contains($js, 'agent-install-log'),
    'Agent installation modal has responsive styling' => str_contains($designFixes, '.agent-install-overlay') && str_contains($designFixes, '.agent-progress-track'),
    'Node.js prerequisite uses system-wide installation paths, not DCP private runtime paths' => str_contains(file_get_contents(__DIR__.'/../installers/prerequisites/node/install-unix.sh'), '/usr/local/lib/nodejs') && str_contains(file_get_contents(__DIR__.'/../installers/prerequisites/node/install-windows.ps1'), 'ProgramFiles') && str_contains(file_get_contents(__DIR__.'/../installers/prerequisites/node/install-windows.ps1'), 'nodejs') && !str_contains(file_get_contents(__DIR__.'/../installers/prerequisites/node/install-unix.sh'), '.local/share/dcp/runtimes'),
    'Agent runtime discovery only accepts system Node.js locations' => str_contains(file_get_contents(__DIR__.'/../app/Services/SystemAgentRuntime.php'), '/usr/local/bin/node') && !str_contains(file_get_contents(__DIR__.'/../app/Services/SystemAgentRuntime.php'), '.local/share/dcp/runtimes/node-v'),
    'Ollama and llama.cpp installers are outside dcp-env' => is_file(__DIR__.'/../installers/engines/ollama/install-linux.sh') && is_file(__DIR__.'/../installers/engines/llama.cpp/install-linux.sh'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$passed) $failed++;
}
echo $failed === 0 ? "ALL LOCAL AI UI CONTRACT CHECKS PASSED\n" : "$failed LOCAL AI UI CONTRACT CHECK(S) FAILED\n";
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit($failed===0?0:1);if($failed!==0)throw new RuntimeException($failed.' Local AI UI contract check(s) failed.');
