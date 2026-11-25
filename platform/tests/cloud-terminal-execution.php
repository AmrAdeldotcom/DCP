<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Services/CloudService.php';
require_once __DIR__ . '/../app/Services/TerminalService.php';
require_once __DIR__ . '/../app/Services/TaskEnvelopeService.php';
require_once __DIR__ . '/../app/Services/CloudTerminalExecutionService.php';

(static function (): void {
$cloud = new class implements CloudTerminalCloudClient {
 public array $submitted=[]; public int $dispatches=0; public bool $valid=true; public array $envelope=[];
 public function __construct() {}
 public function deviceIdentity():array{return ['device_id'=>'target-device-0001','device_token'=>str_repeat('t',48)];}
 public function registerTerminalDeviceSigningKey(string $publicKey):array{return ['registered'=>true];}
 public function dispatchRemoteTerminalRequest(string $requestId):array{$this->dispatches++;return ['envelope'=>$this->envelope,'signature'=>'cloud-signature'];}
 public function verifyTerminalDispatch(array $envelope,string $signature):bool{return $this->valid&&$signature==='cloud-signature';}
 public function submitRemoteTerminalResult(string $requestId,array $signedResult,string $signature):array{$this->submitted[]=['request_id'=>$requestId,'result'=>$signedResult,'signature'=>$signature];return ['status'=>$signedResult['status']];}
};
$terminal = new class implements CloudTerminalShellExecutor {
 public int $runs=0; public function __construct() {}
 public function execute(string $command,?string $cwd=null,int $timeout=30,string $shell='default'):array{$this->runs++;return ['output'=>'REMOTE_OK','exit_code'=>0,'timed_out'=>false,'duration_ms'=>12];}
};
$keys = new class implements CloudTerminalDeviceSigner {
 public function __construct() {}
 public function registerSigningKey(string $deviceId):array{return ['public_key'=>base64_encode(str_repeat("\x01",SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES))];}
 public function signPayload(string $deviceId,array $payload):string{return 'device-result-signature';}
};
$requestId='11111111-1111-4111-8111-111111111111';$envelopeId='22222222-2222-4222-8222-222222222222';$source='source-device-0001';$target='target-device-0001';$command='printf REMOTE_OK';$cwd='/tmp';$shell='bash';$timeout=30;
$hashInput=['request_id'=>$requestId,'source_device'=>$source,'target_device'=>$target,'command'=>$command,'cwd'=>$cwd,'shell'=>$shell,'timeout_seconds'=>$timeout];$hash=hash('sha256',json_encode($hashInput,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
$cloud->envelope=['schema_version'=>1,'envelope_id'=>$envelopeId,'request_id'=>$requestId,'source_device'=>$source,'issuer'=>'dcp-cloud','issuer_key_id'=>str_repeat('a',64),'target_device_id'=>$target,'action'=>'terminal.execute','command'=>$command,'working_directory'=>$cwd,'shell'=>$shell,'timeout_seconds'=>$timeout,'command_sha256'=>$hash,'approval_hash'=>$hash,'issued_at'=>gmdate('Y-m-d\\TH:i:s\\Z'),'expires_at'=>gmdate('Y-m-d\\TH:i:s\\Z',time()+90),'nonce'=>str_repeat('b',48)];
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec("CREATE TABLE cloud_terminal_executions(envelope_id TEXT PRIMARY KEY,request_id TEXT NOT NULL UNIQUE,target_device_id TEXT NOT NULL,command_sha256 TEXT NOT NULL,status TEXT NOT NULL,result_json TEXT NULL,result_signature TEXT NULL,created_at TEXT NOT NULL,completed_at TEXT NULL)");
$service=new CloudTerminalExecutionService($db,$cloud,$terminal,$keys);
$first=$service->executeApproved($requestId);ok($first['status']==='completed'&&$first['result']['output']==='REMOTE_OK'&&$terminal->runs===1,'Target executes the exact Cloud-approved envelope once as the local OS user');
$second=$service->executeApproved($requestId);ok(($second['replayed']??false)===true&&($second['executed_again']??true)===false&&$terminal->runs===1&&count($cloud->submitted)===2,'A retry resubmits the saved signed result without executing the command twice');
$cloud->valid=false;$request2='33333333-3333-4333-8333-333333333333';$cloud->envelope['request_id']=$request2;$cloud->envelope['envelope_id']='44444444-4444-4444-8444-444444444444';
try{$service->executeApproved($request2);$invalidRejected=false;}catch(Throwable $e){$invalidRejected=true;}
ok($invalidRejected&&$terminal->runs===1,'Invalid Cloud signature is rejected before any local shell execution');
})();
