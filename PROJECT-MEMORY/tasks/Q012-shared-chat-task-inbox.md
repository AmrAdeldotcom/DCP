# Q012 — Shared chat, task inbox and agent collaboration

Date: 2026-11-25
Status: active — persistent Task Inbox, background Chat Jobs and provider-boundary safe-checkpoint request implemented and end-to-end tested locally; full Q012 remains open
Roadmap: Stage 6 — Device Collaboration and Agents
Source spec: `platform/docs/proposals/DCP-Chat-Live-Task-Inbox-Interrupt-Queue.md`

## Delivered slices: persistent Task Inbox + background Chat Jobs + provider-boundary checkpoint

- Added additive SQLite `task_inbox` table with stable IDs, optional chat-session association, status, priority and timestamps; indexed active queue lookups.
- Added `TaskInboxService` with enqueue, bounded list, reprioritize, cancel and mark-sent transitions. Messages are limited to 20,000 characters; empty messages and unknown session references are rejected.
- Added CSRF-protected Task Inbox API routes for enqueue, reprioritize, cancel and mark-sent; GET list is bounded to 50 by default and capped at 100. Queue actions are written to `audit_log`.
- Added Chat UI panel that accepts messages while a provider request is busy, persists them locally, lets the user move them to the top/bottom, insert them into the composer, remove them, and marks them sent only after the associated chat job completes.
- Unassigned messages queued while the first chat request is starting remain visible after the session ID is created.
- Added additive SQLite `chat_jobs` table and `ChatJobService`: user message + job are committed before execution; jobs carry provider/model/history, durable status, reply/error, worker PID, 30-minute lease and checkpoint-request state.
- Chat now starts an independent PHP CLI worker (POSIX detached process; Windows detached Windows `cmd.exe start /B` launcher). The worker opens the same installation-local SQLite database, loads the saved provider configuration and persists the assistant response independently of the HTTP request lifetime.
- Added job status, active-job recovery and durable checkpoint retrieval API endpoints. Reopening a chat resumes polling the active job instead of losing its in-progress UI state.
- Added `Interrupt at Checkpoint`: queued jobs can be paused before provider execution; running jobs finish the current provider call, persist the complete response, then transition to `checkpoint_ready`. It does not kill an in-flight request or interrupt a tool/file/database write mid-operation. Every ready checkpoint now persists a `chat_job_checkpoints` snapshot with the last provider call, last 30 conversation messages, up to 50 queued instructions, a SHA-256 context hash and explicit `human_review_required=true` / `automatic_execution=false` flags. Queued-before-claim pauses also create a snapshot without inventing a provider response.
- Task Inbox instructions remain untrusted, visible and queued. They are never executed automatically or silently merged into a provider request. The user must review/insert/send them explicitly.
- Worker startup failure falls back to the synchronous ChatService path; expired worker leases fail closed and expose an actionable error rather than leaving the UI busy indefinitely.

### DC50 — execution-plan checkpoint context foundation (local implementation)

- Added durable SQLite tables for versioned chat execution plans, ordered dependency steps, opaque tool-result references and plan-version-scoped approval requests.
- `ChatJobService` can persist a bounded plan (maximum 100 steps / 32 KB plan metadata), validate unique step keys and dependency references, and reject dependency cycles.
- Step transitions are explicit (`planned → running → completed|blocked|failed`; blocked/failed may be retried deliberately). A dependent step cannot enter `running` or `completed` until all dependencies are confirmed `completed`.
- Tool outputs are not copied into checkpoint JSON. Only opaque result references and SHA-256 hashes are stored.
- Approval requests remain pending until an explicit `approved` or `rejected` decision. Decisions are scoped to the current plan version and never trigger execution.
- Checkpoint snapshot schema is now version 2 and includes `execution_context`: plan version/metadata, last confirmed completed step, dependency graph, tool-result references and pending approvals.
- A snapshot without a recorded execution plan explicitly reports `step_graph_available=false` and `resumable=false`. Even with a plan graph, `resumable=false` and `resume_requires_review=true`: the actual multi-step coordinator/worker resume loop is not implemented yet.
- This is a persistence and safety foundation, not a live agent planner. Current chat worker still performs one provider call; no tool loop was enabled and no queued instruction is automatically merged or executed.

## Tests / evidence

- `platform/tests/task-inbox.php`: persistence, session association, unassigned-message visibility, priority, sent/cancelled state transitions, empty/oversize rejection, unknown-session rejection, priority bounds and duplicate transitions.
- `platform/tests/task-inbox-ui-contract.php`: Chat markup, API routes, UI wiring, insert-to-composer, active-job restoration and checkpoint controls.
- `platform/tests/chat-jobs.php`: atomic queue claim, provider-boundary checkpoint, durable snapshot/hash, pending-instruction capture, queued-before-claim snapshot, persisted response, inbox sent-state, failed job, lease expiry and invalid inbox reference.
- `platform/tests/chat-worker-e2e.php`: real detached CLI worker starts, loads an isolated DCP installation/provider config, calls a local mock provider and persists one user + one assistant message.
- `platform/tests/chat-job-http-e2e.py`: authenticated HTTP API E2E with a delayed mock provider; proves a Task Inbox message can be added while the worker runs, checkpoint waits for provider completion, the snapshot/hash API returns the conversation and pending queue, response/history persist, and queued instructions are not auto-executed.
- `.github/workflows/test-chat-worker.yml`: cross-platform Ubuntu/Windows/macOS matrix for both worker process and HTTP checkpoint E2E; run `45551354459` passed all 3 OS jobs after adding the durable checkpoint snapshot/hash checks.
- Both test files are loaded by `platform/tests/run.php`.
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- PHP lint across `platform/app`, `platform/bootstrap`, and `platform/tests`: passed.
- `node --check platform/public/assets/app.js`: passed.
- Isolated PHP built-in-server HTTP smoke (`DCP_Q012_HTTP_TASK_INBOX_PASS`): CSRF rejection, enqueue, list, priority update, mark-sent and cancel all passed using a disposable DCP user-data root.
- Isolated Chrome DOM smoke: `DCP_DC44_CHROME_DOM_PASS`; the real browser rendered the Task Inbox controls and capability matrix.
- `python3 platform/tests/chat-job-http-e2e.py`: `DCP_CHAT_JOB_HTTP_E2E_PASS` (including durable snapshot and SHA-256 verification).
- DC50 execution-plan tests: DAG cycle rejection, dependency-gated transitions, monotonic plan versions, opaque tool-result references, pending/decided approval state, and schema-v2 checkpoint context all pass as part of `php platform/tests/run.php`.
- DC50 local worker E2E: `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`; delayed-provider HTTP E2E: `DCP_CHAT_JOB_HTTP_E2E_PASS`; PHP lint across app/bootstrap/tests, JS syntax and `git diff --check` pass.
- `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php`: `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- Isolated Chrome DOM smoke: `DCP_DC48_CHROME_DOM_PASS`; checkpoint button/status and Task Inbox rendered.
- `git diff --check`: passed.

### DC51 — Windows workspace ACL and cross-platform full-suite validation

- Windows workspace directories now use NTFS DACLs restricted to the current process user SID, SYSTEM and Administrators. Broad Everyone, Authenticated Users and Users grants are removed.
- Workspace identity manifests receive an explicit restricted ACL; child files inherit the private workspace DACL.
- ACL updates are deliberately non-recursive so a nested symbolic link cannot cause ACL changes to an external target.
- Workspace permission tests are OS-aware: Unix checks 0700 directories / 0600 files; Windows checks the effective named ACL principals. Device Agent output path comparison normalizes Windows separators.
- Local full platform suite passes. GitHub full platform + background worker + HTTP E2E matrix run `45587700487`: Ubuntu 24.04 PASS, Windows Server 2025 PASS, macOS 15 queued at last check.

## Remaining Q012 / Stage 6 work

1. Connect the actual agent execution coordinator/tool loop to the new plan/step/result/approval persistence APIs; implement safe step-level resume with idempotency and recovery.
2. Add queue review + context merger/replanning. A checkpoint must present the new instructions and their impact; conflicting/sensitive changes require explicit user approval before continuation.
3. Add worker lease renewal/heartbeat, crash recovery and safe retry with idempotency keys. Current 30-minute lease is a fail-closed timeout, not resumable execution.
4. Add task lifecycle/event timeline (`reviewing`, `ready_to_integrate`, `integrated`, `executing`, `completed`, `blocked`, `rejected`, `cancelled`) and link each inbox message to a task.
5. Add `Run as Separate Task` with bounded concurrency and independent session context.
6. Add authenticated remote participants and `@dc` / `@dsh` dispatch only after transport and per-device acceptance are verified.
7. Browser collaboration and hierarchical memory remain later Stage 6 slices.

**Important limitation:** the current checkpoint boundary is the end of one provider HTTP call. The snapshot preserves conversation + queued instructions, but it is not yet a checkpoint inside a multi-step agent/tool execution loop and does not automatically merge or execute queued messages.

## Safety boundaries

- Queue messages are untrusted user instructions and are never executed by this slice.
- Chat provider calls now execute in an independent worker; provider adapter semantics remain unchanged.
- No remote task transport, pairing scope or Cloud deployment was changed by Q012.
- No secrets, credentials, keys, SQLite production data or user files were accessed or modified.
- Changes are pushed on `feature/local-ai-runtime-discovery`; cross-platform worker + durable checkpoint snapshot CI run `45551354459` passed Ubuntu 24.04, Windows Server 2025 and macOS 15. No release or deployment was made.
