<?php
declare(strict_types=1);
$capabilityPage=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/PageController.php');
$capabilityApi=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/ApiController.php');
$capabilityJs=(string)file_get_contents(dirname(__DIR__).'/public/assets/app.js');
ok(str_contains($capabilityPage,'id="local-capability-list"')&&str_contains($capabilityPage,'id="local-workspace-info"')&&str_contains($capabilityPage,'id="legacy-task-device-list"'),'Devices page exposes the local capability matrix');
ok(str_contains($capabilityApi,"'local_capabilities'")&&str_contains($capabilityApi,'attachRemote')&&str_contains($capabilityApi,'local_workspace')&&str_contains($capabilityApi,'legacy_task_devices')&&str_contains($capabilityApi,'attachLegacyDevices')&&str_contains($capabilityApi,'DeviceWorkspaceService'),'Cloud status returns explicit local and paired-device capability matrices');
ok(str_contains($capabilityJs,'x.capability_matrix')&&str_contains($capabilityJs,'d.local_capabilities')&&str_contains($capabilityJs,'d.local_workspace')&&str_contains($capabilityJs,'d.legacy_task_devices'),'Devices UI renders explicit per-device and local capability states');
