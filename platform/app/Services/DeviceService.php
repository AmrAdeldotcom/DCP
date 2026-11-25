<?php
declare(strict_types=1);
final class DeviceService {
 public function __construct(private PDO $db){}
 public function invite(string $email,string $name):array{
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Enter a valid email address.');
  $id='inv_'.bin2hex(random_bytes(8));$token=bin2hex(random_bytes(24));$q=$this->db->prepare('INSERT INTO invitations(id,email,device_name,token_hash,expires_at,created_at) VALUES(?,?,?,?,?,?)');$q->execute([$id,$email,$name,password_hash($token,PASSWORD_DEFAULT),date(DATE_ATOM,time()+86400),date(DATE_ATOM)]);return ['id'=>$id,'token'=>$token,'email'=>$email,'device_name'=>$name,'expires_at'=>date(DATE_ATOM,time()+86400)];
 }
 public function pair(string $id,string $token,string $name,string $platform):array{
  $q=$this->db->prepare('SELECT * FROM invitations WHERE id=? AND status=\'pending\'');$q->execute([$id]);$inv=$q->fetch();
  if(!$inv||strtotime($inv['expires_at'])<time()||!password_verify($token,$inv['token_hash']))throw new RuntimeException('Invitation is invalid or expired.');
  $device='dev_'.bin2hex(random_bytes(8));$secret=bin2hex(random_bytes(32));$now=date(DATE_ATOM);
  $q=$this->db->prepare('INSERT INTO devices(id,name,platform,status,token_hash,created_at,last_seen) VALUES(?,?,?,?,?,?,?)');$q->execute([$device,$name,$platform,'paired',password_hash($secret,PASSWORD_DEFAULT),$now,$now]);
  $q=$this->db->prepare('UPDATE invitations SET status=\'accepted\' WHERE id=?');$q->execute([$id]);return ['device_id'=>$device,'device_token'=>$secret,'name'=>$name,'status'=>'paired'];
 }
 public function authenticate(string $token):string{$rows=$this->db->query('SELECT id,token_hash FROM devices')->fetchAll();foreach($rows as $r){if(password_verify($token,$r['token_hash']))return $r['id'];}throw new RuntimeException('Device authentication failed.');}
 public function heartbeat(string $token):array{$rows=$this->db->query('SELECT id,token_hash FROM devices')->fetchAll();foreach($rows as $r){if(password_verify($token,$r['token_hash'])){$q=$this->db->prepare('UPDATE devices SET last_seen=?,status=\'online\' WHERE id=?');$q->execute([date(DATE_ATOM),$r['id']]);return ['ok'=>true,'device_id'=>$r['id']];}}throw new RuntimeException('Device authentication failed.');}
 public function registerAgentSigningKey(string $deviceId,string $publicKey):void{
  if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/',$deviceId))throw new InvalidArgumentException('Invalid paired device identifier.');
  if(strlen($publicKey)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)throw new InvalidArgumentException('Device signing public key has an invalid length.');
  $q=$this->db->prepare('SELECT public_key,revoked_at FROM device_agent_signing_keys WHERE device_id=?');$q->execute([$deviceId]);$old=$q->fetch();$encoded=base64_encode($publicKey);
  if($old&&$old['revoked_at']!==null)throw new RuntimeException('This device signing key was revoked. Re-pair the device before registering a new identity key.');if($old&&!hash_equals((string)$old['public_key'],$encoded))throw new RuntimeException('This device signing key changed. Re-pair the device before rotating its identity key.');
  $now=date(DATE_ATOM);$q=$this->db->prepare('INSERT INTO device_agent_signing_keys(device_id,public_key,created_at,rotated_at,revoked_at) VALUES(?,?,?,?,NULL) ON CONFLICT(device_id) DO UPDATE SET public_key=excluded.public_key,rotated_at=excluded.rotated_at,revoked_at=NULL');$q->execute([$deviceId,$encoded,$now,$now]);
 }
 public function revokeAgentSigningKey(string $deviceId):void{if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/',$deviceId))throw new InvalidArgumentException('Invalid paired device identifier.');$q=$this->db->prepare('UPDATE device_agent_signing_keys SET revoked_at=? WHERE device_id=? AND revoked_at IS NULL');$q->execute([date(DATE_ATOM),$deviceId]);}
 public function agentSigningPublicKey(string $deviceId):?string{
  $q=$this->db->prepare('SELECT public_key,revoked_at FROM device_agent_signing_keys WHERE device_id=?');$q->execute([$deviceId]);$row=$q->fetch();if(!$row)return null;if($row['revoked_at']!==null)throw new RuntimeException('Device signing key is revoked. Re-pair the device before task dispatch.');$raw=base64_decode($row['public_key'],true);if($raw===false||strlen($raw)!==SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)throw new RuntimeException('Stored Device Agent public key is invalid.');return $raw;
 }
 public function list():array{return $this->db->query("SELECT d.id,d.name,d.platform,d.status,d.created_at,d.last_seen,CASE WHEN k.public_key IS NOT NULL AND k.revoked_at IS NULL THEN 1 ELSE 0 END AS signing_key_registered,CASE WHEN k.revoked_at IS NOT NULL THEN 1 ELSE 0 END AS signing_key_revoked FROM devices d LEFT JOIN device_agent_signing_keys k ON k.device_id=d.id ORDER BY d.created_at DESC")->fetchAll();}
 public function invitations():array{return $this->db->query('SELECT id,email,device_name,status,expires_at,created_at FROM invitations ORDER BY created_at DESC LIMIT 30')->fetchAll();}
}
