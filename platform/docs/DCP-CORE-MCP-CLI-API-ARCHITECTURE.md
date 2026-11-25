# DCP Core, MCP, CLI, API & Distributed Agent Architecture

**Status:** Approved architectural direction — 2026-11-25
**Product:** DCP — Distributed AI & Device Orchestration Platform
**Source branch:** `feature/local-ai-runtime-discovery`
**Rule:** DCP Core is the product; MCP, CLI, API and desktop integrations are adapters over the same Core contracts.

## 1. Product definition

DCP is a provider-neutral platform that connects people, teams, computers, local AI runtimes, cloud AI providers, APIs, tools and agents into one governed execution network. It enables an AI client or an authorized human operator to delegate work to one or more devices, coordinate parallel execution, collect results and recover safely from interruptions.

DCP is not a reimplementation of Desktop Commander. Desktop Commander remains an optional integration/plugin. DCP must continue to operate if that plugin is absent, disabled or replaced.

## 2. Core architectural rule

All product surfaces MUST call the same DCP Core services and policy enforcement. No MCP handler, CLI command, API controller, UI or plugin may implement a second copy of authorization, task orchestration, device selection or approval logic.

```text
 ChatGPT       Claude       Gemini       Other MCP clients       Human / CI
    |             |             |                  |                  |
    +-------------+-------------+------------------+------------------+
                                  |
                    DCP MCP Adapters / Gateways
                                  |
      +---------------------------+---------------------------+
      |                       DCP Core                         |
      | Identity / Auth / Policy / Capabilities / Approvals    |
      | Device Registry / Task Coordinator / Plans / Memory    |
      | Provider Registry / Agent Runtime / Results / Recovery |
      +---------------------------+---------------------------+
              |                   |                    |
          DCP API              DCP CLI           DCP Event Bus
              |                   |                    |
      +-------+-------------------+--------------------+-------+
      |                       DCP Network                     |
      | Mac Node | Windows Node | Linux Node | Cloud Node       |
      | Local AI | APIs | Tools | DSH Agent | optional plugins |
      +--------------------------------------------------------+
```

## 3. Official interfaces

### 3.1 DCP Core

Owns canonical domain services and contracts: identity, authentication, authorization, capability discovery, device registry and presence, provider registry, task inbox, task/job lifecycle, execution plans and DAG, bounded coordinator, approval records, result/artifact references, checkpoints, audit trail, event delivery and recovery.

Core MUST be independent of any specific LLM provider, MCP SDK, desktop tool or CLI framework.

### 3.2 DCP MCP

Provide a local STDIO MCP server and a remote Streamable HTTP MCP endpoint. Both expose the same versioned DCP tool catalogue and call Core services; transport-specific authentication/session handling stays in adapters.

- STDIO: local clients such as Claude Desktop/Claude Code and supported local MCP hosts.
- Remote HTTP: clients that connect to remote MCP endpoints, with OAuth/OIDC-compatible authorization, scoped tokens, TLS and explicit device pairing.
- Tool discovery and schemas must be generated from the canonical registry, not duplicated by each transport.
- Per-user, per-team and per-device scopes must be enforced by Core on every call.

Client onboarding is an integration concern: publish supported setup manifests/instructions for ChatGPT, Claude and Gemini. Do not promise universal one-click auto-discovery; each host has its own connector/extension registration and approval flow.

### 3.3 DCP CLI

The CLI is a first-class operator and automation interface over the same local/remote API/Core contracts. Initial command families (proposed names; implement incrementally):

- `dcp install`, `start`, `stop`, `status`, `doctor`, `version`
- `dcp devices list|inspect|connect|disconnect`
- `dcp providers list|test`
- `dcp agents list|run|stop`
- `dcp tasks list|show|create|approve|cancel`
- `dcp plans show|review|resume`
- `dcp mcp serve|status|install|remove`
- `dcp plugins list|install|enable|disable`

CLI output must support human-readable and machine-readable JSON modes, stable exit codes, bounded output, explicit destructive-action confirmation, and non-interactive CI use. Never bypass Core policies when invoked locally.

### 3.4 DCP API

Provide a versioned REST API for identity/session, devices/presence, capabilities, providers, agents, tasks/jobs, plans/steps, approvals, results/artifacts, events and health. Use authenticated scopes, request IDs, idempotency keys for mutations, pagination, rate limits, schema validation and consistent error envelopes. Webhooks/event streams must be signed/authenticated and replay-safe.

### 3.5 DCP Node / Agent Runtime

Each participating machine runs a DCP Node with a stable device identity, authenticated outbound connection, heartbeat/presence, capability inventory, local policy enforcement, task executor, bounded resource scheduler, artifact transfer and audit events. Prefer outbound TLS connections or a secure relay; do not require exposing arbitrary inbound ports on user devices.

A node advertises only explicitly enabled capabilities: OS/runtime facts, approved local models, provider/API connections, tool/plugin availability, resource limits and agent runtimes. Secrets remain local or in a dedicated encrypted secret store and are never broadcast in capability metadata.

## 4. Distributed task coordination

The coordinator turns an approved user goal into a versioned execution plan and bounded DAG. It selects devices by declared capability, authorization, availability, health, load, locality and policy. Steps may execute in parallel only when dependencies and resource constraints permit.

Required controls:

1. Plan version and immutable canonical plan hash.
2. Per-step idempotency key and unique execution lease.
3. Explicit capability allow-list and target-device authorization.
4. Approval bound to plan version, exact tool name and canonical argument hash.
5. Time, call-count, output-byte, artifact-size, concurrency and retry budgets.
6. Worker heartbeat, lease expiry and crash-safe checkpointing.
7. No automatic replay of non-idempotent side effects; require reconciliation/re-approval.
8. Opaque artifact references, content hashes and bounded result previews.
9. Audit records for plan changes, dispatch, approvals, execution and recovery.
10. Fail closed on missing policy, stale approval, hash mismatch, unknown device or uncertain side-effect status.

Provider-proposed tool calls are suggestions, not authority. The coordinator validates them against the shared DCP tool registry, plan, capability, target device and approval policy before dispatch.

## 5. Agent ecosystem and plugins

DSH Agent is supported as an agent runtime/integration, not as the owner of the Core. DCP should also permit other compatible agents through a stable agent contract with declared capabilities, lifecycle, health, task input/output schemas and cancellation semantics.

Desktop Commander is an optional plugin/adapter for its supported terminal, process and filesystem operations. It must be isolated behind the DCP plugin contract, least-privilege scopes and audit layer. DCP native Node tools remain available independently. Never copy Desktop Commander implementation into DCP as a shortcut; study patterns, document attribution/license obligations and implement DCP-owned interfaces.

## 6. Reference implementation study — Desktop Commander

Primary open-source reference checkout: `wonderwhy-er/DesktopCommanderMCP` (local STDIO MCP server and remote device-agent implementation). The official public hosted-service repository `desktop-commander/remote-desktop-commander` contains manifests, docs and issue tracking; the hosted service implementation is proprietary and is not available for source cloning.

Study topics (read-only reference): MCP tool schemas/registration, process lifecycle and streaming, filesystem safety, bounded output pagination, configuration, audit history, local-vs-remote device agent pairing, OAuth device flow, multi-device routing and client-specific manifests. Record observations and DCP adaptations in `platform/docs/research/desktop-commander-reference-review.md`.

The reference checkout is stored outside the DCP source repository at `/Volumes/Data/DiGiPL/References/DesktopCommanderMCP` and must not be included in DCP releases or copied wholesale into DCP.

## 7. Security and privacy invariants

- Authenticate every client, user, node and agent; authorize every operation at Core.
- Use scoped, revocable credentials and secure device pairing.
- Keep provider API keys, private keys, local databases and encryption keys out of Git, MCP tool results and logs.
- Treat model output, remote tool arguments, emails, documents and device metadata as untrusted input.
- Validate schemas, canonicalize arguments, cap sizes and reject unknown fields by default.
- Use approval for sensitive reads, state changes and external side effects according to policy.
- Maintain tamper-evident or integrity-checked audit records; redact secrets and sensitive content.
- Network transport must use TLS; local device agents should establish outbound authenticated sessions.
- A connected AI client receives only the scopes explicitly granted to it; connecting an MCP server is not blanket permission to control every team device.

## 8. Implementation roadmap and relation to Q012

| Priority | Workstream | Exit condition |
|---|---|---|
| P0 | Q012 Task 4 bounded coordinator | Provider tool calls create/advance versioned plan steps only through DcpToolRegistry, with limits and fail-closed authorization |
| P0 | Q012 Task 5 results + approval boundary | Durable result refs, idempotency and exact plan/version/hash-bound approval gate execution |
| P0 | Q012 Tasks 6–7 review + safe resume | Review APIs/UI, worker heartbeat, crash recovery and tests proving no duplicate side effects |
| P1 | DCP CLI foundation | CLI calls existing Core/API services; status/doctor/devices/tasks commands and contract tests |
| P1 | MCP productization | Separate STDIO and remote HTTP transports generated from the shared registry, auth scopes and integration manifests |
| P1 | DCP Node/Agent contract | Device registration, heartbeat, capabilities, secure outbound channel and bounded executor |
| P2 | API/event contract expansion | Versioned REST, event delivery, webhook signatures, idempotency and client SDK contracts |
| P2 | Plugin SDK + Desktop Commander adapter | Optional plugin lifecycle, permissions, compatibility and conformance tests |
| P2 | Team orchestration | Multi-user/team policy, cross-device scheduling, artifact transfer and agent-to-agent coordination |

Do not interrupt or rewrite the current Q012 implementation to create parallel abstractions. Task 4 is the immediate continuation; CLI/MCP/API and Node contracts should reuse the registry, task services and plan model being completed there.

## 9. Decisions

- DCP Core + MCP + CLI + API are mandatory first-class product components.
- Desktop Commander remains an optional plugin, never a hard dependency.
- DSH Agent is supported within a provider-neutral Agent Runtime.
- The system is team/device oriented: multiple computers, local AI, cloud APIs and tools cooperate through governed task execution.
- Local source and installed release are isolated. Source: `/Volumes/Data/ChatGPT/DCP-Source`; installed DCP target: `/Volumes/Data/DiGiPL/DCP`; reference repositories: `/Volumes/Data/DiGiPL/References`.
- Do not commit or push unfinished work without review and explicit approval.


## Temporary DCP hierarchical memory (MD + JSON)

DCP first release now has a lightweight local memory layer in `platform/app/Memory/MarkdownJsonMemory.php`, with `DcpMemoryService` facade. Markdown files are human-reviewable source; an atomic `index.json` provides hierarchical metadata and fast filtering. It supports global/project/task/session/agent levels, project/agent scope, bounded bootstrap, keyword recall, read-one-entry, secret redaction, safe file permissions, and soft retirement that preserves history. REST routes live under `/api/memory/*`; local MCP exposes read-only bootstrap/recall/read tools while mutation tools are deliberately disabled until explicit scoped authorization and approval are added. This subsystem is temporary and independent from deferred `DP_MEMORY`; it must remain replaceable by the future gateway.


## DCP Core MCP endpoints — DC56

DCP now has two separate MCP surfaces backed by the DCP Core policy/tool catalogue:

1. **Local MCP STDIO**: `platform/plugins/tools/mcp-host/server.php`, launched as a subprocess by local MCP clients. Existing seven DCP tools remain the default public contract. Optional temporary memory read tools can be exposed to local STDIO clients with `DCP_MCP_MEMORY_TOOLS=1`; memory writes remain disabled.
2. **Local MCP Streamable HTTP**: `POST http://127.0.0.1:<DCP_PORT>/mcp`. It is loopback-only, validates Origin, requires a separately provisioned bearer token, and filters tools by token scopes. Generate a token with `php platform/bin/mcp-token.php <client-name> [scope1,scope2]`. Available scopes: `platform.read`, `terminal.safe-read`, `sensitive.read`, `task.create`, `memory.read`. The token is stored as SHA-256 only and displayed once. Revoke with `php platform/bin/mcp-token-revoke.php <mcp-token-id>`.
3. **Cloud MCP Streamable HTTP**: `POST https://dcp.amradel.com/api/v1/mcp`. Authenticate using a DCP Cloud API token. Create a separate token using `POST /api/v1/mcp-tokens` with an existing account bearer token; it is returned once and expires after 30 days. Current tools are read-only `dcp_cloud_list_devices` and `dcp_cloud_pairing_overview`. Revoke via `DELETE /api/v1/mcp-tokens/{numeric-token-id}` with the same account token. Optional browser Origins must be in `MCP_ALLOWED_ORIGINS`; clients without an Origin header use bearer auth.

Both HTTP surfaces return JSON responses for Streamable HTTP requests and support protocol versions `2025-11-25` and `2026-07-28` metadata/header validation. They do not implement long-lived SSE subscriptions yet. The local endpoint exposes the local Core; the Cloud endpoint exposes only Cloud account/device metadata. Cloud is still a rendezvous/signaling control plane, not a remote task execution relay. No Cloud MCP tool can run a command, read/write a remote filesystem, or dispatch a remote task. Remote execution remains explicitly unavailable until Cloud task transport, device acceptance, scoped capabilities, signed envelopes, replay protection, plan-bound approvals and result verification are implemented.

### Security boundary

- Local MCP binds to loopback via the desktop runtime; remote LAN binding is not supported by this endpoint.
- MCP bearer tokens are random 256-bit secrets; only SHA-256 hashes are persisted locally. Never put tokens in project files or commit them.
- Tool visibility and execution are separately scope-checked; tool annotations are not authorization.
- Sensitive reads and task creation require explicit token scopes. Mutating remote execution is not exposed.
- Cloud API tokens are owner-bound, expire after 30 days, and can be revoked by owner.
- Current implementation returns `application/json`; SSE streaming/subscriptions and OAuth discovery are future compatibility work.


## Q012 Task 5 — atomic execution claim and idempotency ledger (DC57)

Added `chat_job_tool_executions` as a durable execution ledger with unique idempotency key, plan version, step key, tool name, exact arguments hash, status, lease-token hash, lease expiry, result reference/hash and timestamps. `ChatJobService::claimToolExecution()` re-reads the immutable current plan, checks the exact argument hash, dependency completion and current plan-version approval for approval-required calls, then atomically claims a step. Duplicate claims return already-claimed/completed without running the tool. An expired lease is marked failed with an uncertain side-effect outcome; failed/uncertain steps are not automatically retried. `completeToolExecution()` atomically records an opaque result reference/hash and completes the step using a one-time lease token. `failToolExecution()` closes the lease and requires review before retry.

This is the persistence/claim safety boundary only. It does not yet invoke tools or expose an execution API. Capability authorization, artifact storage/redaction, result handoff to the provider loop and explicit review UI remain required before any tool execution can be enabled.

## DC63 implementation update — local parity and Cloud bridge

The shared local DCP MCP registry now contains 31 tools. In addition to the original platform/provider/device/task/mail/memory tools, it includes:

- Full foreground terminal execution with shell selection, working directory, bounded command/output/time and current-OS-user privileges.
- Interactive STDIO-host terminal sessions (start/read/stdin/stop). These sessions are held in the long-lived MCP host process; they do not survive that process exiting and are not yet shared across independent HTTP requests.
- Bounded workspace filesystem read/list/search/write/append/mkdir/move/delete. Paths must be relative to the configured DCP workspace; symlinks and traversal are rejected; file size and directory/search results are capped.
- Local Core-to-Cloud controls for status, pairing code/request/decision/revocation and signaling connection/send/poll.

Local Streamable HTTP token scopes are independently enforced for `terminal.execute`, `filesystem.read`, `filesystem.write`, `filesystem.delete`, `cloud.status.read`, `cloud.pairing.manage`, `cloud.connection.manage` and `cloud.connection.read`, in addition to existing platform/provider/task/memory scopes. The STDIO host and local HTTP endpoint still dispatch through the same registry.

Cloud landing page uses the existing PHP/HTML/CSS stack, responsive styling and all nine supported DCP locales (ar/en/nl/es/pt/fr/de/zh/ja). The authenticated Cloud MCP gateway remains read-only for account-level device/pairing information. Cloud is not a remote shell host and does not execute terminal/filesystem commands. Secure remote task dispatch must be integrated through signed DCP Node envelopes and per-device Core authorization before exposing any remote execution tool.

### DCP Core parity gaps still open

- Persistent terminal session daemon shared across HTTP requests/restarts; process inventory/terminate and robust PTY support.
- Full filesystem parity: surgical text edits, multi-file operations, metadata/permissions, file search by contents, bounded binary/document format handling.
- Capability grant lifecycle, per-client/per-device grants, audit coverage and explicit approvals for high-impact local MCP calls.
- Cross-platform macOS/Windows/Linux tests for shell selection, paths, process lifetime, output limits and filesystem semantics.
- Cloud remote task dispatch/acknowledgement/result transfer is not exposed. Cloud MCP is a read-only account control plane until signed task execution is wired to the local DCP Node.

### Q012 Tasks 6–7 update

Task 6 adds execution-plan review and approval-decision APIs/UI. Approval decisions are plan-version scoped and do not invoke tools. Task 7 adds explicit integrity-checked conversation-only resume from a `checkpoint_ready` job; a user supplies the next message, and no prior tool call is replayed. Step-level execution resume remains disabled until Task 5 has a capability resolver, private bounded result artifact store/redaction and a reviewed immutable-plan tool invocation service.

## DC65 — approved execution boundary

- `chat_job_capability_grants` binds an explicit approval to the exact job, plan version, execution step, tool name and capability. Approval/rejection updates the grant transactionally.
- `DcpCapabilityGrantService` verifies the current plan and still-approved grant immediately before invocation.
- `DcpToolResultArtifactStore` writes bounded JSON artifacts under a private 0700 directory with 0600 files, atomic replacement, SHA-256 and secret/Bearer redaction. Artifact reads are bound to the owning job's `chat_job_tool_results` record.
- `DcpToolExecutionService` obtains proposal name/arguments only from the immutable stored plan; validates registry policy and canonical argument hash; requires current capability grant for approval-required calls; claims the one-time lease; invokes the shared registry with exact approval context; stores result artifact; and completes/fails the ledger with audit records.
- `POST /api/chat/jobs/tools/execute` is CSRF-protected and requires explicit per-step user action. `GET /api/chat/jobs/tool-result` returns only artifacts attached to that job. Approval alone never executes.
- Chat review UI displays exact arguments and only shows Execute when the step is planned/blocked, dependencies are complete, and any required approval is current. Failed/uncertain executions fail closed.
- Q012 Task 7 safe step resume is explicit, dependency-aware and reviewed. Conversation resume is a separate explicit new turn after checkpoint integrity validation. No automatic multi-step tool replay.


## MCP delivery status — DC68

### Local DCP Core MCP
- STDIO MCP host advertises the DCP logo and remains alive across multiple JSON-RPC requests.
- Local Streamable HTTP endpoint is `/mcp`, bound to loopback, with independently scoped bearer tokens, Origin validation, CSRF-free bearer authentication, bounded request bodies and protocol metadata checks.
- STDIO and HTTP use the same provider-neutral DCP Core registry. STDIO supports all 34 tools, including persistent terminal sessions; HTTP exposes request-safe tools and uses the shared terminal-session daemon for interactive sessions.
- Local memory tools are read-only over MCP until the explicit memory-write authorization path is enabled.
- Token creation/revocation: `platform/bin/mcp-token.php` and `platform/bin/mcp-token-revoke.php`.

### DCP Cloud MCP
- Remote Streamable HTTP endpoint: `https://dcp.amradel.com/api/v1/mcp`.
- Cloud MCP is a control-plane surface, not a shell host. It exposes five authenticated, read-only tools: `dcp_cloud_list_devices`, `dcp_cloud_pairing_overview`, `dcp_cloud_resource_discover`, `dcp_cloud_list_teams`, and `dcp_cloud_list_resource_grants`.
- Resource discovery is account-scoped and follows active user/team/device grants; expired/revoked grants are excluded. Secret references and credentials never appear in MCP output.
- Cloud MCP tokens have their own explicit scopes (`cloud.devices.read`, `cloud.resources.read`, `cloud.teams.read`, `cloud.grants.read`) and 30-day expiry. Ordinary account API tokens retain their existing wildcard API behavior for backwards compatibility; an MCP token cannot mutate Cloud state.
- API token scope storage is included in the fresh-install schema and idempotent upgrade migration. Revoke an MCP token through the owner-authenticated `DELETE /api/v1/mcp-tokens/{id}` route.
- Cloud MCP validates the supported protocol versions and the 2026 method/name metadata headers. Notifications are accepted without creating server-side sessions; SSE subscriptions are not advertised.
- Remote terminal execution, task dispatch, resource invocation and mutations are deliberately not exposed as Cloud MCP tools. Those require the separate Q015 signed Node/target-approval path and owner-local execution.

### Acceptance
- Local STDIO keepalive/logo, all 34 tool definitions and MCP tool dispatch are covered by the local host E2E tests.
- Local Streamable HTTP authentication/scope denial, terminal session continuity and MCP request handling are covered by HTTP E2E tests.
- Cloud MCP SQLite service tests cover token scope filtering, account isolation, device/pairing discovery, user/team/device resource grants, expiry, grant ownership and secret-reference non-disclosure.
- Cloud router contracts cover protocol metadata, token scope schema/migration, rate limits and absence of shell/dispatch tools.
