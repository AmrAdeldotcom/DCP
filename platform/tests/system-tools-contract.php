<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
$terminal = file_get_contents(__DIR__ . '/../app/Services/TerminalService.php');
if (!is_string($page) || !is_string($js) || !is_string($api) || !is_string($terminal)) {
    fwrite(STDERR, "FAIL: system tools source files could not be read.\n");
    exit(1);
}
$checks = [
    'provider CRUD page exposes provider list and add action' => str_contains($page, 'id="provider-list"') && str_contains($page, 'id="add-provider"') && str_contains($js, 'function providerModal'),
    'agent adapters have a dedicated status list' => str_contains($page, 'id="agent-list"') && str_contains($js, "api('/api/agents')"),
    'agent status API is backed by AgentService' => str_contains($api, "if(\$method==='GET'&&\$path==='/api/agents')") && str_contains($api, '$this->agents->all()'),
    'terminal page exposes keyboard-submittable form and output' => str_contains($page, 'id="terminal-form"') && str_contains($page, 'id="terminal-command"') && str_contains($page, 'id="terminal-output"') && str_contains($js, "$('#terminal-form').onsubmit=async e=>{e.preventDefault()"),
    'terminal API is disabled in web preview and runs the allow-listed service' => str_contains($api, "if(\$method==='POST'&&\$path==='/api/terminal/run')") && str_contains($api, "'web-preview'") && str_contains($api, '$this->terminal->run'),
    'terminal uses argument-array process execution without shell evaluation' => str_contains($terminal, 'proc_open($args') && !str_contains($terminal, 'shell_exec') && !str_contains($terminal, 'eval('),
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
