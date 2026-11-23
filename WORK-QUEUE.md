# DCP Work Queue

This file is the durable resume point. Update status, evidence and next action after every completed task. Do not restart completed work after a context or connection interruption.

## Active constraints
- Current machines in scope: Mac + Windows + Ubuntu 24.04. Preserve existing Linux/Kali auto-update compatibility; Ubuntu is now an explicit clean-install target.
- Work from the Git repository at /Volumes/Data/ChatGPT/DCP. Windows replacement is authorized, but preserve the old installation as a timestamped rollback backup and preserve its SQLite/key data until the new install is verified.
- No public tunnel exposure until private paths and terminal access are protected.
- Never place passwords, API keys, tokens, .env, private keys, SQLite data or encryption keys in Git or project Markdown.
- Detect and reuse existing compatible services first. Do not replace, stop, reconfigure or hijack existing services or processes.

## Queue
| ID | Task | Status | Evidence / exit criterion |
|---|---|---|---|
| Q000 | Read Speed Engineer and map current app | done | User-supplied SKILL.md read; initial stack/code scan captured |
| Q001 | Restore source-of-truth repository to requested DCP path | done | Git worktree confirmed at /Volumes/Data/ChatGPT/DCP; installed alpha.8 copy preserved separately; active branch fix/alpha7-localai-cloud-pairing |
| Q002 | Install requested planning/review/security skills | done | 10 skill folders installed locally; upstream revisions recorded in platform/docs/ENGINEERING-SKILLS.md |
| Q003 | Write architecture, product, roadmap, security and performance docs | done | AGENTS.md, PRODUCT-VISION.md, ROADMAP.md, WORK-QUEUE.md and platform/docs specs exist |
| Q004 | Harden web entry and create dcp-shortcut | blocked | Hostinger document root is /home/u163155085/domains/amradel.com/public_html/dcp and serves dcp.amradel.com (not a separate deployment); live /api/v1/health returns 200 and .env/schema direct access is denied. This is the Cloud API root, not proof that the separate platform/index.php web-preview entry is safe. Keep preview unexposed and do not create dcp-shortcut until Access/Basic Auth, private-path denial, rate limits and route isolation are verified |
| Q005 | Managed runtimes, cross-platform packages and GitHub installers | partial | Windows managed PHP 8.5.13 and clean-package CI pass; Mac system-first installer has 6 passing tests, and native Intel + Apple Silicon PHP 8.4.28 builds pass extensions/TLS/dylib/license checks. A linker warning showed prebuilt ICU requires macOS 12.0, so both Mac targets, wrapper and installer are now explicitly 12.0+; fresh native CI is pending. The first clean Mac package smoke passed before the new DCP-UserData layout; the latest package smoke with the new layout is pending. Ubuntu 24.04 Linux package CI passes and the GitHub online installer is implemented. All three installers now preserve DCP-UserData and migrate legacy platform/data + platform/storage into the OS-specific data root (Windows, macOS, Linux); Windows verifies SQLite/master.key SHA-256 and macOS/Linux byte-compare the migrated files before launch. Linux build/release workflows and clean install tests still need CI. All installers and Mac/Windows auto-updaters now require one complete release containing Windows package+update manifest, macOS package, Linux package+SHA-256; partial releases are ignored to prevent mixed versions. Remaining: Mac Intel/ARM64 artifacts + clean package, Linux x64 artifact/release, Windows/Ubuntu online installer clean installs, central version.json/tag injection with UI/bundle consistency checks, and final app-launch verification. |
| Q006 | Fix local chat and llama.cpp port/install lifecycle | pending | Next after Q005 is closed: inspect LocalAIService.php and engine manifests/installers; capture chat request/response/logs; fix hardware detection latency, portable llama.cpp dependencies, CPU-safe Intel build selection, managed process ownership and dynamic port allocation. Preserve existing llama-server/Ollama processes; do not assume port 8080 is free |
| Q007 | Cloud API contract and Hostinger deployment | pending | User authorized Cloud deployment; first back up files + database, review and validate schema.migrate.sql against live schema, apply migration once, deploy matching index.php/site.php to the mapped docroot, then test health/register/login/device routes and rollback readiness |
| Q008 | Header auth + Devices pairing management | pending | Mac and Windows pair, scopes, pause/resume/revoke work |
| Q009 | Mail page and AI mail capability | pending | Titan SMTP/IMAP tests, multi-account and task workflow |
| Q010 | Providers, agents, terminal, notices and clipboard | pending | CRUD/lifecycle/permission and keyboard tests pass |
| Q011 | Custom GGUF + Ollama conversion/model manager | pending | import, run, convert, switch and delete tests pass |
| Q012 | Shared chat, remote agent, browser and memory | pending | authenticated end-to-end tests and capability policy |
| Q013 | Speed/security/code review and release | pending | all release gates green |

## Latest validation checkpoint — 2026-11-23
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- PHP lint across `platform/**/*.php`: passed; final lint output showed no syntax errors.
- `php platform/scripts/verify-runtime.php`: PHP 8.5.9 and all required extensions available.
- `node --check platform/public/assets/app.js`: passed.
- `bash -n` for the macOS runtime installer and its new test script: passed.
- `bash platform/tests/runtime-macos-installer.sh`: 6/6 passed (system-first, managed fallback, checksum mismatch, missing checksum, unsupported architecture).
- Objective-C macOS wrapper compiled for x86_64 (10.15 target) and arm64 (11.0 target): passed.
- YAML syntax parsed for the macOS PHP build workflow, macOS release workflow, clean macOS package CI workflow, Windows package CI workflow and PHP craft recipe; JSON manifest parsed. `actionlint` is not installed on this Mac.
- PowerShell parser: `install-runtime-windows.ps1`, `build-windows.ps1`, and `update-windows.ps1` passed.
- Windows package-copy smoke: passed; required manifests/scripts present and developer build/runtime/source/binary trees excluded.
- GitHub Actions YAML parse for release-macos.yml, release-windows.yml, test-cloud.yml: passed.
- `git diff --check`: passed.
- `git check-ignore`: confirmed llama.cpp build cache, source checkout and local binary are ignored.
- No commit, push, release, Hostinger DB change, Apache change, tunnel exposure or alpha.8 modification made.
- Working tree remains dirty by design; inspect diff before any commit.

## Resume protocol
1. Read this file first.
2. Read AGENTS.md and the relevant plan/spec.
3. Check git status, last commit, active process/tool calls and WORK-QUEUE.md.
4. Continue the first non-blocked task; do not redo done tasks.
5. Update this file with exact file paths, commands, test results and next step.
6. Do not commit/push/release until all relevant release gates pass and the user has a checkpoint.


## DC03 continuity checkpoint — 2026-11-23
- Created `PROJECT-MEMORY/INDEX.md`, `CURRENT-STATE.json`, `DECISIONS.json`, `CHANGELOG.md`, immutable version checkpoint, Q005 task plan and DC03 handoff. Read `PROJECT-MEMORY/INDEX.md` first in every new session.
- Active task remains Q005; Q006 must not start until Q005 acceptance criteria pass.
- Remote Desktop Commander connection recovered after intermittent persisted-session/fetch failure; device Amrs-Mac-Pro is online (app 0.3.4).
- Host is Intel macOS 14.7.1, PHP 8.5.9 with required extensions. Disk has only 8.3 GiB free (99% used); do not compile or install large build dependencies locally.
- StaticPHP v2.8.5 is pinned to commit 4318ef8fa32a02460ec1554746674a7bc42b49fa for CI validation; PHP 8.4.28 source SHA-256 is pinned in platform/dcp-env/manifests/php-runtime.json. v3 is alpha; v2 docs list PHP 8.4 as stable. No runtime binary has been built or adopted yet.
- No application code changed during the initial DC03 memory initialization. Subsequent Q005 changes are recorded below. Existing dirty changes preserved; no commit/push/release/deployment.


## DC03 Q005 implementation checkpoint — 2026-11-23
- Added `platform/tests/runtime-macos-installer.sh`; six tests pass, including rejecting a tampered or checksum-less managed PHP executable.
- `platform/scripts/install-runtime-macos.sh` now checks compatible system PHP first, then verifies the managed executable SHA-256 and its private ini/config directory before running it. It refuses unknown CPU architectures and never modifies Homebrew, Apache or services.
- `platform/wrappers/macos/main.m` checks curl, intl, mbstring, fileinfo, openssl, pdo_sqlite and sodium; it verifies the managed PHP SHA-256 and applies PHPRC only to the managed runtime. x86_64 and arm64 wrapper compilation passed.
- Added pinned build recipe and manifest: StaticPHP v2.8.5 commit `4318ef8fa32a02460ec1554746674a7bc42b49fa`, PHP 8.4.28 official source SHA-256, extension set and macOS minimum targets.
- Added `.github/workflows/build-php-runtime-macos.yml` with native Intel/Apple Silicon runners, source checksum verification, extension/TLS/dylib/deployment-target/license checks and runtime artifacts. Added runtime artifact embedding and package verification to `.github/workflows/release-macos.yml`.
- Added `.github/workflows/test-windows-package.yml` for clean Windows package build and data/secret exclusion checks. It has not run yet.
- Current PHP build workflows are configured but unverified until pushed and run in GitHub Actions. Q005 remains partial; do not begin Q006.

- Added `.github/workflows/test-macos-package.yml`: non-publishing push/PR/manual CI that builds both managed PHP architectures, builds the universal app, packages it and checks runtime inclusion plus private-data/build-cache exclusions. YAML parsing passed; workflow has not run.


## First GitHub Actions feedback — 2026-11-23
- Commit `4752f9d` pushed to `fix/alpha7-localai-cloud-pairing`; remote branch matches local HEAD.
- Windows clean-package CI exposed CS0136 in `Program.cs`: nested WebView2 local `userData` shadowed the outer DCP data directory variable. Renamed the nested variable to `webViewData`.
- macOS ARM64 runtime CI reached StaticPHP download and failed because the unauthenticated GitHub API request for StaticPHP hosted releases returned HTTP 403. The download step now supplies the workflow's read-only `GITHUB_TOKEN`; rerun on the next push.
- Windows fix and macOS token fix are being committed as a focused Q005 follow-up.


## Second GitHub Actions feedback — 2026-11-23
- Commit `41cd3d3` fixed the Windows C# CS0136 error and authenticated StaticPHP GitHub API downloads with `GITHUB_TOKEN`.
- The clean Windows package workflow (`45271261380`) and existing Windows app build workflow (`45271261254`) then exposed a PowerShell regex error in `build-windows.ps1:84`: a single backslash was passed as the regex pattern. Corrected the expression to `-replace '\\','/'`; verified locally with PowerShell that `platform\foo\bar` normalizes to `platform/foo/bar`.
- macOS runtime workflow `45271261590` is still running; verify the authenticated source/dependency download and subsequent build/TLS steps.


## DC04 checkpoint — 2026-11-24
- SSH key-based access is now verified for Hostinger, Windows and Ubuntu. Detailed safe access routes and device inventory are in `PROJECT-MEMORY/ACCESS-INVENTORY.md`; private keys remain on the external drive.
- Hostinger read-only audit: `https://dcp.amradel.com/api/v1/health` returns HTTP 200 and database/schema OK. Database has six tables; `devices.user_id` is NOT NULL; pairing tables are absent. Live `index.php` does not include anonymous registration/pairing routes and differs from local `platform/cloud/index.php`. No migration, upload or permission change was made. Cloud Q007 remains pending.
- Windows read-only inventory: Windows 11 Pro build 26100, 4 vCPU, 7.98 GiB RAM. Existing installed DCP copy is older commit `5b91bcc`, PHP 8.5.13. Existing SQLite/master key remain inside the old app tree; preserve the installed copy until new package layout is validated.
- Ubuntu 24.04 was inventoried read-only (2 vCPU, 7.7 GiB RAM, 64 GB disk); Linux implementation remains deferred.
- GitHub Actions: Windows build `45273110655` passed; Cloud PHP `45272657130` passed. macOS runtime `45273110645` compiled both architectures and passed extensions/TLS/dylib/deployment-target checks, then failed only at StaticPHP license dumping.
- Inspected pinned StaticPHP v2.8.5 `LicenseDumper.php`: it checks `SOURCE_PATH/openssl/LICENSE.txt` before the `BUILD_ROOT_PATH/source-licenses/openssl/0.txt` fallback. Updated the local macOS workflow to place the exact matching OpenSSL license in both paths. Ruby YAML parse, PHP syntax checks and `git diff --check` passed.
- The Q005 workflow fix and memory updates are local/uncommitted. Do not stage unrelated dirty cloud/web-entry/LocalAI changes. Obtain explicit approval before focused commit/push; Q005 remains partial and Q006 must not start.
- Hostinger `.env` is filesystem mode 0644 but direct HTTP access returned 403; review tightening to 0600 only after approval. No live permission change was made.
