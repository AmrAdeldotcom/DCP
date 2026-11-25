# DC46 — Stage 6 signed Device Agent transport end-to-end

Timestamp: 2026-11-25T05:57:38+02:00
Device: Amrs-Mac-Pro
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
Stable tag/main: `v1.0.0` / `cb3087e27c344af774ecf60aca40c98904859a85` (unchanged)

## Roadmap status
- Stage 6 — Device Collaboration and Agents remains open.
- Q006 Local AI / llama.cpp remains complete.
- Q012 Task Inbox remains active; persistent manual/review-only first slice is implemented and tested.
- Q014 signed Device Agent transport is integrated and end-to-end tested locally; real installed-device acceptance on Windows/Ubuntu/macOS remains outstanding.
- Q005 stable release remains open pending publication, cleanup verification and live target update/rollback/data-preservation acceptance.

## Major implementation completed
- Per-device workspaces are allocated by `DeviceWorkspaceService` under the installation's DCP user-data root with stable DB identity, manifest, private permissions and path validation.
- `device-agent.php` now discovers its authenticated device ID from heartbeat, loads its local DCP data root, provisions its own workspace automatically and uses that workspace for the existing allow-listed worker.
- Device Agent creates an Ed25519 keypair and registers its public key over HTTPS with its paired bearer token.
- Server pins the agent public key to the paired device and returns its own signing public key. Agent pins the server key locally; silent key changes and revoked trust cannot be silently re-enabled.
- Server `/api/device/tasks` issues canonical signed envelopes binding issuer/key ID, target device, task ID, action, payload, nonce and expiry. It transitions task claim and envelope creation transactionally.
- Agent verifies trusted issuer key, signature, target, task ID, action/payload consistency, expiry and replay ledger before worker execution.
- Agent signs results; server verifies the registered device public key and atomically consumes the exact issued envelope with the task result update.
- Registered agents cannot downgrade to unsigned results. Revoked agent keys block task polling.
- Older agents without a registered key retain the legacy bearer-token payload/result compatibility path; Devices UI reports `available_legacy` vs `available_signed`.
- Worker path validation hardened for backslash traversal and drive-prefixed paths.

## End-to-end evidence
`platform/tests/device-agent-signed-api.php` runs a disposable DCP API server and a real subprocess Device Agent. It:
1. Creates and pairs test devices.
2. Creates a remote task in `approval_required` and explicitly approves it.
3. Agent registers/pins Ed25519 keys and gets the task envelope.
4. Agent verifies the signature/target/task ID, writes only inside its per-device workspace, and signs the result.
5. Server verifies the signed result and consumes the envelope; task becomes completed.
6. Forged signatures are rejected and cannot complete a task.
7. A signed device cannot submit an unsigned result.
8. An older unregistered agent still receives the legacy allow-listed payload and can return a bearer-authenticated result.

Other tests cover replay, expiry, tampering, wrong target, task ID substitution, key rotation/revocation, backslash/drive traversal, absolute paths, symlinks, workspace permissions and Task Inbox/capability UI.

## Validation
- `php platform/tests/run.php`: completed successfully (exit code 0).
- PHP lint across app/bootstrap/tests/agent: 55 files passed.
- `node --check platform/public/assets/app.js`: passed.
- Isolated HTTP smoke `DCP_DC46_HTTP_PASS`: passed.
- Isolated Chrome DOM smoke `DCP_DC46_CHROME_DOM_PASS`: passed.
- `git diff --check`: passed.

## Remaining
1. Run upgraded Device Agent on actual Windows, Ubuntu and macOS devices and verify signing, task execution and workspace behavior.
2. Confirm installers/updater ship the new agent code and preserve local signing keys and workspaces across upgrades.
3. Implement task lease timeout/recovery for interrupted devices.
4. Add UI for signing-key revocation and explicit re-pair.
5. Run long-duration/restart/clock-skew/network-loss acceptance.

## Git / release boundaries
- Changes remain local and uncommitted at this checkpoint; next action is a scoped commit/push to `feature/local-ai-runtime-discovery` only.
- Do not move `main` or `v1.0.0` while stable release workflow `45538884432` is still running its preflight.
- No production deployment, real remote-device modification, or user-data migration was performed.
