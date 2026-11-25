# DCP Cross-Platform Auto-Update — Alpha 10 implementation plan

## User decision

The user explicitly approved a new unified v1.0.0-alpha.10 across Windows, Linux (Ubuntu and Kali compatibility), and macOS. Published v1.0.0-alpha.9 is immutable historical evidence and must not be retagged, rewritten, or have its assets overwritten.

The canonical Alpha 10 manifest is platform/version.json:
- version: 1.0.0-alpha.10
- releaseTag: v1.0.0-alpha.10
- build: 10
- packageRevision: 1

Legacy Alpha 9 packages have no packageRevision field and are treated as revision 0.

## Verified release baseline

- Published release: v1.0.0-alpha.9, release ID 456688871.
- Exact tag commit: 8b5bf08d6291897338ca322432ccf1962e0c2ed1.
- Annotated tag object: 71b8378a4989844ae764db0c3ce124f0153e2cdd.
- Six release assets remain unchanged: Windows ZIP/update manifest, Intel macOS ZIP, Apple Silicon macOS ZIP, Linux tarball and SHA-256 sidecar.
- The Alpha 9 Linux archive is defective: it lacks platform/app/Core/Database.php, causing HTTP 500. The focused source fix is commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592, CI run 45398600124 passed, but this fix is not attached to any published release.
- Alpha 9 Windows installation was validated under the interactive user. SQLite and master.key hashes match all retained rollback backups. The exact hashes are recorded in project memory; no secrets or key contents are stored here.
- Intel Mac Alpha 9 smoke passed and the isolated smoke folder was removed; original services and project tree were preserved. Apple Silicon evidence is native CI only.

## Current Alpha 10 branch

- Branch: feature/alpha10-package-revision.
- Current pushed HEAD at the latest checkpoint: 7a70284 (full SHA is recorded in PROJECT-MEMORY/CURRENT-STATE.json).
- Previous commit 3f37d4d introduced the package revision metadata and Windows same-semver revision comparison.
- Commit 8190261 fixed the macOS clean-package workflow failure by writing DCPPackageRevision into the test app's Info.plist before asserting bundle/package parity.
- Commit ec170a6 hardens Windows delta apply and rollback.
- Draft PR creation is currently blocked by GitHub integration permission (HTTP 403); branch push works. Do not merge or release yet.

## Current CI evidence

- Commit 3f37d4d: Windows build 45413374795 passed; clean Windows package 45413374899 passed; Linux build 45413375192 passed; Cloud PHP 45413374999 passed.
- macOS clean-package run 45413374893 failed only because the test workflow did not populate DCPPackageRevision in DCP.app/Contents/Info.plist. Native PHP and llama runtime artifacts, architecture checks, runtime verification, and native app build had passed before the final metadata assertion.
- Fix commit 8190261 added the missing Info.plist revision. Rerun 45416881645 was queued at 2026-11-24 16:49 EET; inspect its Intel and Apple Silicon jobs before relying on it.
- Commit ec170a6: Windows build 45418031428 passed; clean Windows package 45418031542 passed, including the new updater transaction tests. Linux build 45418031828 and Cloud PHP 45418031562 were still running at 16:49 EET.
- Release v1.0.0-alpha.9 remains unchanged.

## Implemented Windows updater improvements

Files:
- platform/wrappers/windows/Program.cs
- platform/scripts/update-windows.ps1
- platform/tests/update-windows.ps1
- .github/workflows/test-windows-package.yml

Changes:
1. Treat missing legacy DCPPackageRevision metadata as revision 0; schema 2 manifests require a positive package revision.
2. Validate that the manifest version matches the GitHub release tag, and that the manifest URL is HTTPS on the official github.com/AmrAdeldotcom/DCP/releases/download/ origin.
3. Validate SHA-256 format and non-negative file sizes; reject user-data paths including DCP-UserData, platform/data, and platform/storage.
4. Compare previous manifest chain by both semantic version and package revision.
5. Update PowerShell apply logic to accept schema 1/2 and verify the actual packaged PHP path: platform/dcp-env/runtime/php/windows-x64/php.exe.
6. Reject path traversal, drive-qualified paths, and reparse-point traversal.
7. Validate all payload hashes/sizes before writing any installed file.
8. Back up replaced files, restore them in reverse order on mid-apply failure, retain the two most recent rollback snapshots under %LOCALAPPDATA%/DCP/rollback, and write update-status.json.
9. Launch the updated app and require it to remain running for a 12-second startup check before marking success. The temp updater script only self-deletes when launched from the expected DCP-Update-* temp path.

Local tests on Amrs-Mac-Pro:
- pwsh -NoProfile -File platform/tests/update-windows.ps1: all tests passed.
- Success case verifies schema 2, correct PHP path, rollback snapshot, and SQLite/master.key hash preservation.
- Forced mid-apply failure restores the original file and records rolled_back.
- User-data replacement path is rejected.
- PowerShell parser checks passed for updater, test script, and Windows builder.
- dotnet build DCP.WebView.csproj with Alpha 10 version/revision: succeeded with 0 errors and one existing WindowsBase reference warning.
- GitHub Windows clean-package run 45418031542 passed the new updater test step.

## Remaining critical work — do not close Q005

### Windows
- Implement a real full-package fallback when the installed package is too far behind or its delta base revision is unknown.
- Current C# updater still uses the previous-manifest chain and raw source-file downloads; it does not yet select a full package.
- Add release-side package checksum metadata and validate full package size/hash before extraction.
- Complete end-to-end Alpha 9 → Alpha 10 update on the Windows VM after a new release exists, preserving existing backup and comparing DB/key hashes before and after.

### Linux (Ubuntu/Kali)
- GitUpdateService.php remains Git-checkout-only; it is not a packaged updater.
- Implement a packaged updater adapter, base-aware delta selection, full fallback, staging, rollback, user-data preservation, relaunch and status reporting.
- Validate Ubuntu 24.04 and Kali-compatible dependencies/launcher behavior.

### macOS Intel/Apple Silicon
- Current main.m updater check is semantic-version-only and calls the online installer; it is not a real delta updater and does not compare packageRevision.
- Implement a packaged updater helper that handles both native architectures, preserves DCP-UserData/macOS, waits for the app to exit, stages and atomically swaps the package, health-checks, and rolls back on failure.
- Native Intel and Apple Silicon CI must pass; only Intel physical testing is available.

### Shared release contract/security
- Use a common schema carrying version, packageRevision, platform, architecture, release tag, exact delta base, changed files/deletions, full-package fallback, asset size and SHA-256.
- Validate official HTTPS GitHub release origin, manifest integrity, payload size/hash, safe archive paths, and package architecture.
- Cryptographic manifest signature/attestation is not implemented yet; do not claim signature verification until it is added and tested.
- Keep SQLite, master keys, settings, workspaces, logs, models and all DCP-UserData outside update payloads.
- Test fresh install, Alpha 9 → Alpha 10 delta, full fallback, corrupted/interrupted update, forced rollback, restart/health and data preservation on every supported OS.

## Release gate

Do not create tag v1.0.0-alpha.10 or publish assets until:
1. Windows, Linux, Intel macOS and Apple Silicon packages all build with matching version/revision.
2. Native CI and clean-package tests are green.
3. Real packaged updaters and delta/full fallback are implemented on all platforms.
4. End-to-end update and forced rollback tests pass.
5. SQLite/master-key hashes and all other user data are preserved.
6. Ubuntu and Windows target installs are validated; macOS Intel smoke passes; Apple Silicon is covered by native CI.
7. Project memory and handoff contain exact commit/run IDs and evidence.

Q005/DC0005 remains partial. Do not start PHP feature development until this gate is closed.


## DC05 CI corrections — 2026-11-24
- `46a0753` changed Linux package transport to tar.gz to preserve executable permission bits.
- Kali run `45426353224` confirmed the tar artifact downloaded but the extraction step used `${{ runner.temp }}` whose host path was not mounted inside the job container.
- `7a70284` changes the download destination to workspace-relative `dcp-linux-artifact`, asserts the archive exists, then extracts it. Ubuntu package build and archive upload passed in run `45431191589`; Kali runtime installation was still in progress at 18:08 EET.
- macOS run `45425681044`: Intel native package and all PHP/llama runtime artifacts passed; Apple Silicon native package job remains queued.


## DC05 CI result — 2026-11-24T18:12:00+02:00
- Commit `7a70284ef0b74cdb86276b41692b20c023a8f32d` is pushed to `feature/alpha10-package-revision`.
- Linux distro workflow `45431191589`: Ubuntu 24.04 build/package/archive/upload SUCCESS; Kali Rolling dependency installation, artifact download, archive extraction preserving executable permissions, library/package verification and packaged HTTP/API smoke test SUCCESS. Kali job `138577144451` concluded success. This proves one portable Linux package works in both tested distro environments (Ubuntu 24.04 and Kali Rolling CI container).
- Windows build `45431191605`: SUCCESS; Windows x64 build, PowerShell script parsing and package exclusion checks all passed.
- Cloud PHP `45431191791`: SUCCESS; php-lint/source validation passed.
- macOS clean-package workflow `45425681044`: Intel native package `138569576679` SUCCESS; managed PHP x64+arm64 and llama.cpp Intel+ARM64 artifacts all SUCCESS; Apple Silicon native package `138569576535` remains QUEUED. Do not mark complete until it runs and passes.
- User confirmed local Kali is powered off and Ubuntu 24.04 is the active Linux machine. Read-only SSH verified host `amr-ubuntu24`, Ubuntu 24.04 LTS, kernel 7.0.0-43-generic, amd64; no DCP/PHP server was running during this check. No files or installed app were changed on that host.
- Next engineering: common signed update manifest, Linux/macOS packaged updater adapters with version+packageRevision comparison, exact-base delta and full fallback, atomic staging/rollback/health/restart, complete Windows full fallback, then target-device Alpha9→Alpha10 acceptance tests and data preservation. No release published; Alpha9 remains unchanged; Q005/DC0005 remains open; PHP feature work remains gated.
