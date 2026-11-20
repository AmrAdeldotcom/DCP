<?php
declare(strict_types=1);

final class LocalAIService {
 private string $root; private string $storage;
 private array $catalog=[
  ['id'=>'qwen3:0.6b','name'=>'Qwen3 0.6B','ram'=>4,'download'=>'~0.5 GB','tier'=>'Starter','note'=>'Very light; basic chat and quick tasks'],
  ['id'=>'qwen3:1.7b','name'=>'Qwen3 1.7B','ram'=>8,'download'=>'~1.4 GB','tier'=>'Light','note'=>'Good balance for low-memory devices'],
  ['id'=>'qwen3:4b','name'=>'Qwen3 4B','ram'=>12,'download'=>'~2.5 GB','tier'=>'Balanced','note'=>'Recommended everyday assistant'],
  ['id'=>'qwen3:8b','name'=>'Qwen3 8B','ram'=>20,'download'=>'~5.2 GB','tier'=>'Powerful','note'=>'Better reasoning; needs more memory'],
  ['id'=>'gemma3:1b','name'=>'Gemma 3 1B','ram'=>6,'download'=>'~0.8 GB','tier'=>'Starter','note'=>'Small general-purpose model'],
  ['id'=>'gemma3:4b','name'=>'Gemma 3 4B','ram'=>12,'download'=>'~3.4 GB','tier'=>'Balanced','note'=>'Vision-capable model; check device memory'],
  ['id'=>'llama3.2:3b','name'=>'Llama 3.2 3B','ram'=>10,'download'=>'~2.0 GB','tier'=>'Light','note'=>'Compact general-purpose chat']
 ];
 public function __construct(array $config){$this->root=dirname(__DIR__,2);$this->storage=$config['storage'];}
 private function run(string $cmd):string{$out=[];$code=0;exec($cmd.(PHP_OS_FAMILY==='Windows'?' 2>NUL':' 2>/dev/null'),$out,$code);return $code===0?trim(implode("\n",$out)):'';}
 private function binary(string $name):string{$which=$this->run('command -v '.escapeshellarg($name));if($which!=='')return $which;if(PHP_OS_FAMILY==='Windows'){$v=$this->run('where '.escapeshellarg($name));return strtok($v,"\n")?:'';}return '';}
 private function llamaBinary():string{$bin=$this->binary('llama-server');if($bin===''){ $local=(getenv('HOME')?:'').'/.local/bin/llama-server';if(is_executable($local))$bin=$local;}return $bin;}
 private function ollama():array{$ch=curl_init('http://127.0.0.1:11434/api/tags');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>3]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$data=$raw!==false&&$code===200?json_decode($raw,true):[];return ['online'=>is_array($data),'models'=>array_map(fn($m)=>['id'=>$m['name'],'size'=>$m['size']??0],$data['models']??[])];}
 private function hardware():array{
  $os=PHP_OS_FAMILY;$arch=php_uname('m');$ram=0;$gpu='Not detected';
  if($os==='Darwin'){$ram=(int)$this->run('/usr/sbin/sysctl -n hw.memsize');$gpu=$this->run('/usr/sbin/system_profiler SPDisplaysDataType | /usr/bin/grep -E "Chipset Model|VRAM \\(Total\\)" | /usr/bin/head -4')?:'Integrated / CPU inference';}
  elseif($os==='Linux'){$m=@file_get_contents('/proc/meminfo');if($m&&preg_match('/MemTotal:\\s+(\\d+)/',$m,$x))$ram=(int)$x[1]*1024;$gpu=$this->run('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader')?:'CPU / Vulkan GPU not detected';}
  elseif($os==='Windows'){$ram=(int)$this->run('powershell -NoProfile -Command "(Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory"');$gpu=$this->run('powershell -NoProfile -Command "(Get-CimInstance Win32_VideoController | Select-Object -First 2 -ExpandProperty Name) -join \", \""')?:'Not detected';}
  $gb=$ram>0?round($ram/1073741824,1):0;$vram=0;if(preg_match('/([0-9]+(?:\\.[0-9]+)?)\\s*GB/i',$gpu,$vm))$vram=(float)$vm[1];$accelerated=$vram>=8||in_array(strtolower($arch),['arm64','aarch64'],true);$recommended=$gb>=24&&$accelerated?'qwen3:8b':($gb>=12?'qwen3:4b':($gb>=8?'qwen3:1.7b':'qwen3:0.6b'));
  return ['os'=>$os,'architecture'=>$arch,'ram_bytes'=>$ram,'ram_gb'=>$gb,'gpu'=>$gpu,'recommended'=>$recommended,'models'=>array_values(array_filter($this->catalog,fn($m)=>$gb>0&&$m['ram']<=$gb-2))];
 }
 public function status():array{
  $oll=$this->ollama();$llama=$this->llamaBinary();
  return ['hardware'=>$this->hardware(),'engines'=>['ollama'=>['installed'=>$this->binary('ollama')!=='','online'=>$oll['online'],'models'=>$oll['models'],'url'=>'http://127.0.0.1:11434'],'llama_cpp'=>['installed'=>$llama!=='','binary'=>$llama,'online'=>$this->processRunning('llama-server')&&$this->port(8080),'url'=>'http://127.0.0.1:8080/v1']],'catalog'=>$this->catalog,'install_log'=>$this->tailLog()];
 }
 private function processRunning(string $name):bool{if(PHP_OS_FAMILY==='Windows')return $this->run('powershell -NoProfile -Command "Get-Process '.escapeshellarg($name).' -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Id"')!=='';return $this->run('pgrep -x '.escapeshellarg($name))!=='';}
 private function port(int $port):bool{$s=@fsockopen('127.0.0.1',$port,$e,$m,.3);if($s){fclose($s);return true;}return false;}
 private function tailLog():string{$out=[];foreach(['local-ai-install.log','local-ai-pull.log','llama-server.log'] as $name){$f=$this->storage.'/logs/'.$name;if(is_file($f)){$lines=array_slice(file($f)?:[],-8);if($lines){$out[]='--- '.$name.' ---';$out=array_merge($out,$lines);}}}return implode('',array_slice($out,-24));}
 public function start(string $engine):array{if($engine==='ollama'){if($this->binary('ollama')==='')throw new RuntimeException('Ollama is not installed.');$os=PHP_OS_FAMILY;if($os==='Darwin')exec('nohup ollama serve >/dev/null 2>&1 < /dev/null &');elseif($os==='Windows')pclose(popen('start /B ollama serve','r'));else exec('nohup ollama serve >/dev/null 2>&1 < /dev/null &');return ['ok'=>true,'message'=>'Ollama start requested.'];}throw new InvalidArgumentException('Unsupported engine start action.');}
 public function install(string $engine):array{
  if(!in_array($engine,['ollama','llama.cpp'],true))throw new InvalidArgumentException('Unsupported local AI engine.');
  if(!is_dir($this->storage.'/logs'))mkdir($this->storage.'/logs',0700,true);
  $log=$this->storage.'/logs/local-ai-install.log';$os=PHP_OS_FAMILY;
  if($engine==='ollama'){
   if($this->binary('ollama')!=='')return ['ok'=>true,'message'=>'Ollama is already installed.'];
   if($os==='Darwin')$cmd='brew install --cask ollama && open -a Ollama';
   elseif($os==='Linux')$cmd='curl -fsSL https://ollama.com/install.sh | sh';
   elseif($os==='Windows')$cmd='powershell -NoProfile -ExecutionPolicy Bypass -Command "irm https://ollama.com/install.ps1 | iex"';
   else throw new RuntimeException('Unsupported operating system.');
  }else{
   if($this->llamaBinary()!=='')return ['ok'=>true,'message'=>'llama.cpp is already installed.'];
   if($os==='Darwin')$cmd='brew install llama.cpp';
   elseif($os==='Linux')$cmd='if command -v x-terminal-emulator >/dev/null 2>&1; then x-terminal-emulator -e bash '.escapeshellarg($this->root.'/scripts/install-llama-cpp-linux.sh').'; elif command -v gnome-terminal >/dev/null 2>&1; then gnome-terminal -- bash '.escapeshellarg($this->root.'/scripts/install-llama-cpp-linux.sh').'; elif command -v konsole >/dev/null 2>&1; then konsole -e bash '.escapeshellarg($this->root.'/scripts/install-llama-cpp-linux.sh').'; else echo "No terminal emulator found. Open a terminal and run: '.escapeshellarg($this->root.'/scripts/install-llama-cpp-linux.sh').'"; fi';
   elseif($os==='Windows')$cmd='winget install llama.cpp --accept-package-agreements --accept-source-agreements';
   else throw new RuntimeException('Unsupported operating system.');
  }
  $script=$this->storage.'/local-ai-install.'.($os==='Windows'?'ps1':'sh');
  if($os==='Windows')file_put_contents($script,chr(36).'ErrorActionPreference = "Stop"'."\n".$cmd."\n");
  if($os==='Windows')$launch='powershell -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1';
  else{$launch='nohup /bin/bash '.escapeshellarg($script).' >> '.escapeshellarg($log).' 2>&1 < /dev/null &';}
  if($os==='Windows')pclose(popen('start /B '.$launch,'r'));else exec($launch);
  return ['ok'=>true,'message'=>'Installation started. Keep DCP open and refresh engine status.'];
 }

 public function startLlama(string $model):array{
  $profiles=['qwen3:1.7b'=>'tensorblock/Qwen_Qwen3-1.7B-GGUF:Q4_K_M','qwen3:4b'=>'Qwen/Qwen3-4B-GGUF:Q4_K_M','llama3.2:3b'=>'bartowski/Llama-3.2-3B-Instruct-GGUF:Q4_K_M'];
  if(!isset($profiles[$model]))throw new InvalidArgumentException('This model is not enabled for llama.cpp yet.');
  $bin=$this->llamaBinary();if($bin==='')throw new RuntimeException('Install llama.cpp first.');
  $os=PHP_OS_FAMILY;$log=$this->storage.'/logs/llama-server.log';if(!is_dir(dirname($log)))mkdir(dirname($log),0700,true);
  $args=' -hf '.escapeshellarg($profiles[$model]).' -c 8192 --host 127.0.0.1 --port 8080';
  if($os==='Windows'){$ps=$this->storage.'/start-llama-server.ps1';file_put_contents($ps,'Start-Process -FilePath '.(chr(39).$bin.chr(39)).' -ArgumentList '.(chr(39).$args.chr(39)).' -WindowStyle Hidden');pclose(popen('start /B powershell -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($ps),'r'));}
  else exec('nohup '.escapeshellarg($bin).$args.' > '.escapeshellarg($log).' 2>&1 < /dev/null &');
  return ['ok'=>true,'message'=>'llama.cpp server starting; first model load downloads the selected GGUF from Hugging Face.','model'=>$model,'base_url'=>'http://127.0.0.1:8080/v1'];
 }
 public function pull(string $model):array{
  $allowed=array_column($this->catalog,'id');if(!in_array($model,$allowed,true))throw new InvalidArgumentException('Model is not in the approved free model catalogue.');
  if($this->binary('ollama')==='')throw new RuntimeException('Install Ollama first.');
  $os=PHP_OS_FAMILY;$log=$this->storage.'/logs/local-ai-pull.log';if(!is_dir(dirname($log)))mkdir(dirname($log),0700,true);
  if($os==='Windows'){$cmd='Start-Process -FilePath "ollama" -ArgumentList @("pull",'.("'".$model."'").') -WindowStyle Hidden';$ps=$this->storage.'/local-ai-pull.ps1';file_put_contents($ps,$cmd);pclose(popen('start /B powershell -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($ps).' > '.escapeshellarg($log).' 2>&1','r'));}
  else exec('nohup '.escapeshellarg($this->binary('ollama')).' pull '.escapeshellarg($model).' > '.escapeshellarg($log).' 2>&1 < /dev/null &');
  return ['ok'=>true,'message'=>'Model download started.','model'=>$model];
 }
}
