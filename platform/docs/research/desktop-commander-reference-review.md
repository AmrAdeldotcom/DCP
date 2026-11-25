# Desktop Commander — Reference Study Log

**Purpose:** Read-only engineering reference for DCP. No code is copied into DCP.

## Repositories

- Open-source local MCP implementation: https://github.com/wonderwhy-er/DesktopCommanderMCP
- Official hosted Remote MCP public manifests/docs: https://github.com/desktop-commander/remote-desktop-commander
- The hosted Remote MCP service implementation is proprietary; this repository is not its backend source.

## Local reference checkout

`/Volumes/Data/DiGiPL/References/DesktopCommanderMCP`

Remote name: `upstream` (read-only reference; do not push).

## Areas to inspect

1. MCP server registration and tool schema definitions.
2. Terminal command execution, interactive sessions, process ownership and output pagination.
3. Filesystem path validation, symlink handling and surgical edit operations.
4. Tool-call audit history, bounded logs and configuration updates.
5. Remote device pairing, device authorization, OAuth/PKCE and multi-device relay.
6. Cross-client MCP manifests and setup flows (Claude, ChatGPT, Gemini CLI, VS Code).
7. License and dependency obligations before adopting any implementation pattern.

## DCP adaptation rules

- Reuse architectural lessons, not their product identity or code wholesale.
- DCP Core owns authorization, capability policy, task plans, approvals, device selection and audit.
- Desktop Commander functionality is an optional plugin; native DCP functionality must remain independent.
- Any future code reuse requires a file-level license review, attribution and compatibility review.

## Initial verified observations (2026-11-25)

- The local Desktop Commander project is an open-source MCP server distributed as `@wonderwhy-er/desktop-commander`; its documented local setup uses a local checkout or `npx` and supports Claude Desktop configuration.
- The official Remote Desktop Commander public repository documents a hosted Streamable HTTP MCP endpoint, OAuth 2.0/PKCE and paired per-device agents. It explicitly states the hosted service implementation is proprietary.
- The remote README documents device pairing and support for ChatGPT, Claude, Cursor, VS Code and Gemini CLI, plus a device agent that must remain running.
- DCP should implement its own Core-owned device registry, secure pairing, relay and policy enforcement rather than depending on the hosted Desktop Commander service.

## Review status

Repository clone initiated; source-level review and file map pending clone completion. Do not treat this note as a completed code audit.


## Source-level review observations (2026-11-25)

- `src/index.ts` establishes the MCP STDIO transport before startup logging, buffers startup messages until the client initialization handshake is complete, then enables notifications and performs deferred preload work. DCP adaptation: preserve strict transport stdout purity and do not let diagnostics corrupt JSON-RPC streams.
- `src/server.ts` centralizes MCP request handlers, tool/resource/prompt registration, client identity and client-specific behavior. DCP adaptation: centralize tool definitions and schema exports in the DCP shared registry, while keeping host-specific onboarding in adapters.
- `src/tools/schemas.ts` is a dedicated Zod schema catalogue and the server converts schemas to JSON Schema. DCP adaptation: use one canonical schema/policy source for MCP, provider tool calling and CLI/API validation; add contract tests that ensure all exported interfaces stay in sync.
- `src/terminal-manager.ts` manages long-running process sessions, timeout/wait ceilings, bounded output buffers and process-tree termination. It has a 50 MiB per-session output buffer, 1 MiB line splitting and a 2 MiB initial wait-output ceiling. DCP adaptation: use smaller role-based per-task budgets, explicit leases and process ownership; never assume a process is gone merely because a wait timed out.
- `src/tools/filesystem.ts` resolves real paths, handles symlinks/junctions and checks configured allowed directories; it also bounds directory listing and file-operation timeouts. DCP adaptation: preserve canonical-path checks at the node boundary and enforce workspace roots independently of the AI client.
- `src/utils/toolHistory.ts` stores tool-call arguments and output previews in local JSONL, capped at 1,000 in-memory records, 4 KiB stored output per record and 5 MiB active history file. DCP adaptation: audit records should be bounded and redact secrets; prefer hashes/opaque artifact references over persisting raw tool output in general audit history.
- `src/remote-device/device-authenticator.ts`, `device.ts` and `remote-channel.ts` implement device authorization, persisted access/refresh sessions, token refresh, realtime channel, heartbeat and reconnect/backoff behavior. DCP adaptation: device pairing must be explicit, token rotation must be durable, reconnects need jitter/backoff and observable presence, and revoked credentials must not silently fall back to weaker authentication.
- `SECURITY.md` explicitly describes allowed directories and command blocklists as guardrails, not a sandbox, because arbitrary shell execution can bypass them. DCP adaptation: the Core must not equate a path allow-list or terminal blocklist with a security boundary. Use OS-level isolation where required, narrow node capabilities, approval gates and server-side authorization.
- `package.json` identifies the local package as `@wonderwhy-er/desktop-commander`, version `0.3.4`, MIT licensed, requiring Node `>=22.12.0`. No dependency install or build was run for this reference checkout.

### DCP decisions derived from the review

1. Keep MCP transport adapters thin; Core owns authorization and domain operations.
2. Implement DCP CLI as a separate client of the same stable Core/API contract, not as privileged direct database access.
3. Treat every DCP Node as a separate trust boundary with explicit capabilities, device identity, leases and revocation.
4. Make output, history, search results and process sessions bounded; large results become private artifacts with opaque references and hashes.
5. Never advertise DCP terminal restrictions as a sandbox. For stronger containment use OS/container/VM isolation.
6. The hosted Remote Desktop Commander backend is not present in its public repo, so DCP's remote relay design must be implemented and audited independently.
