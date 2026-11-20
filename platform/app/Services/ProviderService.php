<?php
declare(strict_types=1);
final class ProviderService {
 public function __construct(private PDO $db,private Crypto $crypto,private PluginManager $plugins){}
 public function list():array{$rows=$this->db->query('SELECT * FROM providers ORDER BY created_at')->fetchAll();foreach($rows as &$r){$r['api_key_masked']=$this->crypto->mask($this->crypto->decrypt($r['api_key_enc']));unset($r['api_key_enc']);$r['enabled']=(bool)$r['enabled'];}return $rows;}
 public function save(array $d):array{
  $id=trim((string)($d['id']??''));$name=trim((string)($d['name']??''));$driver=trim((string)($d['driver']??'openai-compatible'));$url=rtrim(trim((string)($d['base_url']??'')),'/');$model=trim((string)($d['model']??''));$key=(string)($d['api_key']??'');
  if($name===''||$url==='')throw new InvalidArgumentException('Provider name and endpoint are required. Select a model after live model discovery.');
  if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true))throw new InvalidArgumentException('Provider URL must be a valid HTTP(S) URL.');
  if(parse_url($url,PHP_URL_SCHEME)==='http'&&!in_array(parse_url($url,PHP_URL_HOST),['127.0.0.1','localhost','::1'],true))throw new InvalidArgumentException('Remote providers must use HTTPS.');
  $plugin=$this->plugins->get($driver);if(!$plugin||($plugin['type']??'')!=='provider')throw new InvalidArgumentException('Unknown provider adapter.');$driver=(string)($plugin['driver']??$driver);
  $now=date(DATE_ATOM);$existing=null;if($id!==''){$q=$this->db->prepare('SELECT * FROM providers WHERE id=?');$q->execute([$id]);$existing=$q->fetch()?:null;}
  if($existing){$enc=$key!==''?$this->crypto->encrypt($key):$existing['api_key_enc'];$q=$this->db->prepare('UPDATE providers SET name=?,driver=?,base_url=?,model=?,api_key_enc=?,enabled=?,updated_at=? WHERE id=?');$q->execute([$name,$driver,$url,$model,$enc,(int)($d['enabled']??true),$now,$id]);}
  else{$id='prv_'.bin2hex(random_bytes(8));$q=$this->db->prepare('INSERT INTO providers(id,name,driver,base_url,model,api_key_enc,enabled,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute([$id,$name,$driver,$url,$model,$this->crypto->encrypt($key),(int)($d['enabled']??true),$now,$now]);}
  return $this->get($id);
 }
 public function get(string $id):?array{$q=$this->db->prepare('SELECT * FROM providers WHERE id=?');$q->execute([$id]);$r=$q->fetch();if(!$r)return null;$r['api_key_masked']=$this->crypto->mask($this->crypto->decrypt($r['api_key_enc']));unset($r['api_key_enc']);$r['enabled']=(bool)$r['enabled'];return $r;}
 public function secret(string $id):array{$q=$this->db->prepare('SELECT * FROM providers WHERE id=? AND enabled=1');$q->execute([$id]);$r=$q->fetch();if(!$r)throw new RuntimeException('Provider not found or disabled.');$r['api_key']=$this->crypto->decrypt($r['api_key_enc']);return $r;}
 public function delete(string $id):void{$q=$this->db->prepare('DELETE FROM providers WHERE id=?');$q->execute([$id]);}
}
