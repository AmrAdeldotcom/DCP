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
 public function list():array{return $this->db->query('SELECT id,name,platform,status,created_at,last_seen FROM devices ORDER BY created_at DESC')->fetchAll();}
 public function invitations():array{return $this->db->query('SELECT id,email,device_name,status,expires_at,created_at FROM invitations ORDER BY created_at DESC LIMIT 30')->fetchAll();}
}
