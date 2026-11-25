<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
if(str_ends_with($path,'/chat/completions')){$body=json_decode((string)file_get_contents('php://input'),true)?:[];$message=(string)($body['messages'][array_key_last($body['messages']??[])]['content']??'');usleep(2500000);echo json_encode(['choices'=>[['message'=>['content'=>'DELAYED_CHAT_OK:'.$message]]]],JSON_UNESCAPED_UNICODE);return;}
http_response_code(404);echo json_encode(['error'=>'not found']);
