# DCP Security and Trust Model

## Local device
- The desktop wrapper starts a loopback-only service; no LAN/public bind by default.
- Local terminal runs as the signed-in OS user and may execute arbitrary user-level commands because the device owner explicitly requested a full local terminal. It must never silently elevate to Administrator/root.
- Web/tunnel preview mode must disable unrestricted terminal, local file browsing and secret-bearing diagnostics unless protected by an authenticated operator session.

## Remote device
- Every remote device gets a unique identity and revocable device credential.
- Each remote device has a separate workspace root; file tools and terminal cannot escape that root.
- Capability scopes are explicit and independently revocable: workspace read/write, file transfer, isolated environment, shell tier, APIs, local AI, mail, browser, chat and task execution.
- Shell tiers: workspace file operations; isolated environment; full trust. Full trust is a high-risk bilateral grant requiring both sides to approve the exact capabilities, duration and workspace boundary.
- Local terminal authority is not inherited by a remote peer. The local user approves remote actions separately.

## Cloud control plane
- Cloud stores accounts, device metadata, hashed/revocable tokens, scopes, invitations, pairing requests, presence and signaling envelopes only.
- No local database, model weights, provider API keys, email passwords or workspaces are uploaded to the control plane.
- Anonymous pairing is short-lived; account-linked devices are durable and manageable.
- Cloud URL is configurable; HTTPS certificate validation is mandatory.
- Every privileged API checks device identity, scope, expiry, replay protection and audit record.

## Secrets and web hosting
- Use OS Keychain/Credential Manager when possible; otherwise encrypt at rest with a per-install key and owner-only file permissions.
- Never store credentials in shell history, source files, logs, screenshots or Git.
- Cloud deployment keeps .env and keys outside public access; migration is backed up and reviewed before production execution.
- Web root denies config, app, bootstrap, cloud, dcp-env, data, storage, scripts, tests, vendor metadata, .env and SQLite/key files.
- Public test route requires Cloudflare Access or equivalent authentication.
