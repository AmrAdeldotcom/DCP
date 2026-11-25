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


## 2026-11-24 — DC10: raise macOS minimum to 12.0
- Intel PHP link logs showed prebuilt ICU objects built for macOS 12.0 while the x64 binary declared 10.15. Do not claim Catalina compatibility.
- Current Mac is macOS 14.7.1. Set both Intel and Apple Silicon runtime/wrapper minimums to macOS 12.0.
- Updated build matrix, runtime manifest, universal wrapper target, Info.plist LSMinimumSystemVersion, online installer preflight, docs and CI assertions.
- New native Mac runtime builds are required; earlier 10.15/11.0 CI results remain historical only.


## 2026-11-24 — DC11: memory reconciliation and version validation
- Re-read the project memory hierarchy and found stale CURRENT-STATE and access-inventory claims relative to the live worktree. Refreshed CURRENT-STATE with verified HEAD fc4d5d7, current task and latest local test evidence; marked access routes for revalidation before use.
- Confirmed platform/version.json is local/untracked at 1.0.0-alpha.9; reviewed PHP config/UI version injection, Windows/Linux package metadata generation and macOS tag-to-bundle version handling.
- Ran platform tests (all passed, including version manifest/tag/build tests), full platform PHP lint, JS syntax, shell syntax, PowerShell parser, version JSON parse and git diff --check; all passed.
- Latest known GitHub Actions results remain green for Windows, Ubuntu 24.04, native Mac Intel/ARM64 managed PHP, Mac clean package smoke and Cloud PHP lint. Real target-device install/launch/data-decryption verification remains outstanding.
- No application commit, push, release, production deployment, Windows uninstall or Ubuntu install was performed in this checkpoint.


## 2026-11-24 — DC12: cloud API deployment, version UI tests and LocalAI hardening
- Recovered the Mac Desktop Commander queue and resumed from the DC11 checkpoint; inspected pending call results before issuing new commands.
- Re-ran git diff --check and php platform/tests/run.php; both passed. All platform tests passed, including new Dashboard/Pairing rendering assertions for unified version/base path.
- Recorded verified Q007 deployment: pre-change DB/file backup with checksums, .env tightened to mode 0600, cloud entry/site/.htaccess deployed atomically, migration verified, health healthy and temporary end-to-end registration/device/pairing/signaling/revocation test passed; all temporary test records cleaned. Full desktop UI and security audit remain open.
- Recorded Q006 code changes for managed llama process identity checks, Linux llama.cpp installer, Ollama service reuse and Windows runtime path alignment. Syntax checks passed, but runtime and cross-platform verification remain pending.
- Added version manifest/tag assertions to package and release workflows. These are local and await push/CI.
- No commit, push, public release, Windows replacement or Ubuntu/Mac LocalAI install in this checkpoint.

- Follow-up web-entry check found standalone site.php was still publicly reachable (HTTP 200). Inlined the landing page into cloud index.php, removed site.php from source and live docroot, updated Cloud CI assertion, backed up live index/.htaccess/site.php to /home/u163155085/dcp-backups/20261124T232000Z, and verified site.php/DEPLOYMENT.md return 404, .env files return 403, while root and API health return 200.

- Extended DC12 Q006 evidence: Mac Intel AVX2 binary failed with illegal instruction (exit 132); SSE4.2 binary and service selection passed. Fixed CPU-aware Intel selection, Linux x64 managed path, Ollama Linux path, undefined startLlama pidRunning call, and direct Linux installer execution (not a staged copy with broken relative paths). Added four platform/CPU candidate tests.
- Ubuntu 24.04 smoke-tested official llama.cpp b12655 SHA-256, dynamic dependencies and version. Executed the actual Linux installer in a temporary repo-shaped tree using wget; installed and verified the binary, then cleaned the temp tree. Updated online/engine installers to support wget fallback; tested online installer metadata retrieval and safe no-complete-release refusal. Windows engine smoke was blocked by a stalled zero-byte download and remains pending.
- Extended DC12 Q007 hardening: Hostinger does not reliably apply .htaccess restrictions to arbitrary static files, so moved legacy ZIP/docs/schema/default.php/cloud-platform-upload out of the public root and into the external backup. Moved production .env to /home/u163155085/dcp-private/.env (directory 0700, file 0600), updated PHP to prefer the private file, and removed the webroot copy. Root and health remain HTTP 200; .env is 403; removed paths are 404.

- Pushed four focused commits to fix/alpha7-localai-cloud-pairing: e815242, 4348aa8, 7d9fc56, 58f11d2. GitHub Actions on 58f11d2: cloud, Windows build and clean Windows package succeeded; Linux WebView and macOS clean package were still in progress.
- Windows llama.cpp b12655 x64 runtime smoke passed after downloading via Ubuntu wget (Windows host's direct GitHub download stalled): SHA-256 matched and llama-server --version passed; temp files cleaned.
- Added CI/release build of pinned llama.cpp b12655 generic Intel SSE4.2 runtime and package inclusion with license, so older Intel Macs without AVX2 receive a working engine instead of relying on a developer-only source checkout.

- Added commit 8c3c4a5: CI now smoke-tests the official Apple Silicon llama.cpp release archive on arm64 runners (SHA-256, architecture and --version). Updated current branch head and CI checkpoint; no complete release or desktop installation yet.

- Q007 API hardening pushed as e4301f7: 64 KiB body limit, opportunistic cleanup of expired ephemeral records/tokens/rate buckets, and broader IP-hashed rate limits across device, heartbeat, connection and signaling endpoints. Live oversized POST returned 413; health stayed 200. Cloud CI now asserts these controls.

- Added c96a1c5: ensure CMake is available in macOS package and release jobs before building the generic SSE4.2 llama.cpp runtime. Cloud CI passed; Windows build and macOS package are queued/in progress; Linux WebView still running.

- Added 0eaf0a6: automated LocalAI ownership regression verifies that a stale managed state cannot make DCP stop an unrelated process. Full platform tests passed; Cloud CI passed, Linux/Windows jobs in progress and Mac package queued.

- CI on branch head 0eaf0a6: Cloud PHP, Windows build and Linux WebView all succeeded; macOS clean package remains in progress. Captured Windows/Ubuntu target preflight (WebView2 and free disk; Ubuntu GNOME, wget, no curl/PHP CLI).

- Confirmed Ubuntu 24.04 target already has GTK3 3.24.41 and WebKit2GTK 4.1 2.52.6, plus GNOME graphical target. wget exists; curl and PHP CLI do not, which the installer now handles.

- DC12 resume reconciliation: true branch head is 8705c38 (not 0eaf0a6 or 58f11d2); later commits 8c3c4a5, c96a1c5, 0eaf0a6 and ba5409e were also pushed. Local source tree is clean; memory/queue/roadmap notes are dirty.
- Diagnosed macOS CI failures: unauthenticated GitHub API 403 in Apple Silicon smoke, and Intel SSE4.2 CMake build exit 2 with output hidden in redirected log. Commit 8705c38 adds GitHub token auth for the CI API and an ERR trap that prints CMake/build logs. New CI batch is pending.
- Release inventory confirms alpha.8 lacks Linux tarball and SHA-256; no complete five-asset release exists and alpha.9 is not published. No desktop app installation or model inference has been verified on any target OS.


## 2026-11-24 — DC04 conversation resume and validation
- Recovered Amrs-Mac-Pro and reconciled project memory with the live repository and GitHub Actions.
- Corrected CURRENT-STATE task sequencing to Q005 active, Q006 next; Q006 implementation remains partial but gated behind Q005.
- On commit 8705c38, Cloud PHP, Windows build, clean Windows package and Linux WebView workflows passed; clean macOS package remained in progress with both native PHP builds compiling.
- Re-ran the full platform suite (all passed), macOS runtime installer suite (6/6), LocalAIService PHP lint, JS syntax, Bash syntax, version JSON, all nine workflow YAML parses and git diff check.
- Confirmed Mac free disk is 8.1 GiB; no local runtime compilation attempted. No application source, deployment, release or install changes made.


## 2026-11-24 — DC04 CI and target preflight update
- Both native macOS PHP 8.4.28 runtimes passed CI with extension, HTTPS/TLS, dylib, macOS 12.0 deployment-target and license checks; artifacts uploaded for Intel x64 and Apple Silicon arm64.
- Dependent macOS clean-package smoke is running after successful runtime artifact embedding/checksum verification.
- Revalidated Windows and Ubuntu SSH; verified Windows original user data exists and confirmed a temporary interactive Task Scheduler probe runs in the correct user context (task/file cleaned up).
- Ubuntu active GNOME Wayland session, GTK/WebKitGTK, passwordless sudo and launch sockets verified without changing the machine.


## 2026-11-24 — Intel llama.cpp portability issue diagnosed
- macOS package CI reached Intel SSE4.2 llama.cpp linking and failed on unresolved OpenSSL/X509 symbols; PHP runtimes, universal app and Apple Silicon llama smoke passed.
- Found both cross-architecture Homebrew OpenSSL selection and build-directory LC_RPATH/dynamic OpenSSL dependencies in the prior local engine.
- Prepared a native Intel reusable workflow with pinned static OpenSSL 3.5.4, relocatable `@loader_path` rpaths, no external OpenSSL dylibs, and explicit license inclusion; test/release macOS workflows now consume its artifact.
- Local Bash/YAML/JSON/diff checks passed. Fix remains local/uncommitted pending approval to push and rerun CI.


## DC04 — Native macOS package split (2026-11-24)
- User approved replacing the macOS Universal build with two native deliverables: Intel x86_64 and Apple Silicon arm64. This removes the mandatory lipo/universal merge and prevents shipping the other architecture's PHP/llama runtime.
- Commit pushed: 8b5bf08 (refactor(macos): publish native Intel and Apple Silicon packages).
- platform/wrappers/build-macos.sh now compiles exactly one native Cocoa/WebKit binary, selected by DCP_MACOS_ARCH (defaulting to runner uname -m). No lipo merge is performed.
- build-macos-generic.sh now supports x86_64 (SSE4.2, runtime folder macos-x64-sse42) and arm64 (Metal enabled, runtime folder macos-arm64) using architecture-matched pinned static OpenSSL.
- Added .github/workflows/build-llama-macos-arm64.yml to build/test ARM64 llama.cpp on native Apple Silicon with static OpenSSL 3.5.4. Existing Intel native builder remains separate.
- Reworked clean package and release workflows into native architecture matrix jobs. Each package includes only its matching PHP and llama.cpp runtime. Release upload is aggregated once after both package artifacts are available.
- Release asset names are now DCP-macOS-Intel.zip and DCP-macOS-AppleSilicon.zip. macOS/Linux/Windows online installers and Windows/macOS desktop update checks require both assets for a synchronized release; macOS installer selects the matching asset via uname -m.
- Updated README and INSTALL documentation. Windows updater release completeness check now recognizes both native macOS assets.
- Local validation: php platform/tests/run.php ALL PLATFORM TESTS PASSED; macOS runtime installer tests 6/6 passed; PHP lint, JS syntax, Bash syntax, three macOS workflow YAML parses, git diff --check passed. Native Intel Cocoa/WebKit wrapper compiled successfully and file confirmed x86_64 with system-only framework dependencies. No local runtime compilation performed.
- GitHub CI triggered on commit 8b5bf08: native macOS package run 45315165121 (queued at last check); Intel llama reusable job and Intel PHP job had started, ARM64 PHP/llama jobs queued. Windows build/package, Cloud PHP and Linux workflows also triggered. The older 45312067263 failure belongs to the previous universal workflow and is superseded.
- Do not tag/publish alpha.9 until both native package jobs and all platform workflows pass and the release contains both macOS assets plus the Windows/Linux assets. Q005 remains partial.


- Latest CI update: ARM64 llama.cpp reusable job 138180147358 completed successfully, including pinned static OpenSSL, native llama.cpp build, ARM64 architecture/minimum macOS 12 verification, server smoke, and artifact upload. Artifact dcp-llama-macos-arm64 ID 14884354767, 30,464,748 bytes, SHA-256 2eefbcc8c6195aff20a7c8224298885ce48c1b196a2fc2db74daaf546bee7eca.
- Remaining in macOS run 45315165121: Intel llama.cpp build after successful static OpenSSL; Intel PHP and ARM64 PHP compile after successful source verification. Package matrix waits for these artifacts.
- Windows build 45315165131, Windows package 45315165149 and Cloud PHP 45315165123 are all green. Linux package run 45315165137 remains in progress.

## 2026-11-24 — Alpha 9 native macOS release
- Published `v1.0.0-alpha.9` prerelease with six synchronized platform assets, including separate Intel and Apple Silicon macOS packages. Native release/package workflows passed for both architectures.
- Began official online installer validation: Intel Mac isolated installation and HTTP/API/PHP/llama smoke passed; Ubuntu installation in progress; Windows VM unreachable and left untouched.


## DC06 — 2026-11-24T14:57:19+02:00: Alpha 9 install validation and Linux package defect
- GitHub tag v1.0.0-alpha.9 resolves to commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1; release ID 456688871 is published with all six expected assets. Native Intel/ARM64 macOS build/package jobs, Linux release, Windows release and clean Windows package workflows all completed successfully on that exact commit.
- Revalidated the Intel Mac smoke package: app 1.0.0-alpha.9, x86_64 Mach-O, macOS minimum 12.0; managed PHP 8.4.28 hash matched php.sha256 and verify-runtime.php passed; llama-server x86_64 --version and --help passed; 101 tracked application files across nine package directories were byte-identical to /Volumes/Data/ChatGPT/DCP.
- Removed only ~/Applications/DCP-Alpha9-Smoke (130 MiB) after confirming smoke PIDs 77765/77768 and port 50641 were absent. Original project remains at HEAD 8b5bf08. Existing PHP 92383:18991, llama-server 99659:19123 and Ollama 2101/2106:11434 remain active; no unrelated service was stopped or changed.
- Ubuntu installer completed from tag-pinned Alpha 9 script at /home/amr/Applications/DCP; apt prerequisites installed. PHP 8.3.6 and all required extensions pass verify-runtime.php; ELF x86-64 DCP binary has no unresolved ldd libraries; desktop launcher created. Launch in the active amr Wayland session started DCP PID 742874 and PHP PID 742899 on loopback 127.0.0.1:18765.
- Ubuntu HTTP root and /api/status both returned 500. DCP-UserData/Linux/storage/logs/php-error.log shows platform/app/Core/Database.php missing. The archive has Controllers/Core/Services directly under platform/ instead of platform/app. Root cause: build-linux.sh failed to pre-create OUT/platform before the first cp -R ROOT/app OUT/platform/; the first copy created the destination as the app contents. CI asserted public/router.php but omitted the critical app paths and HTTP smoke.
- Stopped only the two DCP test PIDs; port 18765 is closed. Retained /home/amr/Applications/DCP and its error log as evidence; no other Ubuntu services were targeted.
- Prepared a scoped four-file fix: pre-create OUT/platform; assert platform/app/Core/Database.php and platform/app/Controllers/ApiController.php in both Linux workflows; add platform/tests/linux-package-smoke.sh to run PHP verification plus root and /api/status HTTP checks using isolated user data. Bash syntax, YAML parsing, copy-layout regression, full platform suite and git diff --check passed. A corrected copy of the installed package passed the HTTP smoke on Ubuntu (PHP 8.3.6, 23 plugins). Fix is not yet committed/pushed.
- Installer source audit: default main is commit 25c81e23e0a6810a7c9b70a33d0794b00ed5de0d, lacks platform/Install-DCP-Online.sh, and has an older Windows online installer. Alpha9 README one-liners reference raw main; Mac/Ubuntu tests therefore used the tag-pinned script. Synchronizing main requires human review; do not merge the accumulated release branch automatically.
- Windows VM remains unreachable at .62/legacy .54 per prior timeouts; no Windows files changed. Before replacement, preserve timestamped backup and compare DB/master-key hashes under interactive amradeldotcom. No Apple Silicon device exists; native CI is the available evidence.
- Next: commit/push only the four Linux packaging fix files, verify branch CI, then request human decision on a new superseding alpha release/main synchronization. Retry Windows VM connectivity.



## DC07 — 2026-11-24T15:00:25+02:00: Linux package fix pushed; CI and corrected fixture pass
- Commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592 fixes the Linux staging root by creating OUT/platform before copying app/bootstrap/config/etc. It adds required app-path assertions and a real PHP root/API smoke test to both Linux workflows.
- Only four source/workflow files were staged and committed: .github/workflows/build-linux.yml, .github/workflows/release-linux.yml, platform/scripts/build-linux.sh, and platform/tests/linux-package-smoke.sh. Push to fix/alpha7-localai-cloud-pairing succeeded. No project-memory/roadmap files were staged.
- GitHub Actions run 45398600124 on 0e3f411 completed success: Build Linux WebView, Verify package contents, Smoke-test packaged Linux application, Upload Linux x64 artifact. Artifact DCP-Linux-x64 ID 14922751159 (585,549 bytes). Windows build 45398600263 and Cloud PHP 45398600090 also passed.
- Corrected temporary Ubuntu package fixture passed the same HTTP smoke and a full Run-DCP.sh launch under the active Wayland session: root HTTP 200, /api/status ok=true, PHP 8.3.6, 23 plugins; app PID 744649 and PHP PID 744674 were stopped, port 18765 closed, and /tmp fixture removed.
- Published Alpha9 Ubuntu install remains broken and retained at /home/amr/Applications/DCP for evidence. Its test app PIDs 742874/742899 were stopped. Do not claim Alpha9 Linux passes; do not overwrite or rewrite the published tag. A new approved alpha release is required to distribute the fix.
- Windows retry at 14:58 EET: SSH to 192.168.8.62:22 timed out, TCP probes to .62/.54 failed, ping .62 had 100% loss. Desktop Commander still lists only the Mac. No Windows files were changed.
- Default main remains commit 25c81e23e0a6810a7c9b70a33d0794b00ed5de0d and is not synchronized with the release branch. It lacks Install-DCP-Online.sh; its Windows installer is older. Tag-pinned scripts were used. Human review is required before merging/synchronizing main.
- Next human checkpoint: bring the Windows VM/agent/network online and decide whether to approve a superseding alpha release plus main synchronization. Apple Silicon physical testing is unavailable; native CI passed.

## 2026-11-24 — DC12: user-approved alpha.9 identity and proposal archive
- Archived the DCP Chat Live Task Inbox & Interrupt Queue feature specification and OpenAI feature request under `platform/docs/proposals/`.
- User preference: keep a unified visible `v1.0.0-alpha.9` identity across Windows/Linux/macOS while correcting the Linux packaging defect and completing auto-update capability; do not move to alpha.10 at this time.
- Release safety: current published alpha.9 tag points to 8b5bf08; Linux package fix commit 0e3f411 is a child commit. A same-version republish requires package revision metadata and update logic that detects revision changes; semantic version equality alone is insufficient.
- Existing updater audit remains open: Windows delta manifest and updater script exist; end-to-end packaged updater behavior and Linux/macOS update support still need verification.


## 2026-11-24T16:10:39+02:00 — DC13: user approved unified alpha.10 and full cross-platform Updater
- User approved publishing `v1.0.0-alpha.10`; preserve published `v1.0.0-alpha.9` unchanged.
- New hard requirement: real packaged Updater and Delta Update on Windows, Linux (Ubuntu/Kali) and macOS, with integrity checks, staging, rollback, restart and user-data/SQLite/master-key preservation.
- DC0005/Q005 remains the release gate. Close only after all OS CI, installs, alpha.9-to-alpha.10 update, rollback and data-preservation tests pass. Then begin PHP development separately.


## 2026-11-24T16:13:54+02:00 — DC13: alpha.10 package revision groundwork
- Canonical version manifest bumped to v1.0.0-alpha.10, packageRevision 1; alpha.9 left unchanged.
- Propagated packageRevision through Windows assembly/manifest, Linux package metadata and macOS app Info.plist; Windows updater can compare revision.
- Local test baseline remains green (platform suite, YAML, Bash, PowerShell, JSON, diff check); Windows wrapper build succeeded with one WindowsBase warning.
- Native Linux/macOS packaged updater implementations and cross-platform update/rollback tests remain outstanding.


## 2026-11-24 — DC14: Windows updater transaction hardening and Alpha10 CI repair
- Added Windows updater schema 2/revision handling, official release manifest URL/version checks, SHA-256/size validation, path/reparse protection, user-data exclusions, correct managed PHP path, reverse-order rollback, retained rollback snapshots, update-status JSON and post-launch process check.
- Added PowerShell transaction tests for successful apply with SQLite/master.key preservation, forced mid-apply rollback, and user-data path rejection. Local tests passed; GitHub Windows clean-package run 45418031542 passed the updater test step. Windows build 45418031428, Linux build 45418031828 and Cloud PHP 45418031562 also passed on ec170a6.
- Fixed macOS clean-package test workflow to embed DCPPackageRevision in its test app bundle (commit 8190261) after CI exposed the missing Info.plist key. macOS retry 45416881645 remains queued/in progress and must be checked for both architectures.
- Verified Alpha 9 release ID 456688871, exact tag commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1 and six assets unchanged. Published Linux package remains defective; no new release published.
- Read-only VM checks confirm Windows SQLite/master.key and three backup copies still match; Ubuntu remains on defective Alpha 9 package. No VM install/update performed.
- Q005 remains partial: Windows full fallback/signature, Linux and macOS packaged updaters, and end-to-end update/rollback tests remain outstanding.

- 2026-11-24T16:54:11+02:00: macOS Alpha10 retry 45416881645 advanced; Intel llama artifact job passed, Intel PHP compile and Apple Silicon llama build are in progress, Apple Silicon PHP job queued. Release/package test remains pending.


## DC15 — Alpha 10 macOS metadata fix and Kali compatibility CI (2026-11-24 17:34 EET)
- User asked to apply the CI fix and continue Alpha 10. Branch `feature/alpha10-package-revision`; latest pushed commit `46a0753335e50c4882534bcc8300ce11fe541c14`.
- `platform/wrappers/build-macos.sh` now reads version, build and packageRevision from `platform/version.json` and writes `CFBundleShortVersionString`, `CFBundleVersion`, and integer `DCPPackageRevision` to `DCP.app/Contents/Info.plist` before signing. `.github/workflows/test-macos-package.yml` asserts bundle/manifest parity. This removes the original failure `Print: Entry, ":DCPPackageRevision", Does Not Exist` by making the native build script responsible for the metadata.
- Added `.github/workflows/test-linux-distros.yml`: Ubuntu 24.04 builds a portable package; Kali Rolling installs runtime libraries, verifies package dependencies and PHP extensions, then runs `linux-package-smoke.sh` to check HTTP root and `/api/status`.
- Kali first attempt `45426144391` failed at `test -x platform/dist/linux/DCP`: GitHub artifact directory transport did not preserve executable mode bits. The workflow now tars the package before upload and extracts it on Kali to preserve permissions (`46a0753`).
- `45426353230` Windows build on `46a0753`: success. `45426353842` Cloud PHP: success. `45426353224` Linux distro workflow: Ubuntu package build at archive upload; Kali job pending. `45425681044` macOS clean-package workflow on `d1e6f1a`: Intel PHP and Intel llama native build in progress; Apple Silicon jobs queued.
- Local validation: `php platform/tests/run.php` — ALL PLATFORM TESTS PASSED; all platform PHP lint passed; `zsh -n platform/wrappers/build-macos.sh`, `bash -n` Linux builder/smoke test, JSON/YAML parsing and `git diff --check` passed.
- No release created, no Alpha 9 assets/tag changed, no target-device installation performed. Q005 remains partial.
- Next: verify Kali HTTP smoke and macOS Intel/Apple Silicon packages; then implement Linux/macOS packaged updater + delta/full fallback, complete Windows full fallback, signed manifest/attestation and end-to-end upgrade/rollback/data-preservation.


## DC05 — Kali artifact path correction (2026-11-24T18:08:00+02:00)
- Inspected Kali failure logs for run `45426353224`: Ubuntu build, archive creation, upload and Kali artifact download all succeeded. Failure occurred because `${{ runner.temp }}` resolved to `/home/runner/work/_temp`, which was not the mounted workspace path visible inside the Kali job container; the tar archive was downloaded successfully but the extraction step looked in the wrong filesystem path.
- Fixed `.github/workflows/test-linux-distros.yml` to download into workspace-relative `dcp-linux-artifact`, assert the archive exists, then extract into `platform/dist/linux`. Commit `7a70284` pushed to `feature/alpha10-package-revision`. YAML parse and `git diff --check` passed.
- New Linux distro run `45431191589`: Ubuntu 24.04 package build, tar archive and artifact upload all passed. Kali job `138577144451` was installing runtime dependencies at 18:08 EET; artifact download/extract, dependency verification and HTTP/API smoke were still pending. Do not claim Kali green until job concludes.
- macOS clean-package run `45425681044`: Intel native package passed; Intel/Apple Silicon PHP runtime and llama artifacts all passed; Apple Silicon native package job `138569576535` remains queued.
- Previous `46a0753` Windows build and Cloud PHP runs passed. Latest `7a70284` Windows run `45431191605` and Cloud PHP run `45431191791` were still in progress (Cloud PHP lint passed).
- Remaining Q005 work is unchanged: real packaged Linux/macOS updaters, Windows full-package fallback, common signed/attested manifest, delta/full selection, all-platform upgrade/rollback and data-preservation acceptance. Alpha 10 remains unpublished; Alpha 9 untouched; do not start PHP features.


## DC05 CI result — 2026-11-24T18:12:00+02:00
- Commit `7a70284ef0b74cdb86276b41692b20c023a8f32d` is pushed to `feature/alpha10-package-revision`.
- Linux distro workflow `45431191589`: Ubuntu 24.04 build/package/archive/upload SUCCESS; Kali Rolling dependency installation, artifact download, archive extraction preserving executable permissions, library/package verification and packaged HTTP/API smoke test SUCCESS. Kali job `138577144451` concluded success. This proves one portable Linux package works in both tested distro environments (Ubuntu 24.04 and Kali Rolling CI container).
- Windows build `45431191605`: SUCCESS; Windows x64 build, PowerShell script parsing and package exclusion checks all passed.
- Cloud PHP `45431191791`: SUCCESS; php-lint/source validation passed.
- macOS clean-package workflow `45425681044`: Intel native package `138569576679` SUCCESS; managed PHP x64+arm64 and llama.cpp Intel+ARM64 artifacts all SUCCESS; Apple Silicon native package `138569576535` remains QUEUED. Do not mark complete until it runs and passes.
- User confirmed local Kali is powered off and Ubuntu 24.04 is the active Linux machine. Read-only SSH verified host `amr-ubuntu24`, Ubuntu 24.04 LTS, kernel 7.0.0-43-generic, amd64; no DCP/PHP server was running during this check. No files or installed app were changed on that host.
- Next engineering: common signed update manifest, Linux/macOS packaged updater adapters with version+packageRevision comparison, exact-base delta and full fallback, atomic staging/rollback/health/restart, complete Windows full fallback, then target-device Alpha9→Alpha10 acceptance tests and data preservation. No release published; Alpha9 remains unchanged; Q005/DC0005 remains open; PHP feature work remains gated.


## DC05 local regression verification — 2026-11-24T18:16:00+02:00
- Re-ran `php platform/tests/run.php` on Amrs-Mac-Pro: ALL PLATFORM TESTS PASSED.
- Re-ran `pwsh -NoProfile -File platform/tests/update-windows.ps1`: ALL WINDOWS UPDATER TESTS PASSED, including schema 2 apply, SQLite/master.key preservation, retained rollback snapshot, forced mid-apply rollback and unsafe user-data path rejection.
- `bash -n` Linux builder and smoke test, `zsh -n` macOS build wrapper, and `git diff --check` passed.
- macOS clean-package run `45425681044` remains partially complete: Intel native package passed; Apple Silicon native package job `138569576535` is still queued.


## 2026-11-24T18:24:00+02:00 — DC05 CI green
- Recorded green Ubuntu 24.04 + Kali Rolling Linux package compatibility, Intel + Apple Silicon macOS clean packages, Windows build and Cloud PHP CI.
- Recorded local green signed-manifest, delta-package builder and signing tests.
- Reconciled the remaining gap: schema-3 is not yet wired to native updater/release workflows; Linux/macOS packaged updaters and Windows full fallback remain outstanding.


## 2026-11-24T18:28:00+02:00 — signed-manifest CI guard
- Added `.github/workflows/test-update-manifest.yml` to continuously validate schema-3 manifest verification, delta packaging and detached-signature tamper checks. Local YAML and all three test groups passed; GitHub execution remains pending.


## 2026-11-24 — DC16 Alpha 10 signed manifest and delta package foundation
- Pushed commit `2f5a5d55a6e3c100e5425bdabbf8e8c4c6b9ddfe` to `feature/alpha10-package-revision` with schema-3 manifest verifier, pinned RSA public key, delta package builder, manifest generator, detached signing script and local tests.
- Local PHP suite, manifest/signature/tamper tests, tar.gz/zip delta builder tests and Windows updater transaction tests all passed.
- GitHub Cloud PHP, Windows build, clean Windows package, Linux build and Ubuntu/Kali compatibility CI passed on `2f5a5d5`; latest macOS run remains in progress/queued by architecture. Earlier native macOS run `45425681044` passed Intel and Apple Silicon.
- RSA-3072 private key is held in macOS login Keychain; GitHub Actions secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` is not yet configured. No private key material stored in repository or project memory.
- Schema-3 is not yet wired into native updater clients or release workflows. Windows full fallback, Linux/macOS packaged update adapters and target upgrade/rollback acceptance remain open. Alpha 9 remains immutable; Alpha 10 not published; Q005 remains partial.

- Worktree note for DC16: observed untracked `.github/workflows/test-update-manifest.yml` after push; not part of commit `2f5a5d5`, not staged, and left for review because equivalent test steps are already wired into Linux/macOS workflows.


## 2026-11-24T18:39:31+02:00 — DC17 Linux updater core
- Added the packaged Linux updater transaction engine, safe tar extractor, signed multi-platform manifest synchronization, explicit user-consent startup check, full fallback, DB/key snapshot and post-update API health rollback.
- Added Linux updater transaction/extraction/health tests and wired them into platform, package smoke, Ubuntu and Kali CI.
- Local platform suite, package-builder, extraction, lint, shell syntax and diff checks passed. No production release assets or target installs changed.


## 2026-11-24T18:58:54+02:00 — DC18 Linux/Windows updater hardening
- Pushed `4ddb91e38a0a919cfe7d7bc01cfc5a74f1a88f50`: crash-safe Linux package transaction/recovery, user-data-isolated health check, signed full-deletion list, delta-missing full fallback, process/lock guard, and explicit Python 3 runtime dependency for Ubuntu/Kali.
- Added native Windows schema-3 signed updater client and C# contract tests; integrated full fallback selection and portable PowerShell atomic replacement.
- Local Linux/Windows updater, manifest/signature, package builder, extraction, contract, syntax and Windows build tests passed.
- CI on 4ddb91e: Windows build/package, Linux build and Cloud PHP passed; Kali compatibility and new native macOS run still in progress at checkpoint.
- Read-only SSH to Windows/Ubuntu succeeded; no device installs or data mutations. Alpha9 unchanged; Alpha10 not released.
- Reviewed untracked macOS updater prototype and rejected it for now due missing crash recovery and unsafe stale-PID termination; no commit. Q005 remains partial.


## 2026-11-24 — DC19 live monitor and platform updater regression
- Added tools/dcp-watch.sh live Terminal monitor for queue, memory, branch, dirty files and local build/test processes.
- Confirmed Ubuntu Alpha 9 installation is incomplete: platform/app/Core/Database.php, packaged updater and pinned key are missing; no server is running. No target changes made.
- Fixed macOS updater process-identity matching for /var vs /private/var; added process-guard tests. platform/tests/update-macos.php passes 40 assertions.
- Re-ran platform, Linux package/extraction, Windows transaction and C# contract tests plus Windows app build; all passed. Windows build has one existing WindowsBase warning and zero errors.
- Pushed a8bc81fd. CI started: manifest/delta, Linux and Windows in progress; native macOS package queued. Previous Ubuntu/Kali distro run 45446122964 passed.
- Production signing Actions secret remains missing; manifest preflight confirms this is the remaining failure after contract tests.


## 2026-11-24T20:23:42+02:00 — DC21 Alpha 10 update-monitor fixes and CI
- Recorded pushed HEAD f59c086 and the five-minute updater monitor implementations for Windows/Linux/macOS.
- Recorded macOS pending-package cache predicate fix and single verified-release snapshot; macOS updater suite now 52 assertions.
- Recorded green local platform/transaction/signature/release-assembly tests and green Linux Ubuntu/Kali, Windows build and manifest CI; native macOS package CI remains queued.
- Recorded read-only Mac/Windows/Ubuntu state and immutable Alpha 9 release evidence. No release, merge or target replacement performed.



## 2026-11-25 — DC31 Windows LocalAI lifecycle
- Fixed Windows PowerShell 5.1 native-stderr handling in the managed llama.cpp runner; non-zero native exit codes still propagate.
- Verified real interactive-user Qwen3 0.6B start, OpenAI-compatible chat and managed stop on Windows using the cached GGUF.
- Added port/PID/executable ownership checks and exact scheduled-task cleanup. Full platform tests pass; no database or encryption key changed.



## 2026-11-25 — DC32 Q006 cross-platform LocalAI closeout
- Verified real Qwen3 0.6B managed start/chat/stop on Windows interactive user, Ubuntu 24.04 and Intel macOS.
- Fixed Intel Mac readiness timeout (10s was too short for initial GGUF mapping); readiness now allows 60s and cleans up on failure.
- Preserved existing Mac llama.cpp listener 19123 and Ubuntu update watcher; all disposable test harnesses/model copies removed.
- Added Local AI UI/API contract checks; full platform suite, model-manager contract, UI contract and JS syntax pass.


## 2026-11-25 — DC34 Windows terminal and agent status
- Fixed Windows TerminalService pwd quoting and verified the allow-listed commands under the interactive Windows user.
- Verified DCP Local Node status on Windows, macOS and Ubuntu; made DSH/Desktop Commander process probes platform-aware.
- DSH and Desktop Commander remain detection-only/not connected; no false connected status claimed.


## 2026-11-25 — DC35 real custom GGUF conversion
- Imported a real Qwen3 0.6B Q4_K_M GGUF, verified SHA-256/private 0600 storage, converted through Ollama, ran a real chat probe, then deleted only the temporary model and imported file.
- Existing Ollama DiGEMA models and service remained intact; no SQLite/master.key changed.


## 2026-11-25 — DC36 DCP GGUF UI/API flow
- Verified real DCP app API import, Ollama conversion, ready-state polling, Qwen3 chat via the Ollama provider adapter, and API deletion.
- Fixed Ollama manifest visibility race (120-second grace) and Qwen3 hidden-thinking output.
- Temporary model/file removed; existing DiGEMA models and Ollama service preserved.


## 2026-11-25 — DC37 local security hardening
- Restricted desktop static router to canonical public/assets files and verified encoded traversal does not expose config source.
- Isolated web-preview data root, added nonce CSP/security headers, enforced Cloud HTTPS/TLS verification, and ignored local release keys/test harnesses.
- Security contracts, Cloud/UI/model/system contracts, 61 PHP lints, JavaScript syntax and full platform suite pass.


## 2026-11-25 — DC38 browser interaction acceptance
- Verified real browser keyboard Enter in DCP Terminal (`pwd`, `ls .`) and confirmed output/input behavior.
- Verified Devices one-time pairing-code clipboard UI path using a mocked API and page-context clipboard test double; no real pairing code or host clipboard content was accessed.
- Q010 remains active only for authenticated Desktop Commander/DeepSeek Harness lifecycle adapters.


## 2026-11-25 — DC39 resilient macOS source fetch
- Added bounded retry helper for pinned GitHub source clones and integrated it into Intel macOS OpenSSL/llama.cpp builds.
- Added a local regression test that simulates two transient DNS failures and succeeds on the third clone attempt. Bash syntax, workflow YAML and diff checks pass.
- Q005 remains partial; this local change is not committed/pushed and cannot yet be validated on a GitHub runner.


## 2026-11-25 — DC40 full updater regression pass
- Re-ran local platform suite, macOS updater (53 assertions), Linux updater health/transaction, Delta package builder, Linux archive extraction, signed release assembly with an ephemeral test key, Windows updater, 61 PHP lints, JS syntax and diff check. All passed.
- These are local tests only; no real Alpha9→Alpha10 target upgrade, production signing, GitHub CI rerun or release publication occurred.


## 2026-11-25 — DC41 Alpha 10 stable promotion plan
- Confirmed Alpha 10 release is published with 19 assets but remains prerelease.
- Pushed `dd0595f` with offline-safe Linux updater checks and bounded pinned-source fetch retries. Local cross-platform regression suite passed.
- Documented correct stable promotion path: merge reviewed code to main, create a semver `v1.0.0` stable release, verify all native signed packages, then remove old release records/tags. Historical Actions runs are separate from release records.


## 2026-11-25 — DC42 stable tag and release workflow
- Fast-forwarded `main` to `cb3087e` after green feature-branch CI and pushed annotated `v1.0.0` tag at exactly the same commit.
- Started stable signed cross-platform release run `45538884432`. Linux and Windows package jobs passed; macOS Intel/Apple Silicon and managed PHP jobs are queued.
- Added stable Alpha10→1.0.0 Delta regression coverage and committed cleanup automation to remove prior Alpha/Beta releases/tags and completed failed Actions runs only after stable release assets/signatures are verified.


## 2026-11-25 — DC43: Stage 6 Task Inbox Queue MVP first slice
- Clarified canonical roadmap status: Stage 6 Device Collaboration and Agents remains open; Q006 Local AI is done, while Q005 stable-release acceptance remains open.
- Implemented a persistent SQLite Task Inbox with enqueue/list/reprioritize/cancel/mark-sent service transitions and CSRF-protected API routes.
- Added Chat UI queue controls that remain usable while a provider request is busy; queued messages are review/manual-send only and never auto-execute.
- Added service and UI contract tests. Full platform suite, PHP lint, JS syntax and git diff check passed.
- Changes remain local and uncommitted on feature/local-ai-runtime-discovery; no push, merge, release or deployment.


## 2026-11-25 — DC44: Stage 6 read-only per-device capability matrix
- Added a fail-closed capability matrix derived from the verified DCP Local Node status and explicit paired-device scopes.
- Local workspace writes are marked as requiring explicit task approval; current local terminal is identified as safe/allow-listed. Remote workspace, terminal, task dispatch and browser control remain not implemented.
- Extended Cloud status and Devices UI to render local and paired-device capability matrices.
- Re-ran full platform/security tests, PHP lint, JS syntax, isolated Task Inbox HTTP smoke, isolated Chrome DOM smoke and git diff check; all passed.
- Changes remain local and uncommitted; no remote execution, Cloud deployment, push or release.


## 2026-11-25 — DC45: Stage 6 workspaces and signed task-envelope foundation
- Added isolated per-device workspace allocation with stable identity manifests, private permissions and traversal/symlink protection.
- Added Ed25519 device signing keys encrypted at rest, explicit approved issuer-key registry, canonical signed envelopes, target binding, expiry and replay ledger. Verification does not execute tasks.
- Reconciled existing legacy bearer-token Device Agent polling: it is already implemented behind explicit task approval and allow-listed workspace actions; new signed envelopes are not integrated with it yet. Capability API/UI now distinguishes legacy task agents from Cloud signaling pairings.
- Full platform/security suite, PHP lint, JS syntax, isolated HTTP, Chrome DOM and diff checks passed. Changes remain local/uncommitted.


## 2026-11-25 — DC46: Signed Device Agent transport end-to-end
- Bound Device Agent to an automatically provisioned per-device workspace and hardened worker path checks for backslash/drive-prefix traversal.
- Integrated Ed25519 key registration/pinning, task-bound signed envelopes, action/target/expiry/replay verification, signed results and server-side atomic envelope acknowledgement.
- Preserved legacy bearer-token compatibility only for devices without a registered signing key; signed devices cannot downgrade, and revoked keys block dispatch.
- End-to-end API test covers explicit approval, signed execution/result, forged signature rejection, downgrade rejection and legacy compatibility. Full platform suite, 55 PHP lint files, JS syntax, HTTP/Chrome smoke and diff check passed.
- Stage 6 remains open pending real Windows/Ubuntu/macOS agent acceptance, updater preservation and task lease/revocation workflows.


## 2026-11-25 — DC47: Stable release and cleanup verified
- Published `v1.0.0` as stable/latest with 19 assets; release workflow `45538884432` succeeded.
- Fixed the legacy cleanup Actions pagination mapper and added manual/push repair triggers plus a stable-publication cutoff; cleanup run `45544044653` succeeded. Legacy Alpha/Beta release records/tags are gone; no completed failures were found among the latest 100 Actions runs.
- Q005 remains open only for actual Windows/Ubuntu/macOS installed-client update, rollback and data-preservation acceptance.
- Stage 6 continues on `feature/local-ai-runtime-discovery`: Q012 Task Inbox and Q014 signed Device Agent protocol are implemented/tested locally; real OS acceptance and safe checkpoint integration remain.


## 2026-11-25 — DC48: Background Chat Jobs and Safe Checkpoint
- Added independent PHP CLI Chat Worker, durable job state, status/recovery APIs, and provider-boundary checkpoint request.
- Task Inbox accepts messages while a provider request is running; queued messages remain manual-review only and are never auto-executed.
- Full platform suite, isolated worker E2E, authenticated HTTP checkpoint E2E and Chrome DOM smoke passed on macOS.
- Added Ubuntu/Windows/macOS GitHub Actions matrix; cross-platform result pending.
- DP_Anything explicitly deferred; continue DCP only.

- Windows CI exposed that the PowerShell launcher could block the HTTP request. Changed it to `Start-Process` without `-PassThru` and an immediate `STARTED` marker; the worker now persists its own PID at atomic claim time. Local worker and delayed-provider HTTP E2E pass; cross-platform CI retry pending.

- Cross-platform worker CI run `45550728368` passed both real background-worker E2E and authenticated delayed-provider HTTP/checkpoint E2E on Ubuntu 24.04, Windows Server 2025 and macOS 15. Windows uses a detached `cmd.exe start /B` launcher; its HTTP response now returns before the provider call completes.


## 2026-11-25 — DC49: Durable checkpoint context snapshots
- Added atomic SQLite checkpoint snapshots containing provider result, recent conversation, pending Task Inbox messages and SHA-256 context hash.
- Added checkpoint retrieval API and Chat UI summary. Queued instructions remain manual-review only; no auto-merge or execution.
- Unit and authenticated HTTP E2E verify snapshot contents and hash. New cross-platform CI run pending.

- DC49 cross-platform GitHub Actions run `45551354459` passed on Ubuntu 24.04, Windows Server 2025 and macOS 15, including real worker launch and authenticated checkpoint snapshot/Hash E2E.

- DC50: checkpoint schema v2 now captures versioned execution plans, dependency step graphs, opaque tool-result references and pending human approvals; dependency gates and cycle validation added. Local full suite and worker/HTTP E2E pass; cross-platform CI pending.

- DC51: enforce private NTFS ACLs for per-device workspaces (current user, SYSTEM, Administrators only), preserve inherited private file ACLs without recursive symlink traversal, and make workspace tests OS-aware. Local full suite passes; GitHub full-platform CI 45587700487 passes Ubuntu and Windows; macOS queued.

- DC52: extract the seven-tool MCP catalog/dispatch into shared `DcpToolRegistry`; add explicit capability/approval policies, JSON-schema/path/content validation, and MCP stdio E2E. Fix the platform test harness so it reaches Linux updater transaction/rollback/crash-recovery/health contracts. Local full suite + worker + HTTP E2E pass; cross-platform CI run 45589253032 pending.
