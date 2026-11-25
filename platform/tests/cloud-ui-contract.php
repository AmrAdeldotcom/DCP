<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
$css = file_get_contents(__DIR__ . '/../public/assets/app.css');
$api = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
if (!is_string($page) || !is_string($js) || !is_string($css) || !is_string($api)) {
    fwrite(STDERR, "FAIL: DCP UI source files could not be read.\n");
    exit(1);
}
$deviceStart = strpos($page, 'id="page-devices"');
$settingsStart = strpos($page, 'id="page-settings"');
$cloudPanel = strpos($page, '<h3>DCP Cloud</h3>');
$checks = [
    'DCP Cloud account and pairing panel is inside Devices page' => $deviceStart !== false && $settingsStart !== false && $cloudPanel !== false && $cloudPanel > $deviceStart && $cloudPanel < $settingsStart,
    'Settings page no longer owns the Cloud pairing panel' => $settingsStart !== false && strpos(substr($page, $settingsStart), '<h3>DCP Cloud</h3>') === false,
    'top header exposes a Cloud account status control' => str_contains($page, 'id="cloud-header-status"'),
    'Cloud status refresh updates the header state' => str_contains($js, "header.dataset.state=d.connected?"),
    'opening Devices refreshes cloud pairing state' => str_contains($js, "if(name==='devices'){loadDevices();loadCloudStatus()}"),
    'header Cloud control opens Devices management' => str_contains($js, "$('#cloud-header-status').onclick=()=>showPage('devices')"),
    'Cloud sign-in and registration remain available' => str_contains($page, 'id="cloud-login"') && str_contains($page, 'id="cloud-register"'),
    'one-time pairing code and request controls remain available' => str_contains($page, 'id="cloud-create-pairing-code"') && str_contains($page, 'id="cloud-request-pair"'),
    'incoming pairing approval and revoke lists remain available' => str_contains($page, 'id="cloud-pending-pairings"') && str_contains($page, 'id="cloud-device-list"'),
    'paired device inventory renders pause and resume presence controls' => str_contains($js, 'data-pair-presence=') && str_contains($js, 'Pause presence') && str_contains($js, 'Resume presence'),
    'presence pause/resume UI preserves pairing and signaling permissions' => str_contains($js, 'signaling permissions will be unchanged') && str_contains($js, "api('/api/cloud/pairing-presence'"),
    'local API exposes the authenticated pairing presence action' => str_contains($api, "'/api/cloud/pairing-presence'") && str_contains($api, '$this->cloud->setPairingPresence'),
    'header Cloud state is styled for account, guest and offline states' => str_contains($css, '.cloud-header-pill[data-state="account"]') && str_contains($css, '.cloud-header-pill[data-state="guest"]') && str_contains($css, '.cloud-header-pill[data-state="offline"]'),
    'Cloud header remains visible on narrow screens' => str_contains($css, '@media(max-width:720px){.cloud-header-pill{display:inline-flex'),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo $failed === 0 ? "ALL CLOUD UI CONTRACT CHECKS PASSED\n" : "$failed CLOUD UI CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
