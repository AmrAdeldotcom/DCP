# DC56 — DCP Core MCP local + Cloud

Date: 2026-11-25

## Implemented locally
- Local `/mcp` Streamable HTTP endpoint with bearer tokens, scopes, Origin validation, loopback-only access, scope-filtered tool discovery and scope-enforced tool calls.
- `mcp_tokens` schema and `platform/bin/mcp-token.php` one-time token provisioning.
- Local MCP HTTP E2E: initialization, list, safe call, denied sensitive call, Origin rejection, current protocol metadata and header mismatch.
- Cloud `/api/v1/mcp` endpoint, authenticated account read-only device/pairing tools, token create/revoke routes, current protocol metadata/header validation.
- Cloud contract tests assert no task dispatch or terminal execution is exposed.
- DCP worker adds compact hierarchical memory bootstrap from Markdown+JSON.
- macOS wrapper sets TMPDIR=/tmp; source-built app Cloud heartbeat verified.

## Verified
- Local MCP HTTP: `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS`.
- Cloud static contract: `ALL CLOUD CONTRACT CHECKS PASSED`.
- DCP memory: `DCP_MEMORY_MD_JSON_PASS`.
- Worker E2E after memory integration: `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.

## Limits
- Cloud MCP only lists Cloud account devices and pairing overview; it does not run DCP Core tools on remote nodes.
- Cloud task dispatch remains unimplemented. No arbitrary remote execution exposed.
- HTTP MCP currently returns JSON; SSE subscriptions/notifications not implemented.
- No commit/push/release. Stable installed v1.0.0 unchanged. Source app bundle was rebuilt separately for verification.
