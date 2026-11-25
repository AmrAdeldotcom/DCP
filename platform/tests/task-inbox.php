<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Services/TaskInboxService.php';
$inbox=new TaskInboxService($db);
$sessionId='inbox-session-'.bin2hex(random_bytes(5));
$now=date(DATE_ATOM);
$db->prepare('INSERT INTO chat_sessions(id,title,provider_id,created_at,updated_at) VALUES(?,?,?,?,?)')->execute([$sessionId,'Inbox test','mock',$now,$now]);
$queuedA=$inbox->enqueue('Continue the current implementation',$sessionId);
$queuedB=$inbox->enqueue('After that, run the regression suite',$sessionId);
$queuedGlobal=$inbox->enqueue('Global queued instruction without an active session');
ok(($queuedA['status']??'')==='queued'&&($queuedA['session_id']??'')===$sessionId,'Task Inbox persists a queued instruction against its chat session');
ok(count($inbox->list($sessionId))===3,'Task Inbox includes unassigned messages queued while a new chat is still starting');
$inbox->reprioritize($queuedB['id'],10);
ok(($inbox->list($sessionId)[0]['id']??'')===$queuedB['id'],'Task Inbox reorders queued messages by explicit priority');
$sent=$inbox->markSent($queuedB['id']);
ok(($sent['status']??'')==='sent'&&count($inbox->list($sessionId))===2,'Task Inbox marks an instruction sent only after the chat request succeeds');
$cancelled=$inbox->cancel($queuedA['id']);
ok(($cancelled['status']??'')==='cancelled'&&count($inbox->list($sessionId))===1,'Task Inbox cancellation preserves history and removes the message from the active queue');
$emptyRejected=false;try{$inbox->enqueue('   ',$sessionId);}catch(InvalidArgumentException $e){$emptyRejected=true;}
ok($emptyRejected,'Task Inbox rejects empty messages');
$oversizedRejected=false;try{$inbox->enqueue(str_repeat('x',20001),$sessionId);}catch(InvalidArgumentException $e){$oversizedRejected=true;}
ok($oversizedRejected,'Task Inbox enforces the 20,000-character message limit');
$unknownSessionRejected=false;try{$inbox->enqueue('must not attach','missing-session');}catch(InvalidArgumentException $e){$unknownSessionRejected=true;}
ok($unknownSessionRejected,'Task Inbox rejects unknown chat session references');
$badPriorityRejected=false;try{$inbox->reprioritize($queuedB['id'],10000);}catch(InvalidArgumentException $e){$badPriorityRejected=true;}
ok($badPriorityRejected,'Task Inbox rejects priorities outside the supported range');
$alreadySentRejected=false;try{$inbox->markSent($queuedB['id']);}catch(RuntimeException $e){$alreadySentRejected=true;}
ok($alreadySentRejected,'Task Inbox prevents marking an already-sent message twice');
$alreadyCancelledRejected=false;try{$inbox->cancel($queuedA['id']);}catch(RuntimeException $e){$alreadyCancelledRejected=true;}
ok($alreadyCancelledRejected,'Task Inbox prevents cancelling an already-cancelled message twice');
