<?php
declare(strict_types=1);
final class MailService {
 public function __construct(private PDO $db,private Crypto $crypto){}
 public function save(array $d):void{
  $keys=['smtp_host','smtp_port','smtp_secure','smtp_user','smtp_pass','imap_host','imap_port','imap_secure','imap_user','imap_pass','from_email','from_name','auto_poll'];
  foreach($keys as $k){if(!array_key_exists($k,$d))continue;$v=(string)$d[$k];if(in_array($k,['smtp_pass','imap_pass'],true)&&$v==='')continue;$enc=in_array($k,['smtp_pass','imap_pass'],true)?$this->crypto->encrypt($v):$v;$q=$this->db->prepare('INSERT INTO settings(key,value,updated_at) VALUES(?,?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value,updated_at=excluded.updated_at');$q->execute(['mail.'.$k,$enc,date(DATE_ATOM)]);}
 }
 public function settings(bool $secrets=false):array{$out=[];foreach($this->db->query("SELECT key,value FROM settings WHERE key LIKE 'mail.%'") as $r){$k=substr($r['key'],5);$out[$k]=str_ends_with($k,'_pass')?($secrets?$this->crypto->decrypt($r['value']):$this->crypto->mask($this->crypto->decrypt($r['value']))):$r['value'];}return $out;}
 private function readReply($fp):string{$all='';do{$line=fgets($fp,515)?:'';$all.=$line;}while(strlen($line)>=4&&$line[3]==='-');return $all;}
 private function smtp(array $s,string $to,string $subject,string $body,?string $attachmentPath=null):void{
  $host=$s['smtp_host']??'';$port=(int)($s['smtp_port']??587);if(!$host)throw new RuntimeException('SMTP host is missing.');
  $scheme=($s['smtp_secure']??'starttls')==='ssl'?'ssl://':'tcp://';$fp=stream_socket_client($scheme.$host.':'.$port,$errno,$errstr,12,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]));if(!$fp)throw new RuntimeException('SMTP connect: '.$errstr);
  stream_set_timeout($fp,12);$this->readReply($fp);$send=function(string $cmd)use($fp){fwrite($fp,$cmd."\r\n");$r=$this->readReply($fp);if(!preg_match('/^[23]/',$r))throw new RuntimeException('SMTP: '.trim($r));return $r;};
  $send('EHLO localhost');if(($s['smtp_secure']??'starttls')==='starttls'){$send('STARTTLS');if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed.');$send('EHLO localhost');}
  if(($s['smtp_user']??'')!==''){$send('AUTH LOGIN');$send(base64_encode($s['smtp_user']));$send(base64_encode($s['smtp_pass']??''));}
  $from=$s['from_email']??$s['smtp_user'];$send('MAIL FROM:<'.$from.'>');$send('RCPT TO:<'.$to.'>');$send('DATA');
  $safeSubject='=?UTF-8?B?'.base64_encode($subject).'?=';$headers="From: ".str_replace([chr(13),chr(10)],' ',(string)($s['from_name']??'DCP'))." <".$from.">\r\nTo: ".$to."\r\nSubject: ".$safeSubject."\r\nMIME-Version: 1.0\r\n";
  if($attachmentPath!==null){$boundary='=_DCP_'.bin2hex(random_bytes(12));$name=basename($attachmentPath);$data=file_get_contents($attachmentPath);if($data===false)throw new RuntimeException('Unable to read report attachment.');$headers.="Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";$msg=$headers."\r\n--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\n.","\n..",$body)."\r\n--$boundary\r\nContent-Type: application/json; name=\"$name\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$name\"\r\n\r\n".chunk_split(base64_encode($data))."--$boundary--\r\n.";}else{$headers.="Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";$msg=$headers."\r\n".str_replace("\n.","\n..",$body)."\r\n.";}fwrite($fp,$msg."\r\n");$r=$this->readReply($fp);if(!str_starts_with($r,'2'))throw new RuntimeException('SMTP DATA: '.trim($r));$send('QUIT');fclose($fp);
 }
 public function send(string $to,string $subject,string $body,?string $attachmentPath=null):void{if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Recipient email is invalid.');$s=$this->settings(true);if(!filter_var((string)($s['from_email']??''),FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Sender email is invalid.');if($attachmentPath!==null&&(!is_file($attachmentPath)||filesize($attachmentPath)>5242880))throw new InvalidArgumentException('Attachment is missing or exceeds 5 MB.');$subject=str_replace([chr(13),chr(10)],' ',$subject);$this->smtp($s,$to,$subject,$body,$attachmentPath);}
 public function testSmtp():array{$s=$this->settings(true);if(empty($s['smtp_host']))throw new RuntimeException('Save SMTP settings first.');$host=$s['smtp_host'];$port=(int)($s['smtp_port']??587);$scheme=($s['smtp_secure']??'starttls')==='ssl'?'ssl://':'tcp://';$fp=stream_socket_client($scheme.$host.':'.$port,$e,$m,10,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]));if(!$fp)throw new RuntimeException('SMTP connection failed: '.$m);stream_set_timeout($fp,10);$greeting=$this->readReply($fp);$send=function($cmd)use($fp){fwrite($fp,$cmd."\r\n");$r=$this->readReply($fp);if(!preg_match('/^[23]/',$r))throw new RuntimeException('SMTP: '.trim($r));return $r;};$send('EHLO localhost');if(($s['smtp_secure']??'starttls')==='starttls'){$send('STARTTLS');if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed.');$send('EHLO localhost');}if(!empty($s['smtp_user'])){$send('AUTH LOGIN');$send(base64_encode($s['smtp_user']));$send(base64_encode($s['smtp_pass']??''));}$send('QUIT');fclose($fp);return ['ok'=>true,'reply'=>trim($greeting),'authenticated'=>!empty($s['smtp_user'])];}
 public function inbox():array{
  $s=$this->settings(true);if(empty($s['imap_host']))throw new RuntimeException('Save IMAP settings first.');
  $host=$s['imap_host'];$port=(int)($s['imap_port']??993);$scheme=($s['imap_secure']??'ssl')==='ssl'?'ssl://':'tcp://';
  $fp=stream_socket_client($scheme.$host.':'.$port,$e,$m,12,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]));
  if(!$fp)throw new RuntimeException('IMAP connection failed: '.$m);stream_set_timeout($fp,12);$greeting=fgets($fp,1024);
  $cmd=function(string $tag,string $line)use($fp){fwrite($fp,$tag.' '.$line."\r\n");$out='';while(($r=fgets($fp,4096))!==false){$out.=$r;if(str_starts_with($r,$tag.' '))break;}if(str_contains($out,$tag.' NO')||str_contains($out,$tag.' BAD'))throw new RuntimeException('IMAP: '.substr($out,0,400));return $out;};
  if(($s['imap_secure']??'ssl')==='starttls'){$cmd('A0','STARTTLS');if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('IMAP TLS negotiation failed.');}
  $cmd('A1','LOGIN "'.addcslashes($s['imap_user'],'"\\').'" "'.addcslashes($s['imap_pass'],'"\\').'"');
  $folders=$cmd('A2','LIST "" "*"');$cmd('A3','SELECT INBOX');$search=$cmd('A4','UID SEARCH UNSEEN');preg_match('/\* SEARCH(.*)/',$search,$mm);
  $uids=array_slice(preg_split('/\s+/',trim($mm[1]??''))?:[],0,20);$messages=[];
  foreach($uids as $i=>$uid){if(!ctype_digit($uid))continue;$raw=$cmd('B'.($i+1),'UID FETCH '.$uid.' (BODY.PEEK[HEADER.FIELDS (FROM SUBJECT DATE)] BODY.PEEK[TEXT]<0.800>)');
   preg_match('/^Subject:\s*(.+)$/im',$raw,$sub);preg_match('/^From:\s*(.+)$/im',$raw,$from);preg_match('/^Date:\s*(.+)$/im',$raw,$date);
   $preview='';if(preg_match('/BODY\[TEXT\].*?\r\n(.*?)\r\n\)/s',$raw,$body))$preview=trim(strip_tags($body[1]));
   $subject=trim($sub[1]??'(no subject)');$category=preg_match('/invitation|invite|pair|device|دعوة|جهاز/i',$subject.' '.$preview)?'invitation':'general';
   $messages[]=['id'=>$uid,'subject'=>$subject,'from'=>trim($from[1]??''),'date'=>trim($date[1]??''),'preview'=>substr($preview,0,800),'category'=>$category];
  }
  $cmd('A5','LOGOUT');fclose($fp);return ['messages'=>$messages,'folders'=>$folders,'unread_count'=>count($messages)];
 }
}
