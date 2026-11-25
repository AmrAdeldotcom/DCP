# Q012 — Shared chat, task inbox and agent collaboration

Date: 2026-11-25
Status: active — first Task Inbox queue slice implemented locally; full Q012 remains open
Roadmap: Stage 6 — Device Collaboration and Agents
Source spec: `platform/docs/proposals/DCP-Chat-Live-Task-Inbox-Interrupt-Queue.md`

## Delivered slice: persistent Task Inbox Queue MVP

- Added additive SQLite `task_inbox` table with stable IDs, optional chat-session association, status, priority and timestamps; indexed active queue lookups.
- Added `TaskInboxService` with enqueue, bounded list, reprioritize, cancel and mark-sent transitions. Messages are limited to 20,000 characters; empty messages and unknown session references are rejected.
- Added CSRF-protected API routes for enqueue, reprioritize, cancel and mark-sent; GET list is bounded to 50 by default and capped at 100. Queue actions are written to `audit_log`.
- Added Chat UI panel that can accept messages while the provider request is busy, persists them locally, lets the user move them to the top/bottom, insert into the composer, remove them, and marks them sent only after the chat API succeeds.
- Unassigned messages queued while the first chat request is starting remain visible after the session ID is created.
- The inbox is deliberately not an autonomous execution queue yet. It never runs a queued instruction automatically and does not claim that the currently running provider call can be interrupted.

## Tests / evidence

- `platform/tests/task-inbox.php`: persistence, session association, unassigned-message visibility, priority, sent/cancelled state transitions, empty/oversize rejection, unknown-session rejection, priority bounds and duplicate transitions.
- `platform/tests/task-inbox-ui-contract.php`: Chat markup, API routes, UI wiring, insert-to-composer and independent busy-state behavior.
- Both test files are loaded by `platform/tests/run.php`.
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- PHP lint across `platform/app`, `platform/bootstrap`, and `platform/tests`: passed.
- `node --check platform/public/assets/app.js`: passed.
- Isolated PHP built-in-server HTTP smoke (`DCP_Q012_HTTP_TASK_INBOX_PASS`): CSRF rejection, enqueue, list, priority update, mark-sent and cancel all passed using a disposable DCP user-data root.
- Isolated Chrome DOM smoke: `DCP_DC44_CHROME_DOM_PASS`; the real browser rendered the Task Inbox controls and capability matrix.
- `git diff --check`: passed.

## Remaining Q012 / Stage 6 work

1. Add an execution coordinator with safe checkpoints; do not abort an in-flight tool/database write.
2. Add context merge/replanning and explicit approval for conflicting or sensitive instructions.
3. Add durable task execution checkpoints, idempotency keys and restart recovery; distinguish queued, reviewing, ready, executing, completed, blocked, rejected and cancelled.
4. Define per-device workspace and capability matrix, signed task envelope, replay protection, scoped remote execution and audit.
5. Add shared chat participants and explicit `@dc` / `@dsh` dispatch only after authenticated agent lifecycle adapters and transport are verified.
6. Browser collaboration and hierarchical memory remain later Stage 6 slices.

## Safety boundaries

- Queue messages are untrusted user instructions and are never executed by this slice.
- Existing chat provider execution behavior remains unchanged.
- No remote task transport, pairing scope or Cloud deployment was changed.
- No secrets, credentials, keys, SQLite production data or user files were accessed or modified.
- Changes are local on `feature/local-ai-runtime-discovery`; no commit, push, merge, release or deployment was made.
