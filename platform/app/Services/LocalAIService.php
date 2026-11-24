<?php
declare(strict_types=1);

final class LocalAIService {
 private string $root;
 private string $storage;
 private array $catalog=[
  ['id'=>'qwen3:0.6b','name'=>'Qwen3 0.6B','ram'=>4,'download'=>'~0.5 GB','tier'=>'Starter','note'=>'Very light; basic chat and quick tasks'],
  ['id'=>'qwen3:1.7b','name'=>'Qwen3 1.7B','ram'=>8,'download'=>'~1.4 GB','tier'=>'Light','note'=>'Good balance for low-memory devices'],
  ['id'=>'qwen3:4b','name'=>'Qwen3 4B','ram'=>12,'download'=>'~2.5 GB','tier'=>'Balanced','note'=>'Recommended everyday assistant'],
  ['id'=>'qwen3:8b','name'=>'Qwen3 8B','ram'=>20,'download'=>'~5.2 GB','tier'=>'Powerful','note'=>'Better reasoning; needs more memory'],
  ['id'=>'gemma3:1b','name'=>'Gemma 3 1B','ram'=>6,'download'=>'~0.8 GB','tier'=>'Starter','note'=>'Small general-purpose model'],
  ['id'=>'gemma3:4b','name'=>'Gemma 3 4B','ram'=>12,'download'=>'~3.4 GB','tier'=>'Balanced','note'=>'Vision-capable model; check device memory'],
  ['id'=>'llama3.2:3b','name'=>'Llama 3.2 3B','ram'=>10,'download'=>'~2.0 GB','tier'=>'Light','note'=>'Compact general-purpose chat']
 ];

 public function __construct(array $config) {
  $this->root=dirname(__DIR__,2);
  $this->storage=(string)$config['storage'];
 }

 private function run(string $cmd,int $timeout=6):string {
  $spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
  $proc=@proc_open($cmd,$spec,$pipes,$this->root);
  if(!is_resource($proc))return '';
  fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
  $stdout='';$started=microtime(true);$exit=-1;
  do {
   $stdout.=stream_get_contents($pipes[1])?:'';stream_get_contents($pipes[2]);
   $status=proc_get_status($proc);
   if(!$status['running']){$exit=(int)$status['exitcode'];break;}
   if(microtime(true)-$started>$timeout){proc_terminate($proc,9);$exit=124;break;}
   usleep(50000);
  } while(true);
  $stdout.=stream_get_contents($pipes[1])?:'';fclose($pipes[1]);fclose($pipes[2]);
  $closed=proc_close($proc);if($exit<0)$exit=$closed;
  return $exit===0?trim($stdout):'';
 }

 private function llamaRuntimeCandidates(string $os,string $arch,string $features=''):array {
  $arch=strtolower($arch);
  if($os==='Linux')return in_array($arch,['x86_64','amd64'],true)?['linux-x64']:[];
  if($os==='Darwin') {
   if(in_array($arch,['arm64','aarch64'],true))return ['macos-arm64'];
   if(in_array($arch,['x86_64','amd64'],true)) {
    $hasAvx2=preg_match('/(?:^|\s)AVX2(?:\s|$)/i',$features)===1;
    return $hasAvx2?['macos-x64-avx2','macos-x64-sse42']:['macos-x64-sse42'];
   }
  }
  return [];
 }

 private function binary(string $name):string {
  if(PHP_OS_FAMILY==='Windows') {
   $safe=preg_replace('/[^A-Za-z0-9._-]/','',$name)?:'';
   $found=$safe!==''?$this->run('where.exe '.$safe,3):'';
   if($found!=='')return strtok($found,"\n")?:'';
   $local=getenv('LOCALAPPDATA')?:'';$programFiles=getenv('ProgramFiles')?:'C:\\Program Files';
   $candidates=[];
   if($name==='ollama'||$name==='ollama.exe')$candidates=[
    $this->root.'\\dcp-env\\engines\\ollama\\windows-x64\\ollama.exe',
    $local.'\\Programs\\Ollama\\ollama.exe',$local.'\\Ollama\\ollama.exe',$programFiles.'\\Ollama\\ollama.exe'
   ];
   if($name==='llama-server'||$name==='llama-server.exe')$candidates=[
    $this->root.'\\dcp-env\\engines\\llama.cpp\\windows-x64\\llama-server.exe',
    $local.'\\DCP\\llama.cpp\\bin\\llama-server.exe'
   ];
   foreach($candidates as $candidate)if($candidate!==''&&is_file($candidate))return $candidate;
   return '';
  }
  $system=$this->run('command -v '.escapeshellarg($name),3);
  if($system!==''){
   $system=strtok($system,"\n")?:'';
   if($system!==''&&is_executable($system))return $system;
  }
  $arch=strtolower(php_uname('m'));
  $osArch=in_array($arch,['arm64','aarch64'],true)?'macos-arm64':'macos-x64';
  if($name==='llama-server') {
   $features='';
   if(PHP_OS_FAMILY==='Darwin'&&in_array($arch,['x86_64','amd64'],true))
    $features=$this->run('/usr/sbin/sysctl -n machdep.cpu.leaf7_features 2>/dev/null').' '.$this->run('/usr/sbin/sysctl -n machdep.cpu.features 2>/dev/null');
   $candidates=$this->llamaRuntimeCandidates(PHP_OS_FAMILY,$arch,$features);
   foreach($candidates as $runtime){$managed=$this->root.'/dcp-env/engines/llama.cpp/'.$runtime.'/llama-server';if(is_executable($managed))return $managed;}
   $legacy=(getenv('HOME')?:'').'/.local/bin/llama-server';
   if(is_executable($legacy))return $legacy;
  }
  if($name==='ollama') {
   $systemApp='/Applications/Ollama.app/Contents/Resources/ollama';
   if(PHP_OS_FAMILY==='Darwin'&&is_executable($systemApp))return $systemApp;
   $managed=PHP_OS_FAMILY==='Linux'
    ?$this->root.'/dcp-env/engines/ollama/linux-x64/ollama'
    :$this->root.'/dcp-env/engines/ollama/'.$osArch.'/Ollama.app/Contents/Resources/ollama';
   if(is_executable($managed))return $managed;
  }
  return '';
 }

 private function llamaBinary():string {
  if(PHP_OS_FAMILY==='Windows')return $this->binary('llama-server.exe')?:$this->binary('llama-server');
  return $this->binary('llama-server');
 }

 private function ollama():array {
  $ch=curl_init('http://127.0.0.1:11434/api/tags');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>3]);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
  $data=$raw!==false&&$code===200?json_decode($raw,true):[];
  return ['online'=>is_array($data)&&isset($data['models']),'models'=>array_map(fn($m)=>['id'=>$m['name'],'size'=>$m['size']??0],$data['models']??[])];
 }

 private function hardware(bool $forceRefresh=false):array {
  $cacheFile=$this->storage.'/cache/hardware.json';
  if(!$forceRefresh&&is_file($cacheFile)&&filemtime($cacheFile)>=time()-21600) {
   $cached=json_decode((string)file_get_contents($cacheFile),true);
   if(is_array($cached)&&($cached['version']??0)===2&&is_array($cached['data']??null))return $cached['data'];
  }
  $os=PHP_OS_FAMILY;$arch=php_uname('m');$ram=0;$gpu='Not detected';$cpu=php_uname('v');
  if($os==='Darwin') {
   $ram=(int)$this->run('/usr/sbin/sysctl -n hw.memsize');
   $gpuRaw=$this->run('/usr/sbin/ioreg -l -w0 -r -c IOAccelerator | /usr/bin/grep -m1 \"IOClass\"',2);
   $gpu='GPU not detected';
   if(stripos($gpuRaw,'AMDRadeon')!==false)$gpu='AMD Radeon (IOAccelerator)';
   elseif(stripos($gpuRaw,'Intel')!==false)$gpu='Intel integrated graphics';
   elseif(stripos($gpuRaw,'Apple')!==false)$gpu='Apple integrated graphics';
   if($forceRefresh){$detail=$this->run('/usr/sbin/system_profiler SPDisplaysDataType | /usr/bin/grep -E \"Chipset Model|VRAM \\(Total\\)\" | /usr/bin/head -4',15);if($detail!=='')$gpu=$detail;}
   $cpu=$this->run('/usr/sbin/sysctl -n machdep.cpu.brand_string',2)?:$this->run('/usr/sbin/sysctl -n hw.model',2)?:php_uname('m').' · '.php_uname('s');
  } elseif($os==='Linux') {
   $memory=@file_get_contents('/proc/meminfo');
   if($memory&&preg_match('/^MemTotal:\\s+(\\d+)/m',$memory,$match))$ram=(int)$match[1]*1024;
   $gpu=$this->run('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader')?:($this->run('lspci | grep -Ei "VGA|3D controller|Display controller" | head -3')?:'CPU inference / GPU not detected');
   $cpu=$this->run('lscpu | grep -m1 "Model name" | cut -d: -f2')?:php_uname('m');
  } elseif($os==='Windows') {
   $ps='[Console]::OutputEncoding=[System.Text.Encoding]::UTF8; $c=Get-CimInstance Win32_ComputerSystem; $p=Get-CimInstance Win32_Processor | Select-Object -First 1; $g=Get-CimInstance Win32_VideoController | Select-Object -First 3 Name,AdapterRAM; [pscustomobject]@{RamBytes=[int64]$c.TotalPhysicalMemory;Cpu=[string]$p.Name;Gpu=(@($g | ForEach-Object { if ($_.Name) { if ($_.AdapterRAM) { $_.Name + " (" + [math]::Round($_.AdapterRAM/1GB,1) + " GB)" } else { $_.Name } } }) -join "; ")} | ConvertTo-Json -Compress';
   $encoded=base64_encode(mb_convert_encoding($ps,'UTF-16LE','UTF-8'));
   $raw=$this->run('powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand '.escapeshellarg($encoded),8);
   $info=json_decode($raw,true);
   if(is_array($info)){$ram=(int)($info['RamBytes']??0);$cpu=(string)($info['Cpu']??'Unknown CPU');$gpu=(string)($info['Gpu']??'GPU not detected');}
  }
  $gb=$ram>0?round($ram/1073741824,1):0;$vram=0;
  if(preg_match('/([0-9]+(?:\\.[0-9]+)?)\\s*GB/i',$gpu,$match))$vram=(float)$match[1];
  $accelerated=$vram>=8||in_array(strtolower($arch),['arm64','aarch64'],true);
  $recommended=$gb>=24&&$accelerated?'qwen3:8b':($gb>=12?'qwen3:4b':($gb>=8?'qwen3:1.7b':'qwen3:0.6b'));
  $result=['os'=>$os,'architecture'=>$arch,'cpu'=>$cpu,'ram_bytes'=>$ram,'ram_gb'=>$gb,'gpu'=>$gpu,'recommended'=>$recommended,'models'=>array_values(array_filter($this->catalog,fn($model)=>$gb>0&&$model['ram']<=$gb-2))];
  $cacheDir=dirname($cacheFile);if(!is_dir($cacheDir))@mkdir($cacheDir,0700,true);
  @file_put_contents($cacheFile,json_encode(['version'=>2,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
  return $result;
 }

 private function llamaStateFile():string{return $this->storage.'/runtime/llama-server.json';}
 private function llamaState():array {
  $file=$this->llamaStateFile();if(!is_file($file))return [];
  $state=json_decode((string)file_get_contents($file),true);
  if(!is_array($state)||!isset($state['port'])||!is_numeric($state['port']))return [];
  $port=(int)$state['port'];if($port<1024||$port>65535)return [];
  if(($state['managed']??false)&&!$this->managedProcessMatches($state)){@unlink($file);return [];}
  if(!($state['managed']??false)&&!$this->port($port)){@unlink($file);return [];}
  return $state;
 }
 private function llamaApiReady(int $port):bool {
  $ch=curl_init('http://127.0.0.1:'.$port.'/v1/models');
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>2]);
  $raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
  if($raw===false||$code!==200)return false;$data=json_decode($raw,true);
  return is_array($data)&&is_array($data['data']??null);
 }
 private function externalLlamaPorts():array {
  // Include DCP's established local llama.cpp port as well as common OpenAI-compatible defaults.
  return [8080,8081,8082,8000,8090,9000,19123];
 }
 private function discoverExternalLlama():array {
  foreach($this->externalLlamaPorts() as $port)if($this->port($port)&&$this->llamaApiReady($port))return ['port'=>$port,'managed'=>false,'model'=>'','pid'=>null,'ready'=>true];
  return [];
 }
 private function freePort():int {
  $socket=@stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
  if(!$socket)throw new RuntimeException('Could not reserve a free loopback port: '.$error);
  $address=stream_socket_get_name($socket,false);fclose($socket);
  $port=(int)substr((string)$address,strrpos((string)$address,':')+1);
  if($port<1024)throw new RuntimeException('The operating system returned an invalid local port.');
  return $port;
 }
 private function managedProcessMatches(array $state):bool {
  if(!($state['managed']??false))return false;
  $pid=(int)($state['pid']??0);$port=(int)($state['port']??0);$binary=(string)($state['binary']??'');
  if($pid<1||$port<1024||$port>65535||$binary==='')return false;
  if(PHP_OS_FAMILY==='Windows') {
   $ps='$p=Get-CimInstance Win32_Process -Filter "ProcessId = '.$pid.'"; if($p){[pscustomobject]@{Path=$p.ExecutablePath;CommandLine=$p.CommandLine}|ConvertTo-Json -Compress}';
   $raw=$this->run('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps),4);
   $info=json_decode($raw,true);
   if(!is_array($info))return false;
   $actual=strtolower(str_replace('/','\\',(string)($info['Path']??'')));
   $expected=strtolower(str_replace('/','\\',$binary));
   $line=(string)($info['CommandLine']??'');
   return $actual!==''&&$actual===$expected&&preg_match('/(?:^|\\s)--port\\s+'.preg_quote((string)$port,'/').'(?:\\s|$)/',$line)===1;
  }
  $line=$this->run('ps -p '.(int)$pid.' -o command=',3);
  if($line===''||!str_contains($line,$binary))return false;
  return preg_match('/(?:^|\\s)--port\\s+'.preg_quote((string)$port,'/').'(?:\\s|$)/',$line)===1;
 }
 private function processRunning(string $name):bool {
  if(PHP_OS_FAMILY==='Windows')return $this->run('powershell.exe -NoProfile -NonInteractive -Command "Get-Process -Name '.preg_replace('/[^A-Za-z0-9_-]/','',$name).' -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Id"',3)!=='';
  return $this->run('pgrep -x '.escapeshellarg($name),3)!=='';
 }
 private function port(int $port):bool {
  $socket=@fsockopen('127.0.0.1',$port,$error,$message,.3);
  if($socket){fclose($socket);return true;}return false;
 }
 private function tailLog():string {
  $out=[];
  foreach(['local-ai-install.log','local-ai-pull.log','ollama-server.log','llama-server.log','llama-server.stdout.log','llama-server.stderr.log'] as $name) {
   $file=$this->storage.'/logs/'.$name;
   if(is_file($file)){$lines=array_slice(file($file)?:[],-8);if($lines){$out[]='--- '.$name.' ---';$out=array_merge($out,$lines);}}
  }
  return implode('',array_slice($out,-24));
 }

 public function status(bool $refreshHardware=false):array {
  $oll=$this->ollama();$llama=$this->llamaBinary();$state=$this->llamaState();
  if(!$state)$state=$this->discoverExternalLlama();
  $port=(int)($state['port']??0);$online=$port>0&&(($state['ready']??false)||$this->llamaApiReady($port));
  $url=$online?'http://127.0.0.1:'.$port.'/v1':'';
  return ['hardware'=>$this->hardware($refreshHardware),'engines'=>[
   'ollama'=>['installed'=>$this->binary('ollama')!=='','online'=>$oll['online'],'models'=>$oll['models'],'url'=>'http://127.0.0.1:11434'],
   'llama_cpp'=>['installed'=>$llama!=='','binary'=>$llama,'online'=>$online,'url'=>$url,'port'=>$port?:null,'pid'=>$state['pid']??null,'model'=>$state['model']??'','managed'=>(bool)($state['managed']??false)]
  ],'catalog'=>$this->catalog,'install_log'=>$this->tailLog()];
 }

 public function start(string $engine):array {
  if($engine!=='ollama')throw new InvalidArgumentException('Unsupported engine start action.');
  if($this->ollama()['online'])return ['ok'=>true,'already_running'=>true,'message'=>'Ollama is already running; DCP is reusing the existing service.'];
  $bin=$this->binary('ollama');if($bin==='')throw new RuntimeException('Ollama is not installed.');
  if($this->port(11434))throw new RuntimeException('Port 11434 is occupied by another service. DCP will not replace or reconfigure it.');
  $log=$this->storage.'/logs/ollama-server.log';if(!is_dir(dirname($log)))mkdir(dirname($log),0700,true);
  $managed=str_contains(str_replace('\\','/',$bin),'/dcp-env/');
  if(PHP_OS_FAMILY==='Windows') {
   $script=$this->storage.'/start-ollama.ps1';$exe=str_replace("'","''",$bin);$logPath=str_replace("'","''",$log);
   $models=dirname($this->storage).'\\models\\ollama';
   $body=<<<'PS'
$ErrorActionPreference = 'Stop'
$env:OLLAMA_HOST = '127.0.0.1:11434'
__MODEL_ENV__
$p = Start-Process -FilePath '__EXE__' -ArgumentList 'serve' -PassThru -WindowStyle Hidden -RedirectStandardOutput '__LOG__' -RedirectStandardError '__LOG__'
Write-Output $p.Id
PS;
   $modelEnv=$managed?'$env:OLLAMA_MODELS = ' . "'" . str_replace("'","''",$models) . "'\n":'';
   $body=str_replace(['__MODEL_ENV__','__EXE__','__LOG__'],[$modelEnv,$exe,$logPath],$body);
   file_put_contents($script,$body);
   $pid=(int)$this->run('powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '.escapeshellarg($script),8);
   if($pid<1)throw new RuntimeException('Windows could not start Ollama.');
  } else {
   $envPrefix=$managed?'OLLAMA_MODELS='.escapeshellarg(dirname($this->storage).'/models/ollama').' OLLAMA_HOST=127.0.0.1:11434 ':'';
   $pid=(int)$this->run('nohup env '.$envPrefix.escapeshellarg($bin).' serve > '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!',5);
   if($pid<1)throw new RuntimeException('Could not start Ollama.');
  }
  return ['ok'=>true,'started'=>true,'pid'=>$pid,'message'=>'Ollama start requested.'];
 }

 public function stop(string $engine):array {
  if($engine!=='llama.cpp')throw new InvalidArgumentException('Only a DCP-managed llama.cpp process can be stopped here.');
  $state=$this->llamaState();
  if(!$state)return ['ok'=>true,'stopped'=>false,'message'=>'No DCP-managed llama.cpp process is running.'];
  if(!($state['managed']??false))throw new RuntimeException('This is an external llama.cpp service. DCP will not stop a process it does not own.');
  if(!$this->managedProcessMatches($state)) {
   @unlink($this->llamaStateFile());
   return ['ok'=>true,'stopped'=>false,'message'=>'The saved process identity no longer matches this DCP-managed server; no process was stopped.'];
  }
  $pid=(int)($state['pid']??0);
  if($pid>0) {
   if(PHP_OS_FAMILY==='Windows')$this->run('powershell.exe -NoProfile -NonInteractive -Command "Stop-Process -Id '.$pid.' -Force -ErrorAction SilentlyContinue"',4);
   else $this->run('kill '.(int)$pid.' 2>/dev/null || true',3);
  }
  @unlink($this->llamaStateFile());
  return ['ok'=>true,'stopped'=>true,'message'=>'DCP-managed llama.cpp server stopped.'];
 }

 public function install(string $engine):array {
  if(!in_array($engine,['ollama','llama.cpp'],true))throw new InvalidArgumentException('Unsupported local AI engine.');
  $logs=$this->storage.'/logs';if(!is_dir($logs)&&!mkdir($logs,0700,true)&&!is_dir($logs))throw new RuntimeException('Cannot create local AI log directory.');
  $log=$logs.'/local-ai-install.log';$os=PHP_OS_FAMILY;
  if($engine==='llama.cpp'&&$this->llamaBinary()!=='')return ['ok'=>true,'installed'=>true,'message'=>'llama.cpp is already installed: '.$this->llamaBinary()];
  if($engine==='ollama'&&$this->ollama()['online'])return ['ok'=>true,'installed'=>true,'reused_existing'=>true,'message'=>'A healthy Ollama service is already running; DCP will reuse it.'];
  if($engine==='ollama'&&$this->binary('ollama')!=='')return ['ok'=>true,'installed'=>true,'message'=>'Ollama is already installed.'];
  if($os==='Darwin') {
   $folder=$engine==='llama.cpp'?'llama.cpp':'ollama';
   $script=$this->root.'/dcp-env/engines/'.$folder.'/install-macos.sh';
   if(!is_file($script))throw new RuntimeException('DCP managed installer is missing: '.$script);
   $pid=(int)$this->run('nohup /bin/bash '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!',5);
   if($pid<1)throw new RuntimeException('Could not start the DCP-managed runtime installer.');
  } elseif($os==='Windows') {
   $folder=$engine==='llama.cpp'?'llama.cpp':'ollama';
   $script=$this->root.'\\dcp-env\\engines\\'.$folder.'\\install-windows.ps1';
   if(!is_file($script))throw new RuntimeException('DCP managed installer is missing: '.$script);
   $launcher=$this->storage.'/launch-local-ai-install.ps1';$scriptEsc=str_replace("'","''",$script);$logEsc=str_replace("'","''",$log);
   $body=<<<'PS'
$ErrorActionPreference = 'Stop'
Start-Transcript -Path '__LOG__' -Append
try { & '__SCRIPT__' } finally { Stop-Transcript }
PS;
   $body=str_replace(['__LOG__','__SCRIPT__'],[$logEsc,$scriptEsc],$body)."\n";
   file_put_contents($launcher,$body);
   pclose(popen('start \"\" powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($launcher),'r'));
  } elseif($os==='Linux') {
   $folder=$engine==='llama.cpp'?'llama.cpp':'ollama';
   $script=$this->root.'/dcp-env/engines/'.$folder.'/install-linux.sh';
   if(!is_file($script))throw new RuntimeException('DCP Linux runtime installer is missing: '.$script);
   if($engine==='llama.cpp') {
    // Execute from the application tree: the installer derives its platform root
    // relative to its own location and writes the managed engine beside the app.
    $launch='nohup /bin/bash '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!';
   } else {
    if($this->run('id -u')==='0')$prefix='';
    elseif($this->run('sudo -n true >/dev/null 2>&1; echo $? ',3)==='0')$prefix='sudo -n ';
    else {$pkexec=$this->binary('pkexec');if($pkexec==='')throw new RuntimeException('Ollama installation requires administrator approval. Run the DCP installer from a terminal with sudo access.');$prefix=escapeshellarg($pkexec).' ';}
    $launch='nohup '.$prefix.'/bin/bash '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!';
   }
   $pid=(int)$this->run($launch,5);
   if($pid<1)throw new RuntimeException('Could not launch the Linux runtime installer. Check Engine activity logs.');
  } else throw new RuntimeException('Unsupported operating system: '.$os);
  return ['ok'=>true,'started'=>true,'message'=>'Installer launched. Progress and any errors will appear in Engine activity.'];
 }

 public function startLlama(string $model):array {
  $profiles=['qwen3:1.7b'=>'tensorblock/Qwen_Qwen3-1.7B-GGUF:Q4_K_M','qwen3:4b'=>'Qwen/Qwen3-4B-GGUF:Q4_K_M','llama3.2:3b'=>'bartowski/Llama-3.2-3B-Instruct-GGUF:Q4_K_M'];
  if(!isset($profiles[$model]))throw new InvalidArgumentException('This model is not enabled for llama.cpp yet.');
  $state=$this->llamaState();
  if($state&&((($state['managed']??false)===true)||$this->llamaApiReady((int)$state['port'])))return ['ok'=>true,'already_running'=>true,'message'=>'The existing DCP llama.cpp server is already running.','model'=>$state['model']??$model,'base_url'=>'http://127.0.0.1:'.(int)$state['port'].'/v1','port'=>(int)$state['port']];
  $external=$this->discoverExternalLlama();
  if($external)return ['ok'=>true,'reused_existing'=>true,'message'=>'Reusing the existing llama.cpp server without changing its process or settings.','model'=>$model,'base_url'=>'http://127.0.0.1:'.(int)$external['port'].'/v1','port'=>(int)$external['port']];
  $bin=$this->llamaBinary();if($bin==='')throw new RuntimeException('llama-server is not installed or could not be detected.');
  $port=$this->freePort();$os=PHP_OS_FAMILY;$log=$this->storage.'/logs/llama-server.log';if(!is_dir(dirname($log)))mkdir(dirname($log),0700,true);
  $args=['-hf',$profiles[$model],'-c','8192','--host','127.0.0.1','--port',(string)$port];
  if($os==='Windows') {
   $ps=$this->storage.'/start-llama-server.ps1';$exe=str_replace("'","''",$bin);$out=str_replace("'","''",$this->storage.'/logs/llama-server.stdout.log');$err=str_replace("'","''",$this->storage.'/logs/llama-server.stderr.log');
   $quoted=implode(',',array_map(static fn($value)=>"'".str_replace("'","''",$value)."'", $args));
   $body=<<<'PS'
$ErrorActionPreference = 'Stop'
$p = Start-Process -FilePath '__EXE__' -ArgumentList @(__ARGS__) -WindowStyle Hidden -PassThru -RedirectStandardOutput '__OUT__' -RedirectStandardError '__ERR__'
Write-Output $p.Id
PS;
   $body=str_replace(['__EXE__','__ARGS__','__OUT__','__ERR__'],[$exe,$quoted,$out,$err],$body)."\n";
   file_put_contents($ps,$body);
   $pid=(int)$this->run('powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '.escapeshellarg($ps),8);
   if($pid<1)throw new RuntimeException('Windows could not start llama-server. Check the llama.cpp runtime log.');
  } else {
   $command=escapeshellarg($bin);foreach($args as $arg)$command.=' '.escapeshellarg($arg);
   $pid=(int)$this->run('nohup '.$command.' > '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!',5);
   if($pid<1)throw new RuntimeException('Could not launch llama-server. Check the DCP Engine activity log.');
  }
  $runtime=dirname($this->llamaStateFile());if(!is_dir($runtime))mkdir($runtime,0700,true);
  $state=['port'=>$port,'pid'=>$pid,'binary'=>$bin,'model'=>$model,'managed'=>true,'started_at'=>date(DATE_ATOM)];
  file_put_contents($this->llamaStateFile(),json_encode($state,JSON_UNESCAPED_SLASHES),LOCK_EX);
  return ['ok'=>true,'started'=>true,'message'=>'llama.cpp is starting on an available loopback port.','model'=>$model,'base_url'=>'http://127.0.0.1:'.$port.'/v1','port'=>$port,'pid'=>$pid];
 }

 public function pull(string $model):array {
  $allowed=array_column($this->catalog,'id');
  if(!in_array($model,$allowed,true))throw new InvalidArgumentException('Model is not in the approved free model catalogue.');
  if($this->binary('ollama')==='')throw new RuntimeException('Install Ollama first.');
  foreach($this->ollama()['models'] as $existing)if(($existing['id']??'')===$model||($existing['id']??'')===$model.':latest')return ['ok'=>true,'already_installed'=>true,'message'=>'This model is already downloaded.','model'=>$model];
  if(!$this->ollama()['online'])throw new RuntimeException('Ollama is installed but not running. Start Ollama before downloading a model.');
  $log=$this->storage.'/logs/local-ai-pull.log';if(!is_dir(dirname($log)))mkdir(dirname($log),0700,true);$bin=$this->binary('ollama');
  if(PHP_OS_FAMILY==='Windows') {
   $ps=$this->storage.'/local-ai-pull.ps1';$exe=str_replace("'","''",$bin);$name=str_replace("'","''",$model);$out=str_replace("'","''",$log);
   $body=<<<'PS'
$p = Start-Process -FilePath '__EXE__' -ArgumentList @('pull','__MODEL__') -PassThru -WindowStyle Hidden -RedirectStandardOutput '__OUT__' -RedirectStandardError '__OUT__'
Write-Output $p.Id
PS;
   file_put_contents($ps,str_replace(['__EXE__','__MODEL__','__OUT__'],[$exe,$name,$out],$body)."\n");
   $this->run('powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File '.escapeshellarg($ps),5);
  } else $this->run('nohup '.escapeshellarg($bin).' pull '.escapeshellarg($model).' > '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!',5);
  return ['ok'=>true,'message'=>'Model download started.','model'=>$model];
 }
}
