<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
$runtime=(string)($argv[1]??'');if($runtime===''||!is_dir($runtime))exit(2);
$token=trim((string)@file_get_contents($runtime.DIRECTORY_SEPARATOR.'daemon.token'));if(strlen($token)!==64)exit(3);
$root=dirname(__DIR__);$config=require $root.'/config/app.php';
require $root.'/app/Services/DcpTerminalSessionClient.php';
require $root.'/app/Services/TerminalService.php';
$config['terminal_session_daemon_worker']=true;
$terminal=new TerminalService($config);
$server=@stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN);if(!$server)exit(4);
$address=stream_socket_get_name($server,false);$port=(int)substr(strrchr((string)$address,':'),1);
$state=['port'=>$port,'pid'=>getmypid(),'started_at'=>date(DATE_ATOM),'protocol'=>1];
$stateFile=$runtime.DIRECTORY_SEPARATOR.'daemon.json';file_put_contents($stateFile,json_encode($state,JSON_UNESCAPED_SLASHES),LOCK_EX);@chmod($stateFile,0600);
$running=true;
while($running){
 $client=@stream_socket_accept($server,1);if(!$client)continue;
 stream_set_timeout($client,3);$line=fgets($client,65537);$request=json_decode((string)$line,true);
 try{
  if(!is_array($request)||!is_string($request['token']??null)||!hash_equals($token,$request['token']))throw new RuntimeException('Unauthorized terminal daemon client.');
  $payload=is_array($request['payload']??null)?$request['payload']:[];$action=(string)($request['action']??'');
  $data=match($action){
   'ping'=>['ready'=>true,'pid'=>getmypid()],
   'start'=>$terminal->startSession((string)($payload['command']??''),isset($payload['cwd'])?(string)$payload['cwd']:null,(string)($payload['shell']??'default')),
   'read'=>$terminal->readSession((string)($payload['session_id']??''),(int)($payload['max_bytes']??16384)),
   'write'=>$terminal->writeSession((string)($payload['session_id']??''),(string)($payload['input']??'')),
   'stop'=>$terminal->stopSession((string)($payload['session_id']??'')),
   'shutdown'=>['stopping'=>true],
   default=>throw new InvalidArgumentException('Unknown terminal daemon action.')
  };
  fwrite($client,json_encode(['ok'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n");
  if($action==='shutdown')$running=false;
 }catch(Throwable $e){fwrite($client,json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n");}
 fclose($client);
}
fclose($server);@unlink($stateFile);exit(0);
