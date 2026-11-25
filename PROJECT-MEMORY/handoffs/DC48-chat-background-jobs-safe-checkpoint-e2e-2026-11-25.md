# DC48 — DCP Chat Background Jobs and Safe Checkpoint E2E

Timestamp: 2026-11-25T06:53:33+02:00
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
Scope: DCP only. `DP_Anything` is explicitly deferred.

## Delivered
- `chat_jobs` additive SQLite table for durable chat job state.
- `ChatJobService` persists the user message and job before execution; validates provider/session/Task Inbox references; atomically claims jobs; persists assistant responses; tracks worker PID, status, errors, 30-minute lease and checkpoint intent.
- `platform/bin/chat-worker.php` loads the installation-local DCP config, SQLite DB, provider config and executes independently from the HTTP request.
- Detached process launch: `nohup` on POSIX; hidden PowerShell `Start-Process` on Windows.
- API: `POST /api/chat` returns a durable `job_id`; `GET /api/chat/jobs?id=...`; `GET /api/chat/jobs/active?session_id=...`; `POST /api/chat/jobs/interrupt`.
- Chat UI polls job status, restores active jobs when reopening a session, and offers `Interrupt at Checkpoint`.
- Task Inbox accepts new messages while the provider is working. Those messages stay queued; nothing is automatically executed or merged.
- Checkpoint semantics: if still queued, stop before provider execution; if already running, allow the current provider HTTP call to finish, persist its reply, then transition to `checkpoint_ready`. No unsafe process kill.
- Worker launch fallback preserves synchronous chat if the background launcher cannot start. A 30-minute lease fails closed when a worker disappears.

## Verified locally on macOS
- `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED`.
- `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- `python3 platform/tests/chat-job-http-e2e.py` → `DCP_CHAT_JOB_HTTP_E2E_PASS`.
- HTTP E2E used an authenticated isolated DCP runtime and a delayed mock OpenAI-compatible provider; proved inbox insertion during an active worker, checkpoint wait, response/history persistence, queued-message retention and no auto-execution.
- Chrome headless DOM → `DCP_DC48_CHROME_DOM_PASS`, including interrupt button and checkpoint status label.
- PHP lint, Node syntax, workflow YAML parse and `git diff --check` pass.

## Cross-platform CI
Added `.github/workflows/test-chat-worker.yml` matrix for `ubuntu-24.04`, `windows-2025`, `macos-15`, running both the real worker E2E and HTTP checkpoint E2E. This is committed locally but not yet pushed/run; do not claim cross-platform pass until Actions confirms all three.

## Explicit limits / next steps
1. Checkpoint currently occurs at the end of one provider HTTP call; no mid-tool-loop checkpoint yet.
2. No durable agent plan/context snapshot or resumable step graph yet.
3. No queue auto-merge; human review/insert/send remains mandatory.
4. Add lease heartbeat, crash recovery, idempotency keys and safe retry.
5. Add review/context merger/replanning and approval gate.
6. Verify Windows/Linux/macOS CI and actual packaged desktop acceptance.
7. Continue Q014 live Device Agent acceptance and Q005 installed-client update/rollback/data-preservation gates after Q012 baseline.

## Safety / repo hygiene
- No production user data, SQLite DB, `master.key`, provider secrets or installed app were touched; E2E uses disposable temp user-data roots.
- No release/deployment was performed.
- Preserve unrelated existing untracked historical handoffs/version files. Stage only Q012 implementation/tests/workflow and DC48 memory files.
