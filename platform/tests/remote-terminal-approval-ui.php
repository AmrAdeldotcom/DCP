<?php
declare(strict_types=1);
$cloudService=(string)file_get_contents(dirname(__DIR__).'/app/Services/CloudService.php');
$api=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/ApiController.php');
$page=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/PageController.php');
$js=(string)file_get_contents(dirname(__DIR__).'/public/assets/app.js');
ok(str_contains($cloudService,'function requestRemoteTerminal')&&str_contains($cloudService,'function remoteTerminalInbox')&&str_contains($cloudService,'function decideRemoteTerminalRequest'),'CloudService exposes remote request submission, target inbox and explicit decision methods');
ok(str_contains($api,"/api/cloud/terminal-requests')")&&str_contains($page,'terminal-target-device')&&str_contains($js,"api('/api/cloud/terminal-requests'")&&str_contains($js,'populateTerminalTargets'),'Source Terminal UI can select only paired devices that granted terminal.request and submit an approval-only request');
ok(str_contains($api,"/api/cloud/terminal-requests/inbox")&&str_contains($api,"/api/cloud/terminal-requests/decision")&&str_contains($api,'$this->csrf()'),'local Cloud approval API exposes a CSRF-protected explicit target decision route');
ok(str_contains($page,'cloud-terminal-requests')&&str_contains($page,'terminal.request'),'Devices UI exposes a target-device terminal approval inbox and separate pairing permission');
ok(str_contains($js,'Approve this exact command')&&str_contains($js,'Reject')&&str_contains($js,'SHA-256:')&&str_contains($js,'Approval is recorded. Execution is a separate action on this device.'),'Target approval UI displays exact command hash and approve/reject actions without executing it');

ok(str_contains($api,"/api/cloud/terminal-requests/execute")&&str_contains($js,'data-terminal-execute')&&str_contains($js,'Execute approved command'),'Target approval requires a separate explicit Execute click on the executing device');
ok(str_contains($api,'CloudTerminalExecutionService')&&str_contains($api,'$this->terminal'),'Approved remote command is executed through the local TerminalService on the target device');

ok(str_contains($api,"/api/cloud/terminal-requests/status")&&str_contains($page,'terminal-remote-check')&&str_contains($js,'checkRemoteTerminalStatus')&&str_contains($js,'terminal-remote-result-output'),'Source device can retrieve and display the signed remote execution result from Cloud');
