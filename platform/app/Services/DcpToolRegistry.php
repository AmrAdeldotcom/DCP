<?php
declare(strict_types=1);
final class DcpToolRegistry {
 private array $definitions;
 private array $policies=[
  'dcp_get_status'=>['classification'=>'safe-read','capability'=>'platform.status','approval_required'=>false],
  'dcp_list_providers'=>['classification'=>'sensitive-read','capability'=>'provider.list','approval_required'=>true],
  'dcp_list_devices'=>['classification'=>'sensitive-read','capability'=>'device.list','approval_required'=>true],
  'dcp_list_tasks'=>['classification'=>'sensitive-read','capability'=>'task.list','approval_required'=>true],
  'dcp_create_task'=>['classification'=>'state-change','capability'=>'task.create','approval_required'=>true],
  'dcp_run_safe_terminal'=>['classification'=>'safe-read','capability'=>'terminal.safe-read','approval_required'=>false],
  'dcp_read_unread_mail'=>['classification'=>'sensitive-read','capability'=>'mail.read','approval_required'=>true],
 ];
 public function __construct(private array $config,private PDO $db,private ProviderService $providers,private DeviceService $devices,private TerminalService $terminal,private PluginManager $plugins,private Crypto $crypto){
  $empty=['type'=>'object','properties'=>(object)[],'additionalProperties'=>false];
  $this->definitions=[
   ['name'=>'dcp_get_status','description'=>'Read DCP local platform and Ollama health.','inputSchema'=>$empty,'annotations'=>['readOnlyHint'=>true]],
   ['name'=>'dcp_list_providers','description'=>'List AI providers with masked API key previews only.','inputSchema'=>$empty,'annotations'=>['readOnlyHint'=>true]],
   ['name'=>'dcp_list_devices','description'=>'List paired DCP devices and their status.','inputSchema'=>$empty,'annotations'=>['readOnlyHint'=>true]],
   ['name'=>'dcp_list_tasks','description'=>'List DCP tasks and approval/execution states.','inputSchema'=>$empty,'annotations'=>['readOnlyHint'=>true]],
   ['name'=>'dcp_create_task','description'=>'Create an approval-required task. A user must approve it in the DCP dashboard before execution.','inputSchema'=>['type'=>'object','properties'=>['title'=>['type'=>'string','minLength'=>1,'maxLength'=>200],'description'=>['type'=>'string','minLength'=>1,'maxLength'=>4000],'target_device_id'=>['type'=>'string','minLength'=>1,'maxLength'=>80],'actions'=>['type'=>'array','minItems'=>1,'maxItems'=>20,'items'=>['type'=>'object','properties'=>['type'=>['type'=>'string','enum'=>['create_directory','write_file','read_file','list_directory']],'path'=>['type'=>'string','minLength'=>1,'maxLength'=>1024],'content'=>['type'=>'string','maxLength'=>1048576]],'required'=>['type','path'],'additionalProperties'=>false]]],'required'=>['title','description','actions'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>false,'destructiveHint'=>false]],
   ['name'=>'dcp_run_safe_terminal','description'=>'Run only pwd, ls, date, whoami, php -v, php -m or git status.','inputSchema'=>['type'=>'object','properties'=>['command'=>['type'=>'string','minLength'=>1,'maxLength'=>500]],'required'=>['command'],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true]],
   ['name'=>'dcp_read_unread_mail','description'=>'Read unread IMAP headers and short previews. Requires configured IMAP settings.','inputSchema'=>$empty,'annotations'=>['readOnlyHint'=>true]],
  ];
 }
 public function definitions():array{return $this->definitions;}
 public function policy(string $name):array{if(!isset($this->policies[$name]))throw new InvalidArgumentException('Tool policy not found.');return $this->policies[$name];}
 public function argumentsHash(array $arguments):string{return hash('sha256',json_encode($this->canonicalize($arguments),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));}
 public function call(string $name,array $arguments,array $context=[]):array{
  $definition=null;foreach($this->definitions as $candidate)if($candidate['name']===$name){$definition=$candidate;break;}if($definition===null)throw new InvalidArgumentException('Tool not found.');
  $this->validateSchema($definition['inputSchema'],$arguments,'arguments');$this->validateActionSemantics($name,$arguments);$policy=$this->policy($name);$this->authorize($policy,$arguments,$context);
  switch($name){
   case 'dcp_get_status':$ch=curl_init('http://127.0.0.1:11434/api/tags');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>1,CURLOPT_TIMEOUT=>2]);$raw=curl_exec($ch);$online=$raw!==false&&curl_getinfo($ch,CURLINFO_RESPONSE_CODE)===200;curl_close($ch);return ['platform'=>'DCP','version'=>$this->config['version']??'unknown','php'=>PHP_VERSION,'database'=>'SQLite','ollama_online'=>$online,'plugin_count'=>count($this->plugins->all())];
   case 'dcp_list_providers':return ['providers'=>$this->providers->list()];
   case 'dcp_list_devices':return ['devices'=>$this->devices->list()];
   case 'dcp_list_tasks':return ['tasks'=>$this->db->query('SELECT id,title,description,status,target_device_id,created_at,updated_at FROM tasks ORDER BY created_at DESC LIMIT 50')->fetchAll()];
   case 'dcp_create_task':$id='task_'.bin2hex(random_bytes(8));$now=date(DATE_ATOM);$q=$this->db->prepare('INSERT INTO tasks(id,title,description,payload,status,created_at,updated_at,target_device_id) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$id,trim($arguments['title']),trim($arguments['description']),json_encode(['actions'=>$arguments['actions']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'approval_required',$now,$now,(string)($arguments['target_device_id']??'local')]);return ['id'=>$id,'status'=>'approval_required','message'=>'Task created. A user must approve it in DCP before execution.'];
   case 'dcp_run_safe_terminal':return $this->terminal->run($arguments['command']);
   case 'dcp_read_unread_mail':$plugin=$this->plugins->get('smtp-imap');if(!$plugin)throw new RuntimeException('IMAP plugin is not installed.');$factory=require dirname($plugin['_manifest']).'/driver.php';$mail=$factory->build($this->db,$this->crypto);return $mail->inbox();
   default:throw new InvalidArgumentException('Tool not found.');
  }
 }
 private function authorize(array $policy,array $arguments,array $context):void{
  $caller=(string)($context['caller']??'mcp');if($caller==='mcp'){if(($context['integration_configured']??false)!==true)throw new RuntimeException('MCP tool calls require an explicitly configured local integration.');return;}
  if($caller!=='agent')throw new RuntimeException('Tool caller is not authorized.');$capabilities=$context['capabilities']??[];if(!is_array($capabilities)||!in_array($policy['capability'],$capabilities,true))throw new RuntimeException('Agent tool call is blocked: the required DCP capability is not enabled.');
  if($policy['approval_required']){$version=$context['plan_version']??null;$approvedVersion=$context['approval_plan_version']??null;if(($context['approval_status']??'')!=='approved'||!is_int($version)||$version<1||$approvedVersion!==$version)throw new RuntimeException('Agent tool call requires an explicit approval for the current plan version.');$approvedHash=(string)($context['approved_arguments_hash']??'');if(!preg_match('/^[a-f0-9]{64}$/',$approvedHash)||!hash_equals($approvedHash,$this->argumentsHash($arguments)))throw new RuntimeException('Agent tool call arguments do not match the approved arguments.');}
 }
 private function validateActionSemantics(string $name,array $arguments):void{
  if($name!=='dcp_create_task')return;if(trim($arguments['title'])===''||trim($arguments['description'])==='')throw new InvalidArgumentException('Task title and description cannot be blank.');if(isset($arguments['target_device_id'])&&!preg_match('/^[A-Za-z0-9_-]{1,80}$/',$arguments['target_device_id']))throw new InvalidArgumentException('Target device identifier is invalid.');foreach($arguments['actions'] as $action){$path=str_replace('\\','/',$action['path']);if(str_contains($path,"\0")||str_starts_with($path,'/')||preg_match('/^[A-Za-z]:/',$path))throw new InvalidArgumentException('Task action paths must remain relative to the assigned workspace.');foreach(explode('/',$path) as $segment)if($segment===''||$segment==='.'||$segment==='..')throw new InvalidArgumentException('Task action path contains an invalid segment.');if(strlen($path)>1024)throw new InvalidArgumentException('Task action path exceeds the byte limit.');if($action['type']==='write_file'&&!array_key_exists('content',$action))throw new InvalidArgumentException('write_file actions require content.');if($action['type']!=='write_file'&&array_key_exists('content',$action))throw new InvalidArgumentException('content is only valid for write_file actions.');if($action['type']==='write_file'&&strlen($action['content'])>1048576)throw new InvalidArgumentException('Task file content exceeds 1 MB.');}
 }
 private function validateSchema(array $schema,mixed $value,string $path):void{
  $type=$schema['type']??null;
  if($type==='object'){
   if(!is_array($value)||($value!==[]&&array_is_list($value)))throw new InvalidArgumentException($path.' must be an object.');$properties=(array)($schema['properties']??[]);foreach($schema['required']??[] as $key)if(!array_key_exists($key,$value))throw new InvalidArgumentException($path.'.'.$key.' is required.');if(($schema['additionalProperties']??true)===false)foreach($value as $key=>$unused)if(!array_key_exists($key,$properties))throw new InvalidArgumentException($path.'.'.$key.' is not allowed.');foreach($properties as $key=>$child)if(array_key_exists($key,$value))$this->validateSchema($child,$value[$key],$path.'.'.$key);return;
  }
  if($type==='array'){
   if(!is_array($value)||!array_is_list($value))throw new InvalidArgumentException($path.' must be an array.');$count=count($value);if(isset($schema['minItems'])&&$count<$schema['minItems'])throw new InvalidArgumentException($path.' has too few items.');if(isset($schema['maxItems'])&&$count>$schema['maxItems'])throw new InvalidArgumentException($path.' has too many items.');foreach($value as $i=>$item)$this->validateSchema($schema['items']??[],$item,$path.'['.$i.']');return;
  }
  if($type==='string'){
   if(!is_string($value))throw new InvalidArgumentException($path.' must be a string.');$length=function_exists('mb_strlen')?mb_strlen($value):strlen($value);if(isset($schema['minLength'])&&$length<$schema['minLength'])throw new InvalidArgumentException($path.' is too short.');if(isset($schema['maxLength'])&&$length>$schema['maxLength'])throw new InvalidArgumentException($path.' is too long.');if(isset($schema['enum'])&&!in_array($value,$schema['enum'],true))throw new InvalidArgumentException($path.' has an unsupported value.');return;
  }
  throw new InvalidArgumentException('Unsupported tool schema type at '.$path.'.');
 }
 private function canonicalize(mixed $value):mixed{if(!is_array($value))return $value;if(array_is_list($value))return array_map(fn($v)=>$this->canonicalize($v),$value);ksort($value,SORT_STRING);foreach($value as $key=>$item)$value[$key]=$this->canonicalize($item);return $value;}
}
