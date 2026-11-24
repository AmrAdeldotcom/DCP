<?php
declare(strict_types=1);
require __DIR__.'/../app/Core/Database.php';
require __DIR__.'/../app/Core/Crypto.php';
require __DIR__.'/../app/Core/PluginManager.php';
require __DIR__.'/../app/Core/ProviderAdapter.php';
require __DIR__.'/../app/Core/AgentAdapter.php';
require __DIR__.'/../app/Controllers/PageController.php';
require __DIR__.'/../app/Services/ProviderService.php';
require __DIR__.'/../app/Services/ChatService.php';
require __DIR__.'/../app/Services/LocalAIService.php';
require __DIR__.'/../app/Services/CloudService.php';
require __DIR__.'/../app/Services/MailService.php';
require __DIR__.'/../app/Services/DeviceService.php';
require __DIR__.'/../app/Services/TerminalService.php';
require __DIR__.'/../app/Services/ReportService.php';
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException('FAIL: '.$m);echo "PASS: $m\n";}
ob_start();
$versionManifest=json_decode((string)file_get_contents(dirname(__DIR__).'/version.json'),true);
$appConfig=require dirname(__DIR__).'/config/app.php';
ok(is_array($versionManifest)&&$appConfig['version']===$versionManifest['version'],'application version is sourced from platform/version.json');
ok($appConfig['release_tag']===$versionManifest['releaseTag']&&$appConfig['build']===(string)$versionManifest['build'],'release tag and build metadata are synchronized');
$root=sys_get_temp_dir().'/dcp-platform-test-'.bin2hex(random_bytes(4));mkdir($root,0700,true);
$c=['database'=>$root.'/db.sqlite','key_file'=>$root.'/keys/master.key','storage'=>$root.'/storage','terminal_root'=>dirname(__DIR__)];
$db=Database::connect($c);Database::migrate($db);$crypto=new Crypto($c['key_file']);$plugins=new PluginManager(dirname(__DIR__).'/plugins');
$_SESSION=[];$_SERVER['DCP_REQUEST_PATH']='/';$_SERVER['REQUEST_URI']='/dcp/';
$page=new PageController($db,['version'=>$appConfig['version'],'base_path'=>'/dcp'],$plugins);
ob_start();$page->render();$dashboardHtml=(string)ob_get_clean();
ok(str_contains($dashboardHtml,'DCP v'.$appConfig['version'])&&!str_contains($dashboardHtml,'__DCP_VERSION__'),'dashboard renders the unified version');
ok(str_contains($dashboardHtml,'/dcp/assets/bootstrap.min.css?v='.$appConfig['version'])&&str_contains($dashboardHtml,'/dcp/assets/app.js?v='.$appConfig['version']),'dashboard assets use the unified version and base path');
$_SERVER['DCP_REQUEST_PATH']='/pair';$_GET['id']='test-invitation';
ob_start();$page->render();$pairHtml=(string)ob_get_clean();
ok(str_contains($pairHtml,'/dcp/api/pair')&&!str_contains($pairHtml,'__DCP_BASE__')&&!str_contains($pairHtml,'__DCP_VERSION__'),'pairing page resolves base path and version placeholders');
unset($_GET['id'],$_SERVER['DCP_REQUEST_PATH'],$_SERVER['REQUEST_URI']);
$providers=new ProviderService($db,$crypto,$plugins);
$probe=@stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
if(!$probe)throw new RuntimeException('Could not reserve a local mock-provider port: '.$errstr);
$address=stream_socket_get_name($probe,false);fclose($probe);
$mockPort=(int)substr((string)$address,strrpos((string)$address,':')+1);
$nullDevice=PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null';
$mockServer=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$mockPort,__DIR__.'/mock-provider-router.php'],[0=>['file',$nullDevice,'r'],1=>['file',$nullDevice,'a'],2=>['file',$nullDevice,'a']],$mockPipes,__DIR__);
if(!is_resource($mockServer))throw new RuntimeException('Could not start the isolated mock provider.');
$mockReady=false;
for($attempt=0;$attempt<40;$attempt++){ $socket=@fsockopen('127.0.0.1',$mockPort,$connectError,$connectMessage,0.1); if($socket){fclose($socket);$mockReady=true;break;} usleep(50000); }
if(!$mockReady){proc_terminate($mockServer);proc_close($mockServer);throw new RuntimeException('The isolated mock provider did not become ready.');}
register_shutdown_function(static function()use($mockServer):void{if(is_resource($mockServer)){proc_terminate($mockServer);proc_close($mockServer);}});
$mockBaseUrl='http://127.0.0.1:'.$mockPort.'/v1';
$secret='dcp-test-'.bin2hex(random_bytes(16));
$p=$providers->save(['name'=>'Mock Provider','driver'=>'openai-compatible','base_url'=>'' . $mockBaseUrl . '','model'=>'mock-model','api_key'=>$secret]);
$raw=$db->query("SELECT api_key_enc FROM providers WHERE id=".$db->quote($p['id']))->fetchColumn();
ok($raw!==$secret&&!str_contains($raw,$secret),'API key is encrypted in SQLite');
ok($crypto->decrypt($raw)===$secret,'API key decrypts correctly');
$emptyModel=$providers->save(['name'=>'Preset without model','driver'=>'openai-compatible','base_url'=>'' . $mockBaseUrl . '','model'=>'','api_key'=>'']);ok($emptyModel['model']==='','provider preset can be saved before live model discovery');
ok($p['api_key_masked']!==$secret&&str_contains($p['api_key_masked'],'•'),'API key is returned only as a masked preview');
$providers->save(['id'=>$p['id'],'name'=>'Mock Provider Renamed','driver'=>'openai-compatible','base_url'=>'' . $mockBaseUrl . '','model'=>'mock-model','api_key'=>'']);
ok($providers->secret($p['id'])['api_key']===$secret,'blank key on edit preserves existing encrypted key');
$chat=new ChatService($providers,$plugins);ok($chat->models($p['id'])[0]['id']==='mock-model','OpenAI-compatible model discovery');ok($chat->send($p['id'],'mock-model','hello',[])==='MOCK_CHAT_OK:hello','OpenAI-compatible chat adapter');
$mail=new MailService($db,$crypto);$mail->save(['smtp_host'=>'smtp.example.test','smtp_pass'=>'smtp-secret-'.bin2hex(random_bytes(8)),'imap_host'=>'imap.example.test','imap_pass'=>'imap-secret-'.bin2hex(random_bytes(8))]);$mailRows=$db->query("SELECT key,value FROM settings WHERE key LIKE 'mail.%pass'")->fetchAll();foreach($mailRows as $row)ok(!str_contains($row['value'],'secret-'),'mail credential encrypted: '.$row['key']);
$devices=new DeviceService($db);$inv=$devices->invite('device@example.test','Test Device');$paired=$devices->pair($inv['id'],$inv['token'],'Test Device','Test OS');ok($paired['status']==='paired','one-time device invitation pairing');ok($devices->heartbeat($paired['device_token'])['ok'],'paired device heartbeat');
$term=new TerminalService(['terminal_root'=>dirname(__DIR__)]);ok($term->run('pwd')['exit_code']===0,'safe terminal command');$blocked=false;try{$term->run('rm -rf /');}catch(Throwable $e){$blocked=true;}ok($blocked,'unsafe terminal command blocked');
$report=new ReportService($db,$c['storage']);$r=$report->create('Smoke test','Report content');ok(is_file($r['file']),'bug report file generated');
$notify=$db->prepare('INSERT INTO notifications(id,title,body,kind,created_at) VALUES(?,?,?,?,?)');$notify->execute(['ntf_test','Test task','Needs approval','task',date(DATE_ATOM)]);$n=$db->query("SELECT read_at FROM notifications WHERE id='ntf_test'")->fetchColumn();ok($n===null,'new notification starts unread');$db->exec("UPDATE notifications SET read_at=datetime('now') WHERE id='ntf_test'");ok((bool)$db->query("SELECT read_at FROM notifications WHERE id='ntf_test'")->fetchColumn(),'notification read state persists in SQLite');
foreach(['en','ar','nl','es','pt','fr','de','zh','ja'] as $locale){$j=json_decode(file_get_contents(dirname(__DIR__).'/locales/'.$locale.'.json'),true);ok(is_array($j)&&isset($j['ai_chat'])&&isset($j['notifications']),'locale JSON '.$locale);}
$localAI=new LocalAIService($c);$localStatus=$localAI->status();ok(isset($localStatus['hardware']['os'],$localStatus['hardware']['ram_gb']),'local AI hardware detection');ok(count($localStatus['catalog'])>=5,'local AI free model catalogue');ok(isset($localStatus['engines']['ollama'],$localStatus['engines']['llama_cpp']),'local AI engine detection');
$runtimeMethod=new ReflectionMethod(LocalAIService::class,'llamaRuntimeCandidates');$runtimeMethod->setAccessible(true);ok($runtimeMethod->invoke($localAI,'Linux','x86_64')===['linux-x64'],'Linux llama.cpp managed runtime path');ok($runtimeMethod->invoke($localAI,'Darwin','x86_64','SSE4.2')===['macos-x64-sse42'],'Intel Mac without AVX2 selects SSE4.2 runtime');ok($runtimeMethod->invoke($localAI,'Darwin','x86_64','AVX2 SSE4.2')===['macos-x64-avx2','macos-x64-sse42'],'Intel Mac with AVX2 selects compatible optimized runtime');ok($runtimeMethod->invoke($localAI,'Darwin','arm64')===['macos-arm64'],'Apple Silicon selects ARM64 runtime');$externalPortsMethod=new ReflectionMethod(LocalAIService::class,'externalLlamaPorts');$externalPortsMethod->setAccessible(true);ok(in_array(19123,$externalPortsMethod->invoke($localAI),true),'LocalAI discovers the existing DCP llama.cpp service on its established 19123 port');$profilesMethod=new ReflectionMethod(LocalAIService::class,'llamaModelProfiles');$profilesMethod->setAccessible(true);$llamaProfiles=$profilesMethod->invoke($localAI);ok(($llamaProfiles['qwen3:0.6b']??'')==='mradermacher/Qwen3-0.6B-GGUF:Q4_K_M','low-memory devices can launch Qwen3 0.6B with the verified Hugging Face GGUF profile');
if(PHP_OS_FAMILY!=='Windows') {
 $ownershipDir=sys_get_temp_dir().'/dcp-ai-ownership-'.bin2hex(random_bytes(4));mkdir($ownershipDir.'/runtime',0700,true);
 $proc=proc_open(['/bin/sleep','20'],[0=>['pipe','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
 if(is_resource($proc)) {
  if(isset($pipes[0])&&is_resource($pipes[0]))fclose($pipes[0]);
  $procInfo=proc_get_status($proc);$foreignPid=(int)($procInfo['pid']??0);
  file_put_contents($ownershipDir.'/runtime/llama-server.json',json_encode(['managed'=>true,'pid'=>$foreignPid,'port'=>34567,'binary'=>'/bin/sleep','model'=>'test']));
  $ownershipService=new LocalAIService(['storage'=>$ownershipDir]);$stopResult=$ownershipService->stop('llama.cpp');
  $stillRunning=(bool)(proc_get_status($proc)['running']??false);
  ok(($stopResult['stopped']??true)===false&&$stillRunning,'LocalAI stop refuses to kill a foreign process with a stale managed state');
  proc_terminate($proc,9);proc_close($proc);
 } else ok(false,'LocalAI process ownership test could start its harmless fixture');
 @unlink($ownershipDir.'/runtime/llama-server.json');@rmdir($ownershipDir.'/runtime');@rmdir($ownershipDir);
}

$cloud=new CloudService($db,$crypto);ok(method_exists($cloud,'createPairingCode')&&method_exists($cloud,'requestPairing')&&method_exists($cloud,'decidePairing'),'cloud supports anonymous device identity and approval-based pairing');$cloudSettings=$db->query("SELECT COUNT(*) FROM settings WHERE key LIKE 'cloud.%token%enc'")->fetchColumn();ok((int)$cloudSettings===0,'cloud device and account tokens are not created or exposed by local unit tests');
require __DIR__ . '/update-manifest.php';
require __DIR__ . '/linux-update-transaction.php';
require __DIR__ . '/linux-updater-health.php';
echo "ALL PLATFORM TESTS PASSED\n";
