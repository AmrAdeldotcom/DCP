<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../cloud/index.php');
if (!is_string($source)) {
    fwrite(STDERR, "FAIL: could not read DCP Cloud router.\n");
    exit(1);
}
require_once __DIR__ . '/../cloud/token.php';
$rotationToken = str_repeat('t', 64);
$currentKey = str_repeat('C', 64);
$previousKey = 'REPLACE_WITH_AT_LEAST_64_RANDOM_HEX_CHARACTERS';
$rotationHashes = dcp_cloud_hash_token_candidates($rotationToken, $currentKey, $previousKey);
$deviceRevokeStart = strpos($source, "if(preg_match('#^/api/v1/devices/([a-f0-9-]{36})/revoke$#',\$path,\$m)");
$deviceRevokeEnd = $deviceRevokeStart === false ? false : strpos($source, "if(\$path==='/api/v1/devices/heartbeat", $deviceRevokeStart);
$deviceRevokeRoute = ($deviceRevokeStart !== false && $deviceRevokeEnd !== false)
    ? substr($source, $deviceRevokeStart, $deviceRevokeEnd - $deviceRevokeStart)
    : '';

$checks = [
    'production APP_KEY must be at least 32 printable non-placeholder characters' => str_contains($source, 'strlen($appKey)<32') && str_contains($source, 'replace[-_ ]?with'),
    'current and previous APP_KEY hashes are supported during rotation' => count($rotationHashes) === 2 && hash_equals($rotationHashes[0], dcp_cloud_hash_token($rotationToken, $currentKey)) && hash_equals($rotationHashes[1], dcp_cloud_hash_token($rotationToken, $previousKey)),
    'auth, account linking and pairing-code lookup accept previous-key tokens' => substr_count($source, 'hash_token_candidates(') >= 5,
    'token helper is denied to direct HTTP requests' => str_contains((string)file_get_contents(__DIR__ . '/../cloud/.htaccess'), 'RewriteRule ^token[.]php$ - [F,L,NC]'),
    'account device listings are rate limited' => str_contains($source, "rate_limit('device-list',240,60)"),
    'pairing decisions are rate limited' => str_contains($source, "rate_limit('pairing-decision',60,3600)"),
    'paired-device listings are rate limited' => str_contains($source, "rate_limit('paired-device-list',240,60)"),
    'device revocation revokes all related pairings transactionally' => str_contains($deviceRevokeRoute, "UPDATE device_pairings SET revoked_at=NOW()"),
    'device revocation expires pending pairing requests' => str_contains($deviceRevokeRoute, "UPDATE pairing_requests SET status='expired'"),
    'device revocation expires pending connection requests' => str_contains($deviceRevokeRoute, "UPDATE connection_requests SET status='expired'"),
    'pair-scope checks reject revoked owner devices' => str_contains($source, 'JOIN devices a ON a.id=p.owner_device AND a.revoked_at IS NULL'),
    'pair-scope checks reject revoked peer devices' => str_contains($source, 'JOIN devices b ON b.id=p.peer_device AND b.revoked_at IS NULL'),
    'pairing request codes cannot target revoked owners' => str_contains($source, 'JOIN devices owner ON owner.id=pc.owner_device AND owner.revoked_at IS NULL'),
    'pairing request lists hide revoked requesters' => str_contains($source, "AND x.revoked_at IS NULL ORDER BY r.created_at"),
    'paired-device lists hide revoked peers' => str_contains($source, "AND p.revoked_at IS NULL AND x.revoked_at IS NULL ORDER BY x.label"),
    'signal polling rejects revoked endpoints and expired requests' => str_contains($source, "JOIN devices a ON a.id=r.source_device AND a.revoked_at IS NULL JOIN devices z ON z.id=r.target_device AND z.revoked_at IS NULL WHERE r.id=? AND r.expires_at>NOW() AND r.status='pending'"),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
$probeKey = static function(string $key): string {
    $envFile = tempnam(sys_get_temp_dir(), 'dcp-cloud-env-');
    if ($envFile === false) return '';
    file_put_contents($envFile, 'APP_KEY=' . $key . PHP_EOL);
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/../cloud/index.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        ['DCP_CLOUD_ENV' => $envFile, 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/']
    );
    if (!is_resource($process)) { @unlink($envFile); return ''; }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    @unlink($envFile);
    return $output;
};
$validKeyOutput = $probeKey(str_repeat('A', 40) . '!@#$%^');
$shortKeyOutput = $probeKey('short');
$placeholderKeyOutput = $probeKey('REPLACE_WITH_AT_LEAST_64_RANDOM_HEX_CHARACTERS');
$runtimeChecks = [
    '46-character printable production-style APP_KEY is accepted' => str_contains($validKeyOutput, 'DCP Cloud') && !str_contains($validKeyOutput, 'security key is not configured'),
    'short APP_KEY is rejected before database access' => str_contains($shortKeyOutput, 'DCP Cloud security key is not configured.'),
    'example placeholder APP_KEY is rejected before database access' => str_contains($placeholderKeyOutput, 'DCP Cloud security key is not configured.'),
];
foreach ($runtimeChecks as $label => $ok) {
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
echo $failed === 0 ? "ALL CLOUD CONTRACT CHECKS PASSED\n" : "$failed CLOUD CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
