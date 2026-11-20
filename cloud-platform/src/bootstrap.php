<?php
declare(strict_types=1);
$env=dirname(__DIR__).'/.env';if(!is_file($env))throw new RuntimeException('Missing .env');foreach(file($env,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){$line=trim($line);if($line===''||$line[0]==='#'||!str_contains($line,'='))continue;[$k,$v]=explode('=',$line,2);putenv(trim($k).'='.trim($v," \\t\\n\\r\\0\\x0B\\\"'"));}
$dsn='mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306).';dbname='.getenv('DB_NAME').';charset=utf8mb4';$db=new PDO($dsn,(string)getenv('DB_USER'),(string)getenv('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function out(int $code,array $data):never{http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_SLASHES);exit;}
function body():array{$x=json_decode(file_get_contents('php://input')?:'{}',true);return is_array($x)?$x:out(400,['error'=>'invalid_json']);}
function uuid4():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
function secret_hash(string $s):string{return hash_hmac('sha256',$s,(string)getenv('APP_KEY'));}
function bearer():string{$h=$_SERVER['HTTP_AUTHORIZATION']??'';return preg_match('/^Bearer\\s+(.+)$/i',$h,$m)?trim($m[1]):'';}
function auth_device(PDO $db):array{$t=bearer();if(strlen($t)<32)out(401,['error'=>'unauthorized']);$q=$db->prepare('SELECT * FROM devices WHERE credential_hash=? AND revoked_at IS NULL');$q->execute([secret_hash($t)]);$d=$q->fetch();if(!$d)out(401,['error'=>'unauthorized']);$db->prepare('UPDATE devices SET last_seen_at=NOW() WHERE id=?')->execute([$d['id']]);return $d;}
