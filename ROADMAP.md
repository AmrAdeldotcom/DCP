# DCP Roadmap

## Stage 0 — Baseline, docs and safety (active)
- [x] Capture current product requests and screenshots.
- [x] Read the supplied Speed Engineer skill.
- [x] Establish source-of-truth Git worktree at /Volumes/Data/ChatGPT/DCP and preserve the installed alpha.8 copy.
- [x] Install the requested engineering skills and pin their source revisions.
- [x] Write the project map, security model, performance audit and work queue.
- [ ] Confirm the public tunnel route is protected before publishing a test entry point. (Blocked: Apache AllowOverride None; mod_rewrite unavailable; authentication/rate-limit/private-path checks remain.)

## Stage 1 — Restructure without breaking desktop builds
- [x] Promote the Git repository to /Volumes/Data/ChatGPT/DCP with reversible backup.
- [x] Add platform/index.php and hardened platform/.htaccess. (Files are present; web-server enforcement and public route remain blocked under Q004.)
- [x] Move platform/cloud source to platform/cloud and update workflows.
- [x] Add dcp-env manifests and OS-specific bootstrap/install/update/verify scripts. (Mac managed PHP build recipe and native Intel/ARM64 CI workflow are configured; CI build and clean-install verification remain outstanding; see Q005.)
- [ ] Add root launchers and keep release ZIP + terminal install parity.
- [ ] Preserve Windows and macOS local databases/keys; do not package runtime data. (Packaging exclusions exist; full release build/restore verification remains.)
- [ ] Add the dcp-shortcut symlink only after web-root security tests pass.

## Stage 2 — Repair core reliability (Mac + Windows)
- [ ] Fix local chat failure with captured request/response and engine logs.
- [ ] Remove llama.cpp fixed port 8080; allocate and persist a free port.
- [ ] Fix engine install idempotency, process ownership, status refresh and uninstall.
- [ ] Fix provider delete, persistent global notifications, keyboard clipboard behavior.
- [ ] Reconcile Desktop Commander adapter status and add lifecycle actions.

## Stage 3 — Cloud identity and device management
- [ ] Audit Hostinger Cloud API and database schema before migration.
- [ ] Fix route contract mismatch behind not_found and email_already_registered.
- [ ] Add configurable Cloud base URL and health/compatibility display.
- [ ] Move login/register to global header modal; move pairing into Devices.
- [x] Device inventory: online/offline, identity, scopes, pause/resume/revoke. (Local UI/API/schema and contract tests pass; live two-device acceptance is pending the separately approved Cloud deployment.)
- [ ] Persist account-linked identities and provide transparent 24-hour anonymous pairing policy.
- [ ] Verify two real devices (Mac + Windows) through full pairing and revocation tests.

## Stage 4 — Mail as an application capability
- [ ] Create Mail page with inbox/outbox/drafts/sent/failed, search and pagination.
- [ ] Support multiple accounts and CC/BCC, attachments, send/receive and reply threading.
- [ ] Test Titan SMTP SSL 465 and IMAP SSL 993 without logging secrets.
- [ ] Expose a permission-gated mail tool to Chat, Tasks and agents; keep approval and audit trail.

## Stage 5 — Local AI and custom models
- [ ] Fix chat provider/model discovery and response handling.
- [ ] Add custom GGUF import with size/hash/free-space validation.
- [ ] Manage llama.cpp and Ollama models (import/convert/create/list/run/stop/delete).
- [ ] Preserve external Ollama installation and detect its custom paths.
- [ ] Separate system and DCP-managed runtimes and model directories.

## Stage 6 — Device collaboration and agents
- [ ] Add per-device workspace and explicit capability matrix.
- [ ] Local terminal is full user-level shell; remote terminal is scoped to workspace.
- [ ] Add remote task transport, signed task envelope, replay protection, approval and audit.
- [ ] Add shared chat participants and @dc/@dsh dispatch with approval cards.
- [ ] Evaluate Playwright + managed Chromium for shared visible browser sessions; prototype only after permissions/transport are ready.
- [ ] Implement hierarchical memory with per-user/per-device scopes, provenance, retention and export/delete.

## Stage 7 — Performance, hardening and release
- [ ] Complete Speed Engineer audit and apply evidence-backed fixes.
- [ ] Code review and security audit before each release.
- [ ] End-to-end regression on Mac + Windows; verify Linux/Kali updater compatibility only after those pass.
- [ ] Publish stable release notes and verified download/terminal instructions.
