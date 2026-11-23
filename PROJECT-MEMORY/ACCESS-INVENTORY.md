# DCP access inventory — 2026-11-23

This file records connection methods and verification status only. It must never contain passwords, private-key material, API tokens, recovery codes or secret values.

## GitHub
- Repository: `AmrAdeldotcom/DCP`.
- SSH alias: `github-dcp`.
- Configured key file: `~/.ssh/dcp-github-write` (private key contents are not read or stored here).
- Verification: SSH authentication succeeded and fast-forward push to `fix/alpha7-localai-cloud-pairing` was verified at commit `41cd3d3c2b5b95034bb43de883a44c455638ad88`.
- GitHub CLI (`gh`) is not installed. Git operations use the configured SSH alias; GitHub metadata and Actions can be inspected through the connected GitHub app.

## Hostinger / DCP cloud
- SSH alias: `dcp-hostinger`.
- Configured key file: `~/.ssh/dcp-hostinger`, present with mode `0600`.
- Verification: verbose SSH confirmed that the key is offered, but the server rejected it with `Permission denied (publickey,password)`.
- Status: cloud SSH access is not authenticated. No password guessing, alternate-key spraying, deployment, database migration or file upload is allowed until an approved login is restored.
- Previous DCP cloud API check returned 404 for anonymous device registration; cloud deployment was on hold pending API entry-point deployment and validation.

## Lab devices
- SSH config and key folder: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/` on the external drive.
- Key: `dcp-lab` (ED25519; public-key comment identifies the DCP lab key).
- `windows-dcp`: configured for the Windows LAN host; SSH connection timed out / host reported down during this check.
- `ubuntu-dcp`: host and username are placeholders, so it is not configured for login.
- Preserve the user's preference to keep lab SSH configuration and keys on the external drive.

## Desktop Commander
- `Amrs-Mac-Pro` is online through Desktop Commander Remote, app version 0.3.4.
- This provides access to the Mac only; the Windows and Ubuntu SSH routes above are not currently reachable.

## Operational rules
- Never print, copy into project files, or commit secret values.
- Do not treat a configured alias as proof of working authentication; test it with a non-mutating connection probe.
- No Hostinger deployment until the server accepts the configured key or the user supplies an approved login path.
- Keep DCP's source-of-truth repo at `/Volumes/Data/ChatGPT/DCP`; do not commit unrelated dirty changes.
