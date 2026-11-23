# Project Memory Changelog

## 2026-11-23 — DC03 initialization
- Created the hierarchical PROJECT-MEMORY structure.
- Recorded branch, HEAD, active task, current device state, test baseline and safety constraints.
- Corrected active task sequencing: Q005 first; Q006 only after Q005 closes.
- Recorded the intermittent Remote MCP persisted-session/fetch failure and successful reconnect.
- Recorded host constraint: Intel macOS 14.7.1, 8.3 GiB free disk; avoid local compilation.
- Added Q005 task plan and DC03 handoff.
- No application source code changed in this memory initialization.

## 2026-11-23 — DC03 Q005 installer hardening and upstream research
- Added isolated macOS installer tests; proved system-first, managed fallback and unsupported-architecture behavior.
- Hardened architecture detection and added an optional system-PHP candidate override for deterministic tests while preserving normal PATH/Homebrew candidate lookup.
- Updated macOS wrapper PHP compatibility check to require the complete DCP extension set.
- Verified x86_64 and arm64 Objective-C wrapper compilation.
- Re-ran platform tests successfully.
- Pinned StaticPHP v2.8.5 to commit 4318ef8fa32a02460ec1554746674a7bc42b49fa as a build candidate; v3 remains alpha.
- Selected PHP 8.4.28 as the conservative target because v2 documents 8.4 as stable and 8.5 as beta/partial; recorded official PHP source SHA-256.
- Confirmed native macOS Intel/arm64 GitHub runner labels and public DCP repository visibility.
- Managed PHP build workflow and packaging are not yet implemented; Q005 remains partial.

## 2026-11-23 — DC03 Q005 managed runtime workflow
- Added pinned StaticPHP v2.8.5 / PHP 8.4.28 build manifest and macOS craft recipe.
- Added native macOS Intel/Apple Silicon reusable CI workflow with PHP source SHA-256, extension, TLS, dylib, deployment-target and license checks.
- Wired runtime artifacts into macOS release packaging and added post-copy checksum/runtime checks.
- Added clean Windows package CI workflow with runtime, payload and developer key/database exclusion checks.
- Hardened installer and macOS wrapper to verify managed PHP SHA-256 and set private PHPRC only for managed PHP.
- Expanded macOS installer test coverage to 6/6, including fallback after an incompatible system PHP.
- Full platform suite, PHP lint, JS syntax, shell syntax, YAML/JSON parsing, wrapper compilation and git diff check passed.
- GitHub Actions workflows remain unexecuted; no commit/push/release/deployment was performed. Q005 remains partial.

## 2026-11-23 — DC03 Q005 clean macOS package CI
- Added a non-publishing macOS package smoke workflow for push, pull request and manual dispatch. It builds both PHP runtimes, the universal app and portable ZIP, then checks runtime inclusion and private-data/build-cache exclusions.
- Expanded Q005 automation so package verification can run without publishing a release. Workflow YAML parsed successfully; CI execution is still pending.

## 2026-11-23 — First CI feedback and fixes
- Pushed Q005 commit `4752f9d` to the existing feature branch using the configured GitHub SSH identity.
- Windows CI exposed CS0136 in the WebView2 initialization block; renamed the nested local to `webViewData`.
- macOS ARM64 CI exposed an unauthenticated GitHub API 403 during StaticPHP dependency download; supplied the workflow read-only `GITHUB_TOKEN` to the download step.
- Audited cloud access: `dcp-hostinger` SSH alias exists but its configured private-key path is missing and authentication is rejected. No cloud deployment was attempted.

## 2026-11-23 — Second Windows CI feedback
- Windows CI confirmed the C# variable-shadowing fix and successfully downloaded, verified, installed and ran managed PHP 8.5.13.
- Both clean-package and existing Windows app build workflows then exposed a PowerShell `-replace` regex with a single backslash. Corrected it to match a literal backslash and verified the path-normalization expression locally with PowerShell.
- Audited SSH inventory: GitHub key works; Hostinger key exists and is offered but rejected by server; Windows lab host is unreachable; Ubuntu alias is placeholder. No cloud deployment performed.

## 2026-11-23 — Windows clean package passes
- Clean Windows package CI passed on commit `810834c`: PHP 8.5.13 managed runtime, verifier, release layout and user-data exclusions all passed.
- Existing Windows app build also compiled and installed PHP but failed a stale test assertion for the old PHP path. Updated the Windows test and release workflows to the new `platform/dcp-env/runtime/php/windows-x64/php.exe` location and `DCP-UserData` paths.
- macOS Intel and Apple Silicon builds passed builder setup, official source download and SHA-256 verification; PHP compilation is still running.
