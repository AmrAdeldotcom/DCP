# DC41 — Alpha 10 stable promotion and GitHub cleanup

Timestamp: 2026-11-25T05:02:56+0200
Device: Amrs-Mac-Pro online
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
HEAD: `dd0595ff0d89d17cdaf5b88de7589ae34dd037f6`
Pushed source fix: `dd0595f`

## GitHub inventory
- Default branch `main` at `30fe3fd`.
- Alpha 10 tag `v1.0.0-alpha.10` resolves to commit `ba3b8f5`.
- `feature/alpha10-package-revision` is at `db054a2`, an ancestor of the Alpha 10 tag.
- `feature/local-ai-runtime-discovery` is the active development branch.
- Published release `v1.0.0-alpha.10` has 19 assets: Windows/Linux/macOS Intel/macOS Apple Silicon full packages, signed v3 manifests and signatures, Delta packages and SHA256 files. GitHub currently marks it `prerelease=true`.
- Older published release records currently include Alpha 3–9. Tags exist for Alpha 3–10.

## Source fix pushed
Commit `dd0595f` was pushed to `feature/local-ai-runtime-discovery`. It includes:
- Offline-safe Linux updater check: transient GitHub transport failures return `ok=true`, `available=false`, `retryable=true`, preserving normal application availability.
- Deterministic injected network failure regression tests.
- Bounded pinned-source clone retries for OpenSSL 3.5.4 and llama.cpp b12655 in the Intel macOS build workflow, with a test that simulates two transient failures then succeeds.

## Local validation
- Platform test suite — PASS.
- macOS updater — PASS (53 assertions).
- Linux updater health and transaction/rollback — PASS.
- Update package builder and Linux archive extraction — PASS.
- Signed release assembly — PASS using ephemeral test key only.
- Windows updater — PASS.
- 61 PHP lints, source JS syntax, retry helper test, all workflow YAML parse, `git diff --check` — PASS.
- Linux package HTTP smoke was not run locally because no Linux package exists in this Mac worktree.

## CI findings
- Prior `de0ffdb` run `45533300003` failed only at `Run the packaged updater check on Ubuntu 24.04`, due to a 12-second GitHub network timeout (`Failed to connect to github.com`). Linux package build itself succeeded.
- New `dd0595f` Actions run set: 6 workflows queued/running at checkpoint; Cloud PHP had already passed. Final results must be checked before PR/merge.

## Stable promotion plan
- Do not relabel the semantic prerelease tag `v1.0.0-alpha.10` as if it were a stable semantic version. Promote the Alpha 10 codebase to stable `v1.0.0`.
- Review and merge the current development branch into `main` after all checks pass.
- Update `platform/version.json` to `1.0.0`, release tag `v1.0.0`, channel `stable`; update Windows project default informational version and release workflow to mark stable releases latest/non-prerelease.
- Create `v1.0.0` only at the exact current `main` HEAD. The release workflow explicitly rejects tags whose commit is not the default branch HEAD.
- Build all four native packages, generate synchronized signed schema-3 manifests/signatures and Delta/full assets, verify hashes and publication. Then perform actual target update/rollback and SQLite/master.key byte-preservation acceptance.
- Only after stable release and rollout recovery gates pass, remove Alpha 3–9 GitHub Release records and old tags. Keep Alpha 10 stable lineage and repository source commit history.

## Cleanup caveat
GitHub Actions run history is separate from Releases and Git tags. The current GitHub connector can inspect runs/logs but exposes no direct delete-run or edit-release action. Do not claim historical red workflow records were removed; resolve the failing code and rerun successfully.
