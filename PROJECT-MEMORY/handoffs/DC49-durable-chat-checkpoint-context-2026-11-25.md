# DC49 — Durable Chat Checkpoint Context Snapshot

Timestamp: 2026-11-25T07:05:46+02:00
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
Scope: DCP only; DP_Anything remains deferred.

## Delivered
- Added `chat_job_checkpoints` SQLite table and additive `chat_jobs.checkpoint_id` migration.
- When a running job reaches a requested provider boundary, the worker transaction stores the assistant response, marks any selected inbox item sent, creates the checkpoint snapshot and commits it atomically.
- When a queued job is interrupted before claim, a `paused_before_provider` snapshot is created without fabricating an assistant response.
- Snapshot contains job/session/provider/model, checkpoint kind, last provider call, last 30 conversation messages, up to 50 pending Task Inbox instructions, capture time, `human_review_required=true`, `automatic_execution=false` and a SHA-256 context hash.
- Added `GET /api/chat/jobs/checkpoint?id=...`.
- Chat UI now displays checkpoint ID, context hash, number and short previews of queued instructions. It explicitly states that nothing was merged or executed automatically.
- Extended unit + HTTP E2E tests to verify snapshot persistence, queue capture, hash integrity and manual-only semantics.

## Verification
- `php platform/tests/run.php` → ALL PLATFORM TESTS PASSED.
- `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- `python3 platform/tests/chat-job-http-e2e.py` → `DCP_CHAT_JOB_HTTP_E2E_PASS`, including HTTP checkpoint snapshot retrieval and SHA-256 hash verification.
- PHP lint, Node syntax and `git diff --check` pass.
- Final DC49 cross-platform run `45551354459` completed SUCCESS on Ubuntu 24.04, Windows Server 2025 and macOS 15. All three jobs passed PHP lint, real detached worker E2E and authenticated delayed-provider HTTP E2E including checkpoint snapshot retrieval and SHA-256 verification.

## Remaining
1. Extend snapshot with plan version, last confirmed agent step, tool result references, pending approvals and resumable step graph.
2. Build a human review/context merger that shows diffs and conflicts before explicit approval.
3. Add worker lease heartbeat, crash recovery, idempotency and safe retry.
4. Add separate-task mode and bounded concurrency.
5. Perform packaged desktop acceptance on installed Windows/Ubuntu/macOS targets; Q005 data-preserving update/rollback and Q014 live Device Agent acceptance remain separate gates.

## Safety
- Snapshot is a durable record, not an execution command.
- Pending instructions remain untrusted and queued. No automatic context merge or execution.
- Checkpoint only occurs after a provider call returns, not in the middle of a tool operation.
- E2E uses isolated temporary user-data roots; no production DB, master key or user files were touched.
- No release or deployment performed.
