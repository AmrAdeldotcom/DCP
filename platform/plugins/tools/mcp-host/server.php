<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,3);$config=require $root.'/config/app.php';
require $root.'/app/Core/Database.php';require $root.'/app/Core/Crypto.php';require $root.'/app/Core/PluginManager.php';
require $root.'/app/Core/ProviderAdapter.php';require $root.'/app/Core/AgentAdapter.php';require $root.'/app/Core/MailAdapter.php';require $root.'/app/Core/ToolAdapter.php';require $root.'/app/Core/TaskExecutor.php';
require $root.'/app/Services/ProviderService.php';require $root.'/app/Services/AgentService.php';require $root.'/app/Services/MailService.php';require $root.'/app/Services/TerminalService.php';require $root.'/app/Services/DeviceService.php';require $root.'/app/Services/ChatService.php';require $root.'/app/Services/DcpToolRegistry.php';
$db=Database::connect($config);Database::migrate($db);$plugins=new PluginManager($root.'/plugins');$crypto=new Crypto($config['key_file']);$provider=new ProviderService($db,$crypto,$plugins);$devices=new DeviceService($db);
$tp=$plugins->get('terminal');if(!$tp)throw new RuntimeException('DCP terminal plugin is not installed.');$tf=require dirname($tp['_manifest']).'/driver.php';$terminal=$tf->build($config);
$registry=new DcpToolRegistry($config,$db,$provider,$devices,$terminal,$plugins,$crypto);$tools=$registry->definitions();
function asText($v):array{return ['content'=>[['type'=>'text','text'=>json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]]];}
while(($line=fgets(STDIN))!==false){$req=json_decode($line,true);if(!is_array($req))continue;$id=$req['id']??null;$method=$req['method']??'';$params=$req['params']??[];
 try{
  if($method==='initialize')$result=['protocolVersion'=>$params['protocolVersion']??'2024-11-05','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'DCP MCP Host','version'=>$config['version']]];
  elseif($method==='ping')$result=new stdClass();
  elseif($method==='tools/list')$result=['tools'=>$tools];
  elseif($method==='tools/call'){$name=(string)($params['name']??'');$arguments=is_array($params['arguments']??null)?$params['arguments']:[];$result=asText($registry->call($name,$arguments,['caller'=>'mcp','integration_configured'=>true]));}
  elseif(str_starts_with($method,'notifications/'))continue;
  else throw new RuntimeException('Unsupported MCP method: '.$method);
  if($id!==null)fwrite(STDOUT,json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n");
 }catch(Throwable $e){if($id!==null)fwrite(STDOUT,json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32000,'message'=>$e->getMessage()]],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n");}
}
