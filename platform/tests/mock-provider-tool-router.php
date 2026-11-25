<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';$body=json_decode((string)file_get_contents('php://input'),true)?:[];$model=(string)($body['model']??'');
if($path==='/health'){echo json_encode(['ok'=>true]);return;}
if($path==='/v1/chat/completions'){
 if(isset($body['tools'])&&(!is_array($body['tools'])||count($body['tools'])!==34||($body['tool_choice']??'')!=='auto'||($body['parallel_tool_calls']??true)!==false)){http_response_code(400);echo json_encode(['error'=>['message'=>'tool contract missing']]);return;}
 if(str_starts_with($model,'mock-openai')||$model==='qwen3:0.6b'){
  $message=['content'=>'','tool_calls'=>[]];$finish='stop';
  if($model==='mock-openai-valid'||$model==='qwen3:0.6b'){$message['content']='I will inspect the workspace.';$message['tool_calls']=[['id'=>'call_fixture_01','type'=>'function','function'=>['name'=>'dcp_terminal_execute','arguments'=>'{"command":"pwd"}']]];$finish='tool_calls';if(str_starts_with($model,'qwen3')&&($body['chat_template_kwargs']['enable_thinking']??null)!==false){http_response_code(400);echo json_encode(['error'=>['message'=>'Qwen3 thinking must be disabled']]);return;}}
  elseif($model==='mock-openai-malformed'){$message['tool_calls']=[['id'=>'call_bad_01','type'=>'function','function'=>['name'=>'dcp_terminal_execute','arguments'=>'{"command":']]];$finish='tool_calls';}
  elseif($model==='mock-openai-many'){for($i=0;$i<17;$i++)$message['tool_calls'][]=['id'=>'call_many_'.$i,'type'=>'function','function'=>['name'=>'dcp_terminal_execute','arguments'=>'{"command":"pwd"}']];$finish='tool_calls';}
  elseif($model==='mock-openai-refusal'){$message['refusal']='The requested action is not permitted.';}
  elseif($model==='mock-openai-ordinary'){$message['content']='MOCK_OPENAI_CHAT_OK';}
  echo json_encode(['choices'=>[['message'=>$message,'finish_reason'=>$finish]]]);return;
 }
 echo json_encode(['choices'=>[['message'=>['content'=>'MOCK_OPENAI_DEFAULT'],'finish_reason'=>'stop']]]);return;
}
if($path==='/api/chat'){
 if(!isset($body['tools'])||!is_array($body['tools'])){http_response_code(400);echo json_encode(['error'=>'tools missing']);return;}
 if(preg_match('/^qwen3(?::|$)/i',$model)===1&&($body['think']??null)!==false){http_response_code(400);echo json_encode(['error'=>'think must be false']);return;}
 echo json_encode(['message'=>['content'=>'Ollama will inspect the workspace.','tool_calls'=>[['function'=>['name'=>'dcp_terminal_execute','arguments'=>['command'=>'pwd']]]]],'done_reason'=>'tool_calls']);return;
}
http_response_code(404);echo json_encode(['error'=>'not found']);
