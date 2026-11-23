# DCP access inventory — 2026-11-24

This file records connection methods and verification status only. It must never contain passwords, private-key material, API tokens, recovery codes or secret values.

## GitHub
- Repository: `AmrAdeldotcom/DCP`.
- SSH alias: `github-dcp`.
- Configured key file: `~/.ssh/dcp-github-write` (private key contents are not read or stored here).
- Current branch: `fix/alpha7-localai-cloud-pairing`.
- Latest pushed code commit: `e3b8bf2db2e827c07a227f39ec2b4cbb33682a23`.
- Verification: authenticated SSH pushes succeeded; GitHub Actions inspected through the connected GitHub app.
- GitHub CLI (`gh`) is not installed.

## Hostinger / DCP Cloud
- SSH alias: `dcp-hostinger` in the Mac default SSH config.
- Endpoint: `145.223.105.238:65002`, user `u163155085`.
- Private key: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/dcp-hostinger-2026` (never copy key contents into project files).
- Verification: key-based SSH login succeeded; project directory is `/home/u163155085/domains/amradel.com/public_html/dcp`.
- Live health: `https://dcp.amradel.com/api/v1/health` returns HTTP 200 with database/schema OK.
- Live PHP CLI: PHP 8.5.13; PDO drivers mysql and sqlite.
- Read-only DB inventory: 6 tables (users, api_tokens, devices, connection_requests, signaling_messages, guest_invites). `devices.user_id` is NOT NULL. Pairing tables `pairing_codes`, `pairing_requests`, `device_pairings` are missing.
- Live `index.php` does not contain anonymous-device registration or pairing routes; its hash differs from current `platform/cloud/index.php`. Current local cloud code requires `platform/cloud/schema.migrate.sql` before the new entry point is deployed.
- Direct HTTP access to `.env` and schema files on `dcp.amradel.com` returned 403. The live `.env` filesystem mode is 0644; review and tighten to 0600 after confirming Hostinger PHP execution permissions.
- No live files, permissions, database rows or schema have been changed. No migration or deployment has been performed.

## Windows lab node
- SSH alias: `windows-dcp-admin`.
- Endpoint: `192.168.8.62:22`, user `dcpadmin`.
- Private key: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/dcp-lab-admin-2026`.
- Host ED25519 fingerprint was confirmed on the Windows console and exactly matched the network scan; pinned in Mac `known_hosts`.
- Verification: key-based SSH login succeeded; Windows 11 Pro build 26100 x64, Parallels virtual platform, Intel Xeon W3680, 4 vCPU, 7.98 GiB RAM, C: 254.6 GB with about 217.2 GB free. OpenSSH service is running/automatic. `dcpadmin` is a local Administrator.
- Installed DCP copy: `C:\Users\amradeldotcom\AppData\Local\DCP`; product version commit `5b91bcc66a5caf7e8b06e93d8dee52ce35633aaa` (2026-11-21). Bundled PHP 8.5.13 x64 with curl, intl, mbstring, openssl, pdo_sqlite and sodium.
- Existing installed copy has `platform/data/dcp.sqlite` and `platform/storage/keys/master.key` under the application tree; `DCP-UserData` currently contains only WebView2 data. This installed copy was inspected by path/metadata only and was not modified. Preserve it until the newer package/data layout is validated.

## Ubuntu lab node
- SSH alias: `ubuntu-dcp-admin`.
- Endpoint: `192.168.8.56:22`, user `amr`.
- Private key: same dedicated lab-admin key as Windows.
- Verification: key-based SSH login succeeded; Ubuntu 24.04 LTS x86_64, 2 vCPU Intel Xeon W3680, 7.7 GiB RAM, 64 GB disk with about 49 GB free. SSH is active. Python 3 and OpenSSH are installed; Git, PHP, Composer, Node/npm, Docker/Podman and Ollama were not found on PATH. Docker service is inactive.
- Ubuntu was added for read-only inventory; Linux implementation remains deferred by the current work queue.

## Mac / Desktop Commander
- Device: `Amrs-Mac-Pro`, Desktop Commander Remote 0.3.4, online.
- Repo: `/Volumes/Data/ChatGPT/DCP`; branch `fix/alpha7-localai-cloud-pairing`; HEAD `e3b8bf2db2e827c07a227f39ec2b4cbb33682a23`.
- External SSH folder: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/`; preserve this organization preference.
- Mac data volume had about 8.1 GiB free (99% used); do not compile large runtimes locally.

## Operational rules
- Never print, copy into project files, or commit secret values.
- Do not treat a configured alias as proof of working authentication; test it with a non-mutating connection probe.
- No cloud migration/deployment, production permission changes, package release or public tunnel until the corresponding review/approval gates are met.
- Keep DCP's source-of-truth repo at `/Volumes/Data/ChatGPT/DCP`; do not commit unrelated dirty changes.
