<?php
declare(strict_types=1);
final class SystemAgentRuntime {
 private static function home():string{return PHP_OS_FAMILY==='Windows'?(getenv('USERPROFILE')?:sys_get_temp_dir()):(getenv('HOME')?:sys_get_temp_dir());}
 private static function node():string {
  $home=self::home();$local=getenv('LOCALAPPDATA')?:'';
  $systemNode=glob('/usr/local/lib/nodejs/node-v24.22.3-*/bin/node')?:[];$paths=PHP_OS_FAMILY==='Windows'?['C:\\Program Files\\nodejs\\node.exe','C:\\Program Files (x86)\\nodejs\\node.exe']:array_merge($systemNode,['/usr/local/bin/node','/opt/homebrew/bin/node','/usr/bin/node','/opt/local/bin/node']);
  foreach($paths as $p)if($p!==''&&is_file($p)&&(PHP_OS_FAMILY==='Windows'||is_executable($p)))return $p;
  return '';
 }
 private static function nodeHome():string{$n=self::node();return $n!==''?dirname($n):'';}
 private static function nodeVersion():string{$n=self::node();if($n==='')return ''; $o=[];$c=1;exec(escapeshellarg($n).' --version 2>&1',$o,$c);return $c===0?trim(implode("\n",$o)):'';}
 private static function npmRoot():string {
  $home=self::nodeHome();if($home==='')return '';
  if(PHP_OS_FAMILY==='Windows'){$systemRoot=(getenv('ProgramFiles')?:'C:\\Program Files').'\\nodejs\\node_modules';if(is_dir($systemRoot))return $systemRoot;}
  $npm=$home.(PHP_OS_FAMILY==='Windows'?'\\npm.cmd':'/npm');
  if(!is_file($npm))return '';
  $o=[];$c=1;
  if(PHP_OS_FAMILY==='Windows') {
   $ps='$env:Path=' . "'" . str_replace("'","''",$home.';'.(getenv('PATH')?:'')) . "'" . '; & ' . "'" . str_replace("'","''",$npm) . "'" . ' root --global';
   $command='powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps);
  } else {
   $binPath=$home.PATH_SEPARATOR.(self::home().'/.local/bin').PATH_SEPARATOR.(getenv('PATH')?:'/usr/bin:/bin');
   $command='PATH='.escapeshellarg($binPath).' '.escapeshellarg($npm).' root --global --prefix /usr/local';
  }
  exec($command.' 2>&1',$o,$c);
  return $c===0?trim(implode("\n",$o)):'';
 }
 private static function packagePath(string $id):string {
  $rel=$id==='desktop-commander'?'@wonderwhy-er/desktop-commander':'@deepseek-ai/dsh';
  $roots=[];$npmRoot=self::npmRoot();if($npmRoot!=='')$roots[]=$npmRoot;
  $home=self::home();
  if(PHP_OS_FAMILY!=='Windows') {
   $roots[]='/usr/local/lib/node_modules';
   $roots[]='/opt/homebrew/lib/node_modules';
  }
  foreach($roots as $root) {
   $direct=$root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel);
   if(is_dir($direct))return $direct;
   if(is_dir($root)) {
    $matches=glob($root.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$rel),GLOB_ONLYDIR)?:[];
    if($matches)return $matches[0];
   }
  }
  return '';
 }
 private static function statePaths(string $id):array {
  $dir=PHP_OS_FAMILY==='Windows'?(getenv('LOCALAPPDATA')?:self::home()).'\\DCP\\agent-install':self::home().'/.cache/dcp/agent-install';
  return [$dir.DIRECTORY_SEPARATOR.$id.'.log',$dir.DIRECTORY_SEPARATOR.$id.'.state'];
 }
 private static function processRunning(string $id):bool {
  $needle=$id==='desktop-commander'?'desktop-commander':'@deepseek-ai/dsh';
  if(PHP_OS_FAMILY==='Windows'){$ps='Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like "*'.$needle.'*" } | Select-Object -First 1 -ExpandProperty ProcessId';$o=[];exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps).' 2>NUL',$o);return count($o)>0;}
  $o=[];exec('ps -axo pid=,args= 2>/dev/null',$o);foreach($o as $line)if(str_contains($line,$needle)&&!str_contains($line,'grep '))return true;return false;
 }
 public static function status(string $id):array {
  if(!in_array($id,['desktop-commander','deepseek-harness'],true))throw new InvalidArgumentException('Unsupported system agent.');
  $node=self::node();$version=self::nodeVersion();$package=self::packagePath($id);$running=self::processRunning($id);[$log,$stateFile]=self::statePaths($id);$state=[];
  if(is_file($stateFile)&&!is_link($stateFile))$state=json_decode((string)file_get_contents($stateFile),true)?:[];
  $age=time()-(strtotime((string)($state['updated_at']??''))?:0);$installing=($state['status']??'')==='running'&&$age<1800;$installed=$package!=='';
  $name=$id==='desktop-commander'?'Desktop Commander Remote':'DeepSeek Harness';
  $detail=$installing?'Installing: '.(string)($state['stage']??'Preparing prerequisites'):($running?'Process detected':($installed?'Installed and verified at '.$package:($version===''?'Node.js prerequisite missing':($version<'v24.22.3'?'Node.js '.$version.' is too old; DCP requires v24.22.3':'Not installed. Select Install to provision prerequisites and package.'))));
  $connected=false;$url=$id==='deepseek-harness'?'http://127.0.0.1:3080':'';
  if($id==='deepseek-harness'){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>2,CURLOPT_FOLLOWLOCATION=>false]);curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$connected=$code>=200&&$code<400;}
  return ['id'=>$id,'name'=>$name,'installed'=>$installed,'running'=>$running||$connected,'connected'=>$connected,'installing'=>$installing,'install_stage'=>(string)($state['stage']??''),'install_status'=>(string)($state['status']??''),'install_log'=>is_file($log)?mb_substr((string)file_get_contents($log),-4000):'','detail'=>$detail,'node'=>['path'=>$node,'version'=>$version],'path'=>$package,'url'=>$url,'capabilities'=>$id==='desktop-commander'?['remote-files','remote-process']:['agent-tasks','web-ui'],'integration'=>'local-process'];
 }
 public static function install(string $id):array {
  if(!in_array($id,['desktop-commander','deepseek-harness'],true))throw new InvalidArgumentException('Unsupported system agent.');
  $s=self::status($id);if($s['installed']&&($s['node']['version']??'')==='v24.22.3')return ['ok'=>true,'already_installed'=>true,'message'=>$s['name'].' and its system Node.js prerequisite are already installed and verified.'];if($s['installing'])return ['ok'=>true,'started'=>true,'message'=>'Installation is already running: '.$s['install_stage']];
  [$log,$state]=self::statePaths($id);$dir=dirname($log);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Cannot create agent installer log directory.');
  $platform=dirname(__DIR__,2);$script=$platform.'/installers/agents/';
  if(PHP_OS_FAMILY==='Windows') {
   $launcher=$dir.'\\install-'.$id.'.ps1';$body='$ErrorActionPreference="Stop"; & '."'".str_replace("'","''",$script.'install-agent-windows.ps1')."'".' -Agent '."'".$id."'".' -Log '."'".str_replace("'","''",$log)."'".' -State '."'".str_replace("'","''",$state)."'".' -PlatformRoot '."'".str_replace("'","''",$platform)."'";
   file_put_contents($launcher,$body);
   $args='-NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -File "'.$launcher.'"';
   $ps='$p=Start-Process -FilePath "powershell.exe" -ArgumentList '."'".str_replace("'","''",$args)."'".' -WindowStyle Hidden -PassThru; [Console]::Out.Write($p.Id)';
   $o=[];exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps),$o,$c);$pid=(int)($o[0]??0);
  } else {
   $cmd='nohup /bin/bash '.escapeshellarg($script.'install-agent-unix.sh').' '.escapeshellarg($id).' '.escapeshellarg($log).' '.escapeshellarg($state).' '.escapeshellarg($platform).' >/dev/null 2>&1 < /dev/null & echo $!';
   $o=[];exec($cmd,$o,$c);$pid=(int)($o[0]??0);
  }
  if($pid<1)throw new RuntimeException('Could not start the prerequisite and agent installation workflow.');
  return ['ok'=>true,'started'=>true,'pid'=>$pid,'message'=>'Installing Node.js prerequisite if needed, then '.$s['name'].'.'];
 }
 public static function start(string $id):array {
  $s=self::status($id);if(!$s['installed'])throw new RuntimeException('Install '.$s['name'].' first; package is not verified in the OS global package directory.');
  $node=self::node();if($node==='')throw new RuntimeException('Node.js is missing.');$home=self::home();$nodeHome=dirname($node);
  if(PHP_OS_FAMILY==='Windows') {
   $npx=$nodeHome.'\\npx.cmd';$command=$id==='desktop-commander'?'@wonderwhy-er/desktop-commander@latest remote':'@deepseek-ai/dsh@latest web --no-open';
   $ps='$env:Path='."'".str_replace("'","''",$nodeHome.';'.(getenv('PATH')?:''))."'".'; $p=Start-Process -FilePath '."'".str_replace("'","''",$npx)."'".' -ArgumentList '.("'".str_replace("'","''",$command)."'").' -WindowStyle Hidden -PassThru; [Console]::Out.Write($p.Id)';
   $o=[];exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps),$o,$c);$pid=(int)($o[0]??0);
  } else {
   $npx=$nodeHome.'/npx';$command=$id==='desktop-commander'?'@wonderwhy-er/desktop-commander@latest remote':'@deepseek-ai/dsh@latest web --no-open';
   [$log]=self::statePaths($id);$run=escapeshellarg($npx).' --yes '.$command.' >> '.escapeshellarg($log).'.run 2>&1';
   $out=[];exec('nohup /bin/bash -lc '.escapeshellarg('export PATH='.escapeshellarg($nodeHome.':'.($home.'/.local/bin').':'.(getenv('PATH')?:'')).'; '.$run). ' >/dev/null 2>&1 < /dev/null & echo $!',$out,$c);$pid=(int)($out[0]??0);
   if($id==='deepseek-harness')exec('sleep 2; (open http://127.0.0.1:3080 || xdg-open http://127.0.0.1:3080) >/dev/null 2>&1 &');
  }
  if($pid<1)throw new RuntimeException('Could not start '.$s['name'].'.');
  return ['ok'=>true,'started'=>true,'pid'=>$pid,'message'=>$s['name'].' launch requested; DCP will verify the process/service.'];
 }
 public static function stop(string $id):array {
  if(!in_array($id,['desktop-commander','deepseek-harness'],true))throw new InvalidArgumentException('Unsupported system agent.');
  $needle=$id==='desktop-commander'?'desktop-commander':'@deepseek-ai/dsh';
  if(PHP_OS_FAMILY==='Windows'){$ps='Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like "*'.$needle.'*" } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }';exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps));}
  else {$o=[];exec('ps -axo pid=,args=',$o);foreach($o as $line)if(str_contains($line,$needle)&&!str_contains($line,'grep ')&&preg_match('/^\\s*(\\d+)/',$line,$m))exec('kill -TERM '.(int)$m[1].' 2>/dev/null');}
  return ['ok'=>true,'stopped'=>true,'message'=>'Stop signal sent only to the matching '.$id.' process.'];
 }
}
