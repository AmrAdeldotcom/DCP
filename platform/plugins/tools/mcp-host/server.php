<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,3);$config=require $root.'/config/app.php';
require $root.'/app/Core/Database.php';require $root.'/app/Core/Crypto.php';require $root.'/app/Core/PluginManager.php';
require $root.'/app/Core/ProviderAdapter.php';require $root.'/app/Core/AgentAdapter.php';require $root.'/app/Core/MailAdapter.php';require $root.'/app/Core/ToolAdapter.php';require $root.'/app/Core/TaskExecutor.php';
require $root.'/app/Services/ProviderService.php';require $root.'/app/Services/AgentService.php';require $root.'/app/Services/MailService.php';require $root.'/app/Services/TerminalService.php';require $root.'/app/Services/DeviceService.php';require $root.'/app/Services/ChatService.php';
$db=Database::connect($config);Database::migrate($db);$plugins=new PluginManager($root.'/plugins');$crypto=new Crypto($config['key_file']);$provider=new ProviderService($db,$crypto,$plugins);$devices=new DeviceService($db);
$tp=$plugins->get('terminal');$tf=require dirname($tp['_manifest']).'/driver.php';$terminal=$tf->build($config);
$tools=[
 ['name'=>'dcp_get_status','description'=>'Read DCP local platform and Ollama health.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
 ['name'=>'dcp_list_providers','description'=>'List AI providers with masked API key previews only.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
 ['name'=>'dcp_list_devices','description'=>'List paired DCP devices and their status.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
 ['name'=>'dcp_list_tasks','description'=>'List DCP tasks and approval/execution states.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
 ['name'=>'dcp_create_task','description'=>'Create an approval-required task. A user must approve it in the DCP dashboard before execution.','inputSchema'=>['type'=>'object','properties'=>['title'=>['type'=>'string'],'description'=>['type'=>'string'],'target_device_id'=>['type'=>'string'],'actions'=>['type'=>'array','items'=>['type'=>'object','properties'=>['type'=>['type'=>'string','enum'=>['create_directory','write_file','read_file','list_directory']],'path'=>['type'=>'string'],'content'=>['type'=>'string']],'required'=>['type','path']]]],'required'=>['title','description','actions'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false]],
 ['name'=>'dcp_run_safe_terminal','description'=>'Run only pwd, ls, date, whoami, php -v, php -m or git status.','inputSchema'=>['type'=>'object','properties'=>['command'=>['type'=>'string']],'required'=>['command'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
 ['name'=>'dcp_read_unread_mail','description'=>'Read unread IMAP headers and short previews. Requires configured IMAP settings.','inputSchema'=>['type'=>'object','properties'=>(object)[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]]
];
function asText($v):array{return ['content'=>[['type'=>'text','text'=>json_encode($v,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]]];}
function runDcpTool(string $name,array $a):array{
 global $config,$db,$provider,$devices,$terminal,$plugins,$crypto;
 switch($name){
 case 'dcp_get_status':$ch=curl_init('http://127.0.0.1:11434/api/tags');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>2]);$raw=curl_exec($ch);$online=$raw!==false&&curl_getinfo($ch,CURLINFO_RESPONSE_CODE)===200;curl_close($ch);return ['platform'=>'DCP','version'=>$config['version'],'php'=>PHP_VERSION,'database'=>'SQLite','ollama_online'=>$online,'plugin_count'=>count($plugins->all())];
 case 'dcp_list_providers':return ['providers'=>$provider->list()];
 case 'dcp_list_devices':return ['devices'=>$devices->list()];
 case 'dcp_list_tasks':return ['tasks'=>$db->query('SELECT id,title,description,status,target_device_id,created_at,updated_at FROM tasks ORDER BY created_at DESC LIMIT 50')->fetchAll()];
 case 'dcp_create_task':$actions=$a['actions']??[];if(!is_array($actions)||!count($actions))throw new InvalidArgumentException('At least one allowed action is required.');$id='task_'.bin2hex(random_bytes(8));$q=$db->prepare('INSERT INTO tasks(id,title,description,payload,status,created_at,updated_at,target_device_id) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$id,(string)$a['title'],(string)$a['description'],json_encode(['actions'=>$actions],JSON_UNESCAPED_UNICODE),'approval_required',date(DATE_ATOM),date(DATE_ATOM),(string)($a['target_device_id']??'local')]);return ['id'=>$id,'status'=>'approval_required','message'=>'Task created. A user must approve it in DCP before execution.'];
 case 'dcp_run_safe_terminal':return $terminal->run((string)$a['command']);
 case 'dcp_read_unread_mail':$p=$plugins->get('smtp-imap');$factory=require dirname($p['_manifest']).'/driver.php';$mail=$factory->build($db,$crypto);return $mail->inbox();
 default:throw new InvalidArgumentException('Unknown DCP tool.');
 }
}
while(($line=fgets(STDIN))!==false){$req=json_decode($line,true);if(!is_array($req))continue;$id=$req['id']??null;$method=$req['method']??'';$params=$req['params']??[];
 try{
  if($method==='initialize')$result=['protocolVersion'=>$params['protocolVersion']??'2024-11-05','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'DCP MCP Host','version'=>$config['version']]];
  elseif($method==='ping')$result=new stdClass();
  elseif($method==='tools/list')$result=['tools'=>$tools];
  elseif($method==='tools/call'){$name=(string)($params['name']??'');if(!in_array($name,array_column($tools,'name'),true))throw new InvalidArgumentException('Tool not found.');$result=asText(runDcpTool($name,is_array($params['arguments']??null)?$params['arguments']:[]));}
  elseif(str_starts_with($method,'notifications/'))continue;
  else throw new RuntimeException('Unsupported MCP method: '.$method);
  if($id!==null)fwrite(STDOUT,json_encode(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n");
 }catch(Throwable $e){if($id!==null)fwrite(STDOUT,json_encode(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32000,'message'=>$e->getMessage()]],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n");}
}
