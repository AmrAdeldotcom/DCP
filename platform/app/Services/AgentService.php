<?php
declare(strict_types=1);
final class AgentService {
 public function __construct(private PluginManager $plugins){}
 public function all():array{$out=[];foreach($this->plugins->byType('agent') as $p){$file=dirname($p['_manifest']).'/driver.php';if(!is_file($file)){$out[]=['id'=>$p['id'],'name'=>$p['name'],'installed'=>false,'running'=>false,'connected'=>false,'detail'=>'No adapter driver installed.'];continue;}$adapter=require $file;if(!$adapter instanceof AgentAdapter)continue;$out[]=$adapter->status();}return $out;}
}
