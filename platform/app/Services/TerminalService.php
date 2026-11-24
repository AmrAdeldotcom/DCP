<?php
declare(strict_types=1);

final class TerminalService {
 public function __construct(private array $config){}
 public function commands():array{
  $base=['pwd',PHP_OS_FAMILY==='Windows'?'ls (dir)':'ls','date','whoami','php -v','php -m','git status'];
  if(PHP_OS_FAMILY==='Darwin')$base[]='brew install/list/verify llama.cpp';
  return $base;
 }
 public function run(string $command):array{
  $command=trim($command);if($command==='')throw new InvalidArgumentException('Enter a command.');
  $parts=preg_split('/\\s+/', $command);$bin=array_shift($parts);$root=$this->config['terminal_root'];
  $windows=PHP_OS_FAMILY==='Windows';if($windows&&$bin==='dir')$bin='ls';$args=[];
  if($bin==='pwd')$args=$windows?['cmd.exe','/c','cd']:['/bin/pwd'];
  elseif($bin==='ls'){
   $target=$root;
   if(count($parts)>1)throw new RuntimeException('Only ls [directory] is allowed.');if(isset($parts[0])&&$parts[0]!=='.'){$realRoot=realpath($root)?:$root;$candidate=realpath($root.DIRECTORY_SEPARATOR.$parts[0]);$compareRoot=$windows?strtolower($realRoot):$realRoot;$compareCandidate=$candidate===false?'':($windows?strtolower($candidate):$candidate);$prefix=rtrim($compareRoot,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;if(!$candidate||($compareCandidate!==$compareRoot&&!str_starts_with($compareCandidate,$prefix)))throw new RuntimeException('Path outside DCP workspace.');$target=$candidate;}
   $args=$windows?['powershell.exe','-NoProfile','-NonInteractive','-Command','Get-ChildItem -Force -LiteralPath '.escapeshellarg($target).' | Select-Object Mode,Length,Name | Format-Table -AutoSize']:['/bin/ls','-la',$target];
  }elseif($bin==='date')$args=$windows?['powershell.exe','-NoProfile','-NonInteractive','-Command','Get-Date -Format o']:['/bin/date'];
  elseif($bin==='whoami')$args=$windows?['whoami.exe']:['/usr/bin/whoami'];
  elseif($bin==='php'){
   if(!in_array($parts[0]??'', ['-v','-m','--version'],true)||count($parts)!==1)throw new RuntimeException('Only php -v and php -m are allowed.');
   $args=[PHP_BINARY,$parts[0]];
  }elseif($bin==='git'){
   if(($parts[0]??'')!=='status'||count($parts)>1)throw new RuntimeException('Only git status is allowed.');
   $git=$windows?'git.exe':'/usr/bin/git';$args=[$git,'-C',$root,'status','--short','--branch'];
  }elseif($bin==='brew'&&PHP_OS_FAMILY==='Darwin'){
   $brew='/opt/homebrew/bin/brew';if(!is_executable($brew))$brew='/usr/local/bin/brew';
   $allowed=[['install','llama.cpp'],['list','--versions','llama.cpp'],['--prefix','llama.cpp']];
   if(!in_array($parts,$allowed,true))throw new RuntimeException('Only approved llama.cpp Homebrew install and verification commands are allowed.');
   $args=array_merge([$brew],$parts);
  }else throw new RuntimeException('Command blocked. Allowed: '.implode(', ',$this->commands()).'.');
  $spec=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$proc=proc_open($args,$spec,$pipes,$root);
  if(!is_resource($proc))throw new RuntimeException('Unable to start command.');
  fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);
  return ['command'=>$command,'output'=>substr((string)$out.(string)$err,0,12000),'exit_code'=>$code];
 }
}
