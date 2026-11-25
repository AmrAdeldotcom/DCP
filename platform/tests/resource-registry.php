<?php
declare(strict_types=1);
$resources=new DcpResourceRegistryService($db);
$resource=$resources->register(['kind'=>'local_model','name'=>'DCP Resource Contract Model','provider'=>'ollama','model'=>'qwen3:0.6b','operations'=>['invoke','health.read'],'metadata'=>['context_length'=>2048],'capacity'=>['ram_gb'=>8,'queue_depth'=>0],'data_classification'=>'internal','locality'=>'device','published'=>true],'local','user');
ok(($resource['kind']??'')==='local_model'&&($resource['secret_ref']??'')===''&&is_string($resource['id']??null),'local resource registry stores model metadata without credential material');
$resource=$resources->heartbeat($resource['id'],'local',['status'=>'online','capacity'=>['ram_gb'=>8,'queue_depth'=>1,'latency_ms'=>30]]);
ok(($resource['health_status']??'')==='online'&&($resource['capacity']['queue_depth']??-1)===1,'resource heartbeat updates health and bounded capacity metadata');
$discovered=$resources->discover('local',['kind'=>'local_model','model'=>'qwen3:0.6b','operation'=>'invoke']);
ok(count($discovered)===1&&$discovered[0]['id']===$resource['id'],'resource discovery filters by kind, model and supported operation');
$selected=$resources->broker(['kind'=>'local_model','model'=>'qwen3:0.6b','operation'=>'invoke','local_only'=>true,'data_classification'=>'internal'],'local');
ok(($selected['selected']['id']??'')===$resource['id']&&($selected['policy']['local_only']??false)===true,'resource broker selects an online local model under the requested data policy');
$grant=$resources->grant($resource['id'],'local','user','peer',['invoke'],date(DATE_ATOM,time()+3600),25,2);
$peerResources=$resources->discover('peer',['kind'=>'local_model']);
ok(count($peerResources)===1&&$peerResources[0]['id']===$resource['id']&&!array_key_exists('secret_ref',$peerResources[0]),'explicit resource grant exposes metadata but never the owner-local secret reference');
$authorized=$resources->authorize($resource['id'],'user','peer','invoke');
ok(($authorized['grant_id']??'')===$grant['id'],'resource invocation authorization resolves the exact active grant');
$invocation=$resources->beginInvocation($resource['id'],'user','peer','invoke');
ok(($invocation['grant_id']??'')===$grant['id']&&($invocation['status']??'')==='running','resource invocation creates a grant-bound running ledger entry');
$finished=$resources->finishInvocation($invocation['invocation_id'],'local','completed');
ok(($finished['status']??'')==='completed','resource invocation owner can close the invocation ledger');
$resources->revokeGrant($grant['id'],'local');
$revoked=false;try{$resources->authorize($resource['id'],'user','peer','invoke');}catch(RuntimeException $e){$revoked=true;}
ok($revoked,'revoked resource grants block subsequent invocation authorization');
$secretRejected=false;try{$resources->register(['kind'=>'external_api','name'=>'Unsafe API','operations'=>['invoke'],'metadata'=>['api_key'=>'never-store']],'local','user');}catch(InvalidArgumentException $e){$secretRejected=true;}
ok($secretRejected,'resource registry rejects raw credentials in metadata');
$peerVisibleAfterRevoke=$resources->discover('peer',['kind'=>'local_model']);
ok($peerVisibleAfterRevoke===[],'revoked resource grants remove the resource from grantee discovery');
