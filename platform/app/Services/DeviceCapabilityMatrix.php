<?php
declare(strict_types=1);

final class DeviceCapabilityMatrix {
 private const REMOTE_UNAVAILABLE = [
  'workspace.read'=>'Remote workspace read',
  'workspace.write'=>'Remote workspace write',
  'terminal.execute'=>'Remote terminal execution',
  'task.dispatch'=>'Remote task dispatch',
  'browser.control'=>'Remote browser control',
 ];

 public function local(array $agents):array {
  $node=null;foreach($agents as $agent){if(($agent['id']??'')==='dcp-node'){$node=$agent;break;}}
  $capabilities=is_array($node['capabilities']??null)?$node['capabilities']:[];
  $online=is_array($node)&&!empty($node['installed'])&&!empty($node['running'])&&!empty($node['connected']);
  $workspace=$online&&in_array('tasks',$capabilities,true);
  $terminal=$online&&in_array('safe-terminal',$capabilities,true);
  $devices=$online&&in_array('devices',$capabilities,true);
  return [
   $this->entry('workspace.read','Local workspace read',$workspace?'available':'unavailable','not_required',$workspace?'Read-only workspace operations are available inside the DCP workspace.':'DCP Local Node workspace adapter is not connected.'),
   $this->entry('workspace.write','Local workspace write',$workspace?'available':'unavailable','explicit-approval',$workspace?'Writes are limited to approved DCP tasks and the private workspace.':'DCP Local Node workspace adapter is not connected.'),
   $this->entry('terminal.safe','Local safe terminal',$terminal?'available':'unavailable','signed_in_user',$terminal?'Allow-listed commands run as the signed-in user inside the configured DCP root.':'Safe terminal adapter is not connected.'),
   $this->entry('device.inventory','Local device inventory',$devices?'available':'unavailable','not_required',$devices?'Local device inventory is available.':'Device inventory adapter is not connected.'),
   $this->entry('remote.task.dispatch','Remote task dispatch','not_implemented','not_available','DCP Cloud pairing currently grants presence/signaling only. Legacy token-authenticated Device Agent task polling is tracked separately.'),
   $this->entry('remote.browser.control','Remote browser control','not_implemented','not_available','Shared browser transport and explicit browser capability grants are not implemented.'),
  ];
 }

 public function remote(array $device):array {
  $scopes=$device['scopes']??[];
  if(is_string($scopes)){$decoded=json_decode($scopes,true);$scopes=is_array($decoded)?$decoded:[];}
  if(!is_array($scopes))$scopes=[];
  $scopes=array_values(array_unique(array_filter($scopes,'is_string')));
  $presence=in_array('presence',$scopes,true)||!empty($device['presence_granted']);
  $signaling=in_array('signaling',$scopes,true);
  $paused=!empty($device['presence_paused']);
  $matrix=[
   $this->entry('presence','Device presence',$presence?($paused?'paused':'granted'):'not_granted','explicit_pairing_scope',$presence?($paused?'Presence is granted but currently paused.':'Presence sharing was explicitly approved.'):'Presence sharing was not granted.'),
   $this->entry('signaling','Connection signaling',$signaling?'granted':'not_granted','explicit_pairing_scope',$signaling?'Opaque connection signaling was explicitly approved.':'Connection signaling was not granted.'),
  ];
  foreach(self::REMOTE_UNAVAILABLE as $id=>$label)$matrix[]=$this->entry($id,$label,'not_implemented','not_available','Remote execution is disabled until authenticated transport, per-device workspace isolation, capability enforcement and audit are implemented.');
  return $matrix;
 }

 public function legacyDevice(array $device):array {
  $signed=!empty($device['signing_key_registered']);$paired=in_array((string)($device['status']??''),['paired','online'],true)&&empty($device['signing_key_revoked']);
  $state=!$paired?'unavailable':($signed?'available_signed':'available_legacy');
  $detail=!$paired?'This device is not available for task dispatch.':($signed?'This Device Agent uses Ed25519 signed task envelopes, target binding, expiry and replay protection. Tasks still require explicit approval and the worker is restricted to workspace file actions.':'Existing DCP Device Agent uses legacy bearer-token polling. Tasks require explicit approval and the worker is restricted to workspace file actions; signatures and replay protection activate after the agent registers its Ed25519 key.');
  return ['device_id'=>(string)($device['id']??''),'name'=>(string)($device['name']??'DCP device'),'platform'=>(string)($device['platform']??'Unknown'),'status'=>(string)($device['status']??'unknown'),'capabilities'=>[
   $this->entry('workspace.read','Remote workspace read',$state,'explicit_task_approval',$detail),
   $this->entry('workspace.write','Remote workspace write',$state,'explicit_task_approval',$detail),
   $this->entry('task.dispatch','Legacy remote task dispatch',$state,'explicit_task_approval',$detail),
   $this->entry('task.signed-envelope','Signed task envelopes',!empty($device['signing_key_revoked'])?'revoked':(!empty($device['signing_key_registered'])?'available':'not_integrated'),!empty($device['signing_key_registered'])?'paired_device_key':'not_available',!empty($device['signing_key_revoked'])?'This Device Agent signing key was revoked; task polling is blocked until explicit re-pairing.':(!empty($device['signing_key_registered'])?'This paired Device Agent registered its Ed25519 key; newly issued tasks use signed envelopes and replay protection.':'The legacy bearer-token transport remains active until this Device Agent registers an Ed25519 key.')),
   $this->entry('terminal.execute','Remote terminal execution','not_implemented','not_available','The current remote worker does not provide arbitrary terminal execution.'),
   $this->entry('browser.control','Remote browser control','not_implemented','not_available','A shared browser transport and explicit browser capability grants are not implemented.'),
  ]];
 }

 public function attachLegacyDevices(array $devices):array {foreach($devices as &$device)$device['capability_matrix']=$this->legacyDevice($device);unset($device);return $devices;}

 public function attachRemote(array $devices):array {
  foreach($devices as &$device)$device['capability_matrix']=$this->remote($device);
  unset($device);return $devices;
 }

 private function entry(string $id,string $label,string $status,string $approval,string $reason):array {
  return ['id'=>$id,'label'=>$label,'status'=>$status,'approval'=>$approval,'reason'=>$reason];
 }
}
