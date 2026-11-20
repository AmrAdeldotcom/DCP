<?php
declare(strict_types=1);
final class TerminalService {
 public function __construct(private array $config){}
 public function run(string $command):array{
  $command=trim($command);if($command==='')throw new InvalidArgumentException('Enter a command.');
  $parts=preg_split('/\s+/', $command);$bin=array_shift($parts);$root=$this->config['terminal_root'];
  $allowed=['pwd'=>['/bin/pwd'],'ls'=>['/bin/ls'],'date'=>['/bin/date'],'whoami'=>['/usr/bin/whoami'],'php'=>['/usr/local/bin/php'],'git'=>['/usr/bin/git'],'brew'=>['/usr/local/bin/brew']];
  if(!isset($allowed[$bin]))throw new RuntimeException('Command blocked. Allowed: pwd, ls, date, whoami, php -v/-m, git status, brew install/list/verify llama.cpp.');
  $args=$allowed[$bin];if($bin==='ls'){$args[]='-la';if(isset($parts[0])){$target=realpath($root.'/'.$parts[0]);if(!$target||!str_starts_with($target,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Path outside DCP workspace.');$args[]=$target;}}
  elseif($bin==='php'){if(!in_array($parts[0]??'', ['-v','-m','--version'],true))throw new RuntimeException('Only php -v and php -m are allowed.');$args[]=$parts[0];}
  elseif($bin==='git'){if(($parts[0]??'')!=='status')throw new RuntimeException('Only git status is allowed.');$args[]='status';}
  elseif($bin==='brew'){ $allowedBrew=[['install','llama.cpp'],['list','--versions','llama.cpp'],['--prefix','llama.cpp']];if(!in_array($parts,$allowedBrew,true))throw new RuntimeException('Only approved llama.cpp Homebrew install and verification commands are allowed.');$args=array_merge($args,$parts); }
  $proc=proc_open($args,[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);if(!is_resource($proc))throw new RuntimeException('Unable to start command.');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);return ['command'=>$command,'output'=>substr($out.$err,0,12000),'exit_code'=>$code];
 }
}
