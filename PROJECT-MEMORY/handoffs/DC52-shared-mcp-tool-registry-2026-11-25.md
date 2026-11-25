# DC52 — Shared MCP Tool Registry and Agent Policy Boundary

Date: 2026-11-25
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
Code commit: `4672b22`
Test-runner commit: `bdde922`
Scope: DCP Stage 6 / Q012. DP_Anything remains deferred.

## Implemented
- Extracted the seven existing MCP tools into `platform/app/Services/DcpToolRegistry.php`; MCP stdio server now delegates catalog, schema validation, policy and dispatch to this shared registry.
- Internal policy classes: safe-read, sensitive-read and state-change. Agent callers need explicit capabilities; sensitive reads/state changes require an explicit approval tied to the current plan version and exact canonical argument SHA-256.
- JSON-schema validation rejects unknown tools/properties, wrong types, missing required values, invalid enums, malformed action payloads, blank task metadata, invalid target device IDs, absolute/traversal paths and file content over 1 MB.
- Public MCP schema does not expose internal policy metadata. MCP server requires an explicitly configured local integration context. Existing stdio seven-tool contract is preserved.
- `dcp_create_task` still creates only an `approval_required` task; it does not execute the action payload.
- Added `platform/tests/mcp-tool-registry.php` and `platform/tests/mcp-host-e2e.php` to verify policy, schema, exact-argument approvals, stdio initialize/list/call, safe terminal dispatch and unknown tool errors.
- Fixed `local-ai-ui-contract.php` to return to the caller when included, allowing `platform/tests/run.php` to execute update-manifest, Linux transactional update, rollback, crash recovery and updater-health tests.

## Validation
- `php platform/tests/run.php` → `ALL PLATFORM TESTS PASSED` (now includes Linux updater contracts).
- `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- `python3 platform/tests/chat-job-http-e2e.py` → `DCP_CHAT_JOB_HTTP_E2E_PASS`.
- MCP stdio E2E passes as part of the full platform suite.
- PHP lint across app/bootstrap/tests/plugins, `node --check platform/public/assets/app.js`, `git diff --check`: pass.
- GitHub run `45589253032` is in progress on Ubuntu 24.04, Windows Server 2025 and macOS 15; check jobs before the next implementation slice is marked cross-platform green.

## Not yet implemented
- `ProviderAdapter::chat()` remains unchanged. No structured tool-call parsing for Ollama/OpenAI-compatible providers yet.
- The bounded Chat agent coordinator/tool loop is not connected to the registry or execution-plan persistence.
- Step-level resume remains disabled; `resumable=false` and `resume_requires_review=true` remain mandatory until heartbeat, idempotency and crash-safe resume are complete.

## Next
1. Task 2: define provider-neutral structured tool-call result types without breaking ordinary chat.
2. Task 3: implement Ollama and OpenAI-compatible tool-call parsing with fixture tests for malformed, refusal, empty and valid calls.
3. Task 4: bounded coordinator integrating plan/step/result/approval persistence; keep all side effects behind exact-argument approval.
