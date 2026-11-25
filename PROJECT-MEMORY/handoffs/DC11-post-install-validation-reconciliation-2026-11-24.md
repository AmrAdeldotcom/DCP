# DC11 — post-install validation reconciliation

Timestamp: 2026-11-24T15:50:02+02:00 (Africa/Cairo)

## GitHub release and CI
- Published `v1.0.0-alpha.9` (release `456688871`) resolves through its annotated tag to commit `8b5bf08d6291897338ca322432ccf1962e0c2ed1`. It is a published prerelease with exactly six uploaded assets: Windows ZIP/update manifest, Intel macOS ZIP, Apple Silicon macOS ZIP, Linux tarball and SHA-256 sidecar. Asset digests were re-read from GitHub API.
- Tag release workflows all completed successfully on `8b5bf08d6291897338ca322432ccf1962e0c2ed1`: macOS release `45387266140`, macOS clean package `45387266141`, Linux release `45387266328`, Windows release `45387266284`, Windows clean package `45387266218`. Both native macOS package jobs, both PHP runtimes, both llama runtimes and the combined publisher job succeeded.
- Current branch `fix/alpha7-localai-cloud-pairing` and origin HEAD are `0e3f41196e0819e94a8bfa99ecf95378a7a71592`. `main` remains `25c81e23e0a6810a7c9b70a33d0794b00ed5de0d`; no merge or tag rewrite was attempted.
- Linux package fix commit `0e3f41196e0819e94a8bfa99ecf95378a7a71592` has green CI run `45398600124`: build, package assertions, HTTP smoke and artifact upload all succeeded. Artifact `DCP-Linux-x64` ID `14922751159`, 585,549 bytes. Windows build `45398600263` and Cloud PHP `45398600090` also passed. The corrected Linux artifact is a CI artifact only, not attached to a release.

## Windows Alpha 9 install and data safety
- Windows VM `192.168.8.99` is reachable using verified alias `windows-dcp-admin`. Target profile is `AMRWIN11\amradeldotcom`; SSH identity `dcpadmin` was not used as the install profile.
- The independent pre-install backup from DC09 remains at `C:\DCP-Backups\DCP-pre-alpha9-20261124-152849` (668 files, 228.66 MiB, zero failed/mismatched files). A second read-only-state backup was also created at `C:\Users\amradeldotcom\AppData\Local\DCP-Backups\20261124-1535-EET` (550 files, 229,528,770 bytes; Robocopy exit 1 meaning files copied with no reported failure). Both contain the database and master key. The installer rollback copy remains at `C:\Users\amradeldotcom\AppData\Local\DCP.backup-20261124-153339`. No backup was deleted.
- The immutable tag-pinned installer SHA-256 was `edf9a1b02f16b0b9ab5c178f994979519e1369fe6638022e337d3429d03a4c53`. Installed app metadata is `1.0.0-alpha.9`; bundled PHP runtime is 8.5.13 and `verify-runtime.php` passed.
- Database path `DCP-UserData\Windows\data\dcp.sqlite` and key path `DCP-UserData\Windows\storage\keys\master.key` both retain their original SHA-256 values after install and match both independent backups: DB `a2f6252cb0c6984bc2c7b1a30f65e49f0d9a69f2ac2950d613e7c54ecb93fa45`; key `b1d13ad07a1dffc595cb63ee49f6ab0bd748c5155a142d753dd273a4ac10e00d`. SQLite `PRAGMA integrity_check` returned `ok`. The Crypto constructor accepted the key and an encrypt/decrypt round-trip returned `ok`. There were zero persisted encrypted provider/settings/mail values to decrypt, so legacy-secret decryption could not be exercised with actual stored ciphertext.
- During the Explorer-launched smoke, HTTP `/` and `/api/status` both returned 200 on loopback port 58143. API reported `ok=true`, SQLite, 23 plugins; Ollama was offline, Desktop Commander Remote was not connected, DSH was not installed, and devices count was zero. DC10 records the user's screenshot showing the Overview UI rendered. The desktop shortcut exists. The app process was not running at the final post-cleanup process check; no app files or backups were removed.
- A later redundant scheduled-task runner attempt returned Task Scheduler result 1 and produced no trustworthy installer transcript; it is not counted as a successful installer run. Temporary task definitions and Public test/installer files were removed. The earlier successful install is documented in DC09, and the direct runtime, DB/key, HTTP and screenshot evidence above remain valid.

## Ubuntu Alpha 9 remains blocked by the published package
- Ubuntu VM `192.168.8.88` is reachable. Installed Alpha 9 still has DCP PID 754095 and PHP PID 754123 on `127.0.0.1:18765`; HTTP `/` and `/api/status` both return 500. PHP log confirms missing `platform/app/Core/Database.php` required by `platform/bootstrap/app.php:32`.
- The source fix is already pushed and CI-validated in `0e3f41196e0819e94a8bfa99ecf95378a7a71592`, but the fixed tarball has not been published in a superseding release. Do not overwrite the published Alpha 9 tag or replace the installed Ubuntu tree until a new complete release is approved.

## Mac Intel and network watchdog
- Mac smoke target `~/Applications/DCP-Alpha9-Smoke` is absent. Original services remain listening: PHP PID 92383 on 18991, llama-server PID 99659 on 19123, Ollama PID 2106 on 11434. Source HEAD remains `0e3f41196e0819e94a8bfa99ecf95378a7a71592`; only project memory/roadmap/handoff files are dirty/untracked.
- LaunchAgent `com.digipl.dcp-network-watchdog` reports a 600-second interval, last exit code 0, run count 4. Latest state file records gateway and internet reachable at 2026-11-24T13:47:00Z (15:36:59 Cairo); log has no offline-to-online transition and no `NETWORK-RESUME-REQUEST.json` marker exists. It is a one-shot check every 600 seconds, not a persistent ChatGPT session.

## Human checkpoint
- Request approval to publish a superseding complete alpha release containing the already CI-validated Linux fix. Keep `v1.0.0-alpha.9` immutable. Review/synchronize `main` separately; it is behind and its README installer links do not match the tag-pinned scripts.
- After approval: publish all six synchronized assets, then replace/test Ubuntu using the corrected release and verify HTTP + GUI. Do not delete either Windows rollback backup until the user confirms any expected prior encrypted settings/data.

## Checkpoint reached — 2026-11-24T15:51:37+02:00
- Paused the hourly `DCP Alpha 9 Watchdog` automation because the next action requires explicit user approval to publish a superseding release.
- The independent local `com.digipl.dcp-network-watchdog` LaunchAgent remains enabled at 600-second intervals; it continues recording connectivity and recovery markers but cannot reopen a ChatGPT session.
