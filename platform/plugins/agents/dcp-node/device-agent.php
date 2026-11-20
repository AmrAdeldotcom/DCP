<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/app/Core/TaskExecutor.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$server=rtrim((string)($argv[1]??getenv('DCP_SERVER_URL')?:''),'/');
$token=(string)(($argv[2]??'')?:getenv('DCP_DEVICE_TOKEN')?:'');
$workspace=(string)(($argv[3]??'')?:getenv('DCP_WORKSPACE')?:(getcwd().DIRECTORY_SEPARATOR.'DCP-Workspace'));
if(!str_starts_with($server,'https://')&&!str_starts_with($server,'http://127.0.0.1')){fwrite(STDERR,"DCP server must use HTTPS (localhost is allowed for testing).\n");exit(2);}
if($token===''){fwrite(STDERR,"Device token is required.\n");exit(2);}
$factory=require __DIR__.'/worker.php';$executor=$factory($workspace);
function api(string $url,string $token,?array $body=null):array{$ch=curl_init($url);$headers=['Authorization: Bearer '.$token,'Accept: application/json'];$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>60,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2];if($body!==null){$headers[]='Content-Type: application/json';$opts[CURLOPT_HTTPHEADER]=$headers;$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_UNICODE);}curl_setopt_array($ch,$opts);$raw=curl_exec($ch);$error=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if($raw===false||$code<200||$code>=300)throw new RuntimeException('DCP API error '.$code.': '.($error?:substr((string)$raw,0,300)));return json_decode((string)$raw,true)?:[];}
echo "DCP Device Agent started for ".$server."\n";
while(true){try{api($server.'/api/device/heartbeat',$token,[]);$d=api($server.'/api/device/tasks',$token);foreach($d['tasks']??[] as $task){$status='completed';try{$result=$executor->execute($task['payload']??[]);}catch(Throwable $e){$status='failed';$result=['error'=>$e->getMessage()];}api($server.'/api/device/task-result',$token,['id'=>$task['id'],'status'=>$status,'result'=>$result]);echo date(DATE_ATOM).' '.$task['id'].' '.$status."\n";}}catch(Throwable $e){fwrite(STDERR,date(DATE_ATOM).' '.$e->getMessage()."\n");}sleep(10);}
