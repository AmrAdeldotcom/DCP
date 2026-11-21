<?php
declare(strict_types=1);
require __DIR__.'/../app/Core/Database.php';
require __DIR__.'/../app/Core/Crypto.php';
require __DIR__.'/../app/Core/PluginManager.php';
require __DIR__.'/../app/Core/ProviderAdapter.php';
require __DIR__.'/../app/Core/AgentAdapter.php';
require __DIR__.'/../app/Services/ProviderService.php';
require __DIR__.'/../app/Services/ChatService.php';
require __DIR__.'/../app/Services/LocalAIService.php';
require __DIR__.'/../app/Services/CloudService.php';
require __DIR__.'/../app/Services/MailService.php';
require __DIR__.'/../app/Services/DeviceService.php';
require __DIR__.'/../app/Services/TerminalService.php';
require __DIR__.'/../app/Services/ReportService.php';
function ok(bool $v,string $m):void{if(!$v)throw new RuntimeException('FAIL: '.$m);echo "PASS: $m\n";}
$root=sys_get_temp_dir().'/dcp-platform-test-'.bin2hex(random_bytes(4));mkdir($root,0700,true);
$c=['database'=>$root.'/db.sqlite','key_file'=>$root.'/keys/master.key','storage'=>$root.'/storage','terminal_root'=>dirname(__DIR__)];
$db=Database::connect($c);Database::migrate($db);$crypto=new Crypto($c['key_file']);$plugins=new PluginManager(dirname(__DIR__).'/plugins');
$providers=new ProviderService($db,$crypto,$plugins);$secret='dcp-test-'.bin2hex(random_bytes(16));
$p=$providers->save(['name'=>'Mock Provider','driver'=>'openai-compatible','base_url'=>'http://127.0.0.1:18766/v1','model'=>'mock-model','api_key'=>$secret]);
$raw=$db->query("SELECT api_key_enc FROM providers WHERE id=".$db->quote($p['id']))->fetchColumn();
ok($raw!==$secret&&!str_contains($raw,$secret),'API key is encrypted in SQLite');
ok($crypto->decrypt($raw)===$secret,'API key decrypts correctly');
$emptyModel=$providers->save(['name'=>'Preset without model','driver'=>'openai-compatible','base_url'=>'http://127.0.0.1:18766/v1','model'=>'','api_key'=>'']);ok($emptyModel['model']==='','provider preset can be saved before live model discovery');
ok($p['api_key_masked']!==$secret&&str_contains($p['api_key_masked'],'•'),'API key is returned only as a masked preview');
$providers->save(['id'=>$p['id'],'name'=>'Mock Provider Renamed','driver'=>'openai-compatible','base_url'=>'http://127.0.0.1:18766/v1','model'=>'mock-model','api_key'=>'']);
ok($providers->secret($p['id'])['api_key']===$secret,'blank key on edit preserves existing encrypted key');
$chat=new ChatService($providers,$plugins);ok($chat->models($p['id'])[0]['id']==='mock-model','OpenAI-compatible model discovery');ok($chat->send($p['id'],'mock-model','hello',[])==='MOCK_CHAT_OK:hello','OpenAI-compatible chat adapter');
$mail=new MailService($db,$crypto);$mail->save(['smtp_host'=>'smtp.example.test','smtp_pass'=>'smtp-secret-'.bin2hex(random_bytes(8)),'imap_host'=>'imap.example.test','imap_pass'=>'imap-secret-'.bin2hex(random_bytes(8))]);$mailRows=$db->query("SELECT key,value FROM settings WHERE key LIKE 'mail.%pass'")->fetchAll();foreach($mailRows as $row)ok(!str_contains($row['value'],'secret-'),'mail credential encrypted: '.$row['key']);
$devices=new DeviceService($db);$inv=$devices->invite('device@example.test','Test Device');$paired=$devices->pair($inv['id'],$inv['token'],'Test Device','Test OS');ok($paired['status']==='paired','one-time device invitation pairing');ok($devices->heartbeat($paired['device_token'])['ok'],'paired device heartbeat');
$term=new TerminalService(['terminal_root'=>dirname(__DIR__)]);ok($term->run('pwd')['exit_code']===0,'safe terminal command');$blocked=false;try{$term->run('rm -rf /');}catch(Throwable $e){$blocked=true;}ok($blocked,'unsafe terminal command blocked');
$report=new ReportService($db,$c['storage']);$r=$report->create('Smoke test','Report content');ok(is_file($r['file']),'bug report file generated');
$notify=$db->prepare('INSERT INTO notifications(id,title,body,kind,created_at) VALUES(?,?,?,?,?)');$notify->execute(['ntf_test','Test task','Needs approval','task',date(DATE_ATOM)]);$n=$db->query("SELECT read_at FROM notifications WHERE id='ntf_test'")->fetchColumn();ok($n===null,'new notification starts unread');$db->exec("UPDATE notifications SET read_at=datetime('now') WHERE id='ntf_test'");ok((bool)$db->query("SELECT read_at FROM notifications WHERE id='ntf_test'")->fetchColumn(),'notification read state persists in SQLite');
foreach(['en','ar','nl','es','pt','fr','de','zh','ja'] as $locale){$j=json_decode(file_get_contents(dirname(__DIR__).'/locales/'.$locale.'.json'),true);ok(is_array($j)&&isset($j['ai_chat'])&&isset($j['notifications']),'locale JSON '.$locale);}
$localAI=new LocalAIService($c);$localStatus=$localAI->status();ok(isset($localStatus['hardware']['os'],$localStatus['hardware']['ram_gb']),'local AI hardware detection');ok(count($localStatus['catalog'])>=5,'local AI free model catalogue');ok(isset($localStatus['engines']['ollama'],$localStatus['engines']['llama_cpp']),'local AI engine detection');
$cloud=new CloudService($db,$crypto);$cloudStatus=$cloud->status();ok($cloudStatus['configured']===false&&!isset($cloudStatus['access_token']),'cloud credentials are absent until user signs in and are never exposed by status');
echo "ALL PLATFORM TESTS PASSED\n";
