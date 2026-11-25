# DC16 — Alpha 10 signed manifest and delta package foundation

Date: 2026-11-24, checkpoint around 18:25 EET
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/alpha10-package-revision`
HEAD: `2f5a5d55a6e3c100e5425bdabbf8e8c4c6b9ddfe`

## Start-of-run continuity checks
- Read `WORK-QUEUE.md`, `PROJECT-MEMORY/INDEX.md`, `PROJECT-MEMORY/CURRENT-STATE.json`, latest handoff DC15, Q005, `runtime/network-state.json`, and `runtime/network-watchdog.log`.
- `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json` was absent; no resume marker needed acknowledgement or clearing.
- Local network monitor reports online, gateway `192.168.8.1` reachable and internet reachable at `2026-11-24T16:17:17Z`. The 600-second LaunchAgent only records transitions; it cannot reopen or keep a ChatGPT session connected.
- Used Amrs-Mac-Pro device ID `a23231bf-eabc-441c-a6e2-560e3ae7f957`. No large native runtime compilation was started locally. No target VM install or data mutation was performed.

## Release and branch safety
- Published Alpha 9 remains immutable: release ID `456688871`, tag `v1.0.0-alpha.9`, exact tag commit `8b5bf08d6291897338ca322432ccf1962e0c2ed1`, annotated tag object `71b8378a4989844ae764db0c3ce124f0153e2cdd`, six assets. No Alpha 9 tag or asset was changed.
- Alpha 10 branch `feature/alpha10-package-revision` now points to `2f5a5d55a6e3c100e5425bdabbf8e8c4c6b9ddfe`, pushed successfully. Not merged and not released.
- Existing `main` synchronization and PR permission blocker remain unchanged; no PR/merge attempt was made.

## New committed work — `2f5a5d5`
Commit: `feat(updater): add signed manifest contract and delta package builder`

Added:
- `platform/scripts/lib/UpdateManifest.php`: schema-3 validator, detached RSA-SHA256 signature verification, pinned public-key fingerprint, exact official GitHub release URL validation, platform/architecture binding, exact version+packageRevision delta-base selection, full-package fallback selection, safe update path/user-data rejection, and size/SHA-256 verification.
- `platform/update/manifest.schema.json` and `platform/update/release-public.pem`.
- `platform/scripts/build-delta-package.py`: compares two complete package trees, preserves file mode, emits tar.gz or zip delta and explicit deletion index; excludes DCP-UserData, database/key/storage paths, env files, old Windows payload and self-referential update manifests.
- `platform/scripts/build-update-manifest.py`: builds platform-specific schema-3 manifests with full fallback, exact delta base, package revision, release tag/commit, asset URL, size and SHA-256.
- `platform/scripts/sign-update-manifest.sh`: signs exact manifest bytes using protected base64 PEM secret and verifies the detached signature before returning success.
- `platform/tests/update-manifest.php`, `update-package-build.py`, `sign-update-manifest.sh`, integrated into PHP/Linux/macOS test workflows.
- Linux package builder now includes `platform/update`; Windows/macOS package workflows assert the pinned public key and schema are present.

## Signing key custody
- Generated RSA-3072/SHA-256 key pair. Public key fingerprint: `bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67`.
- Private key is stored as a base64 PEM value in the macOS login Keychain, account `DCP Alpha 10`, service `DCP-Alpha10-Release-Signing-Key`. No private key content or plaintext private-key file is stored in the repository or project memory.
- GitHub Actions repository secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` is not configured. The GitHub connector cannot access secrets and `gh` is not installed on this Mac. Before signed release workflows can run, add the Keychain value manually to repository Settings → Secrets and variables → Actions. Safe clipboard command is documented in `platform/update/KEY-SETUP.md`; never paste the secret into chat or logs.

## Local test evidence
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED, including pinned public-key fingerprint, signature/tamper rejection, platform binding, exact base selecting delta, revision mismatch selecting full, official GitHub origin, unsafe path/user-data rejection and full asset size/SHA-256.
- `python3 platform/tests/update-package-build.py`: ALL UPDATE PACKAGE BUILD TESTS PASSED for tar.gz/zip delta creation, mode preservation, explicit deletions, user-data exclusion and schema-3 manifest generation.
- `bash platform/tests/sign-update-manifest.sh`: detached RSA signature verification and byte-tamper rejection passed with an ephemeral test key.
- `pwsh -NoProfile -File platform/tests/update-windows.ps1`: ALL WINDOWS UPDATER TESTS PASSED (schema-2 apply, SQLite/master.key preservation, retained rollback snapshot, forced mid-apply rollback and user-data path rejection).
- PHP lint, Python compilation, Bash/Zsh syntax, JSON/YAML parsing and `git diff --check` passed.

## GitHub Actions evidence on `2f5a5d5`
- `45435155294` Cloud PHP: SUCCESS.
- `45435155119` Windows build: SUCCESS.
- `45435155128` clean Windows package: SUCCESS.
- `45435155129` Linux WebView build: SUCCESS.
- `45435155253` Ubuntu 24.04 + Kali Rolling distro workflow: SUCCESS, including package archive transfer/extraction, runtime/library checks and packaged HTTP/API smoke.
- `45435155319` macOS clean package: IN PROGRESS at checkpoint. Intel PHP/llama jobs running; Apple Silicon PHP/llama jobs queued. Earlier `45425681044` completed SUCCESS for Intel and Apple Silicon native package jobs and both matching PHP/llama artifacts.

## Still incomplete — Q005 remains partial
1. Integrate schema-3 signed manifests into the release asset generation workflows and native clients.
2. Windows: migrate Program.cs to schema 3, verify detached signature and exact platform/architecture, download delta archive or full fallback, and test forced rollback.
3. Linux Ubuntu/Kali: implement packaged updater check/apply adapter, signature verification, exact-base delta/full fallback, safe archive extraction, transaction journal, rollback, relaunch and API health check. `GitUpdateService.php` remains checkout-only.
4. macOS Intel/Apple Silicon: replace semantic-version/online-installer path with the packaged updater helper; preserve `DCP-UserData/macOS`, re-sign app bundle after update, rollback on health failure.
5. Wire release workflows to generate full and delta packages/manifests/signatures. Configure `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` before any tag/release workflow is used.
6. Run Alpha 9 → Alpha 10 update and forced rollback on Windows, Ubuntu and Mac Intel, comparing SQLite/master.key and all user data. Apple Silicon is native CI only; no physical device is available.
7. Do not publish Alpha 10, alter Alpha 9, merge main, or begin PHP feature work until all gates pass.

## Immediate next action
Implement the Linux packaged updater adapter against the tested schema-3 manifest contract, add archive-safety/transaction/rollback/API-health tests, and wire the Linux launcher. Then integrate the same manifest contract into Windows and macOS clients and release workflows.

## Worktree reconciliation
- After commit `2f5a5d5` was pushed, an untracked `.github/workflows/test-update-manifest.yml` appeared (filesystem mtime 18:18:46 EET). It duplicates manifest tests already wired into Linux/macOS workflows. It is not part of HEAD and was deliberately not staged or modified; review it before any later commit. Existing memory/roadmap/proposal edits also remain unstaged.
