<?php
declare(strict_types=1);
final class DeviceWorkspaceService {
 private string $root;
 public function __construct(private PDO $db,array $config){
  $base=(string)($config['user_data']??'');if($base===''||str_contains($base,"\0"))throw new InvalidArgumentException('A valid DCP user-data root is required.');
  if(!is_dir($base)&&!mkdir($base,0700,true)&&!is_dir($base))throw new RuntimeException('Unable to create DCP user-data root.');
  $real=realpath($base);if($real===false||is_link($base))throw new RuntimeException('DCP user-data root must be a real directory, not a symlink.');
  $this->root=rtrim($real,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'workspaces';if(is_link($this->root))throw new RuntimeException('Workspace root cannot be a symlink.');
  if(!is_dir($this->root)&&!mkdir($this->root,0700,true)&&!is_dir($this->root))throw new RuntimeException('Unable to create DCP workspace root.');chmod($this->root,0700);
 }
 public function ensure(string $deviceId):array {
  $this->assertDeviceId($deviceId);$relative=$deviceId;$path=$this->root.DIRECTORY_SEPARATOR.$relative;
  if(is_link($path))throw new RuntimeException('Device workspace cannot be a symlink.');if(!is_dir($path)&&!mkdir($path,0700,true)&&!is_dir($path))throw new RuntimeException('Unable to create device workspace.');chmod($path,0700);
  $real=realpath($path);$root=realpath($this->root);if($real===false||$root===false||dirname($real)!==$root)throw new RuntimeException('Device workspace escaped its assigned root.');
  $q=$this->db->prepare('SELECT * FROM device_workspaces WHERE device_id=?');$q->execute([$deviceId]);$row=$q->fetch();$manifest=$path.DIRECTORY_SEPARATOR.'.dcp-workspace.json';
  if($row){if($row['relative_path']!==$relative||!is_file($manifest)||is_link($manifest))throw new RuntimeException('Device workspace identity manifest is missing or inconsistent.');$data=json_decode((string)file_get_contents($manifest),true);if(!is_array($data)||($data['device_id']??'')!==$deviceId||($data['workspace_id']??'')!==$row['workspace_id'])throw new RuntimeException('Device workspace identity manifest failed verification.');return ['device_id'=>$deviceId,'workspace_id'=>$row['workspace_id'],'path'=>$real,'relative_path'=>$relative,'created_at'=>$row['created_at']];}
  if(file_exists($manifest))throw new RuntimeException('Unregistered workspace manifest already exists. Refusing to adopt it.');$entries=array_values(array_diff(scandir($path)?:[],['.','..']));if($entries!==[])throw new RuntimeException('Unregistered device workspace is not empty. Refusing to adopt existing files.');$workspaceId='ws_'.bin2hex(random_bytes(16));$now=date(DATE_ATOM);$json=json_encode(['schema_version'=>1,'device_id'=>$deviceId,'workspace_id'=>$workspaceId,'created_at'=>$now],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
  $fh=fopen($manifest,'x');if($fh===false)throw new RuntimeException('Unable to create workspace identity manifest.');try{if(fwrite($fh,$json)!==strlen($json))throw new RuntimeException('Unable to write workspace identity manifest.');fflush($fh);}finally{fclose($fh);}chmod($manifest,0600);
  try{$q=$this->db->prepare('INSERT INTO device_workspaces(device_id,workspace_id,relative_path,created_at,updated_at) VALUES(?,?,?,?,?)');$q->execute([$deviceId,$workspaceId,$relative,$now,$now]);}catch(Throwable $e){@unlink($manifest);throw $e;}
  return ['device_id'=>$deviceId,'workspace_id'=>$workspaceId,'path'=>$real,'relative_path'=>$relative,'created_at'=>$now];
 }
 public function resolve(string $deviceId,string $relativePath=''):string {
  $workspace=$this->ensure($deviceId);$relativePath=str_replace('\\','/',$relativePath);if(str_contains($relativePath,"\0")||str_starts_with($relativePath,'/')||preg_match('/^[A-Za-z]:/',$relativePath))throw new InvalidArgumentException('Workspace paths must be relative.');
  $parts=$relativePath===''?[]:explode('/',$relativePath);foreach($parts as $part){if($part===''||$part==='.'||$part==='..')throw new InvalidArgumentException('Workspace path contains an invalid segment.');}
  $candidate=$workspace['path'];$last=count($parts)-1;foreach($parts as $i=>$part){$candidate.=DIRECTORY_SEPARATOR.$part;if(is_link($candidate))throw new RuntimeException('Symbolic links are not allowed inside device workspaces.');if(file_exists($candidate)){$real=realpath($candidate);if($real===false||!str_starts_with($real,rtrim($workspace['path'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR))throw new RuntimeException('Workspace path escaped its device root.');}elseif($i!==$last)throw new RuntimeException('Workspace parent directory does not exist.');}return $candidate;
 }
 private function assertDeviceId(string $id):void{if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/',$id))throw new InvalidArgumentException('Invalid device identifier for workspace allocation.');}
}
