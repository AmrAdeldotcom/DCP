# DC55 — Cloud diagnosis, DP_MEMORY acquisition, temporary DCP memory

Date: 2026-11-25

## Cloud findings
- HTTPS/TLS to `https://dcp.amradel.com/` succeeds (HTTP 200, cert verification 0).
- DCP Cloud `CloudService::status()` calls anonymous device registration when no device token exists, then posts heartbeat and reads pairing lists.
- A disposable `DCP_USER_DATA_DIR` test against the installed stable v1.0.0 backend returned `connected=true`, `account_linked=false`, a generated device ID and verified heartbeat. Disposable DB/key were removed.
- Stable release has no persistent production user data yet (no `dcp.sqlite`, no `master.key`). No user account token is installed. User must sign in on the Devices page to link their cloud account. Do not copy source credentials.
- The app UI init already calls `loadCloudStatus()`. Release `app.js` is older than current source JS; do not patch installed app in place. Exact GUI/runtime process-to-userdata root still needs a live-open-app confirmation.

## DP_MEMORY
- Retrieved the original DP_MEMORY conversation file from Library and preserved its architecture: memory-core hot operational recall, Markdown warm vault, Cognee deep graph, unified MCP Gateway, scope/redaction/budget rules, asynchronous ingestion and soft-retire semantics.
- Cloned full upstream repos under `/Volumes/Data/DiGiPL/DP_MEMORY/sources/`: memory-core HEAD `f09f793`; Cognee HEAD `1961133d0`; official MCP servers HEAD `c799c855`. All remotes are fetch-only (`pushurl=DISABLED`).
- Wrote README, ARCHITECTURE, ROADMAP, DECISIONS and manifests. No install/build/run/dependency download/source edits; the project is deferred.

## DCP temporary memory implementation
- Added `MarkdownJsonMemory` and `DcpMemoryService`: hierarchical global/project/task/session/agent levels, Markdown+JSON, keyword recall, bootstrap/read, redaction, private file modes, soft retirement.
- Added REST `/api/memory/*` and read-only local MCP tools `dcp_memory_bootstrap`, `dcp_memory_recall`, `dcp_memory_read`. MCP memory mutation tools are deliberately blocked until explicit scopes/approval are implemented.
- Added tests and included them in platform suite; validate next.

## Next
1. Finish Q012 Task 4 tests and add bounded context/memory bootstrap to Chat worker without exposing full history unnecessarily.
2. Build DCP-owned MCP local STDIO + authenticated remote Streamable HTTP adapter over the same Core/Tool Registry, expose a minimal Core tool surface and signed remote transport contract. Do not depend on Desktop Commander.
3. Extend Cloud service/endpoint contract only after verifying server API and auth lifecycle; add pairing and DCP-Core task endpoints behind scopes.
4. Add plan/approval review API and Task 5 approval-bound execution gates; keep all proposed actions inert without approvals.
5. Preserve all changes local; no commit/push without review.
