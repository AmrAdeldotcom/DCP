<?php
declare(strict_types=1);
final class AgentService {
 public function __construct(private PluginManager $plugins){}
 private function adapter(string $id):object {
  if(!in_array($id,['desktop-commander','deepseek-harness'],true))throw new InvalidArgumentException('Unsupported agent id.');
  $plugin=$this->plugins->get($id);if(!$plugin)throw new RuntimeException('Agent plugin is not registered.');
  $file=dirname($plugin['_manifest']).'/driver.php';if(!is_file($file))throw new RuntimeException('Agent driver is not installed.');
  $adapter=require $file;if(!$adapter instanceof AgentAdapter)throw new RuntimeException('Invalid agent adapter.');
  return $adapter;
 }
 public function all():array {
  $out=[];foreach($this->plugins->byType('agent') as $plugin){$file=dirname($plugin['_manifest']).'/driver.php';if(!is_file($file)){$out[]=['id'=>$plugin['id'],'name'=>$plugin['name'],'installed'=>false,'running'=>false,'connected'=>false,'detail'=>'No adapter driver installed.'];continue;}$adapter=require $file;if($adapter instanceof AgentAdapter)$out[]=$adapter->status();}
  return $out;
 }
 public function action(string $id,string $action):array {
  if(!in_array($action,['install','start','stop'],true))throw new InvalidArgumentException('Unsupported agent action.');
  $adapter=$this->adapter($id);if(!method_exists($adapter,$action))throw new RuntimeException('This action is not available for the selected agent on this operating system.');
  return $adapter->$action();
 }
}
