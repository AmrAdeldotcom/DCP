<?php
require __DIR__.'/../app/Core/Database.php';require __DIR__.'/../app/Services/DeviceService.php';
$c=require __DIR__.'/../config/app.php';$db=Database::connect($c);$d=new DeviceService($db);$i=$d->invite('remote-test@example.test','Remote Agent Test');$p=$d->pair($i['id'],$i['token'],'Remote Agent Test','macOS Test');file_put_contents('/tmp/dcp-device-test.json',json_encode($p));chmod('/tmp/dcp-device-test.json',0600);echo "TEST_DEVICE_CREATED\n";
