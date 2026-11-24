<?php
declare(strict_types=1);

$page = file_get_contents(__DIR__ . '/../app/Controllers/PageController.php');
$js = file_get_contents(__DIR__ . '/../public/assets/app.js');
if (!is_string($page) || !is_string($js)) {
    fwrite(STDERR, "FAIL: DCP Mail UI source files could not be read.\n");
    exit(1);
}
$mailStart = strpos($page, 'id="page-mail"');
$settingsStart = strpos($page, 'id="page-settings"');
$mailPanel = strpos($page, 'id="smtp_host"');
$checks = [
    'Mail navigation entry exists' => str_contains($page, 'data-page="mail"'),
    'dedicated Mail page exists' => $mailStart !== false,
    'SMTP and IMAP settings live inside Mail page' => $mailStart !== false && $settingsStart !== false && $mailPanel !== false && $mailPanel > $mailStart && $mailPanel < $settingsStart,
    'Settings page no longer owns SMTP and IMAP settings' => $settingsStart !== false && strpos(substr($page, $settingsStart), 'id="smtp_host"') === false,
    'Mail page includes SMTP and IMAP test actions' => str_contains($page, 'id="test-smtp"') && str_contains($page, 'id="test-imap"'),
    'Mail page includes unread inbox and refresh control' => str_contains($page, 'id="mail-inbox"') && str_contains($page, 'id="refresh-inbox"'),
    'opening Mail loads saved settings and inbox' => str_contains($js, "if(name==='mail'){loadSettings();loadInbox()}"),
    'Mail settings and test API endpoints remain available' => str_contains($js, "'/api/mail/save'") && str_contains($js, "'/api/mail/test'") && str_contains($js, "'/api/mail/inbox'"),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo $failed === 0 ? "ALL MAIL UI CONTRACT CHECKS PASSED\n" : "$failed MAIL UI CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
