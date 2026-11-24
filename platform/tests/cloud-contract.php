<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../cloud/index.php');
if (!is_string($source)) {
    fwrite(STDERR, "FAIL: could not read DCP Cloud router.\n");
    exit(1);
}
$deviceRevokeStart = strpos($source, "if(preg_match('#^/api/v1/devices/([a-f0-9-]{36})/revoke$#',\$path,\$m)");
$deviceRevokeEnd = $deviceRevokeStart === false ? false : strpos($source, "if(\$path==='/api/v1/devices/heartbeat", $deviceRevokeStart);
$deviceRevokeRoute = ($deviceRevokeStart !== false && $deviceRevokeEnd !== false)
    ? substr($source, $deviceRevokeStart, $deviceRevokeEnd - $deviceRevokeStart)
    : '';

$checks = [
    'production APP_KEY must be at least 64 hexadecimal characters' => str_contains($source, "if(!preg_match('/^[a-f0-9]{64,}$/i',\$appKey))"),
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
echo $failed === 0 ? "ALL CLOUD CONTRACT CHECKS PASSED\n" : "$failed CLOUD CONTRACT CHECK(S) FAILED\n";
exit($failed === 0 ? 0 : 1);
