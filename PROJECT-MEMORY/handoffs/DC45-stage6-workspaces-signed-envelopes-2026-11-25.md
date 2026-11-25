# DC45 — Stage 6 isolated workspaces, signed envelopes and legacy agent reconciliation

Timestamp: 2026-11-25T05:45:10+02:00
Device: Amrs-Mac-Pro
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
Stable tag/main: `v1.0.0` / `cb3087e27c344af774ecf60aca40c98904859a85` (unchanged)

## Roadmap status
- Stage 6 — Device Collaboration and Agents remains open.
- Q006 Local AI / llama.cpp remains complete.
- Q012 Task Inbox remains active; its first slice is a manual/review-only persistent queue.
- Q014 is active; per-device local workspaces and Ed25519 envelope foundation are implemented locally, but signed envelopes are not yet integrated into remote Device Agent polling.
- Q005 stable release remains open pending publication, cleanup verification and live target update/rollback/data-preservation acceptance.

## New implementation
- `platform/app/Services/DeviceWorkspaceService.php`: private per-device workspace roots under DCP user data, stable DB identity, manifest, 0700 permissions, relative-path checks, traversal rejection and symlink rejection.
- `platform/app/Services/TaskEnvelopeService.php`: per-device Ed25519 signing key registration, encrypted private-key persistence, explicit trusted-device public-key registry, canonical JSON signatures, target-bound envelopes, 15–300 second TTL, action allowlist and replay ledger.
- `platform/app/Core/Database.php`: additive tables for device workspace identities, signing keys, approved trust keys and consumed envelope IDs.
- `ApiController` / Devices page: current local workspace identity displayed; capability API distinguishes Cloud signaling pairings from legacy task-agent pairings.
- `DeviceCapabilityMatrix`: legacy bearer-token task polling is reported as `available_legacy` for paired devices and requires explicit task approval. Signed envelope transport is separately shown as `not_integrated`; arbitrary remote terminal and browser control remain unavailable.
- `platform/tests/device-workspace-envelope.php`: isolated workspace identities, file permissions, traversal/symlink checks, encrypted key storage, Ed25519 verify, wrong target, payload tamper, key trust/revocation, expiry, canonical JSON and replay checks.

## Important repository discovery / correction
The repository already had a legacy remote task transport: `platform/plugins/agents/dcp-node/device-agent.php` polls `/api/device/tasks` with a paired bearer token, and `worker.php` only exposes allow-listed workspace file actions. Tasks are gated by `/api/tasks/approve`. This is not the same as DCP Cloud presence/signaling pairing. The capability matrix and Devices UI now separate those two systems and do not falsely claim remote task polling is absent. However, legacy polling does not yet use the new Ed25519 envelope/replay protections.

## Validation
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED, including security contracts, workspace/envelope tests, Task Inbox and capability matrix tests.
- PHP lint across app/bootstrap/tests: passed.
- `node --check platform/public/assets/app.js`: passed.
- Isolated HTTP smoke `DCP_DC45_HTTP_PASS`: local workspace allocation, local/legacy capability response, CSRF rejection, Task Inbox enqueue/list/reprioritize/mark-sent/cancel all passed.
- Isolated Chrome DOM smoke `DCP_DC45_CHROME_DOM_PASS`: Task Inbox, local workspace indicator and capability panels rendered.
- `git diff --check`: passed.

## Limits / safety
- Signed envelopes are not yet wired into `/api/device/tasks` or `/api/device/task-result`; do not describe remote execution as signature-protected yet.
- Trust key exchange over DCP Cloud pairing is not implemented; trust registration currently requires an explicit local service call.
- The Device Agent's `DCP_WORKSPACE` selection is not yet automatically bound to the new per-device workspace service.
- No task is automatically executed by the new envelope service; verification only consumes the envelope.
- Changes remain local and uncommitted on `feature/local-ai-runtime-discovery`; no push, merge, release, Cloud deployment or live remote-device modification.
