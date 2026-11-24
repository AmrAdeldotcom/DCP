# DC19 Handoff — Live monitor, Ubuntu package diagnosis and macOS updater process guard

Timestamp: 2026-11-24 (local EET)
Branch: feature/alpha10-package-revision
Pushed commit: a8bc81fd

## User-visible monitor
- Added executable tools/dcp-watch.sh; run: cd /Volumes/Data/ChatGPT/DCP && bash tools/dcp-watch.sh
- Optional refresh interval: bash tools/dcp-watch.sh 1; default 3 seconds; stop with Ctrl+C.
- Displays branch/HEAD, dirty files, WORK-QUEUE tail, latest handoff, CURRENT-STATE summary, local build/test/updater processes and recent logs.
- Local repository/process view only; it cannot reconnect ChatGPT or guarantee task liveness.

## Ubuntu 24.04 read-only diagnosis
- Connected to ubuntu-dcp-admin using /Volumes/Data/ChatGPT/DCP-Lab-SSH/config; host amr-ubuntu24.
- Installed path: /home/amr/Applications/DCP; version is 1.0.0-alpha.9.
- platform/app and platform/app/Core/Database.php are absent.
- Packaged updater files platform/scripts/update-linux.php, platform/scripts/lib/LinuxUpdateTransaction.php and platform/update/release-public.pem are absent.
- No DCP/PHP server process was running; local checks to ports 18765 and 18991 returned connection refused.
- Existing DCP-UserData/Linux/storage/logs/php-error.log is present (last modified 18:58 local).
- The white screen is caused by the incomplete published Alpha 9 Linux archive, not just WebView. No Ubuntu application files, SQLite, key or settings were modified.

## macOS updater process-identity fix
- macOS ps -o command= reports the executable image path and discards bundle argv[0]; process identity must inspect ps -o args=.
- /var/... and /private/var/... are lexical/canonical spellings of the same temp path. Previous process guard compared only canonical paths and missed the exact DCP bundle process.
- Updated platform/scripts/update-macos.php to compare exact executable argv[0] against both requested and realpath installation-root spellings. assertNoRunningDcpProcesses also checks both spellings. It still refuses to kill a PHP router and refuses unrelated/stale PIDs.
- Added tests for stopping the exact DCP process without runtime-pid.txt and ignoring an unrelated process referenced by a stale PID file.
- php platform/tests/update-macos.php: ALL MACOS UPDATE CONTRACT TESTS PASSED (40 assertions).
- Commit a8bc81fd pushed; includes updater fix, tests and monitor script.

## Local regression evidence
- php platform/tests/run.php — ALL PLATFORM TESTS PASSED.
- php platform/tests/update-macos.php — 40 assertions passed.
- python3 platform/tests/update-package-build.py — passed.
- python3 platform/tests/extract-linux-update.py — passed.
- pwsh -NoProfile -File platform/tests/update-windows.ps1 — passed apply, full fallback, rollback, SQLite/master.key preservation and unsafe-path tests.
- dotnet run --project platform/tests/windows-update-client/DCP.WindowsUpdater.Tests.csproj --configuration Release — ALL WINDOWS UPDATE CLIENT CONTRACT TESTS PASSED.
- dotnet build platform/wrappers/windows/DCP.WebView.csproj — succeeded, 0 errors, one existing WindowsBase version-conflict warning.
- PHP lint, Bash/Zsh syntax and git diff --check passed.

## GitHub CI / signing blocker
- On a8bc81fd: 45447308256 manifest/delta tooling in progress; 45447308173 Linux build in progress; 45447308527 Windows build in progress; 45447308397 clean native macOS packages queued.
- Earlier Linux distro run 45446122964 on d78a015c: Ubuntu 24.04 package build and Kali Rolling dependency/package/HTTP/API smoke both succeeded.
- Manifest workflow 45446629180 on df35340a passed manifest verification, delta builder and ephemeral signature/tamper tests, then failed only because repository Actions secret DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 is missing.
- Keychain item DCP-Alpha10-Release-Signing-Key is present locally; gh CLI is absent. Do not export or print private key into project files/logs. Repository secret must be added manually in GitHub Settings → Secrets and variables → Actions.

## Release / install safety
- Published v1.0.0-alpha.9 release ID 456688871, tag commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1, six assets remain unchanged.
- Alpha 10 remains unpublished. No merge to main, no target VM installation/update, no user-data or Windows installation mutation.
- Do not update Ubuntu until a complete corrected package with updater and signed manifests is built, CI-green and inspected. Then capture fresh DB/master.key backups/hashes and test HTTP/API plus actual visible GUI.
- Q005/DC0005 remains PARTIAL; no PHP feature work. Q006/Q007 remain isolated.

## Next actions
1. Poll all a8bc81fd CI runs; inspect failed job logs and fix only the failing platform.
2. Complete synchronized signed schema-3 release assembly and manual production Actions secret setup.
3. Verify package contents include all app files, runtime, pinned key, updater and launcher on Ubuntu/Kali, Windows and both macOS architectures.
4. Run clean-package GUI/API smoke and Alpha 9 → Alpha 10 Delta/Full update + forced rollback + SQLite/master.key/settings/workspace preservation on target systems.
5. Publish only after all gates pass; keep Alpha 9 immutable.
