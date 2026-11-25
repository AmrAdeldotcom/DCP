# DC57 — Q012 Task 5 idempotent execution ledger

Date: 2026-11-25
Branch: `feature/local-ai-runtime-discovery`
HEAD: `0522e14` (no commit created)

## Completed in this work session
- Fixed macOS embedded PHP outbound Cloud timeout by setting `TMPDIR=/tmp` in native wrapper. Source-built DCP app verified `connected=true` and device heartbeat; stable installed `/Volumes/Data/DiGiPL/DCP` remains v1.0.0 and unchanged.
- Downloaded full non-shallow upstream repositories under `/Volumes/Data/DiGiPL/DP_MEMORY/sources/`: memory-core `f09f793`, Cognee `1961133d`, MCP servers `c799c855`; upstream push disabled. Documented the separate deferred project architecture/roadmap/decisions.
- Implemented temporary hierarchical DCP Markdown+JSON memory, REST routes, read-only MCP memory tools and compact scoped memory bootstrap in Chat worker.
- Added local loopback-only Streamable HTTP MCP endpoint `/mcp`, token provisioning/revocation CLI, scope filtering, Origin validation and protocol 2026-07-28 metadata/header validation.
- Added Cloud `/api/v1/mcp` authenticated account-scoped read-only device/pairing tools and API token create/revoke endpoints. No remote task dispatch or terminal tools are exposed.
- Added Q012 Task 5 atomic execution claim/idempotency ledger, one-time lease token, plan/hash/approval/dependency revalidation, atomic completion/failure, and fail-closed expired/uncertain execution handling.

## Verification
- `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED` (includes local MCP HTTP E2E and execution-ledger E2E).
- `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- `python3 platform/tests/chat-job-http-e2e.py` → `DCP_CHAT_JOB_HTTP_E2E_PASS`.
- `php platform/tests/mcp-http-e2e.php` → `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS`.
- `php platform/tests/chat-execution-idempotency.php` → `DCP_CHAT_TOOL_EXECUTION_IDEMPOTENCY_PASS`.
- `php platform/tests/cloud-contract.php` → `ALL CLOUD CONTRACT CHECKS PASSED`.
- PHP lint 76 files; JS syntax, shell syntax, installer contract and `git diff --check` pass.

## Current Q012 status
- Task 1 shared registry: complete; previous CI run 45589846000 passed Ubuntu/Windows/macOS.
- Tasks 2–3 provider contract/parsing: locally complete, updated cross-platform CI pending.
- Task 4 bounded proposal coordinator: locally complete, no tool execution.
- Task 5: atomic idempotency claim/result state foundation now present; actual invocation is not yet enabled. Need explicit capability grants, private bounded artifact store/redaction, execution dispatch from immutable plan only, audit and result handoff.
- Task 6 review APIs/UI: not started.
- Task 7 safe resume: not started; `resumable=false`, human review required.

## Boundaries
- DP_MEMORY is separate and deferred; no dependencies/build/run/deploy.
- Cloud MCP is read-only for Cloud account/device metadata. Remote execution remains unavailable.
- No commit, push, release, or modification of installed stable v1.0.0.
- Do not reset/clean source worktree; 80 modified/untracked paths include pre-existing user work and current local changes.
- `/Volumes/Data` has ~9.2 GiB free (99% used); avoid large installs.
