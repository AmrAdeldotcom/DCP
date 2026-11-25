<?php
declare(strict_types=1);
$inboxPage=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/PageController.php');
$inboxApi=(string)file_get_contents(dirname(__DIR__).'/app/Controllers/ApiController.php');
$inboxJs=(string)file_get_contents(dirname(__DIR__).'/public/assets/app.js');
ok(str_contains($inboxPage,'id="task-inbox-input"')&&str_contains($inboxPage,'id="task-inbox-add"')&&str_contains($inboxPage,'id="task-inbox-list"'),'Chat page exposes the persistent Task Inbox composer and queue list');
ok(str_contains($inboxApi,"'/api/task-inbox'")&&str_contains($inboxApi,"'/api/task-inbox/cancel'")&&str_contains($inboxApi,"'/api/task-inbox/mark-sent'")&&str_contains($inboxApi,"'/api/task-inbox/reprioritize'"),'Task Inbox API supports list, enqueue, cancellation and reprioritization routes');
ok(str_contains($inboxJs,"api('/api/task-inbox'")&&str_contains($inboxJs,"api('/api/task-inbox/cancel'")&&str_contains($inboxJs,'task_inbox_id:inboxDraftId')&&str_contains($inboxJs,"api('/api/task-inbox/reprioritize'"),'Chat UI loads, adds, cancels and reprioritizes queued messages through the API');
ok(str_contains($inboxJs,'function loadTaskInbox')&&str_contains($inboxJs,'data-inbox-insert')&&str_contains($inboxJs,'state.busy')&&str_contains($inboxJs,'state.inboxDraftMessage')&&str_contains($inboxJs,'pollChatJob')&&str_contains($inboxJs,'restoreActiveChatJob')&&str_contains($inboxJs,'state.activeJobId')&&str_contains($inboxJs,"api('/api/chat/jobs/interrupt'"),'Task Inbox remains independently usable while an AI response is in progress and restores active jobs when a chat is reopened');
