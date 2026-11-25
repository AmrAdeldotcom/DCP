# DCP Core Desktop Commander Parity & Cloud Integration Roadmap

Date: 2026-11-25

## Product decision

DCP Core is the primary control plane and MCP interface. Its local device capabilities must reach practical Desktop Commander parity. Desktop Commander remains an optional adapter/reference, not a runtime dependency. REA-grade reverse engineering is explicitly out of scope for parity.

## Local parity contract (minimum)

1. Full terminal: shell selection, arbitrary commands as the logged-in OS user, working directory, bounded foreground execution, long-running/background sessions, stdin interaction, output pagination, process listing and termination.
2. Filesystem: read/write/append, multi-read, directory create/list, move/rename, metadata, recursive search, surgical edit, bounded output and supported document formats where practical.
3. Runtime/process: start, inspect, interact, terminate, list sessions/processes, capture stdout/stderr and exit state.
4. Configuration: inspect/change DCP execution defaults and workspace policies with explicit authorization and audit.
5. MCP: same canonical tool registry for STDIO and Streamable HTTP. CLI and dashboard call the same service layer.
6. Cross-platform: macOS, Windows and Linux adapters; no assumption that POSIX shell syntax exists on Windows.

## Security contract

- Terminal executes as the DCP service's current OS identity. It does not silently elevate privileges or bypass OS permissions.
- MCP client permission toggles are not a replacement for DCP authorization. DCP policy, device grants, task approval, audit and revocation remain authoritative.
- File-operation roots are explicit. Shell access is not falsely described as sandboxed by filesystem allowlists.
- Destructive/high-impact operations are separately classified and can require plan-bound approval. No automatic `sudo`, administrator, or root elevation.
- Every execution records caller, device, command/tool, working directory, grant, approval, timestamps, exit status and bounded result reference.

## Cloud integration decision

Keep the current implementation stack: PHP API/backend, MySQL persistence, existing JavaScript/CSS UI, existing device identity/pairing/signaling. Do not introduce Node/Next.js or a second cloud runtime.

Cloud is a control plane and authenticated relay/signaling path, not a second terminal host. A local DCP Node maintains outbound authenticated connectivity and executes locally granted capabilities. Cloud authenticates the account/device, routes signed task envelopes, tracks status/results, enforces expiry/replay/idempotency, and exposes the same DCP Core MCP tool contract through a remote authenticated endpoint. Cloud must never execute shell commands on the web host on behalf of a paired desktop.

## Cloud UX minimum

Create a polished responsive Cloud portal using existing design tokens, CSS and vanilla JS: account/device overview, online state, pairing, capability grants per device, task inbox/approvals, execution progress, results/artifacts, audit history, token/client management. Reuse existing API routes and shared UI patterns. No frontend framework migration.

## Current implementation delta (DC58)

- Added `dcp_terminal_execute` to the shared local DCP tool registry.
- Supports arbitrary foreground shell commands, selected shell, working directory and bounded timeout (1–120 seconds), output capped at 64 KiB.
- Runs as the current OS user; no privilege elevation.
- Added `terminal.execute` scope for local Streamable HTTP MCP tokens.
- The restricted `dcp_run_safe_terminal` tool and allow-list are removed; `dcp_terminal_execute` is the only terminal command interface.

## Remaining work, ordered

A. Finish Desktop Commander parity: persistent interactive sessions, stdin, process lifecycle, filesystem CRUD/search/edit, metadata and bounded file formats.
B. Add unified authorization/capability grants and audit for every tool; local Claude's Always Allow must not override DCP policy.
C. Expose the exact same registry to STDIO and local HTTP, then cloud MCP with device-scoped grants.
D. Implement signed cloud task dispatch to DCP Nodes, acknowledgements, result/artifact upload, retries with idempotency and offline handling.
E. Build the lightweight PHP/JS/CSS Cloud portal on top of the existing API.
F. Cross-platform integration tests on macOS, Windows and Linux; then deploy Cloud migration/code and validate live.

## Explicit non-goals

- Replacing PHP with Node/Next.js.
- Reimplementing REA reverse engineering.
- Running remote terminal commands inside the Cloud web server.
- Treating MCP client consent as a secure sandbox or as the only authorization layer.

## DC65 current implementation status

- Local STDIO, local Streamable HTTP and provider-neutral provider schemas expose all 35 tools. The DCP terminal session daemon owns interactive processes independently of PHP request lifetimes and shares them across STDIO, HTTP and provider adapters.
- Terminal: full foreground shell execution (bounded command/time/output), STDIO-host interactive session start/read/stdin/stop, current-user process listing and graceful ownership-checked termination. No privilege escalation or force-kill fallback.
- Filesystem: bounded read/list/name-search/write/append/mkdir/move/delete/stat and SHA-256-guarded atomic exact text edit. Relative workspace paths only for filesystem tools; symlinks/traversal rejected. File and search size/count limits apply.
- Cloud Core tools: status, pairing-code/request/decision/revoke and signaling connection/send/poll. Cloud remote MCP itself remains authenticated and read-only for account device/pairing information; remote task/terminal dispatch is not yet exposed.
- Authorization/execution: local HTTP token scopes are separated for terminal, filesystem, process and Cloud operations. Q012 now has plan-version/step/tool/capability-bound grants, exact argument hash checks, one-time execution leases, private POSIX 0700/0600 result artifacts and Windows owner/SYSTEM/Administrators ACLs, redaction, audit and an explicit per-step execution UI/API. No provider-proposed tool executes automatically.
- Cloud UX: existing PHP/HTML/CSS frontend redesigned and localized in all nine DCP locales. No Node/Next.js or new frontend framework.
- Q012 Task 6 review/approval UI/API and Task 7 integrity-checked conversation resume + explicit dependency-aware step execution are implemented locally. Automatic multi-step tool loops remain disabled.

## Remaining gaps before declaring full Desktop Commander parity

1. Durable terminal session daemon shared by STDIO, HTTP and provider adapters; verify restart recovery and add native PTY/ConPTY support for full terminal emulation.
2. Filesystem multi-file batch, content search, richer surgical editing, permissions/metadata operations and document-specific formats.
3. Cross-platform Windows/Linux acceptance for shell/process ownership and filesystem behavior; latest full test evidence is from macOS only.
4. Full capability grant management/revocation UI per MCP client and device; current job-step grants are explicit and plan-bound.
5. Signed Cloud task dispatch to DCP Nodes, acknowledgements, offline handling and artifact/result transfer. Do not expose remote terminal until this exists.
6. Production Cloud deployment and live device pairing acceptance remain pending explicit APP_KEY rotation approval.

## DC65 Q012 execution safety

A provider proposal is only a plan. A user reviews the exact tool name and arguments, approves the plan-version-bound approval (which creates a step/tool/capability grant), then separately clicks Execute for that step. The execution service re-reads arguments only from the immutable plan, verifies the shared registry policy/hash/grant, atomically claims a one-time lease, invokes the tool, stores a bounded redacted artifact and completes/fails the ledger. Duplicate or uncertain execution cannot auto-retry. Dependency steps are enabled only after prerequisites complete.


## DC66: MCP keepalive and local server branding

- The STDIO host now uses a non-blocking JSON-RPC read loop and sends a server-initiated MCP `ping` every 25 seconds by default. It consumes ping responses without confusing them for client requests, continues to answer client pings, handles multiple buffered JSON-RPC lines and exits cleanly on stdin EOF.
- The local `initialize.serverInfo` identity is `DCP Core` and advertises the existing DCP logo as an inline PNG data URI through `serverInfo.icons`.
- Added `tests/mcp-host-keepalive-e2e.php`; it shortens the ping interval for deterministic verification and checks logo metadata, server ping/response handling, client ping, tools/list and clean EOF.
- Updated the STDIO E2E identity/logo assertions and integrated the keepalive test into the main platform suite.
- Verification on Amrs-Mac-Pro: PHP lint for changed host/tests PASS; `DCP_MCP_KEEPALIVE_LOGO_E2E_PASS`; full `php tests/run.php` PASS (`ALL PLATFORM TESTS PASSED`). Local HTTP MCP E2E also passed within the full suite.
- The user’s current Claude config already points `dcp-core` at the source host and uses the DCP-Source macOS user-data directory. No Claude app restart/config mutation was performed; logo visibility in the UI must be confirmed after Claude reconnects.
- A separately launched idle PHP host remained alive beyond the initial observation window, which means the log line “process exiting early” alone did not prove PHP crashed. The keepalive implementation is now covered, but the exact Claude-side 60-second lifecycle must still be confirmed by reconnecting and observing the live client.


## DC67: Single full-terminal contract + Resource Registry foundation

- Removed the restricted dcp_run_safe_terminal tool, policy, service path and UI wording. The only terminal command tool is dcp_terminal_execute; the local API and UI now pass command, shell, working directory and bounded timeout to the full-shell service. It runs as the DCP OS identity without elevation.
- Full terminal proposals from providers/agents require exact plan-version-bound approval; a remote target must independently approve on the target device before any command is dispatched. Pairing is not terminal consent. Remote terminal dispatch remains disabled until target-side approval UI and signed delivery are implemented.
- Added local DcpResourceRegistryService with resource registration, health/capacity heartbeat, filtered discovery, owner-local secret references, user grants/revocation and a policy-aware local-first broker. Raw credentials are rejected from metadata.
- Added local SQLite tables for resources, grants and invocation ledger. Added four MCP tools (dcp_resource_discover, dcp_resource_register, dcp_resource_grant, dcp_resource_revoke) and local REST routes under /api/resources.
- Added Cloud MySQL schema and upgrade migration for resource metadata, resource grants, team membership and invocation ledger. Added authenticated Cloud endpoints for device resource registration/heartbeat, account discovery, grant/revoke and team/member management. Cloud never receives raw API credentials; only opaque owner-local secret references are allowed.
- Added Resource Registry and Cloud Resource Mesh contract tests. Latest full suite before Resource Mesh passed; re-run after the new resource tests is pending. No production Cloud migration/deploy, commit or push has occurred.


## DC67: Resource Mesh implementation + cross-transport terminal sessions

- Consolidated the entire product around dcp_terminal_execute; the old restricted diagnostic tool, policy and UI path are removed. Local shell execution uses the current OS identity and does not elevate privileges.
- Added a private, authenticated loopback terminal-session daemon under DCP user data. STDIO MCP, HTTP MCP/REST and provider tool callers now share session IDs, process stdin/stdout and lifecycle across independent PHP requests. Added a dedicated daemon E2E that starts a session through one TerminalService instance and reads/stops it through another; HTTP MCP E2E now starts and reads the same session across requests.
- All 35 tools are now available to local HTTP MCP and provider-neutral tool schemas. Provider schema-definition caps were raised from 32 to 64; the number of model-proposed tool calls remains independently bounded.
- Implemented local DcpResourceRegistryService, SQLite resource/grant/invocation tables, metadata registration, heartbeat/capacity updates, filtered discovery, local-first broker, grant authorization/revocation, quota/concurrency reservation and invocation completion. Added four MCP tools and local REST routes.
- Implemented Cloud MySQL resource/team/grant/invocation schema and upgrade migration; authenticated device resource registration/heartbeat, account discovery, user/team grants, revocation and team membership endpoints. Raw credential fields are rejected; only owner-local opaque secret references are retained.
- Added Cloud remote-terminal request inbox, short expiry, exact command SHA-256 binding, separate terminal.request pairing scope, and explicit target-device approve/reject. Approval only records the exact request; Cloud explicitly reports that no command has been dispatched. Signed delivery, target-side UI and local execution remain blocked until completed.
- Verification: php platform/tests/run.php — ALL PLATFORM TESTS PASSED after the new Resource Mesh, Cloud contracts, daemon sharing, HTTP MCP session reconnect and 35-tool provider schema changes. PHP syntax checks pass. No commit, push, release, production migration/deploy or stable install modification.
- Remaining: native PTY/ConPTY semantics and daemon crash recovery; target device approval UI and signed Cloud dispatch/result envelopes; actual owner-vault credential resolution and provider invocation adapters; Hermes integration; Windows/Ubuntu acceptance of the newest daemon/Resource Mesh code; live Claude reconnect/logo check.
