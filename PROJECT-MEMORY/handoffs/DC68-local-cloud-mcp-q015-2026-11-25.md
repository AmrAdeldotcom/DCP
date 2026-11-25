# DC68 — Local + Cloud MCP Closure and Q015 Progress

Date: 2026-11-25
Branch: `feature/local-ai-runtime-discovery`
HEAD: `cdd65d6`
Source: `/Volumes/Data/ChatGPT/DCP-Source`

## User direction
- Commit the accumulated DCP work.
- Close the DCP MCP story locally and in Cloud, then continue Q015 and subsequent queue work.
- Q005 is closed and must never be reopened. Q012 Tasks 6 and 7 remain complete.

## Commits
- `8967bc0 feat(dcp): complete core MCP and resource mesh foundations`
- `c2998ec feat(cloud): add scoped read-only DCP MCP control plane`
- `c9620e5 feat(resources): invoke owner-local AI resources and return signed remote results`
- `33ecfbf docs(project): checkpoint DC68 MCP closure and Q015 progress`
- `cdd65d6 test(security): verify DCP device signatures against Cloud`

All five commits are local. Branch is five commits ahead of origin. No push, release or production deployment was performed.

## Local MCP — source implementation complete
- Local STDIO host keeps alive, advertises the DCP logo and supports multiple JSON-RPC requests.
- Loopback Streamable HTTP MCP uses bearer tokens/scopes and shares the same DCP Core registry.
- Registry exposes 35 provider-neutral tools, including one full `dcp_terminal_execute`, persistent terminal sessions and `dcp_resource_invoke`.
- No `dcp_run_safe_terminal` alias exists.

## Cloud MCP — source implementation complete
- Endpoint: `https://dcp.amradel.com/api/v1/mcp`.
- Five read-only tools: `dcp_cloud_list_devices`, `dcp_cloud_pairing_overview`, `dcp_cloud_resource_discover`, `dcp_cloud_list_teams`, `dcp_cloud_list_resource_grants`.
- `api_tokens.scopes_json` is part of fresh schema and idempotent upgrade migration. MCP tokens receive explicit device/resource/team/grant read scopes and 30-day expiry.
- Scoped MCP tokens cannot call ordinary Cloud REST APIs. Legacy account tokens keep wildcard compatibility.
- Resource discovery respects active user/team/device grants and excludes expired/revoked/unshared resources. Owner-local secret references are never returned.
- Cloud MCP is intentionally a control plane: no shell, arbitrary task dispatch, write/mutation or remote execution tools are exposed.

## Q015 — owner-local resource invocation
- Added `DcpResourceChatClient` and `DcpResourceInvocationService`.
- Supports Ollama local models and configured OpenAI-compatible providers. External resources use opaque `provider:<provider-id>` references; `ChatService` resolves the encrypted key via local `ProviderService` only at invocation time.
- Invocation ledger records success/failure. Failure closes the ledger; provider diagnostics/credential-bearing output is logged locally but not returned to the caller.
- Added `dcp_resource_invoke` to the shared registry and CSRF-protected `/api/resources/invoke`. Agent calls are sensitive and require exact plan-bound approval.

## Q015 — signed remote terminal
- Cloud issues Ed25519-signed envelope bound to target device, exact approved command hash, expiry and nonce.
- Target verifies Cloud key pin, signature, target/request/hash and expiry before execution. A second explicit target-side click is required after approval.
- Target executes via local `TerminalService` as current OS user, with no elevation. Local ledger prevents duplicate execution; retry resubmits saved signed result rather than running command again.
- Device signs result; Cloud verifies and stores it. Output is redacted before upload. Source UI can retrieve and display the signed result.
- Unit/contract tests use mock Cloud/shell interfaces; no real two-device execution was run in this turn.

## Verification
- `php platform/tests/run.php`: `ALL PLATFORM TESTS PASSED`.
- All PHP files under `platform/`: `php -l` passed.
- `node --check platform/public/assets/app.js`: passed.
- `git diff --check`: passed.
- Local MCP STDIO keepalive/logo and loopback Streamable HTTP E2E pass.
- Cloud MCP SQLite service/contract tests pass for token scope filtering, account isolation, device/pairing/resource/team/grant discovery, expiry, grant ownership and secret isolation.
- Resource invocation tests pass for Ollama, opaque external provider references, missing secret refs and failed-provider ledger closure.
- Signed remote terminal dispatch/result, replay protection, invalid signature rejection and source/target UI contracts pass. A real `TaskEnvelopeService` device signature is verified by the Cloud Ed25519 verifier using the same canonical JSON; tampered device results are rejected. Provider error diagnostics are redacted from logs.

## Production/deployment boundary
- `https://dcp.amradel.com/api/v1/health` returned `ok/database/schema` 200. This is the currently deployed health route and does **not** prove the new Cloud MCP source or `api_tokens.scopes_json` migration is live.
- No production Cloud code upload or MySQL migration was performed.
- Protected `DCP_CLOUD_SIGNING_SEED` must be provisioned in Cloud environment before signed terminal dispatch is live.
- Q007 APP_KEY rotation approval remains pending. Do not bypass secret-file safety.
- Local MySQL daemon uses the existing DiGEMA data directory; it was not accessed or modified.
- Stable installed DCP v1.0.0, user databases, encryption keys and model files were not modified.
- `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json` remains untracked and was intentionally excluded from Git.

## Remaining Q015 work
1. Cloud owner-node resource invocation inbox/proxy with signed request/result, grant generation, quota and concurrency enforcement.
2. Local team/device grant discovery parity and Cloud team-grant invocation routing.
3. Hermes adapter and provider-neutral universal AI gateway orchestration.
4. Real Mac↔Windows/Ubuntu two-device signed terminal and resource invocation acceptance.
5. Native PTY/ConPTY and terminal daemon crash recovery/reattach.
6. Only after explicit Q007 key approval, provision Cloud signing seed, apply `api_tokens.scopes_json` migration and deploy Cloud MCP; test live endpoint with a scoped token.

## Next immediate task
Implement Cloud owner-node resource invocation: requester submits a grant-bound invocation; Cloud creates a bounded pending envelope for the resource owner device; owner device resolves its own vault reference, invokes locally, signs the result and returns it; Cloud verifies result and exposes it to the requester. Preserve owner-local secrets and enforce grant generation/quota/concurrency at claim and completion.
