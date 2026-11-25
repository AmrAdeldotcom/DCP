# DC51 — Windows Workspace ACL + Cross-Platform Full Suite

Date: 2026-11-25
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
Latest code commit: `6d706d5`
Execution-plan context commit: `7a3b8d1`
CI matrix workflow commit: `91d3c4d`
Scope: DCP Stage 6 / Q012 only. DP_Anything remains deferred.

## Project stage
DCP is in Stage 6 (Device Collaboration and Agents). Q012 is the active shared chat / agent collaboration workstream. Stable v1.0.0 is published, while Q005 live installed-device update/rollback acceptance remains a separate open gate.

## Implemented
- Q012 checkpoint snapshot schema v2 persists versioned plan metadata, dependency graph, last confirmed step, opaque tool-result references and pending plan-version-scoped approvals.
- Step dependency DAG is validated; cycles are rejected; dependent steps cannot run/complete until prerequisites are confirmed. Tool output contents are not copied into snapshots.
- Automatic resume remains disabled (`resumable=false`, `resume_requires_review=true`); the real planner/tool loop is not connected yet.
- Windows per-device workspaces now use NTFS ACLs restricted to the current user SID, SYSTEM and Administrators. Everyone, Authenticated Users and Users grants are removed. Workspace manifests receive explicit private ACLs; child files inherit the workspace ACL.
- ACL updates are non-recursive to avoid following nested symlinks to external targets. Tests check Unix 0700/0600 modes or Windows named ACL principals as appropriate. Device Agent path comparisons normalize separators.

## Commits pushed
- `7a3b8d1` — versioned execution-plan checkpoint context.
- `91d3c4d` — run full DCP platform contracts in the chat-worker cross-platform matrix.
- `5e749ef` — initial Windows workspace ACL enforcement.
- `aac5ffb` — include workspace ACL service/tests in cross-platform CI triggers.
- `6d706d5` — ACL inheritance without recursive symlink traversal and Windows path normalization.

## Validation
- Local `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- Local background worker E2E: `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`.
- Local delayed-provider HTTP E2E: `DCP_CHAT_JOB_HTTP_E2E_PASS`.
- PHP lint, JS syntax and `git diff --check`: pass.
- GitHub run `45587700487`: Ubuntu 24.04 full suite + worker + HTTP E2E PASS; Windows Server 2025 full suite PASS; macOS 15 queued at last check.
- GitHub Windows build `45587700142`: PASS. Signed update manifest/delta contract `45587700200`: PASS. Linux WebView and distro package workflows `45587700650` and `45587700677` were still running at last check.

## Next
1. Finish macOS 15 matrix and Linux build/distro workflow checks.
2. Connect the actual agent execution coordinator/tool loop to plan/step/result/approval persistence.
3. Implement reviewed idempotent step-level resume and worker lease heartbeat/crash recovery.
4. Add human queue review/context merger/replanning; queued instructions remain manual and never auto-execute.
