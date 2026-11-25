<?php
declare(strict_types=1);
$sessionConfig=$c;$sessionConfig['terminal_root']=dirname(__DIR__,2);
$sessionOne=new TerminalService($sessionConfig);
$started=$sessionOne->startSession('sleep 0.2; printf DCP_DAEMON_SHARED_SESSION_PASS',dirname(__DIR__,2),'bash');
$sessionId=(string)$started['session_id'];
$sessionTwo=new TerminalService($sessionConfig);
$output='';
for($i=0;$i<20;$i++){usleep(100000);$read=$sessionTwo->readSession($sessionId,4096);$output.=(string)($read['output']??'');if(str_contains($output,'DCP_DAEMON_SHARED_SESSION_PASS'))break;}
ok(str_contains($output,'DCP_DAEMON_SHARED_SESSION_PASS'),'terminal session daemon shares one live shell session between independent TerminalService instances');
$sessionTwo->stopSession($sessionId);
$client=new DcpTerminalSessionClient($sessionConfig);
$stopped=$client->request('shutdown');
ok(($stopped['stopping']??false)===true,'terminal session daemon supports an authenticated graceful shutdown for lifecycle management');
