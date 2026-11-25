<?php
declare(strict_types=1);
(static function (): void {
    global $db;
    require_once __DIR__ . '/../app/Services/DcpResourceChatClient.php';
    $fakeChat=new class implements DcpResourceChatClient {
        public array $calls=[]; public bool $fail=false;
        public function send(string $providerId,string $model,string $message,array $history=[]):string{$this->calls[]=compact('providerId','model','message');if($this->fail)throw new RuntimeException('Provider rejected request with secret diagnostic token=provider-secret');return 'MODEL_REPLY_OK';}
    };
    $registry=new DcpResourceRegistryService($db);
    $service=new DcpResourceInvocationService($registry,$fakeChat);
    $local=$registry->register(['kind'=>'local_model','name'=>'Qwen Local','provider'=>'ollama','model'=>'qwen3:0.6b','operations'=>['invoke'],'metadata'=>[],'capacity'=>[]],'local','user');
    $result=$service->invoke($local['id'],'local','Return a short test response.');
    ok(($result['status']??'')==='completed'&&($result['text']??'')==='MODEL_REPLY_OK'&&$fakeChat->calls[0]['providerId']==='ollama','Owner-local resource invocation routes a local model through the ChatService adapter');
    $external=$registry->register(['kind'=>'external_api','name'=>'OpenAI Resource','provider'=>'openai-compatible','model'=>'gpt-4.1-mini','secret_ref'=>'provider:prv_localtest','operations'=>['invoke'],'metadata'=>[],'capacity'=>[]],'local','user');
    $service->invoke($external['id'],'local','A second test prompt.');
    ok(($fakeChat->calls[1]['providerId']??'')==='prv_localtest','External resource resolves only an opaque provider ID; the ChatService reads its encrypted API key locally');
    $shared=$registry->register(['kind'=>'external_api','name'=>'Missing Vault Ref','provider'=>'openai-compatible','model'=>'gpt-4.1-mini','operations'=>['invoke'],'metadata'=>[],'capacity'=>[]],'local','user');
    try{$service->invoke($shared['id'],'local','No call should happen.');$missingRef=false;}catch(RuntimeException $e){$missingRef=str_contains($e->getMessage(),'owner-local provider credential reference');}
    ok($missingRef&&count($fakeChat->calls)===2,'External resource invocation fails closed when no owner-local secret reference exists');
    $fakeChat->fail=true;
    try{$service->invoke($local['id'],'local','Provider failure test.');$failed=false;}catch(RuntimeException $e){$failed=str_contains($e->getMessage(),'details were not returned');}
    $q=$db->prepare("SELECT COUNT(*) FROM dcp_resource_invocations WHERE resource_id=? AND status='failed'");$q->execute([$local['id']]);
    ok($failed&&(int)$q->fetchColumn()===1,'Failed provider calls close the invocation ledger and do not disclose provider diagnostics');
})();
