# DC05 — Alpha 10 CI green checkpoint

Date: 2026-11-24 18:24 EET
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/alpha10-package-revision`
HEAD: `7a70284ef0b74cdb86276b41692b20c023a8f32d`

## Confirmed by user screenshots
- Linux distro workflow `45431191589`: Ubuntu 24.04 package build/archive/upload and Kali Rolling artifact download/extract, runtime dependencies, library checks and HTTP/API smoke all SUCCESS. Workspace-relative artifact download plus tar.gz preserves executable mode.
- macOS clean-package workflow `45425681044`: Intel x86_64 and Apple Silicon arm64 native package jobs SUCCESS; both managed PHP and llama.cpp artifacts passed; six artifacts.
- Windows build `45431191605`: SUCCESS. Cloud PHP validation `45431191791`: SUCCESS.

## Local tests on Amrs-Mac-Pro
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED, including signed schema-3 manifest validation, pinned key fingerprint, signature/tamper rejection, platform binding, delta-vs-full selection, official origin, unsafe paths and full asset size/hash.
- `python3 platform/tests/update-package-build.py`: ALL UPDATE PACKAGE BUILD TESTS PASSED (tar.gz/zip deltas, deletion index, user-data exclusion and manifest metadata).
- `bash platform/tests/sign-update-manifest.sh`: detached RSA signature and tamper detection passed using an ephemeral test key.
- PHP lint, Python compilation and `git diff --check` passed.
- Previously verified Windows updater transaction tests pass: schema-2 apply, DB/key preservation, rollback snapshot, forced rollback and user-data path rejection.

## Remaining engineering gaps
1. Schema-3 signed manifest builder/verifier currently exists locally but is not wired to release workflows or native updater clients.
2. Windows updater still consumes schema-1/2 raw-file delta chain; full-package fallback and signed schema-3 integration are missing.
3. Linux `GitUpdateService.php` is checkout-only; there is no packaged Linux updater.
4. macOS `main.m` still performs semantic-version check and calls online installer; no packageRevision-aware signed delta/full updater.
5. No target-device Alpha 9→Alpha 10 apply/rollback/data-preservation tests have been performed.

## Constraints
- Keep published `v1.0.0-alpha.9` untouched.
- Do not publish Alpha 10 or close Q005/DC0005 until all native updater, delta/full fallback, rollback, restart/health and data preservation acceptance tests pass.
- Do not begin PHP feature development until Q005 is closed.
- Preserve existing dirty worktree; no commit or push was made during this checkpoint.

## Next action
Integrate the signed schema-3 manifest and exact-base delta/full fallback into the actual release pipeline and native updaters, starting with the cross-platform contract, then implement Linux/macOS packaged apply/rollback and finish Windows full fallback.
