<?php
require __DIR__.'/../app/Core/TaskExecutor.php';
$factory=require __DIR__.'/../plugins/agents/dcp-node/worker.php';$worker=$factory();
$base=dirname(__DIR__).'/data/workspace';
$r=$worker->execute(['actions'=>[['type'=>'create_directory','path'=>'smoke/nested'],['type'=>'write_file','path'=>'smoke/nested/hello.txt','content'=>'DCP_TASK_OK'],['type'=>'read_file','path'=>'smoke/nested/hello.txt'],['type'=>'list_directory','path'=>'smoke/nested']]]);
if(($r['actions'][2]['content']??'')!=='DCP_TASK_OK')throw new RuntimeException('Read after write failed');
if(!is_file($base.'/smoke/nested/hello.txt'))throw new RuntimeException('Task output file missing');
echo "PASS: approved local task actions (mkdir/write/read/list)\n";
$blocked=false;try{$worker->execute(['actions'=>[['type'=>'write_file','path'=>'../../outside.txt','content'=>'bad']]]);}catch(Throwable $e){$blocked=true;}
if(!$blocked)throw new RuntimeException('Traversal was not blocked');
echo "PASS: path traversal blocked\n";
