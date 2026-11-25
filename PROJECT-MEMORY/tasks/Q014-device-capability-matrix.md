# Q014 — Per-device workspaces, capability matrix and signed remote task protocol

Date: 2026-11-25
Status: active — signed Device Agent transport implemented and end-to-end tested locally; live cross-platform acceptance remains open
Roadmap: Stage 6 — Device Collaboration and Agents

## Delivered

### Per-device workspace isolation
- `DeviceWorkspaceService` allocates a private workspace under each installation's DCP user-data root, keyed by the authenticated Device Agent ID.
- SQLite stores stable workspace identity; `.dcp-workspace.json` manifest binds device ID to workspace ID.
- Workspace roots use private permissions (0700 directory, 0600 manifest where supported).
- Relative path resolver rejects traversal, absolute paths, drive-prefixed paths and symlinks.
- Device Agent now provisions its own workspace automatically after authenticated heartbeat and uses it as the worker root unless an explicit `DCP_WORKSPACE` override is supplied.
- Existing worker path validation was hardened for single-backslash traversal and Windows drive-prefixed paths.

### Ed25519 task transport
- Device Agent creates an Ed25519 keypair; private key is encrypted at rest using the existing DCP Crypto key.
- Agent registers its public key with the server over HTTPS using its paired bearer token.
- Server pins the agent public key to that paired device; silent key rotation is rejected.
- Server returns its own signing public key. Agent pins it in a separate trusted-device registry; changed or revoked keys require explicit re-approval/re-pairing.
- Approved remote tasks are issued as canonical-JSON Ed25519 envelopes binding schema, envelope ID, issuer, issuer key ID, target device, task ID, action, issue/expiry times, nonce and payload.
- Envelopes expire within 15–300 seconds; remote worker actions are limited to `workspace.read` and `workspace.write`, with exact payload/action consistency checks.
- Agent verifies issuer trust, signature, target, task ID, expiry, action and replay ledger before invoking the allow-listed worker.
- Agent signs the task result; server verifies the paired agent's signature and atomically acknowledges the exact envelope/task/device before marking the task completed or failed.
- A registered signing key cannot downgrade to unsigned results. Revoked signing keys block polling rather than falling back to legacy mode.

### Backward compatibility
- Older agents that have not registered an Ed25519 key continue to receive the legacy bearer-token task payload and can submit legacy results.
- This compatibility mode is explicitly visible in the Devices capability matrix as `available_legacy`.
- New agents are shown as `available_signed`; revoked devices are shown unavailable/revoked.
- Agent startup falls back to legacy mode only when the server does not expose the signing-key endpoint (HTTP 404); other errors fail closed.

### UI / API
- `/api/cloud/status` distinguishes DCP Cloud presence/signaling pairings from local legacy/modern Device Agent pairings.
- Devices page shows local workspace, local capability matrix, Cloud signaling pairings and per-agent task/signature status.

## Verification
- `platform/tests/run.php`: completed successfully (exit 0), including full security contracts, Q012 Task Inbox, workspace/envelope tests and end-to-end signed API test.
- Signed API end-to-end test creates and explicitly approves a task, registers an agent key, polls a signed envelope, verifies and executes it in the agent's private workspace, signs the result, and confirms the server verifies/consumes it.
- Negative tests: forged result signature rejected; signed device cannot submit unsigned results; envelope tampering, wrong target, task-ID substitution, expiry, key rotation and replay are rejected.
- Legacy compatibility test: an unregistered older agent receives legacy payload and its bearer-authenticated result remains accepted.
- Workspace tests: private permissions, stable identity, traversal, backslash traversal, drive-prefix, absolute path and symlink rejection; worker read/write confined to the assigned workspace.
- Isolated HTTP smoke: `DCP_DC46_HTTP_PASS`.
- Isolated Chrome DOM smoke: `DCP_DC46_CHROME_DOM_PASS`.
- PHP lint across app/bootstrap/tests/agent: 55 files passed.
- `node --check platform/public/assets/app.js`: passed.
- `git diff --check`: passed.

## Remaining acceptance work
1. Run the upgraded Device Agent on real Windows, Ubuntu and macOS installations and verify pairing, key pinning, task execution and signed results.
2. Verify installer/updater supplies the new agent code and preserves its local signing key/workspace across updates.
3. Add task lease expiry/recovery for devices that disconnect after claiming an envelope but before returning a result.
4. Add operator-visible signing-key revocation/re-pair flow and audit controls.
5. Add long-running/restart/clock-skew/network interruption tests on each supported OS.

## Safety / limits
- Only allow-listed workspace file operations are supported remotely; arbitrary terminal execution and browser control remain unavailable.
- Every remote task still requires explicit approval before polling.
- Signed envelope verification does not itself execute tasks; only the Device Agent's existing allow-listed worker runs after verification.
- Legacy mode remains less hardened by design for older clients; it is not used once a device has registered an Ed25519 key.
- Changes are local/uncommitted until the DC46 commit; no production deployment or real remote device was changed during these tests.
