<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
$method=$_SERVER['REQUEST_METHOD']??'GET';
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??'');
$body=json_decode((string)file_get_contents('php://input'),true)?:[];
$deviceToken=str_repeat('b',64);
$userToken=str_repeat('a',64);
$deviceId='00000000-0000-4000-8000-000000000001';
$pairId='00000000-0000-4000-8000-000000000002';
$requestId='00000000-0000-4000-8000-000000000003';
$protected=$path!=='/v1/register'&&$path!=='/v1/login'&&$path!=='/v1/devices/anonymous/register'&&$path!=='/v1/models'&&$path!=='/v1/chat/completions';
if($protected&&!preg_match('/^Bearer\s+[a-f0-9]{64}$/i',$auth)){http_response_code(401);echo json_encode(['error'=>'unauthorized']);return;}
if($path==='/v1/register'||$path==='/v1/login'){echo json_encode(['user_id'=>1,'access_token'=>$userToken,'token_expires_days'=>30]);return;}
if($path==='/v1/devices/anonymous/register'){echo json_encode(['device_id'=>$deviceId,'device_token'=>$deviceToken,'identity'=>'anonymous']);return;}
if($path==='/v1/devices/link-account'||$path==='/v1/devices/unlink-account'||$path==='/v1/devices/heartbeat'){echo json_encode(['ok'=>true,'device_id'=>$deviceId]);return;}
if($path==='/v1/paired-devices'&&$method==='GET'){echo json_encode(['devices'=>[['id'=>$pairId,'label'=>'Mock Peer','platform'=>'Linux']]]);return;}
if($path==='/v1/pairing-requests'&&$method==='GET'){echo json_encode(['requests'=>[]]);return;}
if($path==='/v1/pairing-codes'){echo json_encode(['code'=>'DCP-MOCK-PAIR','expires_in'=>600]);return;}
if($path==='/v1/pairing-requests'&&$method==='POST'){echo json_encode(['request_id'=>$pairId,'status'=>'pending']);return;}
if(preg_match('#^/v1/pairing-requests/[a-f0-9-]{36}/decision$#',$path)){echo json_encode(['ok'=>true,'status'=>($body['accept']??false)?'accepted':'rejected','scopes'=>$body['scopes']??[]]);return;}
if(preg_match('#^/v1/paired-devices/[a-f0-9-]{36}/revoke$#',$path)){echo json_encode(['revoked'=>true]);return;}
if($path==='/v1/connections/request'){echo json_encode(['request_id'=>$requestId,'expires_in'=>90]);return;}
if(preg_match('#^/v1/connections/[a-f0-9-]{36}/signal$#',$path)){echo json_encode(['accepted'=>true,'delivery'=>'signaling_only']);return;}
if(preg_match('#^/v1/connections/[a-f0-9-]{36}/signals$#',$path)){echo json_encode(['signals'=>[['id'=>1,'sender_device'=>$deviceId,'envelope'=>'mock-envelope']]]);return;}
if(str_ends_with($path,'/models')){echo json_encode(['data'=>[['id'=>'mock-model']]]);return;}
if(str_ends_with($path,'/chat/completions')){
 $message=(string)($body['messages'][array_key_last($body['messages']??[])]['content']??'');
 echo json_encode(['choices'=>[['message'=>['content'=>'MOCK_CHAT_OK:'.$message]]]]);return;
}
echo json_encode(['ok'=>true]);
