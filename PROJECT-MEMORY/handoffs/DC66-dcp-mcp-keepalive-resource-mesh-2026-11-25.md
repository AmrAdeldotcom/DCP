# DC66 — MCP Keepalive, DCP Branding and Resource Mesh Direction

Date: 2026-11-25
Branch: feature/local-ai-runtime-discovery
HEAD at start: 0522e14

## User request
Investigate Claude's DCP Core MCP disconnect after roughly 60 seconds; do not reduce terminal to a tiny allow-list; make DCP broader than Desktop Commander by sharing local AI resources/APIs across teams and machines, routing tasks through Hermes and accepting orders from major AI clients; show DCP logo in local MCP server identity.

## Findings
- Claude config already points `dcp-core` to `/Volumes/Data/ChatGPT/DCP-Source/platform/plugins/tools/mcp-host/server.php`, with `DCP_MCP_MEMORY_TOOLS=1` and source macOS user-data directory. No config edits made.
- STDIO host previously blocked in `fgets(STDIN)` and never initiated MCP pings. Its normal behavior was silent while idle.
- An independently started PHP host process remained alive beyond the first 60-second observation window. This does not prove the Claude client’s exact termination cause; the user-visible disconnect must be rechecked after reconnect.
- `DcpToolRegistry` already exposes `dcp_terminal_execute`: arbitrary shell commands, pipelines and scripts as the current OS user, 12 KB command limit, 1–120 second foreground timeout, 64 KB output cap. Interactive sessions exist in the STDIO host. The safe terminal remains a separate diagnostic tool.
- Existing DCP logos: `platform/public/assets/dcp-logo.png` and `dcp-logo-dark.png`.
- Device storage was nearly full (~9.2 GiB free on /Volumes/Data); no large dependencies or runtimes installed.

## Changes
- Reworked `platform/plugins/tools/mcp-host/server.php` to use a non-blocking STDIO JSON-RPC loop, buffer partial/multiple lines, send server-initiated MCP ping every 25 seconds by default, consume ping responses, answer client ping and exit cleanly on stdin EOF.
- MCP initialize now identifies as `DCP Core` and advertises the existing logo as inline PNG `serverInfo.icons`.
- Added `platform/tests/mcp-host-keepalive-e2e.php`; integrated into `platform/tests/run.php`.
- Updated `platform/tests/mcp-host-e2e.php` to assert DCP Core identity and logo metadata.
- Added `platform/docs/DCP-RESOURCE-MESH-AND-UNIVERSAL-AI-GATEWAY.md` defining the broader product: local AI/API resource registry, owner-local secrets, team grants, resource broker, Hermes/provider adapters, universal MCP/API ingress, signed device tasks, scheduler and lifecycle.
- Added DC66 section to parity roadmap.

## Verification
- `php -l` on MCP host, keepalive test, host E2E and main test runner: PASS.
- `php tests/mcp-host-keepalive-e2e.php`: `DCP_MCP_KEEPALIVE_LOGO_E2E_PASS`.
- `php tests/run.php`: `ALL PLATFORM TESTS PASSED`.
- Full suite includes MCP STDIO E2E, logo assertion, keepalive E2E and local Streamable HTTP E2E.

## Boundaries / not done
- No Claude app restart or UI reconnect performed; icon display must be confirmed on next connection.
- Focused keepalive/logo E2E also passed on Ubuntu 24.04 (PHP 8.3.6) and Windows 11 (bundled PHP 8.5.13); syntax checks passed on both. Temporary remote fixtures were cleaned. Full platform suite was run on macOS only.
- No Cloud deployment, commit, push, release or stable install modification.
- DCP full terminal runs as the current OS identity; it does not grant root/admin or bypass OS privacy/security.
- Resource Mesh, Hermes adapter and universal provider ingress are now specified, not yet implemented as a complete feature.
- Remaining next work: durable cross-transport PTY/session daemon; filesystem content/multi-file/metadata parity; resource registry + grants; signed Cloud task dispatch/ack/result; Hermes adapter; Windows/Ubuntu CI and live node acceptance.
