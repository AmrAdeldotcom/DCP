<?php
declare(strict_types=1);
putenv('DCP_CLOUD_SIGNING_SEED=' . base64_encode(str_repeat("\x42", SODIUM_CRYPTO_SIGN_SEEDBYTES)));
require_once __DIR__ . '/../cloud/terminal-signing.php';
require_once __DIR__ . '/../app/Core/Crypto.php';
require_once __DIR__ . '/../app/Services/TaskEnvelopeService.php';
$keys = dcp_cloud_terminal_signing_keys();
$payload = ['schema_version'=>1,'request_id'=>'req-1','target_device_id'=>'device-2','command'=>'printf DCP_OK','approval_hash'=>str_repeat('a',64)];
$signature = dcp_cloud_terminal_sign($payload);
$ok = dcp_cloud_terminal_verify($payload, $signature, $keys['public_key']);
$changed = $payload; $changed['command'] = 'rm -rf /';
$rejectsMutation = !dcp_cloud_terminal_verify($changed, $signature, $keys['public_key']);
$canonicalOrder = dcp_cloud_terminal_canonical_json(['z'=>1,'a'=>['y'=>2,'b'=>3]]) === dcp_cloud_terminal_canonical_json(['a'=>['b'=>3,'y'=>2],'z'=>1]);
$localDb = new PDO('sqlite::memory:');
$localDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$localDb->exec('CREATE TABLE device_signing_keys(device_id TEXT PRIMARY KEY,public_key TEXT NOT NULL,private_key_enc TEXT NOT NULL,created_at TEXT NOT NULL,rotated_at TEXT NOT NULL,revoked_at TEXT NULL)');
$keyDir = sys_get_temp_dir() . '/dcp-cloud-sign-'.bin2hex(random_bytes(5));
$crypto = new Crypto($keyDir.'/master.key');
$deviceSigner = new TaskEnvelopeService($localDb, $crypto);
$deviceKey = $deviceSigner->registerSigningKey('device-2');
$deviceSignature = $deviceSigner->signPayload('device-2', $payload);
$devicePublic = base64_decode($deviceKey['public_key'], true);
$crossProtocol = is_string($devicePublic) && dcp_cloud_terminal_verify($payload, $deviceSignature, $devicePublic);
$deviceMutationRejected = !$deviceSigner->verifyPayload($devicePublic, $changed, $deviceSignature);
@unlink($keyDir.'/master.key'); @rmdir($keyDir);

sodium_memzero($keys['secret_key']);
if (!$ok || !$rejectsMutation || !$canonicalOrder || !$crossProtocol || !$deviceMutationRejected) { fwrite(STDERR,"FAIL: Cloud Ed25519 terminal signing contract\n"); exit(1); }
echo "PASS: Ed25519 signature verifies canonical envelope\nPASS: altered command is rejected\nPASS: canonical JSON is order-independent\nPASS: actual DCP device signing key interoperates with Cloud result verifier\nPASS: altered signed device result is rejected\n";
