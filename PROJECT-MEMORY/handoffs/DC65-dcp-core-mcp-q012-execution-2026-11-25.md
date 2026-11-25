# DC65 handoff — DCP Core MCP parity + Q012 Tasks 5–7

Date: 2026-11-25 20:45 EET
Repository: `/Volumes/Data/ChatGPT/DCP-Source`
Branch: `feature/local-ai-runtime-discovery`
HEAD: `0522e14` (source worktree contains uncommitted work; no commit/push)

## User direction
DCP Core must reach practical Desktop Commander parity, including a full terminal, while remaining independent of Desktop Commander. DCP Cloud should be a lightweight PHP/JS/CSS control plane that connects to DCP Core and supports all nine DCP locales. After local/Cloud MCP work, continue Q012 Tasks 6 and 7.

## DCP Core local MCP

- Shared `DcpToolRegistry` contains 31 local STDIO tools.
- Local Streamable HTTP MCP and provider-neutral Chat tool schemas expose 27 tools. Four interactive terminal session operations are STDIO-only because per-request PHP HTTP workers cannot own persistent process handles. HTTP explicitly omits/rejects them rather than advertising broken support.
- Full foreground shell: command, shell, cwd, bounded 1–120 second timeout, 12 KB command and 64 KB output; executes as current OS user, no privilege elevation.
- Interactive STDIO terminal: start/read/stdin/stop in long-lived MCP host process; sessions die with host restart.
- Filesystem: read/list/name-search/write/append/mkdir/move/delete/stat/exact atomic edit. Workspace-relative paths, symlink/traversal rejection, 256 KB read, 1 MB write/edit target, 500-entry listing and bounded search. Edit requires expected SHA-256 and exactly one match.
- Process: current-user process listing and ownership-checked graceful termination; DCP host and parent protected; no force-kill escalation.
- Local Core Cloud tools: status, pairing-code/request/decision/revoke, signaling connection/send/poll.
- Local HTTP MCP scopes independently enforce terminal, filesystem, process and Cloud permissions. HTTP E2E verifies full terminal execution, write-scope denial and process read/terminate separation.

## DCP Cloud

- `platform/cloud/index.php` landing page redesigned with existing PHP/HTML/CSS and responsive dark visual style.
- Nine locales: ar, en, nl, es, pt, fr, de, zh, ja. Isolated page render test passes all nine.
- Cloud remote MCP remains authenticated and read-only for account device/pairing overview. It does not execute terminal/filesystem commands or dispatch remote tasks.
- Local DCP Core exposes pairing/signaling controls through CloudService. Signed remote task dispatch remains disabled until DCP Node execution, capability grants, signed envelopes and results are integrated end-to-end.
- No production Cloud deploy; APP_KEY rotation approval remains a separate blocker.

## Q012 Tasks 5–7

### Task 5 — explicit execution boundary
- Added `chat_job_capability_grants` bound to job, plan version, step, tool and capability. Explicit approval creates grant transactionally; rejection revokes it.
- Added `DcpCapabilityGrantService`, `DcpToolResultArtifactStore`, `DcpToolExecutionService`.
- Artifact store: private POSIX 0700 directory/0600 files and Windows owner/SYSTEM/Administrators ACLs, atomic writes, 256 KB cap, SHA-256, secret-field/Bearer redaction, opaque IDs and job-bound retrieval.
- Execution service re-reads tool and args only from immutable plan, checks current registry policy + canonical args hash + current approval/grant, claims one-time lease, invokes shared registry as agent, writes artifact, completes/fails ledger and audits.
- API: CSRF-protected `POST /api/chat/jobs/tools/execute`; `GET /api/chat/jobs/tool-result` returns only an artifact linked to that job.
- UI shows exact tool args and a separate Execute button; approval alone never executes. Dependencies must be completed first. Failed/uncertain side effects fail closed and are not auto-retried.

### Task 6 — review API/UI
- `GET /api/chat/jobs/execution-review?id=...` returns plan, dependency graph, tool result refs and approval history.
- CSRF `POST /api/chat/jobs/approvals/decision` records approved/rejected decision and audit; no execution.
- UI displays step status, exact args, approval history and explicit per-step Execute control.

### Task 7 — safe resume
- Checkpoint ownership, exact context hash and stored SHA-256 are verified. Running/claimed/failed/uncertain steps block resume.
- `resumable=true` only when current plan has a step graph and no running/claimed/failed execution; `resume_requires_review=true`, `automatic_execution=false`.
- Step resume is explicit, one step at a time, dependency-aware, with approval/grant and idempotency. No automatic multi-step replay.
- Conversation resume remains a separate explicit new turn from a verified checkpoint; it never replays old tool calls.

## Validation

- `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED` (includes actual approved filesystem write through execution service, capability grant, artifact, redaction and replay prevention).
- `php platform/tests/mcp-http-e2e.php` → `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS`.
- `php platform/tests/cloud-contract.php` → `ALL CLOUD CONTRACT CHECKS PASSED`.
- `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- `python3 platform/tests/chat-job-http-e2e.py` → `DCP_CHAT_JOB_HTTP_E2E_PASS`.
- PHP lint, `node --check platform/public/assets/app.js`, JSON validation and `git diff --check` pass.

## Remaining / not claimed complete

1. Cross-platform CI for latest 31-tool registry and execution boundary on Windows/Linux; current real-host verification is macOS.
2. Durable PTY/session daemon shared across HTTP and STDIO, restart persistence and output pagination.
3. More filesystem parity: multi-file batch, content search, chmod/metadata and document-specific formats.
4. Cloud signed task dispatch/acknowledgement/result transfer to DCP Nodes, then live device acceptance.
5. Q012 automatic multi-step coordinator loop, worker heartbeat/crash recovery and task lifecycle remain open.
6. Production Cloud deployment blocked pending APP_KEY rotation approval.

## Safety and release state

- No commit, push, release, Cloud production deploy or stable v1.0.0 modification.
- Source tree remains intentionally dirty; never reset/clean it. Stable install remains `/Volumes/Data/DiGiPL/DCP`.
- No dependencies or large runtimes installed; `/Volumes/Data` has ~9.2 GiB free (99% used).
- DP_MEMORY remains a separate deferred project at `/Volumes/Data/DiGiPL/DP_MEMORY`.

## Next action

Run/trigger cross-platform CI only after the user authorizes a push. Continue PTY/session manager and remaining DC parity, then secure signed Cloud dispatch. Q012 automatic loop/heartbeat follows after these execution primitives.
