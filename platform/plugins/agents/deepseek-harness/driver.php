<?php
declare(strict_types=1);
return new class implements AgentAdapter {
 private function appPath():string {
  foreach(['/Applications/DeepSeek Harness.app',(getenv('HOME')?:'').'/Applications/DeepSeek Harness.app'] as $path)if(is_dir($path))return $path;
  return '';
 }
 public function status():array {
  if(PHP_OS_FAMILY!=='Darwin')return ['id'=>'deepseek-harness','name'=>'DeepSeek Harness','installed'=>false,'running'=>false,'connected'=>false,'detail'=>'This DSH desktop build is currently macOS-only; DCP has no verified Windows/Linux installer.','capabilities'=>['agent-tasks'],'integration'=>'detection-only'];
  $app=$this->appPath();$out=[];exec('/usr/bin/pgrep -fl '.escapeshellarg('dsh-runtime').' 2>/dev/null',$out);
  $running=count(array_filter($out,static fn($line)=>str_contains($line,'dsh-runtime')&&!str_contains($line,'pgrep')))>0;
  return ['id'=>'deepseek-harness','name'=>'DeepSeek Harness','installed'=>$app!=='','running'=>$running,'connected'=>false,'detail'=>$running?'DSH process detected; DCP task adapter is not yet verified.':($app!==''?'Installed as a normal macOS application.':'Not installed.'),'path'=>$app,'capabilities'=>['agent-tasks'],'integration'=>'detection-only'];
 }
 public function start():array {
  if(PHP_OS_FAMILY!=='Darwin')throw new RuntimeException('This DSH desktop build is currently macOS-only.');
  $app=$this->appPath();if($app==='')throw new RuntimeException('DeepSeek Harness is not installed.');
  exec('/usr/bin/open -a '.escapeshellarg($app).' 2>&1',$out,$code);if($code!==0)throw new RuntimeException('Could not launch DeepSeek Harness.');
  return ['ok'=>true,'started'=>true,'message'=>'DeepSeek Harness launch requested.'];
 }
 public function stop():array {
  if(PHP_OS_FAMILY!=='Darwin')throw new RuntimeException('This DSH desktop build is currently macOS-only.');
  exec('/usr/bin/osascript -e '.escapeshellarg('tell application "DeepSeek Harness" to quit').' 2>&1',$out,$code);
  if($code!==0)throw new RuntimeException('Could not quit DeepSeek Harness.');
  return ['ok'=>true,'stopped'=>true,'message'=>'DeepSeek Harness quit requested.'];
 }
};
