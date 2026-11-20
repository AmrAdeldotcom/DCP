<?php
require __DIR__.'/../app/Core/Crypto.php';require __DIR__.'/../app/Services/MailService.php';
$dir=sys_get_temp_dir().'/dcp-mail-test-'.bin2hex(random_bytes(4));mkdir($dir,0700,true);$db=new PDO('sqlite:'.$dir.'/mail.sqlite');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('CREATE TABLE settings(key TEXT PRIMARY KEY,value TEXT,updated_at TEXT)');
$c=new Crypto($dir.'/key');$m=new MailService($db,$c);$m->save(['smtp_host'=>'127.0.0.1','smtp_port'=>18767,'smtp_secure'=>'none','from_email'=>'dcp@example.test','from_name'=>'DCP','imap_host'=>'127.0.0.1','imap_port'=>18768,'imap_secure'=>'none','imap_user'=>'tester','imap_pass'=>'pass']);
$r=$m->testSmtp();if(!$r['ok'])throw new Exception('SMTP test failed');echo "PASS: SMTP greeting test\n";
$m->send('user@example.test','DCP test','Hello from DCP');echo "PASS: SMTP message send\n";
$i=$m->inbox();if(count($i['messages'])!==1||$i['messages'][0]['subject']!=='DCP test message')throw new Exception('IMAP inbox test failed: '.json_encode($i));echo "PASS: IMAP unread inbox\n";
