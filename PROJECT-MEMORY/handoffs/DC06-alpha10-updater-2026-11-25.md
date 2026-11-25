# DC06 — Alpha 10 signed updater continuation

Date: 2026-11-25 (Cairo)
Repository: /Volumes/Data/ChatGPT/DCP
Local branch: feature/local-ai-runtime-discovery
HEAD: ba3b8f5fdb1696f18957e7a8541b3bd9a202e549
origin/main: ba3b8f5fdb1696f18957e7a8541b3bd9a202e549
Tag v1.0.0-alpha.10: ba3b8f5fdb1696f18957e7a8541b3bd9a202e549

## User direction
- Continue directly from DC05/DC06; do not wait idle for GitHub Actions.
- Keep development and local validation moving while CI runs.
- Preserve signing keys outside the project repository.
- Do not declare Alpha 10 released or update target installs until all platform packages and updater acceptance tests pass.

## Key handling
- Created /Volumes/Data/ChatGPT/DCP-KEYS outside the repository (directory mode 0700).
- Verified the private key retrieved from macOS login Keychain derives the exact public key pinned at platform/update/release-public.pem.
- Created DCP-KEYS/release-private.pem with mode 0600 and DCP-KEYS/release-public.pem with mode 0644.
- KEY-INVENTORY.txt documents key ID, fingerprint, source and GitHub Actions secret name.
- macOS Keychain copy remains intact as the primary protected copy; DCP-KEYS is the verified offline backup. Never commit or upload this directory.
- An additional oddly named imported text file was not validated as a private key and was left untouched. The verified backup is release-private.pem.

## Fixes completed
1. Delta builder and all platform validators now allow the non-secret platform/cloud/.env.example template while still rejecting .env.local and real .env secret paths.
2. Windows PowerShell transactional updater had a second stale .env path guard that still rejected .env.example; fixed it and added a success fixture for .env.example plus rejection test for .env.local.
3. Windows PowerShell source file inspection now uses Get-Item -Force so dotfiles can be inspected consistently in cross-platform PowerShell tests.
4. Release workflow now requires the release tag commit to equal the current default-branch HEAD, preventing a superseded queued tag run from publishing stale packages.

Commits pushed to main:
- ef85794: allow safe .env.example in signed deltas (also updates Linux/macOS/Windows C# validators and tests).
- ba3b8f5: Windows PowerShell env example fix, regression tests, and stale-tag publication guard.

Tag v1.0.0-alpha.10 was moved to ba3b8f5. This is a candidate tag only; no GitHub Release assets have been published by this checkpoint.

## Local validation (all passed)
- php platform/tests/run.php — ALL PLATFORM TESTS PASSED.
- python3 platform/tests/update-package-build.py — ALL UPDATE PACKAGE BUILD TESTS PASSED.
- python3 platform/tests/extract-linux-update.py — ALL LINUX UPDATE ARCHIVE EXTRACTION TESTS PASSED.
- pwsh -NoProfile -File platform/tests/update-windows.ps1 — ALL WINDOWS UPDATER TESTS PASSED, including Delta/Full, rollback, DB/key preservation, .env.example acceptance and .env.local rejection.
- dotnet run --project platform/tests/windows-update-client/DCP.WindowsUpdater.Tests.csproj --configuration Release — ALL WINDOWS UPDATE CLIENT CONTRACT TESTS PASSED.
- bash platform/tests/release-assembly.sh — ALL SIGNED RELEASE ASSEMBLY TESTS PASSED (four synchronized signed manifests, Delta, Full fallback, packageRevision-only update).
- PHP lint for update-linux.php, update-macos.php, UpdateManifest.php and LinuxUpdateTransaction.php passed.
- Python compilation and git diff --check passed.
- macOS updater transaction suite previously passed 53 assertions, including full package extraction, DB/key preservation, health failure rollback, crash recovery and process guards.

## GitHub Actions
- Run #4 45513928920 is pinned to old SHA ef85794 and remains queued/in progress across macOS dependencies. It must not publish: the new release workflow gate will reject it because its tag commit is no longer the current main HEAD.
- Run #5 45515068071 is the current candidate for tag SHA ba3b8f5 and was pending at last check. Recheck jobs.
- Previous run #4 Linux and Windows package jobs passed. Its macOS managed PHP Intel job was compiling; Apple Silicon llama job passed. These are not proof for the new SHA.

## Current phase
Q005 / DC0005 — synchronized signed Alpha 10 release candidate and cross-platform updater acceptance.
- Linux packaged updater core: implemented and locally tested (RSA signature, synchronized manifests, exact version+packageRevision Delta selection, signed Full fallback, size/SHA-256, safe extraction, transactional apply/rollback, health check, SQLite/master.key protection).
- Windows: schema-3 verification/selection in C# plus transactional PowerShell apply; local Delta/Full/rollback tests pass.
- macOS: schema-3 signed updater and native wrapper integration with staged download, explicit user confirmation, transactional app/platform replacement, restart/health verification and rollback; local 53-assertion suite passes.
- Still missing: green native macOS package workflow on the current tag SHA; published complete four-package/four-manifest signed release; target Alpha 9→10 apply tests on Mac/Windows/Ubuntu; forced rollback and byte-identical DB/master.key checks on actual installs; harmless platform-code update probe to prove automatic GitHub update on all three installed systems.

## Safety / next actions
1. Continue local review/tests while Run #5 runs; do not idle.
2. Recheck Run #5 jobs and artifacts; confirm all four full packages, all four signed schema-3 manifests and signatures, plus available Delta assets.
3. Ensure Run #4 cannot publish because its tag SHA is stale; verify the release gate result.
4. Reconcile Ubuntu's unexpected read-only observed Alpha 10 process/watcher state before any installation mutation.
5. Do not install/update any target until the current signed release assets exist and the Alpha 9→10 acceptance plan is ready.
6. After installs pass, upload one harmless platform-code probe and verify all three systems download/apply it from GitHub.
