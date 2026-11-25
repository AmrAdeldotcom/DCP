<?php
declare(strict_types=1);

/**
 * Resource inventory and policy broker. Credentials are never accepted here:
 * secret_ref is an opaque owner-local vault key, not credential material.
 */
final class DcpResourceRegistryService {
 public function __construct(private PDO $db){}
 private function id(string $prefix):string{return $prefix.'_'.bin2hex(random_bytes(12));}
 private function json(array $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
 private function decode(string $value):array{$v=json_decode($value,true);return is_array($v)?$v:[];}
 public function register(array $input,string $ownerId,string $ownerType='user'):array{
  $kind=(string)($input['kind']??'');$allowed=['local_model','inference_engine','external_api','mcp_server','tool','agent','dataset'];
  if(!in_array($kind,$allowed,true))throw new InvalidArgumentException('Unsupported resource kind.');
  $name=trim((string)($input['name']??''));if($name===''||strlen($name)>160)throw new InvalidArgumentException('Resource name must contain 1–160 bytes.');
  $device=trim((string)($input['device_id']??'local'));if($device===''||strlen($device)>100)throw new InvalidArgumentException('Invalid resource device.');
  $ops=$input['operations']??['invoke'];if(!is_array($ops)||$ops===[]||count($ops)>32)throw new InvalidArgumentException('Resource operations must be a bounded non-empty list.');
  foreach($ops as $op)if(!is_string($op)||!preg_match('/^[a-z][a-z0-9_.:-]{0,63}$/',$op))throw new InvalidArgumentException('Invalid resource operation.');
  $secretRef=trim((string)($input['secret_ref']??''));if($secretRef!==''&&!preg_match('/^[A-Za-z0-9_.:-]{1,160}$/',$secretRef))throw new InvalidArgumentException('secret_ref must be an opaque local vault reference.');
  $metadata=$input['metadata']??[];$capacity=$input['capacity']??[];if(!is_array($metadata)||!is_array($capacity))throw new InvalidArgumentException('Metadata and capacity must be objects.');
  if($this->hasSecretKeys($metadata)||$this->hasSecretKeys($capacity))throw new InvalidArgumentException('Credential material cannot be stored in resource metadata.');
  $id=$this->id('res');$now=date(DATE_ATOM);
  $q=$this->db->prepare('INSERT INTO dcp_resources(id,owner_type,owner_id,device_id,kind,name,provider,model,endpoint_class,operations_json,metadata_json,capacity_json,data_classification,locality,secret_ref,health_status,health_at,published,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
  $q->execute([$id,$ownerType,$ownerId,$device,$kind,$name,(string)($input['provider']??''),(string)($input['model']??''),(string)($input['endpoint_class']??'local'),$this->json(array_values(array_unique($ops))),$this->json($metadata),$this->json($capacity),(string)($input['data_classification']??'internal'),(string)($input['locality']??'device'),$secretRef,'unknown',null,(int)($input['published']??false),$now,$now]);
  $this->audit('resource.registered',['resource_id'=>$id,'owner_id'=>$ownerId,'kind'=>$kind,'device_id'=>$device]);
  return $this->get($id,$ownerId);
 }
 public function heartbeat(string $id,string $ownerId,array $health):array{
  $row=$this->owned($id,$ownerId);$status=(string)($health['status']??'online');if(!in_array($status,['online','degraded','offline','error'],true))throw new InvalidArgumentException('Invalid resource health status.');
  $capacity=$health['capacity']??[];if(!is_array($capacity)||$this->hasSecretKeys($capacity))throw new InvalidArgumentException('Capacity must be an object without credential material.');
  $q=$this->db->prepare('UPDATE dcp_resources SET health_status=?,health_at=?,capacity_json=?,updated_at=? WHERE id=? AND owner_id=?');$now=date(DATE_ATOM);$q->execute([$status,$now,$this->json($capacity),$now,$id,$ownerId]);return $this->get($id,$ownerId);
 }
 public function get(string $id,string $ownerId):array{$q=$this->db->prepare('SELECT * FROM dcp_resources WHERE id=? AND owner_id=? AND revoked_at IS NULL');$q->execute([$id,$ownerId]);$r=$q->fetch();if(!$r)throw new RuntimeException('Resource not found or not owned by this user.');return $this->present($r);}
 public function discover(string $ownerId,array $filters=[]):array{
  $q=$this->db->prepare("SELECT r.* FROM dcp_resources r WHERE r.revoked_at IS NULL AND (r.owner_id=? OR EXISTS(SELECT 1 FROM dcp_resource_grants g WHERE g.resource_id=r.id AND g.status='active' AND (g.expires_at IS NULL OR g.expires_at>?) AND (g.grantee_type='user' AND g.grantee_id=?))) ORDER BY r.kind,r.name LIMIT 200");
  $q->execute([$ownerId,date(DATE_ATOM),$ownerId]);$rows=array_map(fn($r)=>$this->present($r),$q->fetchAll());foreach($rows as &$row)if(($row['owner_id']??'')!==$ownerId)unset($row['secret_ref']);unset($row);
  return array_values(array_filter($rows,function($r)use($filters){foreach(['kind','provider','model','device_id'] as $k)if(isset($filters[$k])&&$filters[$k]!==''&&($r[$k]??null)!==$filters[$k])return false;if(isset($filters['operation'])&&!in_array($filters['operation'],$r['operations'],true))return false;return true;}));
 }
 public function grant(string $resourceId,string $ownerId,string $granteeType,string $granteeId,array $operations,?string $expiresAt=null,int $quota=0,int $concurrency=1):array{
  $r=$this->owned($resourceId,$ownerId);if(!in_array($granteeType,['user','team','client','device'],true)||trim($granteeId)==='')throw new InvalidArgumentException('Invalid resource grantee.');
  if($operations===[]||array_diff($operations,$r['operations'])!==[])throw new InvalidArgumentException('Grant operations must be a subset of published resource operations.');
  if($quota<0||$quota>1000000||$concurrency<1||$concurrency>128)throw new InvalidArgumentException('Invalid grant quota or concurrency.');
  if($expiresAt!==null&&strtotime($expiresAt)===false)throw new InvalidArgumentException('Invalid grant expiry.');
  $id=$this->id('rgr');$now=date(DATE_ATOM);$this->db->prepare("INSERT INTO dcp_resource_grants(id,resource_id,grantee_type,grantee_id,operations_json,quota_limit,concurrency_limit,expires_at,status,generation,created_at) VALUES(?,?,?,?,?,?,?,?, 'active',1,?)")->execute([$id,$resourceId,$granteeType,$granteeId,$this->json(array_values(array_unique($operations))),$quota,$concurrency,$expiresAt,$now]);
  $this->audit('resource.grant.created',['resource_id'=>$resourceId,'grant_id'=>$id,'grantee_type'=>$granteeType,'grantee_id'=>$granteeId,'operations'=>$operations]);return ['id'=>$id,'resource_id'=>$resourceId,'grantee_type'=>$granteeType,'grantee_id'=>$granteeId,'operations'=>$operations,'expires_at'=>$expiresAt,'quota_limit'=>$quota,'concurrency_limit'=>$concurrency,'status'=>'active'];
 }
 public function revokeGrant(string $grantId,string $ownerId):array{
  $q=$this->db->prepare("UPDATE dcp_resource_grants SET status='revoked',revoked_at=?,generation=generation+1 WHERE id=? AND resource_id IN (SELECT id FROM dcp_resources WHERE owner_id=? AND revoked_at IS NULL) AND status='active'");$q->execute([date(DATE_ATOM),$grantId,$ownerId]);if($q->rowCount()!==1)throw new RuntimeException('Active owned resource grant not found.');$this->audit('resource.grant.revoked',['grant_id'=>$grantId,'owner_id'=>$ownerId]);return ['id'=>$grantId,'status'=>'revoked'];
 }
 public function authorize(string $resourceId,string $actorType,string $actorId,string $operation):array{
  $q=$this->db->prepare('SELECT * FROM dcp_resources WHERE id=? AND revoked_at IS NULL');$q->execute([$resourceId]);$r=$q->fetch();if(!$r)throw new RuntimeException('Resource is unavailable.');
  if($r['owner_id']===$actorId&&$r['owner_type']===$actorType)return $this->present($r);
  $g=$this->db->prepare("SELECT * FROM dcp_resource_grants WHERE resource_id=? AND grantee_type=? AND grantee_id=? AND status='active' AND (expires_at IS NULL OR expires_at>?) ORDER BY created_at DESC LIMIT 1");$g->execute([$resourceId,$actorType,$actorId,date(DATE_ATOM)]);$grant=$g->fetch();if(!$grant||!in_array($operation,$this->decode($grant['operations_json']),true))throw new RuntimeException('Resource invocation is not granted to this actor.');
  if((int)$grant['quota_limit']>0){$used=$this->db->prepare("SELECT COUNT(*) FROM dcp_resource_invocations WHERE grant_id=? AND created_at>=?");$used->execute([$grant['id'],date('Y-m-d\TH:i:sP',strtotime('today'))]);if((int)$used->fetchColumn()>=(int)$grant['quota_limit'])throw new RuntimeException('Resource grant quota exceeded.');}
  $shared=$this->present($r);unset($shared['secret_ref']);return $shared+['grant_id'=>$grant['id'],'grant_generation'=>(int)$grant['generation']];
 }
 public function broker(array $request,string $actorId):array{
  $candidates=$this->discover($actorId,['kind'=>$request['kind']??'','provider'=>$request['provider']??'','model'=>$request['model']??'','operation'=>$request['operation']??'invoke']);$requiredClass=(string)($request['data_classification']??'internal');$localOnly=(bool)($request['local_only']??false);
  $ranked=[];foreach($candidates as $r){if(!in_array($r['health_status'],['online','degraded'],true))continue;if($localOnly&&$r['locality']!=='device')continue;if($r['data_classification']==='restricted'&&$requiredClass!=='restricted')continue;$cap=$r['capacity'];$score=100+($r['locality']==='device'?25:0)-((int)($cap['queue_depth']??0)*4)-((int)($cap['latency_ms']??0)/100);$ranked[]=['resource'=>$r,'score'=>$score];}
  usort($ranked,fn($a,$b)=>$b['score']<=>$a['score']);if($ranked===[])throw new RuntimeException('No eligible resource matches the request and owner grants.');return ['selected'=>$ranked[0]['resource'],'alternatives'=>array_slice(array_column($ranked,'resource'),1,4),'selection_score'=>$ranked[0]['score'],'policy'=>['local_only'=>$localOnly,'data_classification'=>$requiredClass]];
 }
 public function beginInvocation(string $resourceId,string $actorType,string $actorId,string $operation):array{
  $resource=$this->authorize($resourceId,$actorType,$actorId,$operation);$grantId=$resource['grant_id']??null;$this->db->beginTransaction();
  try{
   if($grantId!==null){$q=$this->db->prepare("SELECT * FROM dcp_resource_grants WHERE id=? AND status='active' AND generation=? AND (expires_at IS NULL OR expires_at>?)");$q->execute([$grantId,(int)$resource['grant_generation'],date(DATE_ATOM)]);$grant=$q->fetch();if(!$grant)throw new RuntimeException('Resource grant was revoked or expired before invocation.');
    $q=$this->db->prepare("SELECT COUNT(*) FROM dcp_resource_invocations WHERE grant_id=? AND status IN ('queued','running')");$q->execute([$grantId]);if((int)$q->fetchColumn()>=(int)$grant['concurrency_limit'])throw new RuntimeException('Resource grant concurrency limit reached.');
    if((int)$grant['quota_limit']>0){$q=$this->db->prepare('SELECT COUNT(*) FROM dcp_resource_invocations WHERE grant_id=? AND created_at>=?');$q->execute([$grantId,date('Y-m-d\\T00:00:00P')]);if((int)$q->fetchColumn()>=(int)$grant['quota_limit'])throw new RuntimeException('Resource grant quota exceeded.');}
   }
   $id=$this->id('rinv');$this->db->prepare('INSERT INTO dcp_resource_invocations(id,resource_id,grant_id,actor_type,actor_id,operation,status,created_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$resourceId,$grantId,$actorType,$actorId,$operation,'running',date(DATE_ATOM)]);$this->db->commit();return ['invocation_id'=>$id,'resource_id'=>$resourceId,'grant_id'=>$grantId,'status'=>'running','resource'=>$resource];
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function finishInvocation(string $invocationId,string $ownerId,string $status):array{
  if(!in_array($status,['completed','failed','cancelled','uncertain'],true))throw new InvalidArgumentException('Invalid resource invocation result state.');$q=$this->db->prepare('UPDATE dcp_resource_invocations SET status=? WHERE id=? AND resource_id IN (SELECT id FROM dcp_resources WHERE owner_id=?) AND status IN (\'queued\',\'running\')');$q->execute([$status,$invocationId,$ownerId]);if($q->rowCount()!==1)throw new RuntimeException('Active resource invocation not found for this owner.');return ['invocation_id'=>$invocationId,'status'=>$status];
 }
 private function hasSecretKeys(array $value):bool{foreach($value as $key=>$item){if(in_array(strtolower((string)$key),['api_key','apikey','password','passwd','secret','token','authorization','private_key','client_secret'],true))return true;if(is_array($item)&&$this->hasSecretKeys($item))return true;}return false;}
 private function owned(string $id,string $ownerId):array{$q=$this->db->prepare('SELECT * FROM dcp_resources WHERE id=? AND owner_id=? AND revoked_at IS NULL');$q->execute([$id,$ownerId]);$r=$q->fetch();if(!$r)throw new RuntimeException('Resource not found or not owned by this user.');return $this->present($r);}
 private function present(array $r):array{return ['id'=>$r['id'],'owner_type'=>$r['owner_type'],'owner_id'=>$r['owner_id'],'device_id'=>$r['device_id'],'kind'=>$r['kind'],'name'=>$r['name'],'provider'=>$r['provider'],'model'=>$r['model'],'endpoint_class'=>$r['endpoint_class'],'operations'=>$this->decode($r['operations_json']),'metadata'=>$this->decode($r['metadata_json']),'capacity'=>$this->decode($r['capacity_json']),'data_classification'=>$r['data_classification'],'locality'=>$r['locality'],'secret_ref'=>$r['secret_ref'],'health_status'=>$r['health_status'],'health_at'=>$r['health_at'],'published'=>(bool)$r['published'],'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at']];}
 private function audit(string $event,array $details):void{$this->db->prepare('INSERT INTO audit_log(event,details,created_at) VALUES(?,?,?)')->execute([$event,$this->json($details),date(DATE_ATOM)]);}
}
