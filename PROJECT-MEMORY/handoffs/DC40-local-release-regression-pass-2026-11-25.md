# DC40 — full local release regression pass

Timestamp: 2026-11-25T04:51:15+0200
Device: Amrs-Mac-Pro online
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
HEAD: `de0ffdb45f1c0ea7b7f8027a8f82f3a580273263`

## Code change under test
- DC39 added `platform/scripts/clone-pinned-source.sh` and integrated bounded retries into `.github/workflows/build-llama-macos-sse42.yml` for OpenSSL 3.5.4 and llama.cpp b12655.
- Deterministic retry test simulates two transient failures and succeeds on the third local clone.

## Fresh local regression evidence
- `php platform/tests/run.php` — PASS (full suite exit 0).
- `php platform/tests/update-macos.php` — PASS, 53 assertions.
- `php platform/tests/linux-updater-health.php` — PASS.
- `php platform/tests/linux-update-transaction.php` — PASS.
- `python3 platform/tests/update-package-build.py` — PASS.
- `python3 platform/tests/extract-linux-update.py` — PASS.
- `bash platform/tests/release-assembly.sh` — PASS; four synchronized schema-3 manifests and asset hashes verified using an ephemeral test key. This does not use the production private key or publish a release.
- `pwsh -NoProfile -File platform/tests/update-windows.ps1` — PASS.
- PHP lint: 61 files — PASS. Source JS syntax — PASS. `git diff --check` — PASS.
- Workflow YAML parse and source-fetch retry test — PASS.

## Q010 browser evidence carried forward
- `DCP_Q010_BROWSER_KEYBOARD_ENTER_PASS` — real browser Terminal `pwd`/`ls .` keyboard Enter flow.
- `DCP_Q010_CLIPBOARD_UI_FLOW_PASS` — Devices pairing-code clipboard UI path tested with mocked API and page-context clipboard test double; host clipboard untouched.

## Branch and tag reconciliation
- `feature/alpha10-package-revision` and `origin/feature/alpha10-package-revision` currently point to `db054a2`.
- The active work branch `feature/local-ai-runtime-discovery` is at `de0ffdb`; its merge base with the Alpha 10 branch is `db054a2`. The Alpha 10 candidate tag `v1.0.0-alpha.10` points to ancestor commit `ba3b8f5`.
- The retry helper/workflow changes are currently uncommitted on `feature/local-ai-runtime-discovery`; they are not yet present on `feature/alpha10-package-revision`. Do not push them to the wrong branch. A later scoped transfer/cherry-pick must preserve the current dirty worktree.

## Current release state
- Q005/DC0005 remains partial. Alpha 9 is still the latest published release; Alpha 10 is not published.
- Candidate signed release run `45515068071` failed before Intel macOS SSE4.2 compilation due runner DNS resolution failure for `github.com`; signed assembly/publication were skipped.
- GitHub Actions signing secret remains a separate publication prerequisite. Local signing key stays outside the repository.
- No actual Alpha 9→Alpha 10 target update, forced rollback, or byte-identical live database/key verification was performed.

## Safety and next action
- No branch switch, reset, stash, commit, push, tag, release, target installation, production deployment or user-data mutation. Existing dirty and untracked files preserved.
- Continue local Q005 code review and updater acceptance preparation. After scoped review and the established approval gate, push only the retry fix for a fresh Intel macOS CI run. Do not close Q005 until all signed packages and real target-device acceptance criteria pass.
