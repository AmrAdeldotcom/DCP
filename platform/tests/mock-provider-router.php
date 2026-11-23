<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(str_ends_with($path,'/models')){
 echo json_encode(['data'=>[['id'=>'mock-model']]]);
 return;
}
if(str_ends_with($path,'/chat/completions')){
 $body=json_decode((string)file_get_contents('php://input'),true)?:[];
 $message=(string)($body['messages'][array_key_last($body['messages']??[])]['content']??'');
 echo json_encode(['choices'=>[['message'=>['content'=>'MOCK_CHAT_OK:'.$message]]]]);
 return;
}
echo json_encode(['ok'=>true]);
