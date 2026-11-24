# DC23 — Cross-platform updater regression and Alpha 10 release hold

Date: 2026-11-24 (Cairo)
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/alpha10-package-revision`
Code checkpoint: `469a028c7c983bf2f1be5fc2013e9db0bd4a1e53`

## Changes and source state
- Linux watcher refresh fix committed and pushed as `469a028` (see DC22).
- Previous Windows archive-root correction `6376992` is present on this branch and passed clean Windows package CI.
- Existing user-modified project memory/work-queue files remain unstaged and untouched.

## Local validation on Amrs-Mac-Pro
- `php platform/tests/run.php`: PASS.
- `php platform/tests/linux-update-transaction.php`: PASS.
- `php platform/tests/linux-updater-health.php`: PASS, including watcher exit after self-update.
- `python3 platform/tests/update-package-build.py`: PASS.
- `python3 platform/tests/extract-linux-update.py`: PASS.
- `bash platform/tests/sign-update-manifest.sh`: PASS (ephemeral test key and tamper rejection).
- `bash platform/tests/alpha9-linux-delta-regression.sh`: PASS against the real published Alpha 9 Linux archive; generated repair Delta includes `platform/app/Core/Database.php` and `platform/version.json`, excludes user data.
- `php platform/tests/update-macos.php`: PASS (52 assertions).
- `pwsh -NoProfile -File platform/tests/update-windows.ps1`: PASS.
- `dotnet run --project platform/tests/windows-update-client/DCP.WindowsUpdater.Tests.csproj --configuration Release`: PASS.
- PHP lint, Bash/Zsh syntax and `git diff --check`: PASS.

## GitHub CI for 469a028
- Linux distro compatibility run `45493736237`: SUCCESS; Ubuntu 24.04 and Kali Rolling package, updater, extraction, executable-mode, and HTTP/API smoke checks passed.
- Windows build run `45493736450`: SUCCESS.
- Signed manifest/delta workflow `45493736306`: SUCCESS, including the production signing-key/public-key match validation.
- Linux WebView build run `45493737259`: SUCCESS.
- Clean Windows package run `45489321230` on commit `6376992`: SUCCESS, including ZIP root-layout regression assertion.

## Target-device observation (read-only)
- Ubuntu 24.04 at `/home/amr/Applications/DCP` is currently Alpha 10, with native DCP, watcher and PHP server running; `/api/status` returned HTTP 200. No files, processes or user data were changed.
- This is not an Alpha 9 → Alpha 10 updater acceptance test and does not prove rollback on the target host.

## Release hold and exact blocker
- GitHub releases API still shows Alpha 9 as the latest published release; no Alpha 10 release assets were published.
- Failed Alpha 10 release workflow `45459081495` built all four native package artifacts, but the signed-manifest assembly failed because the Windows ZIP from the old tag workflow had an extra `windows/` parent directory. The assembler therefore could not find `platform/version.json` at the archive root.
- The corrected Windows archive workflow and regression check were added in commit `6376992`, but the existing `v1.0.0-alpha.10` tag points to an earlier commit and does not contain that fix. Re-running the old tag's release workflow would repeat the failure.
- The failed release run also logged an empty production signing-secret environment. The later manifest CI run validates that the configured signing secret matches the pinned public key; still verify the release job environment on the next approved release run.
- Do not force-move the existing tag, merge the feature branch to main, publish a release, or install a new package on target devices without explicit release approval. A new/superseding tag requires version-manifest alignment and a deliberate decision.

## Next safe actions
1. Keep Alpha 9 published assets immutable.
2. Obtain a release decision: whether to replace the unpublished Alpha 10 tag or cut a superseding prerelease after synchronizing `platform/version.json`.
3. Once the release source/tag is approved, run signed package assembly and require all four signed manifests, full assets, optional Delta assets, checksums, and synchronized commit/version/revision checks to pass.
4. Only then perform real Alpha 9 → Alpha 10 update tests on Windows, Ubuntu and macOS, including forced failure/rollback and byte-identical SQLite/`master.key` checks.
5. After all three Alpha 10 installations are verified, publish a harmless platform-code probe release and verify each client detects, downloads, applies, health-checks, and preserves user data.
