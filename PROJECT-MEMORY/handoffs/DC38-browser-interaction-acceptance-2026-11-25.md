# DC38 — live project recovery and browser interaction acceptance

Timestamp: 2026-11-25T04:49:01+0200
Device: Amrs-Mac-Pro (`a23231bf-eabc-441c-a6e2-560e3ae7f957`), online
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/local-ai-runtime-discovery`
HEAD: `de0ffdb45f1c0ea7b7f8027a8f82f3a580273263`

## Recovery findings
- Recovered the live Mac through the already-authorized Desktop Commander connection; no SSH key material was read or displayed.
- Confirmed the repository and exact current branch. The active worktree is dirty and contains multiple modified memory/roadmap/queue files plus untracked handoffs/version records. No reset, stash, branch switch, or broad staging was performed.
- Alpha 10 release state remains partial. `v1.0.0-alpha.9` is still the latest published release. The current signed candidate run `45515068071` failed before the Intel macOS SSE4.2 source compile because the runner could not resolve `github.com`; signed assembly/publication did not run.
- The verified release private key remains outside the repository at `/Volumes/Data/ChatGPT/DCP-KEYS/release-private.pem`, mode 0600; its contents were not displayed.

## Q010 verification completed
- Browser Terminal keyboard Enter flow passed: `pwd` returned `/Volumes/Data/ChatGPT/DCP`, `ls .` listed the project, and the command input cleared.
- Devices pairing-code clipboard UI flow passed using an intercepted test API response and a page-context clipboard test double. The host clipboard and live pairing backend were not touched.
- Results: `DCP_Q010_BROWSER_KEYBOARD_ENTER_PASS`, `DCP_Q010_CLIPBOARD_UI_FLOW_PASS`.
- Q010 remains open for authenticated Desktop Commander and DeepSeek Harness lifecycle adapters; detection-only status must not be described as connected execution.

## Q005 status and constraints
- Q005/DC0005 remains the primary release gate: synchronized signed Alpha 10 packages/manifests for Windows, Linux, macOS Intel and Apple Silicon; then actual Alpha 9→10 apply, rollback and byte-identical SQLite/master.key preservation on Windows, Ubuntu and Mac. Kali compatibility remains part of Linux checks.
- Local platform suite, update-package builder, Linux extraction, Windows updater/C# contracts, signed release assembly with ephemeral test key, PHP lint, source JS syntax and security contracts have passed at recorded checkpoints.
- Do not publish Alpha 10, alter Alpha 9, replace target installs, or claim release success until current complete assets and all acceptance evidence exist.
- Keep macOS source-of-truth app and user data untouched; use temporary package/test locations. Windows/Ubuntu installs are disposable only after fresh data backups and hashes.
- Continue local development and validation while CI is unavailable; do not wait idle for GitHub.

## Next action
Inspect the Intel macOS source-fetch step and make it resilient to transient DNS/network failures with bounded retries and clear diagnostics, then add a local syntax/contract test for that workflow behavior. Preserve the current dirty worktree and make no commit/push/release without the established approval gate.
