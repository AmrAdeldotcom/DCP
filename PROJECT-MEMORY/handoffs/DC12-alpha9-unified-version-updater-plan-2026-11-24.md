# DC12 Handoff — Unified Alpha 9 and Cross-Platform Auto-Update

Timestamp: 2026-11-24T13:59:42Z

## User decision
Keep visible release version  across Windows, Ubuntu/Kali and macOS. Correct the Linux package and add/complete auto-update; do not publish alpha.10 unless user changes this decision.

## Saved proposal documents
-
-
-

## Release evidence
- Published release ID: 456688871.
- Tag: .
- Annotated tag object: .
- Tag target commit: .
- Branch HEAD: , child of the published target.
- Linux package fix commit 0e3f411 adds platform directory staging and CI package smoke test; CI run 45398600124 passed.
- Existing published release contains six assets; Linux tarball is defective and currently missing .
- Do not overwrite release assets or force-retarget the published tag until corrected artifacts for every OS pass CI and smoke tests and old tag/asset digests have been archived.

## Auto-update audit
- Windows: background release updater exists in Program.cs; delta manifest builder in build-windows.ps1; apply/rollback script in update-windows.ps1. It currently compares semantic versions only; same-version alpha.9 repackaging is skipped.
- GitUpdateService is only for a Git checkout on branch main; packaged  absence returns supported=false.
- No packaged Linux or macOS updater was verified. Cross-platform auto-update is not yet complete.
- Design: preserve semantic version and introduce monotonic ; compare ; manifest declares platform/arch, base revision, artifact size/hash, full/delta; stage, verify, backup, apply, health-check, rollback; preserve user data/SQLite/master keys.

## Validation in this session
- Saved the two user-requested proposal files and the cross-platform updater design.
- PASS: application version is sourced from platform/version.json
PASS: release tag and build metadata are synchronized
PASS: dashboard renders the unified version
PASS: dashboard assets use the unified version and base path
PASS: pairing page resolves base path and version placeholders
PASS: API key is encrypted in SQLite
PASS: API key decrypts correctly
PASS: provider preset can be saved before live model discovery
PASS: API key is returned only as a masked preview
PASS: blank key on edit preserves existing encrypted key
PASS: OpenAI-compatible model discovery
PASS: OpenAI-compatible chat adapter
PASS: mail credential encrypted: mail.smtp_pass
PASS: mail credential encrypted: mail.imap_pass
PASS: one-time device invitation pairing
PASS: paired device heartbeat
PASS: safe terminal command
PASS: unsafe terminal command blocked
PASS: bug report file generated
PASS: new notification starts unread
PASS: notification read state persists in SQLite
PASS: locale JSON en
PASS: locale JSON ar
PASS: locale JSON nl
PASS: locale JSON es
PASS: locale JSON pt
PASS: locale JSON fr
PASS: locale JSON de
PASS: locale JSON zh
PASS: locale JSON ja
PASS: local AI hardware detection
PASS: local AI free model catalogue
PASS: local AI engine detection
PASS: Linux llama.cpp managed runtime path
PASS: Intel Mac without AVX2 selects SSE4.2 runtime
PASS: Intel Mac with AVX2 selects compatible optimized runtime
PASS: Apple Silicon selects ARM64 runtime
PASS: LocalAI stop refuses to kill a foreign process with a stale managed state
PASS: cloud supports anonymous device identity and approval-based pairing
PASS: cloud device and account tokens are not created or exposed by local unit tests
ALL PLATFORM TESTS PASSED: ALL PLATFORM TESTS PASSED.
- No syntax errors detected in platform/app/Services/GitUpdateService.php: passed.
-  for Linux build/runtime installer/package smoke scripts: passed.
-  and  parse as valid JSON.
- : passed.

## Machine/install state
- Windows screenshot confirms Overview UI rendered, DCP service online, PHP 8.5.13 and local DB Ready; Ollama Offline and Desktop Commander Remote Not connected.
- Ubuntu 192.168.8.88 has the old published alpha.9 package; PHP fatal missing , root/API HTTP 500, white window. Do not replace until corrected package is built and tested.
- Windows 192.168.8.99 install backup and DB/master.key hashes remain protected.
- Local Mac network LaunchAgent checks every 600 seconds; it cannot refresh/reopen ChatGPT sessions.
- Hourly DCP watchdog re-enabled and its prompt updated with the user's same-alpha.9 decision and updater audit.

## Next work
1. Implement packageRevision metadata consistently in Windows/Linux/macOS builders and release assertions.
2. Extend Windows updater to detect same-version higher packageRevision and handle correct base-revision/full-fallback semantics.
3. Implement Linux and macOS packaged updater adapters with safe staging/rollback and user-data protection.
4. Add automated tests for same-version update, interrupted update, rollback, DB/key preservation and package metadata parity.
5. Archive old release metadata and all old asset hashes, then retarget/rebuild/overwrite alpha.9 only after all OS CI and package smoke tests pass.
6. Install corrected Linux build on Ubuntu and verify GUI + HTTP/API.

No source release or tag mutation was performed in this checkpoint.
