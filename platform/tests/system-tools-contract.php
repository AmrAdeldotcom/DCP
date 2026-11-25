<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$terminal = file_get_contents(__DIR__ . '/../app/Services/TerminalService.php');
$dsh = file_get_contents(__DIR__ . '/../plugins/agents/deepseek-harness/driver.php');
$desktopCommander = file_get_contents(__DIR__ . '/../plugins/agents/desktop-commander/driver.php');
$ollamaDriver = file_get_contents(__DIR__ . '/../plugins/providers/ollama/driver.php');
if (!is_string($page) || !is_string($js) || !is_string($api) || !is_string($terminal) || !is_string($dsh) || !is_string($desktopCommander) || !is_string($ollamaDriver)) {
    fwrite(STDERR, "FAIL: system tools source files could not be read.\n");
    exit(1);
}
$checks = [
    'provider CRUD page exposes provider list and add action' => str_contains($page, 'id="provider-list"') && str_contains($page, 'id="add-provider"') && str_contains($js, 'function providerModal'),
    'agent adapters have a dedicated status list' => str_contains($page, 'id="agent-list"') && str_contains($js, "api('/api/agents')"),
    'agent status API is backed by AgentService' => str_contains($api, "if(\$method==='GET'&&\$path==='/api/agents')") && str_contains($api, '$this->agents->all()'),
    'terminal page exposes keyboard-submittable form and output' => str_contains($page, 'id="terminal-form"') && str_contains($page, 'id="terminal-command"') && str_contains($page, 'id="terminal-output"') && str_contains($js, "$('#terminal-form').onsubmit=async e=>{e.preventDefault()"),
    'terminal API is disabled in web preview and runs the full shell as the current OS user' => str_contains($api, "if(\$method==='POST'&&\$path==='/api/terminal/run')") && str_contains($api, "'web-preview'") && str_contains($api, '$this->terminal->execute'),
    'terminal uses argument-array process execution without shell evaluation' => str_contains($terminal, 'proc_open($args') && !str_contains($terminal, 'shell_exec') && !str_contains($terminal, 'eval('),
    'Windows terminal pwd uses PowerShell Get-Location instead of cmd cd argument quoting' => str_contains($terminal, "['powershell.exe','-NoProfile','-NonInteractive','-Command','(Get-Location).Path']"),
    'DeepSeek Harness process probing is restricted to macOS where its app can exist' => str_contains($dsh, "PHP_OS_FAMILY !== 'Darwin'") && str_contains($dsh, '/Applications/DeepSeek Harness.app'),
    'Desktop Commander process status uses PowerShell on Windows and pgrep on Unix' => str_contains($desktopCommander, "PHP_OS_FAMILY === 'Windows'") && str_contains($desktopCommander, 'Get-CimInstance Win32_Process') && str_contains($desktopCommander, '/usr/bin/pgrep'),
    'Ollama provider disables Qwen3 thinking so short chat responses are returned as content' => str_contains($ollamaDriver, "\$payload['think']=false") && str_contains($ollamaDriver, "preg_match('/^qwen3(?::|\$)/i',\$model)"),
    'notification panel and mark-all-read control are present' => str_contains($page, 'id="notification-panel"') && str_contains($page, 'id="notifications-read-all"'),
    'notification list supports read-one and read-all lifecycle' => str_contains($js, "api('/api/notifications/read',{id:b.dataset.notification},'POST')") && str_contains($js, "api('/api/notifications/read',{all:true},'POST')"),
    'notification endpoints are implemented server-side' => str_contains($api, "if(\$method==='GET'&&\$path==='/api/notifications')") && str_contains($api, "if(\$method==='POST'&&\$path==='/api/notifications/read')"),
    'pairing-code clipboard copy has a visible manual fallback' => str_contains($js, 'navigator.clipboard.writeText(d.code)') && str_contains($js, 'Pairing code created. Copy it from the screen.'),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo $failed === 0 ? "ALL SYSTEM TOOLS CONTRACT CHECKS PASSED\n" : "$failed SYSTEM TOOLS CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);

$wrapper=(string)file_get_contents(dirname(__DIR__).'/wrappers/macos/main.m');
ok(str_contains($wrapper,'environment[@"TMPDIR"]=@"/tmp";'),'macOS wrapper normalizes TMPDIR so bundled PHP can reach external HTTPS services');
