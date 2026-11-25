# DC42 — stable v1.0.0 tag and cross-platform release run

Timestamp: 2026-11-25T05:22:41+0200
Device: Amrs-Mac-Pro online
Repository: `/Volumes/Data/ChatGPT/DCP`
Feature branch: `feature/local-ai-runtime-discovery`
Main HEAD: `cb3087e27c344af774ecf60aca40c98904859a85`
Stable tag: `v1.0.0` (annotated, pushed; exact main HEAD)

## Main branch promotion
- Fast-forwarded `main` from `30fe3fd` to `cb3087e`; no force push, reset, history rewrite or branch deletion.
- Created and pushed annotated tag `v1.0.0` with message “DCP 1.0.0 — first stable release based on Alpha 10”.
- Stable metadata is `version=1.0.0`, `releaseTag=v1.0.0`, `channel=stable`, `build=10`, `packageRevision=1`.
- Release workflow sets `prerelease=false` and `make_latest=true` for stable tags.
- Stable v1.0.0 explicitly uses `v1.0.0-alpha.10` as the exact Delta base; regression assembly test builds four stable manifests/deltas and verifies signatures using an ephemeral test key.

## Stable release run
- GitHub Actions run: `45538884432` — https://github.com/AmrAdeldotcom/DCP/actions/runs/45538884432
- Linux x64 package: PASS. Windows x64 package: PASS.
- macOS Intel llama.cpp, macOS Apple Silicon llama.cpp, and managed PHP runtimes are queued/in progress behind clean macOS package test runs. No stable release record has been confirmed published yet.
- Release publication requires all native package artifacts, synchronized schema-3 manifests, production signatures and hashes.

## Local validation
- Full platform test suite, macOS updater 53 assertions, Linux updater health/transaction, Windows updater, update package builder, Linux archive extraction, signed release assembly (ephemeral key), stable Alpha10→1.0.0 Delta assembly/signature test, 61 PHP lints, JS syntax, all workflow YAML, source-fetch retry tests and `git diff --check` passed.
- The actual target-device Alpha10→1.0.0 install/rollback and SQLite/master.key byte-preservation tests have not yet been run on the user's Windows/Ubuntu/Mac installations.

## GitHub cleanup automation
- `.github/workflows/cleanup-legacy-github.yml` is present on main. It triggers only after successful completion of the unified stable release workflow for tag `v1.0.0`.
- It refuses cleanup unless the release is published, non-prerelease and contains all four full packages, checksums, and four signed update manifests/signatures.
- After that gate it deletes prior Alpha/Beta release records, Alpha tags, and completed failed Actions run records. Repository commit history and main branch source remain intact.
- Existing historical failures still exist until this post-publish cleanup runs. Current failures identified: `45533300003` (offline Linux updater check before fix) and `45516992736` (older signed update tooling test). Cleanup must be verified after release.

## Next action
Monitor run `45538884432` until all four native packages and signing/publish steps pass. Verify the stable release metadata and asset inventory, then verify cleanup removed Alpha3–Alpha10 release records/tags and historical failed Actions runs. If pre-release queued workflows finish after the first cleanup, perform a second cleanup pass for any remaining pre-stable failed runs.
