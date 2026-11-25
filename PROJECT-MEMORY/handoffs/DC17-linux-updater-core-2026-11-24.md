# DC17 Handoff — Linux packaged updater core

Timestamp: 2026-11-24T18:39:31+02:00
Branch: feature/alpha10-package-revision
Baseline HEAD before this slice: 2f5a5d55a6e3c100e5425bdabbf8e8c4c6b9ddfe

## User instruction
Continue Q005/DC0005 without stopping between tasks. When one CI runner is queued, work on another platform or local tests. Do not touch published Alpha 9 or user data without the required acceptance gates.

## Linux updater implementation completed locally (not yet committed/pushed)
- platform/scripts/lib/LinuxUpdateTransaction.php: transactional delta/full package application, private rollback snapshots under DCP-UserData/Linux/storage/updates/backups, atomic per-file replacement, reverse rollback, keep last two snapshots, exact signed-file hash/size checks, explicit deletions, executable mode preservation, path/symlink protections, legacy platform/data and platform/storage preservation.
- platform/scripts/extract-update-tar.py: safe tar.gz extraction; rejects traversal, absolute/drive paths, symlinks, hardlinks, special files, protected data paths, duplicate entries and oversized archives; full packages skip all bundled DCP-UserData content.
- platform/scripts/update-linux.php: GitHub release discovery; requires complete synchronized full-package and signed-manifest asset set for Windows, Linux, macOS Intel and Apple Silicon; verifies every platform manifest with the pinned RSA public key, same release/version/revision/commit/key ID; chooses exact-base Delta or signed Full fallback; verifies asset size/SHA-256; stages/extracts/applies; snapshots SQLite, WAL, SHM, journal and master.key; runs PHP runtime check plus temporary local /api/status health check; rolls application files and critical DB/key files back if health check fails.
- platform/scripts/build-linux.sh: startup updater check with explicit confirmation via zenity/kdialog/TTY, offline-safe behavior, DCP_DISABLE_UPDATE_CHECK=1 override, and manual CLI fallback when no dialog is available.
- Added platform/tests/linux-update-transaction.php, linux-updater-health.php, extract-linux-update.py; platform/tests/run.php includes transaction and health tests. Linux smoke test asserts updater files and lints PHP/Python. Linux build and distro CI workflows run these tests.

## Validation evidence on Amrs-Mac-Pro
- php platform/tests/run.php: ALL PLATFORM TESTS PASSED, including signed schema-3 manifest, transactional delta/full update, rollback, SQLite/master.key preservation, legacy path preservation, symlink rejection and live isolated HTTP API health.
- python3 platform/tests/update-package-build.py: ALL UPDATE PACKAGE BUILD TESTS PASSED.
- python3 platform/tests/extract-linux-update.py: ALL LINUX UPDATE ARCHIVE EXTRACTION TESTS PASSED (full package user-data exclusion, traversal, symlink and protected path rejection).
- PHP lint for updater/transaction/manifest passed; Bash syntax for Linux builder/runtime/smoke scripts passed; git diff --check passed.
- Live GitHub check from a temporary install root returned {"ok":true,"available":false,"message":"No complete synchronized signed DCP release is available."}; updater fails closed because release assets are not yet published.

## CI state
- Prior GitHub evidence remains green: Linux Ubuntu 24.04 + Kali Rolling workflow 45431191589, Windows build 45431191605, Cloud PHP 45431191791, and macOS clean-package workflow 45425681044.
- Newer macOS workflow 45435155319: both PHP runtime architectures and both llama.cpp runtime architectures passed; Intel and Apple Silicon native package jobs were queued at last check. Recheck before claiming full green.
- Current Linux source changes have not yet been pushed; build-linux.yml and test-linux-distros.yml will rerun after the source commit.

## Remaining Q005/DC0005 blockers
1. Windows native updater still consumes schema 1/2 and lacks signed schema-3 verification and full-package fallback.
2. macOS native updater still uses the older semantic-version/online-installer flow; packaged signed Delta/Full apply/rollback is not complete.
3. Production GitHub Actions secret DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 is not configured; manual repository secret setup is required before signed releases can be generated. Never store the private key in the repo or memory.
4. Wire all platform release workflows to create synchronized schema-3 manifests, detached signatures and delta assets.
5. Run Windows, Ubuntu and macOS target Alpha 9 → Alpha 10 upgrade, forced rollback, HTTP/API health and DB/master.key/data preservation acceptance.
6. Only after all acceptance gates pass, publish Alpha 10; keep Alpha 9 immutable. Q005 remains partial and PHP feature work remains gated.

## Safety and network continuity
- Published Alpha 9 release/tag/assets were not changed. No target VM/app was installed or updated.
- NETWORK-RESUME-REQUEST.json was read. restoredAt was 2026-11-24T16:37:24Z; runtime/network-state.json reported online and gateway/internet reachable. Required project records and GitHub state were re-read/verified in DC17; this handoff records the acknowledgement before marker cleanup. PROJECT-MEMORY/network-watchdog.log was absent at the referenced root path; inspect runtime subdirectory if needed.
- Local source work is not yet committed/pushed. Do not stage unrelated dirty project-memory files or .github/workflows/test-update-manifest.yml; DC16 deliberately left that workflow untracked pending review.
