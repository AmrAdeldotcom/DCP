<?php
declare(strict_types=1);
require_once __DIR__.'/DcpResourceChatClient.php';
final class ChatService implements DcpResourceChatClient {
 public function __construct(private ProviderService $providers,private PluginManager $plugins){}
 private function adapter(string $driver):ProviderAdapter{$dir=dirname(__DIR__,2).'/plugins/providers/'.$driver.'/driver.php';if(!is_file($dir))throw new RuntimeException('Provider adapter is not installed: '.$driver);$adapter=require $dir;if(!$adapter instanceof ProviderAdapter)throw new RuntimeException('Invalid provider adapter.');return $adapter;}
 public function models(string $id):array{if($id==='ollama')return $this->adapter('ollama')->models(['base_url'=>'http://127.0.0.1:11434']);$p=$this->providers->secret($id);$plugin=$this->plugins->get($p['driver']);$driver=$p['driver'];if(!$plugin)throw new RuntimeException('Provider plugin missing.');return $this->adapter($driver)->models($p);}
 public function supportsToolCalls(string $id):bool{if($id==='ollama')return $this->adapter('ollama') instanceof ToolCallingProviderAdapter;$provider=$this->providers->secret($id);return $this->adapter((string)$provider['driver']) instanceof ToolCallingProviderAdapter;}
 private function boundedHistory(array $messages,int $maxMessages=8,int $maxChars=10000):array{
  $out=[];$used=0;
  foreach(array_reverse($messages) as $message){
   if(!is_array($message))continue;
   $role=(string)($message['role']??'');
   if(!in_array($role,['user','assistant','system','tool'],true))continue;
   $content=(string)($message['content']??'');
   if($content==='')continue;
   $remaining=$maxChars-$used;if($remaining<=0)break;
   if(mb_strlen($content)>$remaining)$content=mb_substr($content,-$remaining);
   $out[]=['role'=>$role,'content'=>$content];$used+=mb_strlen($content);
   if(count($out)>=$maxMessages)break;
  }
  return array_reverse($out);
 }
 public function sendWithTools(string $id,string $model,array $messages,array $tools):ProviderToolCallResult{if(trim($model)==='')throw new InvalidArgumentException('Model cannot be empty.');if(!array_is_list($tools)||count($tools)<1||count($tools)>64)throw new InvalidArgumentException('Tool definition count must be between 1 and 64.');if($id==='ollama'){$provider=[];$adapter=$this->adapter('ollama');}else{$provider=$this->providers->secret($id);$adapter=$this->adapter((string)$provider['driver']);}if(!$adapter instanceof ToolCallingProviderAdapter)throw new RuntimeException('Selected provider does not support structured tool calls.');return $adapter->chatWithTools($provider,$model,$this->boundedHistory($messages,8,10000),$tools);}
 public function send(string $id,string $model,string $message,array $history=[]):string{if(trim($message)==='')throw new InvalidArgumentException('Message cannot be empty.');$messages=$this->boundedHistory($history,8,10000);$messages[]=['role'=>'user','content'=>$message];if($id==='ollama')return $this->adapter('ollama')->chat([], $model,$messages);$p=$this->providers->secret($id);return $this->adapter($p['driver'])->chat($p,$model,$messages);}
}
