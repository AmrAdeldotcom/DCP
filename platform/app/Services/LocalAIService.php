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
 private function binary(string $name):string{$which=$this->run('command -v '.escapeshellarg($name));if($which!=='')return $which;if(PHP_OS_FAMILY==='Windows'){$v=$this->run('where.exe '.escapeshellarg($name));if($v!=='')return strtok($v,"\n")?:'';$local=getenv('LOCALAPPDATA')?:'';$candidates=[$local.'\\Microsoft\\WinGet\\Links\\'.$name,$local.'\\Programs\\Ollama\\'.$name,$local.'\\Ollama\\'.$name];foreach($candidates as $candidate)if(is_file($candidate))return $candidate;}return '';}
 private function llamaBinary():string{$names=PHP_OS_FAMILY==='Windows'?['llama-server.exe','llama-server']:['llama-server'];$bin='';foreach($names as $name){$bin=$this->binary($name);if($bin!=='')break;}if($bin===''){ $local=(PHP_OS_FAMILY==='Windows'?(getenv('LOCALAPPDATA')?:'').'\\Microsoft\\WinGet\\Links\\llama-server.exe':(getenv('HOME')?:'').'/.local/bin/llama-server');if(is_executable($local))$bin=$local;}return $bin;}
 private function ollama():array{$ch=curl_init('http://127.0.0.1:11434/api/tags');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>3]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$data=$raw!==false&&$code===200?json_decode($raw,true):[];return ['online'=>is_array($data),'models'=>array_map(fn($m)=>['id'=>$m['name'],'size'=>$m['size']??0],$data['models']??[])];}
 private function hardware():array{
  $os=PHP_OS_FAMILY;$arch=php_uname('m');$ram=0;$gpu='Not detected';$cpu=php_uname('v');
  if($os==='Darwin'){
   $ram=(int)$this->run('/usr/sbin/sysctl -n hw.memsize');
   $gpu=$this->run('/usr/sbin/system_profiler SPDisplaysDataType | /usr/bin/grep -E "Chipset Model|VRAM \(Total\)" | /usr/bin/head -4')?:'Integrated / CPU inference';
   $cpu=php_uname('m').' · '.php_uname('s');
  }elseif($os==='Linux'){
   $m=@file_get_contents('/proc/meminfo');if($m&&preg_match('/^MemTotal:\s+(\d+)/m',$m,$x))$ram=(int)$x[1]*1024;
   $gpu=$this->run('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader')?:($this->run('lspci | grep -Ei "VGA|3D controller|Display controller" | head -3')?:'CPU inference / GPU not detected');
   $cpu=$this->run('lscpu | grep -m1 "Model name" | cut -d: -f2')?:php_uname('m');
  }elseif($os==='Windows'){
   $ps='[Console]::OutputEncoding=[System.Text.Encoding]::UTF8; $c=Get-CimInstance Win32_ComputerSystem; $p=Get-CimInstance Win32_Processor | Select-Object -First 1; $g=Get-CimInstance Win32_VideoController | Select-Object -First 3 Name,AdapterRAM; [pscustomobject]@{RamBytes=[int64]$c.TotalPhysicalMemory;Cpu=[string]$p.Name;Gpu=(@($g | ForEach-Object { if ($_.Name) { if ($_.AdapterRAM) { $_.Name + " (" + [math]::Round($_.AdapterRAM/1GB,1) + " GB)" } else { $_.Name } } }) -join "; ")} | ConvertTo-Json -Compress';
   $encoded=base64_encode(mb_convert_encoding($ps,'UTF-16LE','UTF-8'));
   $raw=$this->run('powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -EncodedCommand '.escapeshellarg($encoded));
   $info=json_decode($raw,true);if(is_array($info)){$ram=(int)($info['RamBytes']??0);$cpu=(string)($info['Cpu']??'Unknown CPU');$gpu=(string)($info['Gpu']??'GPU not detected');}
  }
  $gb=$ram>0?round($ram/1073741824,1):0;$vram=0;if(preg_match('/([0-9]+(?:\.[0-9]+)?)\s*GB/i',$gpu,$vm))$vram=(float)$vm[1];$accelerated=$vram>=8||in_array(strtolower($arch),['arm64','aarch64'],true);$recommended=$gb>=24&&$accelerated?'qwen3:8b':($gb>=12?'qwen3:4b':($gb>=8?'qwen3:1.7b':'qwen3:0.6b'));
  return ['os'=>$os,'architecture'=>$arch,'cpu'=>$cpu,'ram_bytes'=>$ram,'ram_gb'=>$gb,'gpu'=>$gpu,'recommended'=>$recommended,'models'=>array_values(array_filter($this->catalog,fn($m)=>$gb>0&&$m['ram']<=$gb-2))];
 }

 public function status():array{
  $oll=$this->ollama();$llama=$this->llamaBinary();
  return ['hardware'=>$this->hardware(),'engines'=>['ollama'=>['installed'=>$this->binary('ollama')!=='','online'=>$oll['online'],'models'=>$oll['models'],'url'=>'http://127.0.0.1:11434'],'llama_cpp'=>['installed'=>$llama!=='','binary'=>$llama,'online'=>$this->processRunning('llama-server')&&$this->port(8080),'url'=>'http://127.0.0.1:8080/v1']],'catalog'=>$this->catalog,'install_log'=>$this->tailLog()];
 }
 private function processRunning(string $name):bool{if(PHP_OS_FAMILY==='Windows')return $this->run('powershell -NoProfile -Command "Get-Process '.escapeshellarg($name).' -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty Id"')!=='';return $this->run('pgrep -x '.escapeshellarg($name))!=='';}
 private function port(int $port):bool{$s=@fsockopen('127.0.0.1',$port,$e,$m,.3);if($s){fclose($s);return true;}return false;}
 private function tailLog():string{$out=[];foreach(['local-ai-install.log','local-ai-pull.log','llama-server.log','llama-server.stdout.log','llama-server.stderr.log'] as $name){$f=$this->storage.'/logs/'.$name;if(is_file($f)){$lines=array_slice(file($f)?:[],-8);if($lines){$out[]='--- '.$name.' ---';$out=array_merge($out,$lines);}}}return implode('',array_slice($out,-24));}
 public function start(string $engine):array{if($engine==='ollama'){if($this->binary('ollama')==='')throw new RuntimeException('Ollama is not installed.');$os=PHP_OS_FAMILY;if($os==='Darwin')exec('nohup ollama serve >/dev/null 2>&1 < /dev/null &');elseif($os==='Windows')pclose(popen('start /B ollama serve','r'));else exec('nohup ollama serve >/dev/null 2>&1 < /dev/null &');return ['ok'=>true,'message'=>'Ollama start requested.'];}throw new InvalidArgumentException('Unsupported engine start action.');}
 public function install(string $engine):array{
  if(!in_array($engine,['ollama','llama.cpp'],true))throw new InvalidArgumentException('Unsupported local AI engine.');
  $logs=$this->storage.'/logs';if(!is_dir($logs)&&!mkdir($logs,0700,true)&&!is_dir($logs))throw new RuntimeException('Cannot create local AI log directory.');
  $log=$logs.'/local-ai-install.log';$os=PHP_OS_FAMILY;
  if($engine==='llama.cpp' && $this->llamaBinary()!=='')return ['ok'=>true,'installed'=>true,'message'=>'llama.cpp is already installed: '.$this->llamaBinary()];
  if($engine==='ollama' && $this->binary('ollama')!=='')return ['ok'=>true,'installed'=>true,'message'=>'Ollama is already installed.'];
  if($os==='Darwin'){
   $brew=$this->binary('brew')?:'/usr/local/bin/brew';
   if(!is_executable($brew))throw new RuntimeException('Homebrew is required. Install it from https://brew.sh, then retry.');
   $script=$this->storage.'/local-ai-install.sh';
   $body="#!/bin/bash\nset -Eeuo pipefail\nexport PATH=/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin\nexec >> ".escapeshellarg($log)." 2>&1\necho \"[$(date)] Installing ".($engine==='llama.cpp'?'llama.cpp':'Ollama')."\"\n";
   if($engine==='llama.cpp')$body.='"'.addslashes($brew).'" install llama.cpp'."\n".'BIN="$("'.addslashes($brew).'" --prefix llama.cpp)/bin/llama-server"'."\n".'test -x "$BIN"'."\n".'echo "Installed llama-server: $BIN"'."\n";
   else $body.='"'.addslashes($brew).'" install --cask ollama'."\n".'open -a Ollama'."\n";
   $body.="echo \"[$(date)] Installation completed successfully\"\n";
   file_put_contents($script,$body);chmod($script,0700);
   $launch='nohup /bin/bash '.escapeshellarg($script).' >/dev/null 2>&1 < /dev/null & echo $!';
   $pid=$this->run($launch);if($pid==='')throw new RuntimeException('Could not start the installer. Check write permissions for DCP storage.');
  }elseif($os==='Linux'){
   $script=$this->root.'/platform/scripts/install-llama-cpp-linux.sh';
   if($engine==='ollama')$body='#!/usr/bin/env bash'."\nset -Eeuo pipefail\nexport PATH=/usr/local/bin:/usr/bin:/bin:$HOME/.local/bin\ncurl -fsSL https://ollama.com/install.sh | sh\n";
   else $body=is_file($script)?file_get_contents($script):'';
   if(!$body)throw new RuntimeException('Linux llama.cpp installer script is missing.');
   $target=$this->storage.'/local-ai-install.sh';file_put_contents($target,$body);chmod($target,0700);
   $sudo=$this->binary('sudo');$pkexec=$this->binary('pkexec');
   if($this->run('id -u')==='0')$launch='nohup /bin/bash '.escapeshellarg($target).' >> '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!';
   elseif($pkexec!=='')$launch='nohup pkexec /bin/bash '.escapeshellarg($target).' >> '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!';
   else throw new RuntimeException('Linux installation needs administrator approval. Install from a terminal using: '.$target);
   $this->run($launch);
  }elseif($os==='Windows'){
   $script=$this->storage.'/local-ai-install.ps1';
   $body='$ErrorActionPreference = "Stop"'."\n";
   if($engine==='llama.cpp')$body.="winget install --exact --id ggml.llamacpp --silent --accept-package-agreements --accept-source-agreements\nif (\$LASTEXITCODE -ne 0) { throw 'WinGet could not install ggml.llamacpp. Update App Installer or install WinGet, then retry.' }\nif (-not (Get-Command llama-server.exe -ErrorAction SilentlyContinue) -and -not (Test-Path (Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Links\llama-server.exe'))) { throw 'llama.cpp installation completed but llama-server.exe was not found. Check Engine activity and retry after updating PATH.' }\n";
   else $body.='irm https://ollama.com/install.ps1 | iex'."\n";
   file_put_contents($script,$body);
   $launcher=$this->storage.'/launch-local-ai-install.ps1';
   $logPath=str_replace(chr(39),"''",$log);$scriptPath=str_replace(chr(39),"''",$script);
   file_put_contents($launcher,"\$ErrorActionPreference = 'Stop'\nStart-Transcript -Path '$logPath' -Append\ntry { & '$scriptPath'; if (\$LASTEXITCODE -ne 0) { throw 'Installer process failed.' } } finally { Stop-Transcript }\n");
   pclose(popen('start /B powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($launcher),'r'));
  }else throw new RuntimeException('Unsupported operating system: '.$os);
  return ['ok'=>true,'started'=>true,'message'=>'Installer launched. Progress and any errors will appear in Engine activity.'];
 }

 public function startLlama(string $model):array{
  $profiles=['qwen3:1.7b'=>'tensorblock/Qwen_Qwen3-1.7B-GGUF:Q4_K_M','qwen3:4b'=>'Qwen/Qwen3-4B-GGUF:Q4_K_M','llama3.2:3b'=>'bartowski/Llama-3.2-3B-Instruct-GGUF:Q4_K_M'];
  if(!isset($profiles[$model]))throw new InvalidArgumentException('This model is not enabled for llama.cpp yet.');
  $bin=$this->llamaBinary();if($bin==='')throw new RuntimeException('Install llama.cpp first.');
  $os=PHP_OS_FAMILY;$log=$this->storage.'/logs/llama-server.log';if(!is_dir(dirname($log)))mkdir(dirname($log),0700,true);
  $args=' -hf '.escapeshellarg($profiles[$model]).' -c 8192 --host 127.0.0.1 --port 8080';
  if($os==='Windows'){$ps=$this->storage.'/start-llama-server.ps1';$exe=str_replace(chr(39),"''",$bin);$arg=str_replace(chr(39),"''",$args);$stdoutPath=str_replace(chr(39),"''",$this->storage.'/logs/llama-server.stdout.log');$stderrPath=str_replace(chr(39),"''",$this->storage.'/logs/llama-server.stderr.log');file_put_contents($ps,"Start-Process -FilePath '$exe' -ArgumentList '$arg' -WindowStyle Hidden -RedirectStandardOutput '$stdoutPath' -RedirectStandardError '$stderrPath'\n");pclose(popen('start /B powershell.exe -NoProfile -ExecutionPolicy Bypass -File '.escapeshellarg($ps),'r'));}
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
