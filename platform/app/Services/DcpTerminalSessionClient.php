<?php
declare(strict_types=1);

/** Authenticated loopback client for the DCP-owned terminal session daemon. */
final class DcpTerminalSessionClient {
 private string $runtime;
 private string $secretFile;
 private string $stateFile;
 public function __construct(private array $config){
  $storage=(string)($config['storage']??getenv('DCP_USER_DATA_DIR')?:'');
  if($storage==='')throw new RuntimeException('Terminal session daemon requires the private DCP user-data directory.');
  $this->runtime=rtrim($storage,'/\\').DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'terminal-sessions';
  if(!is_dir($this->runtime)&&!mkdir($this->runtime,0700,true)&&!is_dir($this->runtime))throw new RuntimeException('Unable to create private terminal session runtime directory.');
  @chmod($this->runtime,0700);$this->secretFile=$this->runtime.DIRECTORY_SEPARATOR.'daemon.token';$this->stateFile=$this->runtime.DIRECTORY_SEPARATOR.'daemon.json';
 }
 public function request(string $action,array $payload=[]):array{
  $state=$this->ensure();$token=trim((string)@file_get_contents($this->secretFile));if(strlen($token)!==64)throw new RuntimeException('Terminal daemon credential is unavailable.');
  $socket=@stream_socket_client('tcp://127.0.0.1:'.(int)$state['port'],$errno,$errstr,2);if(!$socket)throw new RuntimeException('DCP terminal session daemon is unavailable: '.$errstr);
  stream_set_timeout($socket,4);$request=['token'=>$token,'action'=>$action,'payload'=>$payload];fwrite($socket,json_encode($request,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");$line=fgets($socket,1048576);$meta=stream_get_meta_data($socket);fclose($socket);if($line===false||($meta['timed_out']??false))throw new RuntimeException('Terminal daemon request timed out.');$result=json_decode($line,true);if(!is_array($result))throw new RuntimeException('Terminal daemon returned an invalid response.');if(($result['ok']??false)!==true)throw new RuntimeException((string)($result['error']??'Terminal daemon operation failed.'));return is_array($result['data']??null)?$result['data']:[];
 }
 private function ensure():array{
  $state=$this->readState();if($state&&$this->ping($state))return $state;
  $lock=fopen($this->runtime.DIRECTORY_SEPARATOR.'daemon.lock','c');if(!$lock)throw new RuntimeException('Unable to lock terminal daemon startup.');@chmod($this->runtime.DIRECTORY_SEPARATOR.'daemon.lock',0600);
  try{if(!flock($lock,LOCK_EX))throw new RuntimeException('Unable to acquire terminal daemon startup lock.');$state=$this->readState();if($state&&$this->ping($state))return $state;
   if(!is_file($this->secretFile)||strlen(trim((string)file_get_contents($this->secretFile)))!==64){$token=bin2hex(random_bytes(32));file_put_contents($this->secretFile,$token,LOCK_EX);@chmod($this->secretFile,0600);}
   $script=dirname(__DIR__,2).DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'terminal-session-daemon.php';if(!is_file($script))throw new RuntimeException('Terminal session daemon entry point is missing.');
   if(PHP_OS_FAMILY==='Windows'){$cmd='cmd.exe /D /C start "" /B '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($this->runtime).' >NUL 2>&1';pclose(popen($cmd,'r'));}
   else{$cmd='nohup '.escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($this->runtime).' >/dev/null 2>&1 < /dev/null &';exec($cmd);}
   for($i=0;$i<60;$i++){usleep(50000);$state=$this->readState();if($state&&$this->ping($state))return $state;}
   throw new RuntimeException('DCP terminal session daemon did not become ready.');
  }finally{flock($lock,LOCK_UN);fclose($lock);}
 }
 private function readState():?array{$v=json_decode((string)@file_get_contents($this->stateFile),true);return is_array($v)&&isset($v['port'])&&is_int($v['port'])?$v:null;}
 private function ping(array $state):bool{
  $token=trim((string)@file_get_contents($this->secretFile));if(strlen($token)!==64)return false;$s=@stream_socket_client('tcp://127.0.0.1:'.(int)$state['port'],$e,$m,0.3);if(!$s)return false;stream_set_timeout($s,1);fwrite($s,json_encode(['token'=>$token,'action'=>'ping','payload'=>new stdClass()])."\n");$line=fgets($s,4096);fclose($s);$r=json_decode((string)$line,true);return is_array($r)&&($r['ok']??false)===true;
 }
}
