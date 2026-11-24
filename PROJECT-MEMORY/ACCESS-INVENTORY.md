# DCP access inventory — 2026-11-24

This file records connection methods and verification status only. It must never contain passwords, private-key material, API tokens, recovery codes or secret values.

## GitHub
- Repository: `AmrAdeldotcom/DCP`.
- SSH alias: `github-dcp`.
- Configured key file: `~/.ssh/dcp-github-write` (private key contents are not read or stored here).
- Current branch: `feature/alpha10-package-revision`.
- Latest pushed code commit verified in DC18: `4ddb91e38a0a919cfe7d7bc01cfc5a74f1a88f50`.
- Verification: authenticated SSH pushes succeeded; GitHub Actions inspected through the connected GitHub app.
- GitHub CLI (`gh`) is not installed.

## Hostinger / DCP Cloud
- SSH alias: `dcp-hostinger` in the Mac default SSH config.
- Endpoint: `145.223.105.238:65002`, user `u163155085`.
- Private key: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/dcp-hostinger-2026` (never copy key contents into project files).
- Verification: key-based SSH login succeeded; project directory is `/home/u163155085/domains/amradel.com/public_html/dcp`.
- Live health: `https://dcp.amradel.com/api/v1/health` returns HTTP 200 with database/schema OK.
- Live PHP CLI: PHP 8.5.13; PDO drivers mysql and sqlite.
- Live database migration was applied and verified after backups; `devices.user_id` is nullable and pairing tables plus database-backed rate-limit table are present.
- Current cloud API entry point and migration are deployed. Temporary account/device/pairing/signaling/revocation end-to-end tests passed and test records were cleaned up. Full desktop-client integration and exhaustive authorization/abuse audit remain open.
- Private `.env` was moved outside the public webroot and set to filesystem mode 0600. Direct HTTP checks: `/.env` 403, schema/private paths denied, `/` 200, `/api/v1/health` 200. Request-body cap was verified with HTTP 413 for oversized login payloads.
- Production deployment was performed after verified backups under `/home/u163155085/dcp-backups/`; latest hardening backup: `20261124T232000Z`. Do not repeat migration/deployment without a new backup and review.

## Windows lab node
- SSH alias: `windows-dcp-admin`.
- Endpoint: `192.168.8.99:22`, user `dcpadmin`.
- Private key: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/dcp-lab-admin-2026`.
- Use the dedicated config explicitly: `ssh -F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config windows-dcp-admin`. The default `~/.ssh/config` alias is stale and still points to `192.168.8.62`; do not use it.
- Host ED25519 fingerprint was confirmed on the Windows console and exactly matched the network scan; pinned in Mac `known_hosts`.
- Verification: key-based SSH login succeeded. Read-only DC18 probe on 2026-11-24 18:54 EET returned `amrwin11\dcpadmin`; PowerShell returned machine `AMRWIN11` and a timestamp. `dcpadmin` is the SSH administrator; the installed app belongs to interactive user `amradeldotcom`. No install or file mutation performed in DC18.
- Installed DCP copy: `C:\Users\amradeldotcom\AppData\Local\DCP`; product version commit `5b91bcc66a5caf7e8b06e93d8dee52ce35633aaa` (2026-11-21). Bundled PHP 8.5.13 x64 with curl, intl, mbstring, openssl, pdo_sqlite and sodium.
- Existing installed copy has `platform/data/dcp.sqlite` and `platform/storage/keys/master.key` under the application tree; `DCP-UserData` currently contains only WebView2 data. This installed copy was inspected by path/metadata only and was not modified. Preserve it until the newer package/data layout is validated.

## Ubuntu lab node
- SSH alias: `ubuntu-dcp-admin`.
- Endpoint: `192.168.8.88:22`, user `amr`.
- Private key: same dedicated lab-admin key as Windows.
- Use the dedicated config explicitly: `ssh -F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config ubuntu-dcp-admin`. The default `~/.ssh/config` alias is stale and still points to `192.168.8.56`; do not use it.
- Verification: key-based SSH login succeeded. Read-only DC18 probe on 2026-11-24 18:54 EET returned `amr-ubuntu24`, Ubuntu 24.04 LTS x86_64, `/usr/bin/python3` and `/usr/bin/php`. No DCP or PHP built-in server process was running. No files or packages changed.
- Ubuntu is an active Q005 validation target. The published Alpha 9 package remains installed and is known to lack platform/app/Core/Database.php; no replacement or updater install has been performed. Packaged Linux updater implementation is still outstanding.

## Mac / Desktop Commander
- Device: `Amrs-Mac-Pro`, Desktop Commander Remote 0.3.4, online.
- Repo: `/Volumes/Data/ChatGPT/DCP`; active Alpha 10 branch `feature/alpha10-package-revision`; HEAD `4ddb91e38a0a919cfe7d7bc01cfc5a74f1a88f50` (DC18).
- External SSH folder: `/Volumes/Data/ChatGPT/DCP-Lab-SSH/`; preserve this organization preference. Always pass `-F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config` for the lab aliases because the default `~/.ssh/config` contains stale .62/.56 endpoints.
- Mac data volume had about 8.1 GiB free (99% used); do not compile large runtimes locally.

## Operational rules
- Never print, copy into project files, or commit secret values.
- Do not treat a configured alias as proof of working authentication; test it with a non-mutating connection probe.
- No cloud migration/deployment, production permission changes, package release or public tunnel until the corresponding review/approval gates are met.
- Keep DCP's source-of-truth repo at `/Volumes/Data/ChatGPT/DCP`; do not commit unrelated dirty changes.


## DC04 live connection and Windows data preflight — 2026-11-24
- Revalidated key-based SSH access to Ubuntu and Windows from Amrs-Mac-Pro. Ubuntu returned Ubuntu 24.04 LTS x86_64 with 49 GiB free on root. Windows returned Windows 11 Pro 10.0.26100 with 218.8 GiB free on C:.
- Important account distinction: SSH administrator session is `dcpadmin`, but the existing DCP user installation is under `C:\Users\amradeldotcom\AppData\Local\DCP`, not the administrator profile.
- Read-only preflight confirmed the existing user's `platform/data/dcp.sqlite` exists (102,400 bytes), `platform/storage/keys/master.key` exists (44 bytes), and `DCP-UserData` does not yet exist in that install root. No files were modified. The old installation must be backed up and its DB/key migrated/verified before any new launch.
- Ubuntu SSH access is live; no application installation or package/system mutation performed.


## DC04 Windows installer execution caveat
- The online Windows installer derives its target from the current process user's `$env:LOCALAPPDATA\DCP`. A remote SSH session is authenticated as `dcpadmin`, while the existing app/data belongs to `amradeldotcom`.
- Therefore do NOT launch `Install-DCP-Online.ps1` inside the current SSH admin session with its default parameters: it would target the wrong profile. Run it in the actual `amradeldotcom` interactive user context, or add and test an explicit target-profile option before using the admin session. No installer was run.

- Windows console session preflight: `amradeldotcom` is currently logged on interactively (console session 1, Active) and is a local Administrator. This may permit running the official installer in the correct user context through an interactive-user task after the complete release is available; do not run it as `dcpadmin`.

- Ubuntu graphical-session preflight: `loginctl` shows active `amr` sessions on seat0, default target `graphical.target`, and GNOME Shell running under a Wayland session. This is useful for post-install GUI launch verification; no packages or app files were changed.

- Ubuntu post-install GUI prerequisites confirmed read-only: `sudo -n true` succeeds; `libgtk-3-0t64` 3.24.41 and `libwebkit2gtk-4.1-0` 2.52.6 are installed; active Wayland socket `/run/user/1000/wayland-0`, D-Bus socket and X11 sockets exist. This supports a real launch test through the active amr desktop session.

- Verified a temporary Task Scheduler probe using an interactive-token principal for `AMRWIN11\amradeldotcom` with highest available run level. It executed in the active user's session and reported `amrwin11\amradeldotcom` plus `C:\Users\amradeldotcom\AppData\Local`. The test task and probe file were removed. This provides a tested safe route to invoke the official Windows installer in the correct user context after the complete release exists.

- Ubuntu clean-install target `/home/amr/Applications/DCP` is currently absent; no `DCP-UserData` exists there. Safe to use as a clean install target after the synchronized release is approved and published.

## DC06 Alpha 9 Linux target validation — 2026-11-24T14:57:19+02:00
- Official tag-pinned v1.0.0-alpha.9 shell installer completed on Ubuntu 24.04 x86_64 at /home/amr/Applications/DCP. PHP 8.3.6 with required extensions passes verify-runtime.php; DCP ELF x86-64 has no missing shared libraries; the Applications launcher was created under the amr user.
- GUI launch as amr started DCP PID 742874 and PHP PID 742899 bound only to 127.0.0.1:18765. HTTP root and /api/status returned 500 because the release archive flattened platform/app into the platform root. The PHP error log identifies missing platform/app/Core/Database.php.
- Stopped only those two DCP test PIDs and verified port 18765 closed. The installed Alpha 9 package and error log are retained for diagnosis; unrelated Ubuntu services were not stopped.

## DC07 Linux fix and Windows reachability — 2026-11-24T15:00:25+02:00
- Pushed commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592 to fix/alpha7-localai-cloud-pairing. GitHub Linux build run 45398600124 passed package structure assertions, PHP HTTP smoke and artifact upload; Windows build 45398600263 and Cloud PHP 45398600090 passed.
- Corrected temporary Ubuntu package fixture launched via Run-DCP.sh and returned HTTP root 200 plus /api/status ok=true. Test PIDs 744649/744674 stopped and fixture removed. The installed published Alpha9 package at /home/amr/Applications/DCP remains broken and retained for evidence.
- Windows .62 SSH/TCP port 22 timed out, .54 TCP port 22 failed, and .62 ping had 100% loss. Desktop Commander lists only Amrs-Mac-Pro. No Windows files changed.
