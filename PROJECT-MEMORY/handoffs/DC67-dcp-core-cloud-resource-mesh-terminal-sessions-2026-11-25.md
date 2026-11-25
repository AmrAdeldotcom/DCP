# DC67 — Full Terminal, Resource Mesh, Cloud Grants and Persistent Sessions

Date: 2026-11-25
Branch: feature/local-ai-runtime-discovery
HEAD at start: 0522e14
Working directory: /Volumes/Data/ChatGPT/DCP-Source

## User direction
- Q005 is complete and must never be reopened as the next task.
- Q012 Tasks 6 and 7 are complete. Continue DCP Core and Cloud, without cycling back to Q005.
- There is one terminal interface only: dcp_terminal_execute. It runs general shell commands, pipelines and scripts as the current OS user. A remote terminal request must be explicitly approved by the user on the target device before execution.

## Full terminal consolidation
- Removed dcp_run_safe_terminal from the shared registry, policies, local API/UI wording and operational docs.
- Removed the old TerminalService command allow-list method.
- dcp_terminal_execute is the sole shell command tool; local execution uses current OS identity, no privilege elevation.
- Agent/provider proposals require exact plan-version-bound approval before local tool execution.
- Remote terminal execution remains disabled pending target approval UI and signed dispatch. Pairing or requester-side consent is not sufficient.

## Persistent terminal sessions
- Added app/Services/DcpTerminalSessionClient.php and bin/terminal-session-daemon.php.
- Private runtime/token/state live under DCP user data runtime/terminal-sessions; daemon binds only 127.0.0.1 and authenticates each request with a 256-bit local token.
- TerminalService delegates session start/read/write/stop to the daemon for normal application contexts; daemon worker retains process handles beyond individual PHP request lifetimes.
- STDIO MCP, local HTTP MCP and provider-neutral tools now expose all 35 tools, including interactive session start/read/write/stop.
- Added daemon sharing E2E across two independent TerminalService instances.
- Added HTTP MCP E2E that starts a session in one HTTP request, reads it in later requests and stops it.
- Raised provider tool-definition count cap from 32 to 64 to support the 35-tool registry; provider tool-call count remains separately bounded.
- Limitations: this is a persistent process/pipe session daemon, not yet native PTY/ConPTY terminal emulation. Daemon crash recovery/reattach is not implemented. Windows detached launcher path has not been executed on Windows in this turn.

## Local Resource Mesh
- Added DcpResourceRegistryService with register, health/capacity heartbeat, filtered discovery, policy-aware local-first broker, explicit grants/revocation, authorization and invocation ledger.
- Added SQLite tables: dcp_resources, dcp_resource_grants, dcp_resource_invocations.
- Added REST routes under /api/resources for register, list/discover, heartbeat, grant/revoke, broker and invocation begin/finish.
- Added four MCP tools: dcp_resource_discover, dcp_resource_register, dcp_resource_grant, dcp_resource_revoke.
- Resource metadata rejects raw credentials; secret_ref is an opaque local-vault reference. Actual vault resolution/provider invocation adapters remain future work.
- Local grant discovery currently supports explicit user grants; local team membership integration is not complete.

## Cloud Resource Mesh and target-side terminal approval
- Added MySQL schema and upgrade migration for cloud_teams, cloud_team_members, cloud_resources, cloud_resource_grants, cloud_resource_invocations and cloud_remote_terminal_requests.
- Added authenticated Cloud endpoints for device resource registration/heartbeat, account discovery, grant/revoke, team creation/member management.
- Cloud stores metadata and opaque secret references only; raw API credentials are rejected.
- Added terminal.request pairing permission. It only allows submitting a request, not execution.
- Added Cloud remote terminal request creation, target-device inbox, exact-command SHA-256 binding, five-minute expiry and explicit approve/reject on the target device.\n- Added source-side Terminal target selector, populated only from paired devices with terminal.request scope; source sends an approval-only request and sees request ID/status/hash. Added target-side Devices inbox with exact command, requester, working directory, shell, timeout, expiry and hash; approve/reject is explicit.
- Approval is recorded against the exact command hash and target device. API response explicitly says the command is not dispatched.
- Signed delivery, execution polling and signed results are not implemented yet; target UI only records approval and no remote terminal execution is exposed.

## Verification
- php platform/tests/run.php: ALL PLATFORM TESTS PASSED after Resource Registry, Cloud contracts, terminal daemon, HTTP session reconnect, and 35-tool provider schema changes.
- The suite includes Q012 Tasks 5, 6 and 7, MCP STDIO keepalive/logo, MCP local HTTP, Resource Registry grants/invocation, daemon sharing, HTTP session reconnect, Cloud schema/migration contracts and remote terminal approval contract.
- PHP lint passed for new service, daemon, Cloud index, tests and provider adapters during this turn.
- git diff --check passed before final docs/checkpoint updates; rerun final lint/diff checks after the checkpoint edits.
- No commit, push, release, Cloud production migration/deploy, stable install modification or external release action.

## Queue correction
- Q005: closed per user confirmation. Do not reopen or use it as a blocker.
- Q012 Tasks 6 and 7: complete. Q012 automatic multi-step loop and worker heartbeat/recovery remain separate future work.
- Q015: active and partially implemented. See platform/docs/DCP-RESOURCE-MESH-AND-UNIVERSAL-AI-GATEWAY.md and WORK-QUEUE.md.

## Next work
1. Build target-device approval UI in DCP Core and connect it to the Cloud remote-terminal inbox.
2. Add signed Cloud dispatch envelope and signed result flow; target device executes only after its own explicit approval and exact hash verification.
3. Add owner-local secret vault resolution and actual API/model/MCP resource invocation adapters; enforce grant quotas/concurrency and team/device grant discovery locally.
4. Integrate Hermes Agent adapter and universal AI task gateway.
5. Add native PTY/ConPTY and daemon restart recovery.
6. Validate current code on Windows and Ubuntu, then live Claude reconnect/logo behavior.
7. Return to the remaining original Q012 follow-on items; do not return to Q005.

No production deployment or repository publication is authorized by this checkpoint.
