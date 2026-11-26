<?php
declare(strict_types=1);
return new class implements AgentAdapter {
 private function packagePath():string {
  $root=trim((string)shell_exec(PHP_OS_FAMILY==='Windows'?'npm.cmd root -g 2>NUL':'npm root -g 2>/dev/null'));
  return $root!==''?rtrim($root,'\\/').DIRECTORY_SEPARATOR.'@wonderwhy-er'.DIRECTORY_SEPARATOR.'desktop-commander':'';
 }
 private function processIds():array {
  $out=[];
  if(PHP_OS_FAMILY==='Windows'){
   $probe='Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match "desktop-commander.*remote" -and $_.Name -match "node|npm" } | Select-Object -ExpandProperty ProcessId';
   exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($probe).' 2>NUL',$out);
  }else{
   $lines=[];exec('ps -axo pid=,comm=,args= 2>/dev/null',$lines);
   foreach($lines as $line)if(preg_match('/^\\s*(\\d+)\\s+node(?:\\s|$)/i',$line,$m)&&str_contains($line,'desktop-commander')&&preg_match('/\\bremote\\b/i',$line))$out[]=$m[1];
  }
  return array_values(array_filter(array_map('intval',$out),static fn($pid)=>$pid>1));
 }
 public function status():array {
  $path=$this->packagePath();$installed=$path!==''&&is_dir($path);$ids=$this->processIds();$running=count($ids)>0;
  return ['id'=>'desktop-commander','name'=>'Desktop Commander Remote','installed'=>$installed||$running,'running'=>$running,'connected'=>false,'detail'=>$running?'Remote process detected; authenticated DCP task session is not yet linked.':($installed?'Installed in the current Node global prefix.':'Not installed. DCP can install it in the current Node global prefix.'),'path'=>$path,'capabilities'=>['remote-files','remote-process'],'integration'=>'process-detection-only'];
 }
 public function install():array {
  if($this->status()['installed'])return ['ok'=>true,'already_installed'=>true,'message'=>'Desktop Commander is already installed.'];
  $home=getenv('HOME')?:sys_get_temp_dir();$log=$home.'/.cache/dcp-dc-install.log';@mkdir(dirname($log),0700,true);
  if(PHP_OS_FAMILY==='Windows'){
   $ps='$p=Start-Process -FilePath $env:ComSpec -ArgumentList "/c npm install -g @wonderwhy-er/desktop-commander@latest" -WindowStyle Hidden -PassThru; $p.Id';
   $pid=(int)shell_exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps));
  }else $pid=(int)shell_exec('nohup npm install -g @wonderwhy-er/desktop-commander@latest > '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!');
  if($pid<1)throw new RuntimeException('Could not start npm install. Check that Node.js and npm are installed.');
  return ['ok'=>true,'started'=>true,'pid'=>$pid,'message'=>'Desktop Commander installation started.'];
 }
 public function start():array {
  if(!$this->status()['installed'])throw new RuntimeException('Install Desktop Commander first.');
  if($this->status()['running'])return ['ok'=>true,'already_running'=>true,'message'=>'Desktop Commander Remote is already running.'];
  if(PHP_OS_FAMILY==='Windows'){
   $ps='$p=Start-Process -FilePath $env:ComSpec -ArgumentList "/c npx --yes @wonderwhy-er/desktop-commander@latest remote" -WindowStyle Hidden -PassThru; $p.Id';
   $pid=(int)shell_exec('powershell.exe -NoLogo -NoProfile -NonInteractive -Command '.escapeshellarg($ps));
  }else $pid=(int)shell_exec('nohup npx --yes @wonderwhy-er/desktop-commander@latest remote >/dev/null 2>&1 < /dev/null & echo $!');
  return ['ok'=>true,'started'=>true,'pid'=>$pid,'message'=>'Desktop Commander Remote start requested.'];
 }
 public function stop():array {
  $ids=$this->processIds();if(!$ids)return ['ok'=>true,'stopped'=>false,'message'=>'Desktop Commander Remote is already stopped.'];
  foreach($ids as $pid){if(PHP_OS_FAMILY==='Windows')@exec('taskkill /PID '.(int)$pid.' /T /F 2>NUL');else @exec('kill -TERM '.(int)$pid.' 2>/dev/null');}
  return ['ok'=>true,'stopped'=>true,'message'=>'Desktop Commander Remote stop requested.'];
 }
};
