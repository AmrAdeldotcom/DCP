# DC39 — resilient macOS source fetch and project continuation

Timestamp: 2026-11-25T04:50:08+0200
Device: Amrs-Mac-Pro online
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
HEAD: `de0ffdb45f1c0ea7b7f8027a8f82f3a580273263`

## Implemented locally
- Added `platform/scripts/clone-pinned-source.sh` with bounded retries, increasing backoff, strict argument validation, destination overwrite protection and post-clone HEAD verification.
- Integrated it into `.github/workflows/build-llama-macos-sse42.yml` for pinned OpenSSL 3.5.4 and llama.cpp b12655.
- Added `platform/tests/clone-pinned-source.sh`, simulating two transient DNS failures before a successful third local clone.
- The Intel workflow runs this regression test before installing/building dependencies.

## Validation
- `bash -n platform/scripts/clone-pinned-source.sh platform/tests/clone-pinned-source.sh` — PASS.
- `bash platform/tests/clone-pinned-source.sh` — PASS; two simulated failures, third attempt clones and verifies the pinned fixture.
- Ruby YAML parse of `.github/workflows/build-llama-macos-sse42.yml` — PASS.
- `git diff --check` — PASS.

## Current project phase
- Q005/DC0005 remains active and partial. Alpha 10 is the first approved baseline; Alpha 9 remains the latest published release and must not be altered.
- Candidate run `45515068071` failed at the Intel macOS source fetch because the runner could not resolve `github.com`; signed assembly/publication did not run. This local retry fix has not been tested on GitHub because it is uncommitted/unpushed.
- The GitHub Actions signing secret is a separate publication prerequisite; the local protected key is outside the repo. Do not place private material in Git or chat.
- Q010 browser keyboard Enter and mocked clipboard UI flows have passed; authenticated Desktop Commander/DeepSeek Harness lifecycle adapters remain open.

## Safety
- No branch switch, reset, stash, commit, push, tag, release, target install, production deployment, or user-data/database/key mutation.
- Existing dirty and untracked files are preserved. Do not stage the full worktree.

## Next action
Continue local Q005 manifest/signature/package/updater tests and inspect all release assembly contracts while GitHub is unavailable. Only after a scoped review and the established approval gate should the retry fix be committed/pushed for a fresh native macOS CI run.
