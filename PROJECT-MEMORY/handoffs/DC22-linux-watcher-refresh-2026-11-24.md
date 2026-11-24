# DC22 — Linux watcher refresh after self-update

Date: 2026-11-24 (Cairo)
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/alpha10-package-revision`

## Finding
The packaged Linux `--watch` process is the process that performs the transactional update. A successful update can replace `platform/scripts/update-linux.php` and the managed PHP runtime while the watcher remains alive. Without an explicit exit, that process would continue polling from the old in-memory PHP code and runtime; the launcher would see the old watcher PID as active and would not start a fresh watcher.

## Change
- After a successful background apply and optional desktop notification, the watcher now logs that it is exiting and calls `exit(0)`.
- The next DCP launch detects the dead watcher PID and starts a fresh watcher from the updated package.
- Added a Linux updater health regression assertion for this lifecycle behavior.
- No automatic DCP restart was introduced; the existing message still asks the user to reopen DCP.

## Validation
- `php platform/tests/run.php`: PASS.
- `php platform/tests/linux-update-transaction.php`: PASS (delta/full apply, signed deletion behavior, rollback, crash recovery, health-pending recovery, user-data and symlink protections).
- `php platform/tests/linux-updater-health.php`: PASS (including new stale-watcher regression).
- `python3 platform/tests/update-package-build.py`: PASS.
- `python3 platform/tests/extract-linux-update.py`: PASS.
- `bash platform/tests/sign-update-manifest.sh`: PASS (ephemeral key; signature and tamper checks).
- Real published Alpha 9 Linux package regression: downloaded the official Alpha 9 archive, verified version/revision, generated a repair Delta containing `platform/app/Core/Database.php` and `platform/version.json`, and confirmed user-data paths are excluded.
- PHP lint, Bash syntax and `git diff --check`: PASS.

## Scope and safety
- This is a source/test change only. No Ubuntu, Windows, or macOS installed files were modified.
- Ubuntu was checked read-only: Alpha 10 process, Linux watcher and PHP server were running; `/api/status` returned HTTP 200.
- No Alpha 11 release, test-probe asset, tag, or release was created. No update was applied to a target machine.
- This fix does not itself prove a real Alpha 9 → Alpha 10 update or forced rollback on the Ubuntu host.
