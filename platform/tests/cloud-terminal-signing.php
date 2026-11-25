<?php
declare(strict_types=1);
putenv('DCP_CLOUD_SIGNING_SEED=' . base64_encode(str_repeat("\x42", SODIUM_CRYPTO_SIGN_SEEDBYTES)));
require_once __DIR__ . '/../cloud/terminal-signing.php';
$keys = dcp_cloud_terminal_signing_keys();
$payload = ['schema_version'=>1,'request_id'=>'req-1','target_device_id'=>'device-2','command'=>'printf DCP_OK','approval_hash'=>str_repeat('a',64)];
$signature = dcp_cloud_terminal_sign($payload);
$ok = dcp_cloud_terminal_verify($payload, $signature, $keys['public_key']);
$changed = $payload; $changed['command'] = 'rm -rf /';
$rejectsMutation = !dcp_cloud_terminal_verify($changed, $signature, $keys['public_key']);
$canonicalOrder = dcp_cloud_terminal_canonical_json(['z'=>1,'a'=>['y'=>2,'b'=>3]]) === dcp_cloud_terminal_canonical_json(['a'=>['b'=>3,'y'=>2],'z'=>1]);
sodium_memzero($keys['secret_key']);
if (!$ok || !$rejectsMutation || !$canonicalOrder) { fwrite(STDERR,"FAIL: Cloud Ed25519 terminal signing contract\n"); exit(1); }
echo "PASS: Ed25519 signature verifies canonical envelope\nPASS: altered command is rejected\nPASS: canonical JSON is order-independent\n";
