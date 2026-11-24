# DC18 — Alpha 10 Linux/Windows updater hardening — 2026-11-24T18:58:54+02:00

## Start-of-run continuity
- Read `WORK-QUEUE.md`, `PROJECT-MEMORY/INDEX.md`, `PROJECT-MEMORY/CURRENT-STATE.json`, latest handoff DC17, `PROJECT-MEMORY/tasks/Q005-managed-php-macos.md`, `PROJECT-MEMORY/runtime/network-state.json` and `network-watchdog.log` before acting.
- `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json` was absent. No marker was cleared or archived.
- Network log's latest recorded transition was online; the local 600-second LaunchAgent records transitions only and cannot reopen/refresh ChatGPT. At 18:54 EET, the dedicated SSH aliases and GitHub API were reachable.

## Source commit pushed
- Branch: `feature/alpha10-package-revision`
- Previous local/remote HEAD: `33bf4826a4bca385cfdc12ba50b0087ac5304a57`
- New pushed HEAD: `4ddb91e38a0a919cfe7d7bc01cfc5a74f1a88f50` (`4ddb91e`)
- Commit: `feat(updater): add signed Windows client and crash-safe Linux transactions`
- Explicitly staged 23 source/workflow/test files; no `git add .`, no project-memory files or unrelated dirty work were included.
- Push to `origin/feature/alpha10-package-revision` succeeded. No PR created (known GitHub integration 403), no merge, no tag or release created.

## Linux updater hardening
- Packaged Linux updater now acquires a private `flock` update lock, refuses to apply while the installed DCP native binary or its PHP router is running, and never kills a process.
- Health checks use a disposable `DCP_USER_DATA_DIR`; the real SQLite database, master key, settings, workspaces, logs and models are not initialized/migrated by the updater health probe.
- Transaction journal records an operation as recoverable before mutation, uses same-directory temporary files and atomic rename, flushes/fsyncs the journal, records temporary paths, and recovers both interrupted apply operations and committed updates whose health check was interrupted.
- A package is marked healthy only after the native ELF executable, PHP runtime, API status and installed version/revision are verified. A failed health gate restores the previous package and critical SQLite/key files.
- Full-package apply now deletes only paths explicitly listed in the signed `full.deleted` manifest list. Unknown installation-root files are preserved; protected user-data and legacy platform storage remain excluded.
- Missing optional delta assets select the signed full package fallback; a present delta asset with a mismatched URL is rejected.
- Python 3 is now an explicit Linux updater dependency in the online installer, runtime installer, Ubuntu build, Kali test and package smoke. The earlier Kali CI failure on `33bf482` was caused by `python3` missing in the minimal Kali container; the fix is in this commit.

## Windows updater integration
- Native `WindowsUpdateClient.cs` verifies synchronized schema-3 signed manifests, pinned key identity, official release URLs, platform/architecture, file size/SHA-256 and exact version+packageRevision base.
- It selects full package when the installed base revision differs or the optional delta asset is absent. Full-package deletion paths are checked against staged files and protected user data.
- The native app now hands verified staging data to the transactional PowerShell updater. PowerShell replacement uses a cross-runtime atomic move helper (with a real backup path for Windows PowerShell 5.1 fallback) instead of `File.Replace(..., $null)`.
- Added C# updater contract tests and wired them into Windows CI.

## Local evidence on Amrs-Mac-Pro
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- `php platform/tests/update-manifest.php`: passed.
- `python3 platform/tests/update-package-build.py`: passed.
- `python3 platform/tests/extract-linux-update.py`: passed.
- `bash platform/tests/sign-update-manifest.sh`: passed (ephemeral test key; no production private key exposed).
- `php platform/tests/linux-update-transaction.php`: passed, including DB/master.key/settings/workspaces/logs/models preservation, signed full deletion list, unknown root-file preservation, interrupted transaction recovery and pending-health rollback.
- `php platform/tests/linux-updater-health.php`: passed, including disposable user data, missing-delta full fallback, exact delta URL, process guard and refusal to kill the test server.
- `pwsh -NoProfile -File platform/tests/update-windows.ps1`: ALL WINDOWS UPDATER TESTS PASSED, including apply, full fallback, DB/key preservation, forced rollback and unsafe user-data rejection.
- `dotnet run --project platform/tests/windows-update-client/DCP.WindowsUpdater.Tests.csproj -c Release`: ALL WINDOWS UPDATE CLIENT CONTRACT TESTS PASSED.
- `dotnet build platform/wrappers/windows/DCP.WebView.csproj -c Release -p:EnableWindowsTargeting=true -p:DCPVersion=1.0.0-alpha.10 -p:DCPPackageRevision=1 --no-restore`: succeeded.
- PHP lint, JavaScript syntax, Bash/Zsh syntax, Python compilation, PowerShell parser and `git diff --check`: passed.
- Temporary install-root `DCP_INSTALL_ROOT=<tmp> php platform/scripts/update-linux.php --check` returned `No complete synchronized signed DCP release is available.` and the temp root was removed. No release was applied.

## GitHub Actions on 4ddb91e
- Windows build `45442801370`: SUCCESS.
- Windows clean package `45442801525`: SUCCESS; steps include signed updater archive/version-selection tests, transactional apply/deletion/rollback/user-data preservation, clean package and package-boundary checks.
- Linux WebView build `45442801373`: SUCCESS.
- Cloud PHP `45442801543`: SUCCESS.
- Ubuntu/Kali compatibility `45442801351`: Ubuntu package build SUCCESS; Kali runtime dependency installation was still in progress at this checkpoint. Previous `45439878530` on `33bf482` failed only because Kali did not have Python 3; the new workflow explicitly installs it. Do not claim Kali green until the new run completes.
- Native macOS package run `45442801352`: Intel PHP and Intel llama builds in progress; Apple Silicon PHP and llama jobs queued. Earlier run `45435155319` on `2f5a5d5` completed SUCCESS for both native architectures. Do not claim the new commit's macOS run passed until all four jobs finish.

## Read-only lab access
- `ssh -F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config windows-dcp-admin`: authenticated as `amrwin11\dcpadmin`; `powershell.exe` returned Windows 11 host identity and timestamp. No files or processes changed.
- `ssh -F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config ubuntu-dcp-admin`: authenticated as `amr`; host `amr-ubuntu24`, Ubuntu 24.04 LTS x86_64, `/usr/bin/python3` and `/usr/bin/php` present. No DCP or PHP server process was running. No files or packages changed.
- Default `~/.ssh/config` aliases remain stale; always use the explicit DCP-Lab-SSH config.
- No target install/update performed. Windows must still receive a fresh timestamped rollback backup and current DB/master.key hashes before any replacement. Ubuntu Alpha 9 remains defective and was not replaced. Mac original project/services were not modified by any installer and no target package was installed.

## macOS updater review / blocker
- Found untracked `platform/scripts/update-macos.php` prototype in the worktree. It is not staged or committed and is not wired into `platform/wrappers/macos/main.m`'s current `checkForUpdates` path.
- Review found it still has a separate non-journaled file transaction, an unsafe stale-PID kill path in rollback, no interrupted-transaction recovery, and ZIP symlink/special-file rejection is not yet proven. It must be redesigned to reuse the crash-safe package transaction, verify process ownership before stopping only DCP, integrate `runtime-port.txt`/`runtime-pid.txt`, and run a clean Mac health/rollback test before staging.
- `main.m` already writes runtime port/PID files, but currently still uses the older direct GitHub semantic-version check and terminal installer path. Do not claim macOS packaged update is implemented.

## Release and safety state
- Published `v1.0.0-alpha.9` release ID `456688871` remains on tag commit `8b5bf08d6291897338ca322432ccf1962e0c2ed1` with six assets; no tag, asset or release mutation was performed.
- Alpha 10 remains unpublished. No merge to `main`. Draft PR permission remains 403.
- Production signing key stays in macOS Login Keychain; GitHub Actions secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` still needs manual repository setup. No private key material was printed or committed.
- Signed manifest/release assembly workflows are not yet wired. Do not publish Alpha 10 or close Q005 until macOS adapter, release signing, full/delta update and rollback acceptance pass.
- Q006/Q007 remain isolated. No PHP feature development started.

## Next action
1. Finish and inspect run `45442801351` (Kali) and `45442801352` (Intel + Apple Silicon).
2. Replace the untracked macOS updater prototype with a journaled adapter using the shared package transaction; wire `main.m` to check/recover/launch it safely.
3. Wire signed schema-3 manifest/delta/full generation and signing into one synchronized Alpha 10 release assembly path; configure the GitHub signing secret manually.
4. Run target Alpha 9→Alpha 10 update and forced rollback on Ubuntu, Windows and Mac Intel, with fresh Windows backup/hash evidence; Apple Silicon via native CI only.
