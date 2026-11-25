# Q012 — Shared chat, task inbox and agent collaboration

Date: 2026-11-25
Status: active — Tasks 1–4 implemented locally; Task 5 capability grants + private artifacts + explicit immutable-plan tool execution implemented locally; Task 6 review/approval API+UI implemented; Task 7 integrity-checked conversation resume and explicit per-step reviewed execution implemented; automatic multi-step execution remains disabled
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

### DC52 — shared MCP tool registry and agent policy boundary

- Extracted all seven existing DCP MCP tools into `DcpToolRegistry`, used by both the stdio MCP host and future in-process Chat coordinator. The MCP server no longer maintains a duplicate tool catalog or dispatch switch.
- Added explicit internal policy classes/capabilities: safe-read (`platform.status`, `terminal.safe-read`), sensitive-read (`provider.list`, `device.list`, `task.list`, `mail.read`) and state-change (`task.create`). Agent callers must have the matching enabled capability; sensitive reads and state changes require approval for the current plan version and exact canonical argument SHA-256.
- Public MCP JSON schemas do not expose internal policy metadata. Schema validation rejects unknown tools/properties, malformed types/enums, missing fields, excess actions, blank task metadata, invalid target IDs, absolute/traversal paths, write actions without content and file payloads over 1 MB.
- The existing MCP stdio host keeps its public seven-tool contract and dispatches through the shared registry. MCP dispatch requires an explicitly configured local integration context. Task creation remains `approval_required`; the MCP tool never executes its action payload.
- Added registry contract tests for allow-list, schema validation, capabilities, approval freshness, exact argument hash, and no automatic execution; added an isolated MCP JSON-RPC stdio E2E for initialize, tools/list, safe terminal, approval-required task creation and unknown-tool errors.
- Fixed `local-ai-ui-contract.php` so it returns control when included by `platform/tests/run.php`; the full platform suite now continues through update-manifest, Linux transactional updater, rollback, crash recovery and updater-health contracts rather than exiting early.
- Local `php platform/tests/run.php` now reaches `ALL PLATFORM TESTS PASSED`. Worker E2E and delayed-provider HTTP E2E also pass locally. GitHub run `45589253032` is running the full suite + worker + HTTP matrix on Ubuntu 24.04, Windows Server 2025 and macOS 15.
- Provider tool-call parsing and the actual agent coordinator are not implemented yet. `ProviderAdapter::chat()` remains unchanged; the next task is a backward-compatible provider-neutral structured tool-call contract.

### DC53 — provider-neutral tool-call contract (local implementation)

- Added optional `ToolCallingProviderAdapter` and immutable `ProviderToolCallResult`; existing `ProviderAdapter::chat()` remains string-returning and backward-compatible.
- `DcpToolRegistry::providerTools()` exports the same seven allow-listed MCP definitions as provider-neutral function schemas; no duplicate tool catalog was introduced.
- Added structured tool-call parsing to the Ollama and OpenAI-compatible adapters. Ollama Qwen3 requests explicitly disable thinking; OpenAI-compatible Qwen3 requests use `chat_template_kwargs.enable_thinking=false`.
- Normalization preserves assistant text, finish reason and refusal metadata; rejects malformed JSON, non-object arguments, invalid call IDs/names, oversized content/arguments, and more than 16 calls. Provider tool definitions are bounded to 32. Ollama calls without IDs receive deterministic SHA-256-derived IDs.
- `ChatService::sendWithTools()` exposes the optional capability without changing the ordinary `send()` contract. The bounded coordinator is not connected yet; provider-proposed calls are not executed by this change.
- Added mock HTTP provider fixtures and `platform/tests/provider-tool-calls.php` covering OpenAI-compatible + Ollama normalization, Qwen3 settings, ordinary chat compatibility, malformed/refusal/empty responses, excessive call count, invalid shapes, and ChatService dispatch.
- Initial worker E2E exposed a missing interface include in the detached CLI worker. Fixed `platform/bin/chat-worker.php` and its isolated E2E bootstrap to load the optional interface/result types; the background worker then passed again.
- Local evidence: `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED`; `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`; `python3 platform/tests/chat-job-http-e2e.py` → `DCP_CHAT_JOB_HTTP_E2E_PASS`; PHP lint, JS syntax and `git diff --check` pass.
- These provider-contract changes are still uncommitted/unpushed in the preserved source worktree. Task 1 has cross-platform CI evidence (Ubuntu/Windows/macOS run `45589846000`); Tasks 2–3 need the updated CI matrix before being marked cross-platform green.

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

## Next implementation plan

Detailed implementation breakdown: `tasks/plan.md`. Task 1 (shared MCP tool registry and policy/schema validation) is complete locally; cross-platform CI run `45589253032` is pending.

Ordered work: (1) shared MCP tool registry + explicit policy/schema validation, (2) provider-neutral structured tool-call contract, (3) bounded coordinator integrated with schema-v2 plan/step/result/approval persistence, (4) approval/review/resume APIs and UI, (5) worker heartbeat and crash-safe idempotent resume. No queued instruction or mutating tool may execute without explicit user review/approval.

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


### DC54 — bounded provider proposal coordinator (local implementation)

- Added `ChatExecutionCoordinator` to convert normalized provider tool calls into a bounded, versioned execution plan using the existing `ChatJobService` plan/step/approval persistence.
- Added `DcpToolRegistry::validateProposal()` so proposed calls are checked against the same allow-list, JSON schema and semantic path/content validation before they can enter a plan. No second tool catalogue was introduced.
- Coordinator limits: maximum 16 tool calls per provider response, 16 KB aggregate serialized arguments and 32 KB total plan metadata. Duplicate call IDs, unknown tools, invalid arguments and oversized plans are rejected before plan persistence.
- Each proposal records exact tool name, arguments, canonical SHA-256, classification, capability, provider call ID and deterministic idempotency key in the versioned plan. Approval-required tools create current-plan-version approval records and remain `blocked`; safe-read tools remain `planned`.
- Critical safety invariant: this coordinator slice does not execute any provider-proposed tool call. It returns `execution_started=false`, `automatic_execution=false` and `human_review_required=true`. Actual execution after explicit review/approval is the next integration boundary (Task 5).
- `ChatService::supportsToolCalls()` now identifies adapters that support structured calls. The detached worker uses `sendWithTools()` for compatible providers, persists proposed tool calls through the coordinator, and returns a review-required response; providers without structured tool-call support retain the legacy `send()` path.
- Local evidence: `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED`; focused background worker E2E → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`; PHP syntax checks pass.
- This implementation is local/uncommitted/unpushed. Cross-platform CI for the new coordinator/worker integration is pending.


### DC56 — DCP Core MCP local + Cloud transports

- Added `mcp_tokens` local table and `platform/bin/mcp-token.php`; token secrets are 256-bit random values, SHA-256 only is persisted, and the CLI prints the token once. Scopes include platform read, safe terminal, sensitive read, task creation and memory read.
- Added loopback-only local Streamable HTTP endpoint `POST /mcp` using the same `DcpToolRegistry` as STDIO. It requires Bearer token, validates loopback host/remote address and Origin, filters `tools/list` by token scopes, checks scopes again on `tools/call`, and rejects invalid protocol metadata/header mismatches.
- Added local HTTP MCP E2E for initialize, scope-filtered tools/list, safe status tool call, denied sensitive tool call, malicious Origin, protocol `2026-07-28` metadata and method-header mismatch. Result: `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS`.
- Added authenticated Cloud Streamable HTTP MCP endpoint `POST /api/v1/mcp` with account-bound tools `dcp_cloud_list_devices` and `dcp_cloud_pairing_overview`; both are read-only. Added `POST /api/v1/mcp-tokens` (one-time token, 30-day expiry) and owner-only `DELETE /api/v1/mcp-tokens/{id}`. Cloud token management uses existing API token table.
- Cloud contract tests now verify authentication, read-only scope, absence of remote task/terminal tools, token lifetime/revocation and protocol header/meta validation. Result: `ALL CLOUD CONTRACT CHECKS PASSED`.
- Important boundary: Cloud currently supports device presence/signaling only. No remote task dispatch or remote MCP execution is exposed. Local Core MCP is operational; Cloud MCP is a separate read-only control-plane gateway until secure task dispatch is completed.
- MCP Streamable HTTP supports JSON responses and protocol versions `2025-11-25` and `2026-07-28`; SSE streams/subscriptions and OAuth discovery remain future work.

### DC55 — temporary hierarchical memory + Cloud runtime fix

- Temporary DCP memory uses Markdown files as reviewable source and atomic JSON index as metadata/lookup. Includes global/project/task/session/agent levels, scoped bootstrap, keyword recall, secret redaction, private file modes, read-one and soft-retire with history preserved.
- Chat worker now injects only a compact scoped DCP memory summary pack (up to 8 summaries) before the user message; full files are never dumped into provider context.
- Fixed macOS embedded PHP Cloud timeout by setting `TMPDIR=/tmp` in the native app wrapper. Source-built app verified Cloud device identity + heartbeat; installed stable 1.0.0 was not modified.
- `platform/tests/dcp-memory.php` → `DCP_MEMORY_MD_JSON_PASS`; background worker E2E after memory integration → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.


### DC57 — Q012 Task 5 atomic execution ledger foundation

- Added `chat_job_tool_executions` table with unique idempotency key, plan version, step key, tool name, immutable argument hash, claim status, hashed one-time lease token, lease expiry, result reference/hash and timestamps.
- `ChatJobService::claimToolExecution()` re-reads the current immutable plan and validates plan version, exact arguments hash, dependency completion and explicit plan-version-bound approval before atomically claiming an approval-required step. It does not execute a tool.
- Duplicate claim while running returns `already_claimed`; duplicate after completion returns `already_completed`; expired lease is marked `failed_uncertain_outcome` and cannot be retried automatically; failed calls return `failed_review_required`.
- `completeToolExecution()` atomically records opaque result reference/hash and completes the step only with its one-time lease token; `failToolExecution()` closes the claim and preserves the no-auto-retry rule.
- Added `platform/tests/chat-execution-idempotency.php`, covering duplicate claim, result completion, replayed lease, missing approval, approved claim, failed no-retry, and expired uncertain lease. Result: `DCP_CHAT_TOOL_EXECUTION_IDEMPOTENCY_PASS`.
- This remains the execution ledger/claim foundation only. No tool invocation API, capability grant resolver, artifact storage, provider tool-result loop or auto-execution is enabled. Task 5 continues.


### DC58–DC61 — DCP Core local + Cloud MCP parity expansion

- Expanded the shared `DcpToolRegistry` from 8 to 31 tools. All local MCP transports consume the same registry; provider-neutral tool schemas also export the same definitions.
- Added `dcp_terminal_execute`: arbitrary foreground shell command execution under the current OS identity, selected shell, explicit working directory, 1–120 second timeout, 12 KB command bound and 64 KB output bound. No automatic privilege elevation.
- Added in-host interactive terminal sessions: start, read output, write stdin and stop. Session state is held by the long-lived STDIO MCP host process; sessions do not yet survive separate PHP HTTP requests or host restarts.
- Added workspace filesystem tools: read (256 KB cap), list (500 entries), recursive filename search (bounded), write/append (1 MB), mkdir, move/rename and delete file/empty directory. Relative paths only; reject traversal, absolute paths and symlinks; write/delete operations are separately classified and approval-gated for agent callers.
- Added DCP Core Cloud controls to the same registry: connection status, create pairing code, submit pairing request, approve/reject pairing, revoke pairing, request signaling connection, send bounded opaque signal envelope and poll signals. Cloud remains signaling/control plane; no remote terminal/task dispatch was enabled.
- Local Streamable HTTP MCP token scopes now include independent `terminal.execute`, `filesystem.read`, `filesystem.write`, `filesystem.delete`, `cloud.status.read`, `cloud.pairing.manage`, `cloud.connection.manage` and `cloud.connection.read` scopes. Both tools/list and tools/call enforce scopes. Local HTTP E2E verifies full terminal execution and denies filesystem writes when the write scope is absent.
- Cloud landing page redesigned using existing PHP/HTML/CSS (no new frontend framework) and translated for all nine DCP locales: ar, en, nl, es, pt, fr, de, zh, ja. Responsive dark visual style and Core-first positioning tested with isolated page renders. Cloud MCP stays authenticated and read-only for account device/pairing overview.
- Local evidence at DC61: `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED`; `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS`; `ALL CLOUD CONTRACT CHECKS PASSED`; all PHP lint and JS syntax checks pass; `git diff --check` passes. The full suite was rerun again after Task 6/7 additions; see DC63.

### DC62–DC63 — Q012 Task 6 review and Task 7 safe conversation resume

- Added `ChatJobService::executionReview()` returning current versioned plan, dependency steps, result references and complete approval history without executing tools.
- Added `GET /api/chat/jobs/execution-review?id=...` and CSRF-protected `POST /api/chat/jobs/approvals/decision`. Approval/rejection is plan-version scoped, audited, and explicitly returns `execution_started=false`.
- Chat UI now renders plan step statuses and approval history, provides explicit Approve/Reject actions, and records optional review notes. The UI does not claim that approval executes a tool.
- Added `validateSafeResumeCheckpoint()` verifying checkpoint ownership, exact supplied context hash, stored snapshot SHA-256 and `checkpoint_ready` state. It blocks resume if any tool execution has an unresolved claim or failed/uncertain outcome.
- Added `resumeConversationFromCheckpoint()` and CSRF-protected `POST /api/chat/jobs/resume`. User must provide a new instruction and explicitly confirm. Optional selected Task Inbox instruction is validated and linked to the new chat job. A new provider turn is created; old tool calls are never replayed.
- Chat UI provides a Resume Conversation action on verified checkpoints, clearly stating that this resumes conversation only and does not replay or execute the old plan.
- Task 7 step-level resume is intentionally not enabled: Q012 Task 5 still lacks the capability grant resolver, private bounded artifact/result store and actual immutable-plan tool dispatch boundary. `resumable=false` remains truthful for execution plans.
- Added tests for review plan/step/approval history, durable approval decisions, valid checkpoint resume preflight and forged/stale hash rejection; UI contract covers review/approval/resume wiring. Full platform suite after these additions and the worker bootstrap fix: `ALL PLATFORM TESTS PASSED`; `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`; `DCP_CHAT_JOB_HTTP_E2E_PASS`; `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS`; Cloud contract PASS; PHP lint, JS syntax and diff checks pass.

## Current Q012 task exit status

| Task | Status | Notes |
|---|---|---|
| 1 Shared Tool Registry | Complete locally; earlier cross-platform CI PASS | Registry is now expanded to 31 tools; new expansion needs fresh cross-platform CI |
| 2 Provider Contract | Complete locally | New expanded schemas need provider mock regression and fresh cross-platform CI |
| 3 Provider Parsing | Complete locally | Ollama/OpenAI-compatible adapters unchanged in this slice |
| 4 Bounded Coordinator | Complete locally | Persists proposals; no execution |
| 5 Results + Approval Execution | In progress / incomplete | Idempotency ledger and claim foundation exist; capability resolver, artifact store and invocation service still missing |
| 6 Review APIs/UI | Implemented locally | Plan/approval review and explicit approval/rejection decisions; no implicit execution |
| 7 Safe Resume | Conversation-only resume implemented locally | Integrity-checked explicit new turn; step-level execution resume remains disabled pending Task 5 |


### DC64 — DCP Core filesystem editing and process lifecycle

- Expanded shared local registry from 27 to 31 tools with `dcp_fs_stat`, `dcp_fs_edit`, `dcp_process_list` and `dcp_process_terminate`.
- `dcp_fs_read` now returns SHA-256. `dcp_fs_edit` requires that expected hash, an exact non-empty match occurring exactly once, and writes an atomic same-directory replacement; stale hashes and ambiguous matches fail closed. `dcp_fs_stat` returns bounded metadata and file hash.
- `dcp_process_list` returns a bounded process list for the current OS user only. `dcp_process_terminate` protects the current DCP process and parent, verifies target ownership, sends graceful SIGTERM/OS equivalent, and never escalates to force-kill.
- Added separate `process.read` and `process.terminate` local HTTP MCP scopes. Local HTTP E2E verifies process listing is visible only with read scope and termination remains hidden without its own scope.
- Tests cover atomic exact edit/hash, file metadata hash, bounded process list and protection against terminating the DCP host. macOS local tests pass; Windows process ownership implementation is syntax-reviewed but awaits Windows CI.


### DC65 — Q012 Task 5 execution boundary and reviewed step resume

- Added `chat_job_capability_grants`, bound to job, plan version, exact execution step, tool name and capability. An explicit approval decision creates the grant transactionally; rejection revokes it.
- Added `DcpCapabilityGrantService` to verify the exact current-plan grant and still-approved approval record immediately before invocation.
- Added `DcpToolResultArtifactStore`: private 0700 directory, 0600 atomic JSON artifacts, 256 KB cap, SHA-256, secret-field/Bearer redaction, opaque artifact IDs and job-bound retrieval.
- Added `DcpToolExecutionService`: reads tool name/arguments only from the immutable stored plan; verifies current registry policy, exact canonical argument hash, capability grant and approval; atomically claims one-time lease; invokes shared DcpToolRegistry as agent with exact plan-bound approval context; stores redacted result artifact; completes/fails ledger and audits execution. Failed/uncertain side effects cannot auto-retry.
- Added CSRF-protected `POST /api/chat/jobs/tools/execute` and job-bound `GET /api/chat/jobs/tool-result`. Chat review UI now displays exact proposed arguments and exposes a separate Execute-this-step action only after approval for mutating/sensitive steps; safe-read steps also require an explicit Execute click. Dependencies must be completed before the button is enabled. Approval itself never executes.
- Provider-neutral Chat tool schemas exclude STDIO-only interactive terminal session tools because PHP HTTP worker lifetimes cannot retain their in-memory process handles. Those session tools remain available through the persistent local STDIO MCP host; local HTTP MCP intentionally does not advertise them.
- Q012 Task 7 now reports `resumable=true` only when the current plan has a step graph and no running/claimed/failed execution. Resume remains human-reviewed and step-by-step; there is no automatic multi-step tool loop or replay. Conversation resume remains a separate explicit new-turn operation.
- Added `platform/tests/chat-tool-execution.php` E2E: approval creates exact capability grant, approved filesystem write executes from immutable plan, result artifact is private/readable by owning job, replay is blocked, secrets are redacted, revoked grant blocks further calls. Integrated into `platform/tests/run.php`.
- Final DC65 local validation: `php platform/tests/run.php` → ALL PLATFORM TESTS PASSED; local MCP HTTP E2E PASS; Cloud contract PASS; `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`; `DCP_CHAT_JOB_HTTP_E2E_PASS`; PHP lint, JS syntax and diff check PASS. Latest changes remain local/uncommitted; cross-platform CI pending.
