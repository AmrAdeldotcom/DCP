<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Services/DeviceCapabilityMatrix.php';
$matrix=new DeviceCapabilityMatrix();
$local=$matrix->local([
 ['id'=>'dcp-node','installed'=>true,'running'=>true,'connected'=>true,'capabilities'=>['tasks','terminal.execute','devices']],
]);
$localById=[];foreach($local as $item)$localById[$item['id']]=$item;
ok(($localById['workspace.read']['status']??'')==='available','local node exposes scoped workspace read capability');
ok(($localById['workspace.write']['approval']??'')==='explicit-approval','local workspace writes require explicit task approval');
ok(($localById['terminal.execute']['status']??'')==='available','local node exposes the full terminal as its only terminal capability');
ok(($localById['remote.task.dispatch']['status']??'')==='not_implemented','local matrix does not imply remote task dispatch is implemented');
$remote=$matrix->remote(['scopes'=>['presence','signaling'],'presence_granted'=>true,'presence_paused'=>true]);
$remoteById=[];foreach($remote as $item)$remoteById[$item['id']]=$item;
ok(($remoteById['presence']['status']??'')==='paused','remote presence reports granted-but-paused accurately');
ok(($remoteById['signaling']['status']??'')==='granted','remote signaling is enabled only by an explicit signaling scope');
foreach(['workspace.read','workspace.write','terminal.execute','task.dispatch','browser.control'] as $id){ok(($remoteById[$id]['status']??'')==='not_implemented','remote capability remains disabled: '.$id);}
$untrustedScope=$matrix->remote(['scopes'=>['presence','signaling','remote.task.dispatch','terminal.execute']]);
$untrustedById=[];foreach($untrustedScope as $item)$untrustedById[$item['id']]=$item;
ok(($untrustedById['task.dispatch']['status']??'')==='not_implemented'&&($untrustedById['terminal.execute']['status']??'')==='not_implemented','unknown or unimplemented remote scopes cannot grant execution capabilities');
$offline=$matrix->local([]);$offlineById=[];foreach($offline as $item)$offlineById[$item['id']]=$item;
ok(($offlineById['workspace.read']['status']??'')==='unavailable','local workspace capabilities are unavailable when DCP Local Node is not connected');

$legacy=$matrix->legacyDevice(['id'=>'dev-123','name'=>'Windows Agent','platform'=>'Windows','status'=>'paired','signing_key_registered'=>0]);$legacyById=[];foreach($legacy['capabilities'] as $item)$legacyById[$item['id']]=$item;ok(($legacyById['task.dispatch']['status']??'')==='available_legacy','existing paired Device Agent task polling is reported as a legacy available capability');ok(($legacyById['task.dispatch']['approval']??'')==='explicit_task_approval','legacy remote task dispatch still requires explicit approval');ok(($legacyById['task.signed-envelope']['status']??'')==='not_integrated','new signed task envelopes are not falsely reported as integrated with the legacy agent');$signedLegacy=$matrix->legacyDevice(['id'=>'dev-signed','status'=>'online','signing_key_registered'=>1]);$signedMatrix=[];foreach($signedLegacy['capabilities'] as $item)$signedMatrix[$item['id']]=$item;ok(($signedMatrix['task.signed-envelope']['status']??'')==='available'&&($signedMatrix['task.dispatch']['status']??'')==='available_signed','Device Agent with a registered signing key is reported as using signed envelopes');ok(($legacyById['terminal.execute']['status']??'')==='not_implemented','legacy remote worker does not expose arbitrary terminal execution');$legacyOffline=$matrix->legacyDevice(['id'=>'dev-offline','status'=>'revoked']);ok(($legacyOffline['capabilities'][0]['status']??'')==='unavailable','revoked legacy devices are shown as unavailable');

$revokedSigned=$matrix->legacyDevice(['id'=>'dev-revoked','status'=>'online','signing_key_registered'=>0,'signing_key_revoked'=>1]);$revokedMatrix=[];foreach($revokedSigned['capabilities'] as $item)$revokedMatrix[$item['id']]=$item;ok(($revokedMatrix['task.dispatch']['status']??'')==='unavailable'&&($revokedMatrix['task.signed-envelope']['status']??'')==='revoked','revoked agent signing key blocks legacy downgrade and task dispatch');
