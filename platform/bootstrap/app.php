<?php
declare(strict_types=1);
$config=require dirname(__DIR__).'/config/app.php';
$basePath=rtrim((string)($config['base_path']??''),'/');
$requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if($basePath!=='' && ($requestPath===$basePath || str_starts_with($requestPath,$basePath.'/'))){
 $requestPath=substr($requestPath,strlen($basePath))?:'/';
}
$_SERVER['DCP_REQUEST_PATH']=$requestPath;
date_default_timezone_set($config['timezone']);
umask(0077);
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log',$config['storage'].'/logs/php-error.log');
if(!is_dir($config['storage'].'/logs'))mkdir($config['storage'].'/logs',0700,true);
if(!is_dir($config['storage'].'/reports'))mkdir($config['storage'].'/reports',0700,true);
register_shutdown_function(static function()use($config):void{
 $e=error_get_last();if(!$e||!in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true))return;
 $id='auto_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
 $message=preg_replace('/(?i)(api[_-]?key|token|password|secret)(\s*[=:]\s*)[^\s,;]+/','$1$2[REDACTED]',(string)$e['message']);
 $record=['id'=>$id,'title'=>'Automatic PHP fatal error','body'=>$message,'file'=>basename((string)$e['file']),'line'=>(int)$e['line'],'request_path'=>parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/','created_at'=>date(DATE_ATOM),'php'=>PHP_VERSION,'platform'=>PHP_OS_FAMILY,'capture'=>'shutdown'];
 @file_put_contents($config['storage'].'/reports/'.$id.'.json',json_encode($record,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX);
});

if(session_status()===PHP_SESSION_NONE){
 $forwardedProto=strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''));
 $secureCookie=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||$forwardedProto==='https';
 session_name($config['session_name']);
 session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>$secureCookie,'path'=>'/']);
 session_start();
}
require dirname(__DIR__).'/app/Core/Database.php';
require dirname(__DIR__).'/app/Core/Crypto.php';
require dirname(__DIR__).'/app/Core/PluginManager.php';
require dirname(__DIR__).'/app/Core/ProviderAdapter.php';
require dirname(__DIR__).'/app/Core/ToolCallingProviderAdapter.php';
require dirname(__DIR__).'/app/Core/ProviderToolCallResult.php';
require dirname(__DIR__).'/app/Core/AgentAdapter.php';
require dirname(__DIR__).'/app/Core/MailAdapter.php';
require dirname(__DIR__).'/app/Core/ToolAdapter.php';
require dirname(__DIR__).'/app/Core/TaskExecutor.php';
require dirname(__DIR__).'/app/Services/ProviderService.php';
require dirname(__DIR__).'/app/Services/AgentService.php';
require dirname(__DIR__).'/app/Services/MailService.php';
require dirname(__DIR__).'/app/Services/TerminalService.php';
require dirname(__DIR__).'/app/Services/DcpFilesystemService.php';
require dirname(__DIR__).'/app/Services/DeviceService.php';
require dirname(__DIR__).'/app/Services/DeviceCapabilityMatrix.php';
require dirname(__DIR__).'/app/Services/DeviceWorkspaceService.php';
require dirname(__DIR__).'/app/Services/TaskEnvelopeService.php';
require dirname(__DIR__).'/app/Services/ReportService.php';
require dirname(__DIR__).'/app/Services/ChatService.php';
require dirname(__DIR__).'/app/Services/ChatJobService.php';
require dirname(__DIR__).'/app/Services/DcpCapabilityGrantService.php';
require dirname(__DIR__).'/app/Services/DcpToolResultArtifactStore.php';
require dirname(__DIR__).'/app/Services/DcpToolExecutionService.php';
require dirname(__DIR__).'/app/Services/TaskInboxService.php';
require dirname(__DIR__).'/app/Services/LocalAIService.php';
require dirname(__DIR__).'/app/Services/GitUpdateService.php';
require dirname(__DIR__).'/app/Memory/MarkdownJsonMemory.php';
require dirname(__DIR__).'/app/Services/DcpMemoryService.php';
require dirname(__DIR__).'/app/Services/DcpResourceRegistryService.php';
require dirname(__DIR__).'/app/Services/DcpToolRegistry.php';
require dirname(__DIR__).'/app/Services/CloudService.php';
require dirname(__DIR__).'/app/Services/CloudTerminalExecutionService.php';
require dirname(__DIR__).'/app/Controllers/ApiController.php';
require dirname(__DIR__).'/app/Controllers/PageController.php';
$db=Database::connect($config);
Database::migrate($db);
$plugins=new PluginManager(dirname(__DIR__).'/plugins');
$crypto=new Crypto($config['key_file']);
$api=new ApiController($db,$config,$crypto,$plugins);
$pages=new PageController($db,$config,$plugins);
if(str_starts_with($requestPath,'/api/')||$requestPath==='/mcp'){$api->dispatch();exit;}
$pages->render();
