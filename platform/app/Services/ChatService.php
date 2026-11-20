<?php
declare(strict_types=1);
final class ChatService {
 public function __construct(private ProviderService $providers,private PluginManager $plugins){}
 private function adapter(string $driver):ProviderAdapter{$dir=dirname(__DIR__,2).'/plugins/providers/'.$driver.'/driver.php';if(!is_file($dir))throw new RuntimeException('Provider adapter is not installed: '.$driver);$adapter=require $dir;if(!$adapter instanceof ProviderAdapter)throw new RuntimeException('Invalid provider adapter.');return $adapter;}
 public function models(string $id):array{if($id==='ollama')return $this->adapter('ollama')->models(['base_url'=>'http://127.0.0.1:11434']);$p=$this->providers->secret($id);$plugin=$this->plugins->get($p['driver']);$driver=$p['driver'];if(!$plugin)throw new RuntimeException('Provider plugin missing.');return $this->adapter($driver)->models($p);}
 public function send(string $id,string $model,string $message,array $history=[]):string{if(trim($message)==='')throw new InvalidArgumentException('Message cannot be empty.');$messages=array_slice($history,-30);$messages[]=['role'=>'user','content'=>$message];if($id==='ollama')return $this->adapter('ollama')->chat([], $model,$messages);$p=$this->providers->secret($id);return $this->adapter($p['driver'])->chat($p,$model,$messages);}
}
