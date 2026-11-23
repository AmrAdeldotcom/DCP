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

## 2026-11-23 — Windows CI green
- Updated Windows test/release workflow assertions to the managed PHP runtime path and isolated `DCP-UserData` layout.
- Both clean Windows package CI and the existing Windows app build CI passed on commit `dcfdaf4`.
- Cloud PHP CI passed on the same commit.
- macOS Intel and Apple Silicon have passed source download and official PHP source checksum validation; PHP compilation is still in progress.

## 2026-11-23 — macOS PHP build and license-dumper fix
- ARM64 PHP 8.4.28 compiled successfully; required extensions, real HTTPS/TLS and system-only dylib checks passed.
- StaticPHP v2.8.5 license dumper failed because the prebuilt OpenSSL archive omitted `LICENSE.txt`. Confirmed its documented fallback path and added a matching-version OpenSSL upstream license retrieval step, validated against the OpenSSL 3.5.4 tag.
- Intel compilation was still running; the license fix awaits a focused push and a fresh Mac CI run.

## 2026-11-23 — OpenSSL license fix pushed
- Committed and pushed the exact-version OpenSSL license fallback as `64eaf1c`.
- New native macOS runtime workflow run `45272657258` started. Intel is compiling PHP 8.4.28; Apple Silicon is downloading and verifying the PHP source.
- Windows build/package CI and Cloud PHP CI remain green.

## 2026-11-23 — StaticPHP craft token propagation fix
- The license-fix rerun reached `spc craft` but failed to fetch the prebuilt pkg-config asset because the read-only GitHub token was scoped only to the preceding `spc download` step.
- Added `GITHUB_TOKEN` to the separate `Compile managed PHP` step; YAML validation passed. This fix awaits a focused push and another native Mac CI run.


## 2026-11-24 — DC04 access, cloud audit and Q005 CI correction
- Verified key-based SSH to Hostinger, Windows 11 and Ubuntu 24.04; all private keys remain on the external drive.
- Audited Hostinger DCP Cloud read-only: health endpoint 200, old six-table schema, nullable-device migration and pairing tables not yet deployed, .env web denial 403. No live changes.
- Audited Windows installed DCP copy and Ubuntu node read-only; preserved installed data and kept Linux implementation deferred.
- Reviewed StaticPHP v2.8.5 LicenseDumper and corrected local macOS workflow to place matching OpenSSL LICENSE.txt in both source and buildroot fallback locations.
- CI: Windows build 45273110655 passed; Cloud PHP 45272657130 passed; macOS run 45273110645 built both architectures but failed license dumping before artifacts/package smoke.
- Local YAML/PHP/diff checks passed. No commit, push, release, migration, upload or deployment.


## 2026-11-24 — DC05: synchronized GitHub installers and Ubuntu target
- Corrected the macOS StaticPHP license step to run from its own working directory; the prior run used GitHub Workspace, so the source/license lookup happened in the wrong directory.
- Fixed the generated Linux launcher to execute the shell runtime installer directly rather than passing a .sh file to PHP.
- Added dcp-env manifest/installer payload to Linux packages while excluding build caches, compiled engines and virtual environments.
- Added Linux-aware GitHub online installer with release asset/checksum selection, SHA-256 verification, rollback and user-data preservation; added Ubuntu 24.04 runtime packages and launcher setup.
- Added tagged Linux release workflow; expanded Linux package CI to all branches; serialized Mac/Windows/Linux release workflows to avoid competing updates to the same GitHub Release.
- Windows online installer now requests the official prerequisite setup, installing VC++/WebView2 only if absent.
- User authorized Windows replacement via GitHub installer and added Ubuntu 24.04 as a clean-install target. Existing Windows app data/key must be carried forward; keep the previous installation as a rollback backup.
- Local validation passed: bash -n, PowerShell parser, YAML parse (5 workflows), PHP lint, JSON parse and git diff --check. GitHub CI and real installs remain pending.
- Ubuntu host verified as 24.04 x86_64 with passwordless sudo; no packages or application files were changed.


## 2026-11-24 — DC06: migrate legacy user data into OS-specific DCP-UserData
- Found and corrected an upgrade hazard: desktop wrappers read databases and encryption keys from DCP-UserData/<OS>, not platform/data and platform/storage.
- Windows online installer now gracefully stops DCP, preserves old app as rollback backup, migrates old SQLite/storage into DCP-UserData/Windows and verifies database + master-key SHA-256 before setup/launch.
- macOS installer migrates legacy data into DCP-UserData/macOS; Linux package/installer now uses DCP-UserData/Linux. All installers preserve an existing DCP-UserData tree and only migrate legacy data if the new database/key is absent.
- Windows/Linux/macOS package workflows now stage per-OS data directories and assert they are present while real DB/key files are excluded.
- AGENTS.md updated for Ubuntu 24.04 scope. WORK-QUEUE Q004 corrected: Hostinger path is the single Cloud API document root; platform/index.php web preview remains separately gated.
- Validation passed: PowerShell parser, bash syntax, YAML parse across 7 workflows, platform tests, 6 macOS installer tests and git diff --check.


## 2026-11-24 — DC07: complete-release gate for synchronized updates
- Installers and Windows/macOS background updaters now accept only a GitHub Release containing Windows package + update manifest, macOS universal package, Linux package and Linux SHA-256 in the same tag.
- Partial release uploads are ignored; all devices stay on the last complete release. Current public releases have no complete package set, so alpha.8 is intentionally not eligible for installation/update.
- Updated INSTALL.md with synchronized-release policy.
- Local checks: bash and PowerShell parsers passed; seven YAML workflows parsed; macOS Objective-C syntax passed; .NET build passed with zero errors, and the nullable warning in release-asset filtering was corrected for clean rebuild.


## 2026-11-24 — DC08: verify migrated DB and master key on macOS/Linux
- Added byte-for-byte `cmp -s` verification after macOS and Linux installers migrate legacy SQLite and master key into their OS-specific DCP-UserData directories. Mismatch triggers installer rollback.
- Windows installer already compares SHA-256 for both files.
- Local bash syntax passed. This is an installer-only change; native Mac runtime build is unaffected.
