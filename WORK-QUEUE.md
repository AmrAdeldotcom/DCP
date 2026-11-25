# DCP Work Queue

This file is the durable resume point. Update status, evidence and next action after every completed task. Do not restart completed work after a context or connection interruption.

## Approved architecture direction — 2026-11-25
- DCP is a provider-neutral distributed AI/device orchestration platform. DCP Core is the product; MCP (local STDIO + remote HTTP), CLI, API and DCP Node/Agent Runtime are first-class interfaces/components over the same Core services.
- Desktop Commander remains an optional plugin, not a Core dependency. DSH Agent is supported through the provider-neutral Agent Runtime. Team devices, local AI, cloud APIs and tools cooperate through a policy-governed task coordinator.
- Architecture spec: `platform/docs/DCP-CORE-MCP-CLI-API-ARCHITECTURE.md`. Desktop Commander source-study log: `platform/docs/research/desktop-commander-reference-review.md`.
- Official open-source reference clone target: `/Volumes/Data/DiGiPL/References/DesktopCommanderMCP`; never include this reference checkout in DCP packages or push to its upstream.
- Source remains `/Volumes/Data/ChatGPT/DCP-Source`; installed release target is `/Volumes/Data/DiGiPL/DCP`. Previous incomplete install state was preserved at `/Volumes/Data/DiGiPL/DCP-Preinstall-State`.

## Active constraints
- Current machines in scope: Mac + Windows + Ubuntu 24.04. Preserve Linux/Kali package compatibility; Ubuntu is an explicit clean-install target.
- macOS is the primary product/source-of-truth install. Build and test new Mac bundles in a separate temporary directory; replace only the verified DCP.app bundle in the original project folder after visual and runtime acceptance.
- Windows and Ubuntu app binaries are disposable test installs. The user authorized replacing them from fresh Alpha 10 packages; back up any DCP-UserData/database/key files before replacement. Do not spend time preserving broken app binaries.
- After all three Alpha 10 installs work, publish a harmless platform-code probe update and verify each installed client downloads/applies it from GitHub.
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
| Q005 | DC0005 — first stable 1.0.0 release based on approved Alpha 10 codebase | done — stable v1.0.0 release, legacy cleanup and installed-client acceptance are treated as closed per user confirmation | Stable tag v1.0.0 pinned to release commit cb3087e27c344af774ecf60aca40c98904859a85; release run 45538884432 SUCCESS, 19 assets, stable/latest; legacy Alpha/Beta cleanup run 45544044653 SUCCESS. Q005 is closed; do not reopen it or block subsequent stages on it. |
| Q006 | Fix local chat and llama.cpp port/install lifecycle | done — managed low-memory start/chat/stop verified on Windows, Ubuntu and Intel macOS | Mac existing llama.cpp service on 19123 preserved. Windows interactive-user Qwen3 0.6B smoke returned DCP_QWEN06_WINDOWS_OK and exact owned-process stop succeeded. Ubuntu 24.04 smoke returned DCP_QWEN06_LINUX_OK and left its existing update watcher running. Intel macOS smoke returned DCP_QWEN06_MAC_OK on a separate ephemeral port and preserved the existing 19123 listener. Added 60-second API readiness for slower model loads plus failure cleanup; Windows process ownership checks listener/PID/executable; cached GGUF reuse; UI/API contract checks. Full platform suite, model-manager contract, Local AI UI contract and JS syntax all pass. Commits pushed on feature/local-ai-runtime-discovery: 6201aca, 0f75fb8, 2c39368; current readiness fix follows. No SQLite/master.key modified. |
| Q007 | Cloud API contract and Hostinger deployment | active — key rotation pending approval | Cloud API hardening and desktop-client mock E2E are on main. Added rate limits, transactional device revocation/pairing cleanup, revoked-endpoint checks, and token hash rotation support via APP_KEY_PREVIOUS with lazy re-hashing. Main commit 89b9446; local full platform suite and 19 cloud-contract assertions pass; GitHub Cloud CI 45500943554 PASS after fixing a literal .htaccess grep in the workflow. Hostinger .htaccess mode 600 prevented LiteSpeed rewrites; backed up index/.htaccess, changed mode to 644, and confirmed / and /api/v1/health return 200 while .htaccess/.env/schema/site.php return 403. A first deployment candidate correctly rejected the live APP_KEY because it is the example placeholder (46 chars); restored the previous index.php from backup immediately, and live health returned 200 again. Backed up .env, index, .htaccess and MySQL database to /home/u163155085/dcp-backups/20261124T222224Z-cloud-key-rotation (checksums pass). New key-rotation code supports old tokens without invalidating devices, but changing the production .env was blocked by the secret-file safety guard; do not bypass it. Next: user approval for a new random APP_KEY with the old placeholder retained temporarily as APP_KEY_PREVIOUS, then deploy index.php/token.php/.htaccess atomically and verify existing tokens. |
| Q008 | Header auth + Devices pairing management | active — presence pause/resume implemented; live paired-device acceptance pending Cloud deployment approval | Devices page now renders per-peer identity/platform, effective presence and scopes, with separate Pause presence, Resume presence and Revoke actions. Added idempotent presence_paused_at schema migration, authenticated/rate-limited Cloud endpoint, local CloudService/API route, and tests proving pause hides online/last-seen without changing signaling scopes or revoking the pair. Cloud contract, Cloud UI contract, CloudService mock and full platform tests pass. No production DB migration/upload performed; Q007 APP_KEY rotation still needs explicit approval before live Mac+Windows pairing acceptance. |
| Q009 | Mail page and AI mail capability | active — Mail page and protocol mocks green | Added a dedicated Mail page with SMTP/IMAP settings, connection tests and unread inbox; moved the mail panel out of Settings. MailService now preserves omitted settings and blank passwords during partial saves. Full local suite includes mock SMTP EHLO/AUTH LOGIN and message delivery, mock IMAP login/list/select/unseen/fetch parsing and invitation classification, encrypted/masked credentials, invalid-recipient rejection and 5 MB attachment cap. Mail UI contract (8 checks), cloud UI/API contracts and full platform suite pass. Commit 6f31659 pushed to main; GitHub Cloud CI 45502521736 PASS, Windows/Linux/macOS CI running. Added AI unread-mail summaries and email drafts using the selected configured provider; email excerpts are treated as untrusted context, drafts are editable, and sending requires explicit confirmation in both UI and API. Mail UI contract now verifies these paths; full suite includes a mock-provider AI call. Commit 1f4b58d on main; Cloud CI 45503283670 PASS. Remaining: real Titan endpoint test with user-provided credentials and multi-account management. |
| Q010 | Providers, agents, terminal, notices and clipboard | active — Windows interactive terminal, browser keyboard Enter and clipboard UI flow PASS; authenticated external-agent lifecycle still open | Windows interactive terminal commands pass under AMRWIN11\amradeldotcom; DCP Local Node status probes pass on Windows/macOS/Ubuntu; browser Terminal Enter flow and Devices pairing-code clipboard UI flow pass. Clipboard test used a page-context mock and did not touch the host clipboard or pairing backend. Desktop Commander/DeepSeek Harness remain detection-only. See PROJECT-MEMORY/tasks/Q010-terminal-agent-validation.md. |
| Q011 | Custom GGUF + Ollama conversion/model manager | active — real service and DCP app API/UI lifecycle PASS; direct llama.cpp custom-GGUF launch remains | Added managed GGUF import with header/size/hash/atomic-copy/0600 checks, Ollama Modelfile creation, background conversion tracking, ready-model chat selection and linked delete. Real Qwen3 0.6B conversion and actual DCP app API flow passed: DCP_Q011_REAL_APP_UI_API_FLOW_PASS; Ollama provider returned DCP_Q011_OLLAMA_ADAPTER_OK. Fixed conversion-status manifest visibility race with a 120-second grace window and disabled Qwen3 hidden thinking for chat content. Temporary model/file removed; three existing DiGEMA models and Ollama service preserved. Full platform, model-manager and system-tools contracts pass. See PROJECT-MEMORY/tasks/Q011-custom-gguf-real-conversion.md. |
| Q012 | Shared chat, remote agent, browser and memory | active — Tasks 1–7 implemented locally; Tasks 6 and 7 are closed | Task 5 explicit plan-bound tool execution, grants, one-time leases and private artifacts are implemented. Task 6 review/approval API/UI is complete. Task 7 integrity-checked conversation resume and dependency-aware manual step execution is complete; no automatic multi-step loop. Current follow-on work is DC67/Q015 Resource Mesh and Cloud, not Q005. |
| Q013 | Speed/security/code review and release | active — local security hardening and contracts PASS; release gate remains external | Fixed desktop router traversal exposure, isolated web-preview data root, added nonce CSP/security headers, forced Cloud HTTPS/certificate validation, and ignored local release keys/temp harnesses. Real router traversal, WebPreview isolation, page/pair CSP headers, 61 PHP lints, JS syntax, security/cloud/model/system contracts and full platform suite pass. No tracked secrets or private keys; signing key remains external mode 0600. Open: style-src unsafe-inline cleanup, full dependency audit, external agent lifecycle, Cloud APP_KEY approval, and retry Intel macOS source fetch after GitHub DNS failure. See PROJECT-MEMORY/tasks/Q013-security-review.md. |
| Q015 | DCP Resource Mesh & Universal AI Gateway, including local/cloud MCP and signed device execution | active — MCP closed in source; owner-local invocation and signed remote terminal flow implemented locally | Local MCP STDIO + loopback HTTP share 35 provider-neutral tools; Cloud MCP has five scoped read-only tools and token-scope migration. Full suite, PHP lint, JS syntax, Cloud MCP service tests, owner-local Ollama/OpenAI-compatible invocation tests and signed remote-terminal contracts pass. Commits 8967bc0, c2998ec, c9620e5 are local and branch is ahead 3. Remaining: Cloud owner-node resource invocation/proxy, team/device grant parity, Hermes, real two-device Windows/Ubuntu acceptance, PTY/ConPTY and daemon recovery. No push or Cloud production deploy. See DC68 handoff. |

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


## DC11 continuity and validation checkpoint — 2026-11-24
- Re-read PROJECT-MEMORY/INDEX.md, CURRENT-STATE.json, WORK-QUEUE.md, AGENTS.md, ACCESS-INVENTORY.md, DECISIONS.json and the Q005 task plan before continuing.
- Reconciled stale memory: actual Git HEAD is fc4d5d7 on fix/alpha7-localai-cloud-pairing, matching origin/fix/alpha7-localai-cloud-pairing. CURRENT-STATE and access notes previously contained stale commit/connection details; CURRENT-STATE is refreshed. Revalidate SSH access before any future use.
- platform/version.json exists locally with 1.0.0-alpha.9, release tag v1.0.0-alpha.9, channel alpha, build 9. It is untracked and not committed.
- Version synchronization code is present locally in platform/config/app.php, PageController.php, build-windows.ps1, build-linux.sh, Windows project metadata and release workflows. Windows/Mac/Linux builders derive package metadata from manifest or pushed tag; Mac release sets bundle version/build and checks staged package against tag.
- DC11 validation: php platform/tests/run.php — ALL PLATFORM TESTS PASSED, including version manifest/tag/build tests; PHP lint across platform passed; node --check app.js passed; bash -n for Linux builder, online installer and Mac runtime installer/tests passed; PowerShell parser for build-windows.ps1 passed; version JSON parsed; git diff --check passed.
- Latest known CI batch: Windows x64 build/package passed; Ubuntu 24.04 x64 Linux build/package passed; Mac Intel and Apple Silicon PHP 8.4.28 native builds passed; Mac clean portable package smoke passed with DCP-UserData layout and macOS 12.0 minimum; Cloud PHP lint passed. These are CI validations, not real target-device installs.
- Working tree remains dirty and includes independent Q006 LocalAI, Q007 cloud/API and version synchronization work. No wholesale staging, commit, push, release, Hostinger migration or target installation performed in DC11.
- Q005 remains partial until version-sync edge cases and real clean-install/launch/data-preservation tests pass on target devices.
- Next: review version-sync edge cases and CI assertions; perform authorized Windows replacement with timestamped rollback and DB/key hash verification; install Ubuntu from the same complete GitHub release; test Mac clean package on supported hardware. Then close Q005 and advance Q006/Q007.


## DC12 continuation checkpoint — 2026-11-24
- Remote Desktop Commander recovered; device Amrs-Mac-Pro online. A previously pending inspection process timed out, a pending file read completed, and a trivial echo completed. Do not retry the timed-out command; subsequent commands are responding.
- Re-ran git status; working tree remains dirty across Q005/version, Q006 LocalAI, Q007 cloud, workflows, docs and memory. git diff --check passed.
- Re-ran php platform/tests/run.php: ALL PLATFORM TESTS PASSED. DC12 render tests cover Dashboard and Pairing pages, unified version metadata, base path and unresolved placeholders. No warnings in the latest run.
- Q007 production deployment completed after verified backup at /home/u163155085/dcp-backups/20261123T233943Z (database dump, cloud file archive, SHA256SUMS). Live .env mode is 0600. Matching local platform/cloud/index.php, site.php, .htaccess deployed atomically; schema.migrate.sql applied and verified. Live health returned healthy. Temporary account + two anonymous devices completed registration/login, heartbeat, one-time pairing code, approval/scopes, pair list, connection request, signaling send/receive and revocation; cleanup removed all test records. This validates API E2E, not desktop UI or full security review.
- DC12 Q006 local edits: added managed llama process identity verification before status/stop; corrected Linux installer path and added Ubuntu x64 llama.cpp SHA-256-verified release download; added Ollama healthy-service reuse; aligned Windows runtime manifest path. php -l LocalAIService.php and bash -n passed at the time of edits. Full review/runtime tests remain pending. Do not claim LocalAI installed or verified on any OS.
- DC12 version assertions added to Windows/Mac/Linux build and release workflows; Dashboard/Pairing render tests added to platform/tests/run.php. These are local only; CI assertions are not GitHub-validated until pushed.
- No commit, push, release, Windows replacement, Ubuntu install or Mac engine install performed.
- Next: inspect/fix LocalAI cross-platform edge cases; run full PHP lint, JS/shell/PowerShell/YAML checks; audit production cloud private-path/auth/rate-limit boundaries; then create isolated commits and run CI. Only publish a complete release after all five synchronized assets are built and green. Perform target installs with timestamped rollback and data/key verification.

- DC12 follow-up web-entry hardening: standalone site.php was still directly reachable despite .htaccess rules. Inlined the public landing page into platform/cloud/index.php, removed platform/cloud/site.php, updated Cloud CI to assert the standalone file is absent, deployed the new index atomically, and removed site.php from the live docroot. Created follow-up backup /home/u163155085/dcp-backups/20261124T232000Z before replacement. Verified /site.php=404, /DCPLOYMENT.md=404, /.env=403, /.env.example=403, /=200 and /api/v1/health=200. This closes the specific direct-site.php exposure; full API security audit remains open.

- DC12 LocalAI cross-platform correction: confirmed the current Intel Mac cannot execute the AVX2 llama-server binary (exit 132), while the SSE4.2 build runs. Added CPU-feature-aware runtime selection and testable candidate selection for Linux x64, Intel Mac with/without AVX2 and Apple Silicon. Also fixed Linux managed engine lookup (it previously searched macOS folders) and the undefined pidRunning call in startLlama.
- Verified current Mac LocalAI status resolves to the SSE4.2 binary; the SSE4.2 binary --version passed and the macOS installer correctly reused the existing compatible runtime without reinstalling.
- Verified official Ubuntu x64 llama.cpp b12655 archive: SHA-256 matched, ldd showed no missing dependencies, --version passed on Ubuntu 24.04. Ran the actual Linux installer from a temporary repo-shaped tree on Ubuntu; it downloaded with wget, verified checksum, installed into the expected linux-x64 folder, ran --version and cleaned the temporary tree. No engine was installed into the real DCP app.
- Ubuntu has wget but no curl. Updated Linux online installer to use curl or wget for GitHub API/assets and install curl with Ubuntu prerequisites; llama.cpp installer now supports wget; Ollama installer reuses a healthy service and installs curl through apt only when needed. Tested online installer on Ubuntu: it fetched release metadata with wget and safely stopped because GitHub currently has no complete five-asset synchronized DCP release.
- Attempted Windows llama.cpp binary smoke; GitHub download from the Windows host stalled at 0 bytes and was stopped. No Windows files or app installation were changed. Windows engine binary remains unverified.
- Cloud webroot audit showed Hostinger does not reliably enforce .htaccess rules for arbitrary static files. Removed all non-runtime extras from the public root (legacy ZIP, default.php, README, SQL schema, cloud-platform-upload folder) into backup /home/u163155085/dcp-backups/20261124T232000Z/public-root-extras. Root now contains only .env (then moved out), .htaccess and index.php.
- Moved production .env outside webroot to /home/u163155085/dcp-private/.env (directory 0700, file 0600), backed up the old copy, updated index.php to prefer this private path and documented DCP_CLOUD_ENV override. Removed webroot .env. Verified root=200, API health=200, /.env=403, /site.php=404, and legacy docs/schema/archive paths=404.

- DC12 commit/push sequence on branch fix/alpha7-localai-cloud-pairing: e815242 (version/runtime packaging), 4348aa8 (cloud API source), 7d9fc56 (LocalAI lifecycle), 58f11d2 (bundle generic Intel SSE4.2 llama.cpp runtime). All four commits pushed; source tree changes committed, memory/queue notes remain local.
- GitHub Actions for 58f11d2: Test DCP Cloud PHP succeeded (45295071145); Test DCP Windows build succeeded (45295071137); Test clean DCP Windows package succeeded (45295071126). Build DCP Linux WebView (45295071054) and Test clean DCP macOS package (45295071104) were still in progress at checkpoint. Mac workflow now builds pinned llama.cpp b12655 generic x86_64 SSE4.2 runtime and embeds it plus LICENSE in package/release, so non-AVX2 Intel Macs do not depend on a missing local source tree.
- Windows llama.cpp b12655 archive SHA-256 matched and binary --version passed on the Windows host. Direct Windows GitHub download stalled, so archive was downloaded via Ubuntu wget then copied to Windows; temporary files were cleaned.

- DC12 commit 8c3c4a5 adds a CI smoke test for the official Apple Silicon llama.cpp archive on arm64 macOS runners (download + SHA-256 + architecture + --version). Branch head is now 8c3c4a5.
- Current GitHub status at 00:15 UTC: Cloud PHP passed on 58f11d2 and 8c3c4a5; Windows build and clean Windows package passed on 58f11d2; Windows build on 8c3c4a5 in progress; Linux WebView (45295071054) and macOS package with SSE4.2 build (45295071104) in progress; latest macOS package run (45295429321) queued.
- No complete five-asset release exists yet; no tag, full desktop install or release deployment has been made.

- DC12 Q007 security follow-up: API request body is now capped at 64 KiB before JSON decode; a live oversized POST to /api/v1/login returned 413 request_body_too_large and /api/v1/health remained 200. Added opportunistic expiry cleanup for signaling, connection, pairing, guest invite, API token and rate-limit rows, plus IP-hashed limits on device linking/unlinking/registration/revocation, heartbeat, pairing request listing, pair revocation, connection requests and signaling send/poll. Added matching Cloud CI source assertions and README updates. Production index.php was backed up and atomically redeployed; commit e4301f7 pushed.

- DC12 commit c96a1c5 ensures CMake is installed in both macOS package-test and release workflows before building the generic Intel SSE4.2 runtime. Cloud CI passed on c96a1c5; Windows build is in progress; macOS package is queued. Linux WebView remains in progress on e4301f7.

- DC12 commit 0eaf0a6 adds an automated process-ownership regression test. It launches a harmless /bin/sleep process, writes a stale DCP-managed state pointing at it, calls LocalAI stop, and verifies the unrelated process remains alive. Full platform tests passed. Cloud CI passed on 0eaf0a6; Linux WebView and Windows build are still in progress; macOS package remains queued.

- DC12 GitHub CI update: branch head 0eaf0a6 now has Cloud PHP success (45296169789), Windows build success (45296169709) and Linux WebView success (45296169711). macOS clean package run for c96a1c5 remains in progress (45296024288).
- Windows install preflight: Windows 11 Pro x64, Xeon W3680, 8 GB RAM, WebView2 157.0.4341.60, 218.8 GB free; no DCP app or DCP-UserData found. Ubuntu preflight: Ubuntu 24.04 x64, 7.7 GiB RAM, 49 GB free, GNOME graphical target running, wget present, curl and PHP CLI absent; installer now handles wget and installs curl prerequisites.

- Ubuntu preflight follow-up: confirmed libgtk-3-0t64 3.24.41 and libwebkit2gtk-4.1-0 2.52.6 are installed, alongside GNOME graphical.target. wget is present; curl and PHP CLI are absent, and the online installer now handles this.


## DC12 resume checkpoint — 2026-11-24 02:30 Cairo
- Reconciled the true branch head from Git: 8705c38 on fix/alpha7-localai-cloud-pairing; local source changes are committed and pushed. Only PROJECT-MEMORY/*, WORK-QUEUE.md, and ROADMAP.md notes remain dirty. Mac Desktop Commander is online and responding.
- Current release state: latest public tag is v1.0.0-alpha.8, but it contains only DCP-Windows-x64.zip, DCP-Windows-update.json, and DCP-macOS-universal.zip. The required Linux tarball and SHA-256 are absent. platform/version.json is alpha.9. Do not install from alpha.8 or publish a partial alpha.9.
- Confirmed GitHub run statuses: Linux WebView and Windows build succeeded on ba5409e; earlier Cloud PHP, Windows build/package and Linux WebView passed. Latest 8705c38 workflows (Cloud PHP, Linux WebView, Windows build/package, macOS clean package) were running at checkpoint.
- macOS clean package failures were diagnosed from GitHub job logs: Apple Silicon smoke failed because unauthenticated api.github.com returned HTTP 403. Added GITHUB_TOKEN and Authorization header to the CI smoke step. Intel generic SSE4.2 build failed with exit 2; CMake output was hidden in DCP-UserData logs. Added ERR trap to print configure/build logs on failure. Commit 8705c38 contains both fixes; new macOS run is pending.
- Later commits beyond initial DC12 list: 8c3c4a5 Apple Silicon engine smoke, c96a1c5 ensure CMake available, 0eaf0a6 process-ownership regression test, ba5409e reuse external llama.cpp before requiring local binary, 8705c38 macOS CI diagnostics/token auth.
- Verified release inventory: alpha.8 and alpha.7 are incomplete. No complete five-asset release and no alpha.9 tag/release exists.
- No DCP application was installed or replaced on macOS, Windows or Ubuntu. Only llama-server --version smoke tests ran on those hosts; no model inference or desktop app UI workflow is verified. Do not claim the app is finished.
- Next: inspect the 8705c38 macOS job after completion. If Intel build still fails, use surfaced CMake logs to fix the exact error; do not guess. If Apple Silicon smoke passes, continue package inspection. Then verify all five artifacts exist, publish one prerelease tag only after CI green, install on Ubuntu and Windows with rollback/data checks, and test the Mac package locally. Keep Q008-Q012 pending until acceptance criteria are tested.


## DC04 resume checkpoint — 2026-11-24
- Started this conversation as a continuation of DC03; re-read PROJECT-MEMORY/INDEX.md, CURRENT-STATE.json, WORK-QUEUE.md, AGENTS.md, the Q005 task plan and the latest DC12 resume checkpoint. Recovered the live Mac Desktop Commander connection (Amrs-Mac-Pro, macOS 14.7.1 Intel).
- Reconciled the immediate task ordering: Q005 remains the active release gate; Q006 LocalAI work exists and is partial, but must not be treated as closed or advanced past the Q005 gate. Q007 Cloud API work is deployed and hardened but its desktop integration/security audit remain open.
- Git source HEAD verified as 8705c38 on fix/alpha7-localai-cloud-pairing, matching origin. Application source is clean; only PROJECT-MEMORY/CHANGELOG.md, PROJECT-MEMORY/CURRENT-STATE.json, ROADMAP.md, WORK-QUEUE.md and two existing version checkpoint files are dirty/untracked. No source code staged or changed in this session.
- GitHub Actions for 8705c38: Cloud PHP (45297880102), clean Windows package (45297880346), Windows build (45297880381) and Linux WebView (45297880066) all succeeded. macOS clean package (45297880160) remains in progress; both native managed-PHP jobs (Intel x64 and Apple Silicon arm64) were still compiling. Do not infer package success until both runtime jobs, verification and package smoke complete.
- Re-ran `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED, including version rendering/base path, local AI platform/CPU selection and protection against stopping an unrelated process.
- Re-ran `bash platform/tests/runtime-macos-installer.sh`: ALL 6 TESTS PASSED.
- `php -l platform/app/Services/LocalAIService.php` passed; `node --check platform/public/assets/app.js`, Bash syntax checks, version JSON parse, YAML parse for all 9 GitHub workflows and `git diff --check` passed.
- Mac disk remains critically constrained: 8.1 GiB free, 99% used. Do not compile PHP/llama.cpp locally; use native GitHub Actions runners.
- No commit, push, release, tag, install, Windows replacement, Ubuntu install, Hostinger change or tunnel exposure performed in this session.
- Next action: inspect run 45297880160 after it completes. If failed, fetch the exact failed job logs and fix only the diagnosed cause. If successful, verify runtime artifacts and clean package contents. Preserve the complete five-asset release gate; alpha.8 is incomplete and alpha.9 must not be published until every release workflow and target install gate is green.


## DC04 follow-up — native ARM64 runtime passed
- GitHub run 45297880160 advanced: Apple Silicon managed PHP job 138122769825 completed successfully. The full matrix step sequence passed: compile PHP 8.4.28, verify required extensions and real HTTPS/TLS, verify architecture/dylib/minimum macOS 12.0, dump licenses, upload artifact. Artifact `dcp-php-runtime-macos-arm64` is present (23,324,090 bytes).
- Intel x64 job 138122769491 remains in `Compile managed PHP`; extension/TLS/dylib/license verification and artifact upload are pending. Clean package smoke is gated on both architectures.
- Extended local checks: PHP lint across all 41 PHP files passed; JS syntax, shell syntax, both runtime manifest JSON parses, all 9 GitHub workflow YAML parses and git diff check passed.
- Reviewed `test-macos-package.yml`: after both PHP runtime artifacts, it verifies archive checksums and embedded runtimes, builds/signs the universal app with version/build/minimum OS checks, smoke-tests official Apple Silicon llama.cpp with authenticated GitHub API, compiles Intel SSE4.2 llama.cpp with diagnostic log tails, and inspects a clean portable package for required runtimes/data boundaries/private-data/cache/source exclusions. This package job has not started yet.
- No source changes, commit, push, release or install performed.


## DC04 target-device preflight — 2026-11-24
- SSH read-only access revalidated to Ubuntu and Windows from Mac. Ubuntu 24.04 LTS x86_64 has 49 GiB free; Windows 11 Pro 26100 x64 has 218.8 GiB free.
- Confirmed Windows DCP installation is under `C:\Users\amradeldotcom\AppData\Local\DCP` (SSH admin identity is separate: dcpadmin). Existing `platform/data/dcp.sqlite` is present (102,400 bytes), `platform/storage/keys/master.key` is present (44 bytes), and old install has no DCP-UserData directory. No mutation made; installer must preserve/backup this exact user profile data.

- Windows install safety: online installer targets the current process user's `$env:LOCALAPPDATA\DCP`; SSH is `dcpadmin` while the existing app and SQLite/master key belong to `amradeldotcom`. Do not run installer in admin SSH context with defaults. Use the actual user's interactive context or add/test an explicit target profile option before any remote install.

- Intel runtime job log snapshot shows three 75-second network timeouts to ftp.gnu.org for optional source dependencies gettext, libunistring and libidn2, followed by continued dependency/build steps and compiler warnings; no fatal compiler/CMake error is present in the captured log. Treat these as warnings until the job completes; do not change recipe speculatively.

- Ubuntu GUI preflight: active `amr` seat0 sessions, `graphical.target`, GNOME Shell running in Wayland. This enables a real GUI launch smoke after installation; no system packages or app files changed.

- Ubuntu post-install launch preflight: passwordless sudo confirmed; GTK 3 and WebKitGTK 4.1 runtime packages installed; active Wayland/D-Bus/X11 session sockets present. We can test DCP under the actual `amr` desktop session after the complete release install.

- Additional Intel job log inspection: the last visible build output reaches 100% of the static libcurl target after compiling its final curlx sources; no fatal compiler/CMake error is logged. The outer StaticPHP compile step is still reported in progress, so continue waiting for the step result rather than treating the libcurl target as full PHP completion.

- Verified a temporary Task Scheduler probe using an interactive-token principal for `AMRWIN11\amradeldotcom` with highest available run level. It executed in the active user's session and reported `amrwin11\amradeldotcom` plus `C:\Users\amradeldotcom\AppData\Local`. The test task and probe file were removed. This provides a tested safe route to invoke the official Windows installer in the correct user context after the complete release exists.


## DC04 follow-up — both native managed PHP runtimes passed
- GitHub run 45297880160: Intel x64 job 138122769491 and Apple Silicon arm64 job 138122769825 both completed successfully.
- Both native builds compiled PHP 8.4.28, passed all required/additional extension verification, real HTTPS/TLS smoke, macOS system-dylib-only checks, minimum macOS 12.0 target, OpenSSL license recovery/dump, SHA-256 generation and artifact upload.
- Artifacts present: `dcp-php-runtime-macos-x64` 23,027,980 bytes and `dcp-php-runtime-macos-arm64` 23,324,090 bytes.
- The dependent `macos-package-smoke` job 138130032417 has started. Runtime artifact download, checksum verification, embedding and PHP verifier checks passed. It is currently installing PHP CLI/CMake; app build, llama.cpp smoke/build and final package inspection are pending.

- macOS package smoke job 138130032417 has completed runtime artifact embedding/checksum verification and PHP CLI/CMake setup. It is now building the universal macOS application; Apple Silicon llama.cpp smoke, Intel SSE4.2 build and final package inspection are still pending.

- macOS package smoke advanced: runtime embedding/checksum checks passed; PHP CLI/CMake setup passed; universal app build/signing/version/minimum-OS checks passed; official Apple Silicon llama.cpp download, SHA-256 verification and arm64 `llama-server --version` smoke passed. Intel SSE4.2 llama.cpp build is currently running; package assembly/private-data exclusion checks are queued after it.

- Ubuntu clean install destination `/home/amr/Applications/DCP` and DCP-UserData are both absent; target is clean and will not overwrite an existing app.


## DC04 CI failure diagnosis — Intel SSE4.2 engine portability
- macOS package smoke job 138130032417 failed while linking the Intel SSE4.2 `llama-server`; unresolved OpenSSL/X509 symbols came from `libcpp-httplib.a`. The PHP runtime, universal app build and Apple Silicon llama.cpp smoke had already passed.
- Root cause: the x86_64 SSE4.2 engine was being cross-built on the arm64 `macos-latest` runner while CMake discovered that runner's Homebrew OpenSSL 4.1.0. The current local x86_64 engine confirms the portability risk: `otool -L` shows dynamic dependencies on `/usr/local/opt/openssl@4/lib/libssl.4.dylib` and `libcrypto.4.dylib`; its LC_RPATH points back into the development build directory. The prior `DYLD_LIBRARY_PATH` smoke masked the latter.
- Prepared focused fix locally (not committed/pushed): new reusable `.github/workflows/build-llama-macos-sse42.yml` builds on native `macos-15-intel`, compiles pinned OpenSSL 3.5.4 statically for macOS 12.0, verifies the pinned source commit, passes static libraries to CMake, and uploads a dedicated x86_64 engine artifact with both llama.cpp and OpenSSL licenses.
- `build-macos-generic.sh` now requires the pinned static OpenSSL prefix/license, links OpenSSL statically, removes build-tree rpaths, adds `@loader_path` to the executable and dylibs, rejects non-portable/Homebrew/OpenSSL dylib dependencies, and smoke-tests `llama-server --version` without `DYLD_LIBRARY_PATH`.
- Updated both `test-macos-package.yml` and `release-macos.yml` to consume the Intel engine artifact from the reusable native-Intel workflow; package jobs remain on Apple Silicon to verify both PHP architectures and the ARM64 engine smoke. Package checks now require the OpenSSL license file.
- Local validation after edits: Bash syntax, all 10 workflow YAML files, CURRENT-STATE JSON and `git diff --check` passed. Full platform tests and previous runtime installer tests remain green.
- Historical note superseded: user explicitly approved full completion in DC04. Focused fixes were committed/pushed as `76deefc`, `1b07ec5`, `254fdce`, and `9b531a4`. No release tag has been created; publication remains gated on green macOS and Linux package tests.


## DC04 continuation — release approval and current CI (2026-11-24 03:06 Cairo)
- User explicitly authorized continuing through commit/push, release publication and remaining validation stages.
- Committed/pushed focused Intel portability fix as `76deefc` (four source/workflow files only): native `macos-15-intel` reusable build, pinned static OpenSSL 3.5.4, SSE4.2 llama.cpp b12655, relocatable `@loader_path`, no dynamic OpenSSL dependency, OpenSSL license included. No unrelated memory/docs or Q006/Q007 code was staged.
- Release metadata correction committed as `1b07ec5`: Alpha/Beta tags are explicitly prereleases and `make_latest: false` for Linux/macOS/Windows release workflows.
- Release concurrency correction committed as `254fdce`: concurrency groups include `github.workflow` and tag so Linux/macOS/Windows release workflows do not block or replace each other's pending runs.
- Current branch `fix/alpha7-localai-cloud-pairing` is pushed through `254fdce`. `platform/version.json` on the branch is `1.0.0-alpha.9`, build 9, tag `v1.0.0-alpha.9`.
- Current local validation on `254fdce`: `php platform/tests/run.php` ALL PLATFORM TESTS PASSED; LocalAIService PHP lint; JS syntax; Bash syntax; every workflow YAML; version JSON; `git diff --check` all passed.
- GitHub run `45302111689` Windows build succeeded; `45302111680` clean Windows package succeeded; `45302111830` Cloud PHP succeeded on `254fdce`.
- GitHub run `45301931389` clean macOS package is in progress on `76deefc` (two commits behind current branch; the intervening commits only adjust release workflow prerelease/concurrency metadata). Jobs: managed PHP Intel `138136309494` compiling; managed PHP ARM64 `138136309906` compiling; Intel SSE4.2 `138136309863`: pinned static OpenSSL build passed, llama.cpp build in progress.
- GitHub run `45301931567` Ubuntu 24.04 Linux WebView build is in progress at dependency installation (`138136308353`). Latest macOS package run on `254fdce` is queued (`45302111895`).
- Release state: latest public release remains incomplete `v1.0.0-alpha.8`; no `v1.0.0-alpha.9` tag/release exists. Required five assets remain mandatory: Windows ZIP, Windows update manifest, universal macOS ZIP, Linux tarball, Linux SHA-256 sidecar.
- No target application installation/replacement or release tag has been performed. Windows original profile SQLite/master.key and Ubuntu clean destination remain preserved. Do not mark Q005 complete until all package workflows, synchronized assets and actual target-device install/launch/data checks pass.
- Next: monitor runs `45301931389` and `45301931567`; fix only diagnosed failures; inspect the full clean macOS package smoke. Then tag/publish alpha.9 only when all platform builds are green, verify the five assets, install Mac to isolated smoke directory, install Ubuntu 24.04 to its clean destination, and replace Windows only through the verified interactive `amradeldotcom` session after timestamped backup and SQLite/key hash checks.

## DC04 Intel verification follow-up — 2026-11-24
- Native Intel build completed successfully in run `45301931389`: static OpenSSL 3.5.4, llama.cpp SSE4.2 build, server launch/version smoke, x86_64 file identification, and minOS 12.0 all passed.
- The post-build architecture verification step failed only because the `lipo` CLI arguments were reversed (`lipo -verify_arch x86_64 file`). Correct syntax is `lipo file -verify_arch x86_64`.
- Fixed in commit `9b531a4` and pushed to branch. The corrected invocation was validated locally against the existing x86_64 Mach-O binary; workflow YAML and `git diff --check` pass.
- New clean macOS package CI run `45303462099` is queued on `9b531a4`; Windows build and Cloud PHP workflows `45303462037` and `45303462060` are running. Previous run `45301931389` did pass arm64 PHP and completed the Intel engine build; its only failure was the lipo syntax above. Linux run `45301931567` remains in dependency installation.
- No alpha.9 tag or release created. Continue until clean macOS and Linux package builds pass, then publish and validate all platform installs.

## DC04 Q005 progress update — 2026-11-24 03:27 Cairo
- Verified remote branch HEAD is `9b531a4`, matching local HEAD; no application source changes are pending beyond the pushed commits. Only project-memory/queue/roadmap notes remain dirty and intentionally uncommitted.
- Latest-commit CI: Windows build `45303462037` — success; Cloud PHP `45303462060` — success.
- Linux WebView/package CI `45301931567` — success (run was triggered at `76deefc`, after Linux packaging/version-manifest changes; produced `DCP-Linux-x64.zip`, artifact SHA-256 `3f90be49363ed4a7e01f76d8ef04c6790039a9826cc86223c27ffa90814ccbd3`). Package assertions passed, including version/tag consistency, data/key exclusion, launcher scripts, and dependency resolution.
- Latest clean macOS package CI `45303462099` on `9b531a4` is still in progress. ARM64 managed PHP compilation is active; Intel managed PHP has completed source download and is queued for compilation; Intel static OpenSSL passed and the SSE4.2 llama.cpp build is active. The corrected `lipo` verification and final package smoke have not run yet.
- Older macOS attempts `45302016616` and `45302111895` failed only at the known pre-fix `lipo` argument order; do not retry them. Their PHP builds and Intel llama build completed; the new `9b531a4` run is the authoritative validation.
- No alpha.9 tag/release exists yet. Do not tag until `45303462099` passes Intel architecture verification, uploads all runtime artifacts, and the clean package smoke succeeds. Then trigger all three release workflows and verify five synchronized assets plus prerelease metadata before installs.
- Pre-install target preflight: Ubuntu 24.04 destination `/home/amr/Applications/DCP` is absent, filesystem has ~49 GB free and `wget` is available. Mac isolated smoke destination `$HOME/Applications/DCP-Alpha9-Smoke` is absent. Windows legacy app remains at `C:\Users\amradeldotcom\AppData\Local\DCP`; read-only `certutil` baseline SHA-256: `platform/data/dcp.sqlite` = `a2f6252cb0c6984bc2c7b1a30f65e49f0d9a69f2ac2950d613e7c54ecb93fa45`; `platform/storage/keys/master.key` = `b1d13ad07a1dffc595cb63ee49f6ab0bd748c5155a142d753dd273a4ac10e00d`. These are pre-install comparison values; do not overwrite or discard the legacy backup.


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

## DC05 checkpoint — Alpha 9 release and installations (2026-11-24)
- `v1.0.0-alpha.9` published; release ID 456688871; prerelease=true, draft=false; all six required assets verified.
- Native macOS release and clean package workflows succeeded on 8b5bf08 (runs 45387266140 and 45387266141); Linux release 45387266328 and Windows release 45387266284 succeeded; Windows clean package 45387266218 succeeded.
- Intel Mac official online installer passed in isolated `~/Applications/DCP-Alpha9-Smoke`: app 1.0.0-alpha.9 x86_64 launched; HTTP 200 and API status ok; managed PHP 8.4.28 hash/runtime verification passed; bundled llama-server version/help passed; 56 tracked app-source files compared with zero differences.
- Original Mac PHP/llama/Ollama processes and ports remained unchanged; new app bound to port 50641. Original repo hashes unchanged. Cleanup of isolated smoke app remains pending final checks.
- Ubuntu online installer running on `amr-ubuntu24`; apt-get update succeeded, apt prerequisites installation in progress. Target `/home/amr/Applications/DCP` was absent, 48 GiB free, sudo -n and graphical Wayland session verified.
- Windows VM unavailable over SSH on 192.168.8.62 and 192.168.8.54; ping to .62 lost 100%. No Windows mutation. Need restore VM network/SSH or Desktop Commander device before backup/install.
- Hourly DCP Alpha 9 Watchdog automation created (Africa/Cairo); cadence is hourly, not continuous.
- Next: complete Ubuntu validation; Windows backup/install/verification when reachable; remove only isolated Mac smoke folder; preserve original repo and services; then close Q005 only when target install gates are met. Apple Silicon physical smoke is unavailable; native CI passed.


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



## DC08 network continuity checkpoint — 2026-11-24T15:27:40+02:00
- Verified fixed guest network settings: Windows 192.168.8.99/24, gateway/DNS 192.168.8.1, MAC 00-1C-42-FA-1E-9B, IPv4 PrefixOrigin Manual; Ubuntu 192.168.8.88/24, gateway/DNS 192.168.8.1, interface enp0s5, MAC 00:1c:42:72:ea:56, NetworkManager method manual.
- Verified SSH host fingerprints locally and updated /Volumes/Data/ChatGPT/DCP-Lab-SSH/config aliases; both windows-dcp-admin and ubuntu-dcp-admin successfully authenticated from Amrs-Mac-Pro.
- Installed user LaunchAgent com.digipl.dcp-network-watchdog at ~/Library/LaunchAgents; StartInterval=600 seconds; script under ~/Library/Application Support/DCP/watchdog. plutil -lint and bash -n passed; launchctl bootstrap/kickstart succeeded; launchd reports job running. First recorded check: public internet reachable; gateway ICMP did not reply (not treated as offline because HTTPS checks succeeded).
- Watchdog logs network state transitions and writes PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json on offline-to-online recovery. This is a local recovery marker only; it cannot refresh/reopen a ChatGPT session or start a new assistant session.
- Updated existing hourly DCP Alpha 9 Watchdog instructions to read network monitor state/log and recovery marker, and to use the verified VM SSH aliases. Platform scheduling minimum is hourly; the separate local network monitor is every 10 minutes.
- Open item: guest IPs are statically assigned inside Windows/Ubuntu but not reserved in DHCP. Check Parallels/router DHCP range/exclusions before declaring conflict-proof permanence.
- Next: continue Q005 from CURRENT-STATE and latest handoff; validate Linux corrected release only after approval, and Windows rollback/data-preservation install when its VM is available.


## DC09 Windows Alpha 9 install checkpoint — 2026-11-24T15:36:15+02:00
- Reconnected to Windows at 192.168.8.99 via verified SSH alias. Correct target profile is AMRWIN11\amradeldotcom; SSH admin profile dcpadmin does not own the app.
- Before replacement, confirmed DCP.exe was not running and no SQLite WAL/SHM sidecars existed. Created independent timestamped backup `C:\\DCP-Backups\\DCP-pre-alpha9-20261124-152849` using Robocopy: 668 files, 228.66 MiB, zero failed/mismatched files. Original SQLite SHA-256 a2f6252c...c93fa45 and master.key b1d13ad0...10e00d matched the backup.
- Downloaded the installer from immutable tag v1.0.0-alpha.9 and verified SHA-256 edf9a1b02f16b0b9ab5c178f994979519e1369fe6638022e337d3429d03a4c53 before execution. Ran via tested interactive scheduled task as AMRWIN11\amradeldotcom, not dcpadmin. Installer completed successfully, installed managed PHP 8.5.13 and launched DCP.exe. Prior installation retained at `C:\\Users\\amradeldotcom\\AppData\\Local\\DCP.backup-20261124-153339`.
- Post-install SHA-256 of `DCP-UserData\\Windows\\data\\dcp.sqlite` and `DCP-UserData\\Windows\\storage\\keys\\master.key` exactly match the pre-install backup. `verify-runtime.php` passed all required extensions. HTTP root returned 200; `/api/status` returned 200 with `ok=true`, `database=sqlite`, `plugins=23`. DCP.exe PID 11412 and PHP PID 12256 were responsive; PHP listens on 127.0.0.1:64792.
- GUI caveat: process MainWindowHandle=0 and MainWindowTitle empty in remote query. HTTP/process health is proven; visual GUI launch/use is NOT yet proven. Preserve both rollback backups until visual and data-decryption checks pass. Temporary installer task/runner removed; transcript retained at `C:\\DCP-Backups\\alpha9-installer-20261124.log`.
- Q005 remains partial. Next checkpoint is human visual confirmation on Windows VM; then test decryption/legacy data use and proceed with corrected Linux release only after explicit version/tag approval.

## DC10 user visual confirmation and Ubuntu diagnosis — 2026-11-24 15:46:00 EET
- User supplied a Windows 11 VM screenshot showing the DCP main Overview window rendered, green "DCP service online" indicator, PHP Platform 8.5.13, DCP Local Database Ready, and local PHP service online. This closes the previously pending visual-window confirmation for Windows Alpha 9; Ollama is visibly Offline and Desktop Commander Remote is Not connected, so these are not claimed as passing.
- User supplied an Ubuntu 24.04 screenshot showing the DCP application window open but entirely white.
- Reconnected to Ubuntu through the explicit SSH config at /Volumes/Data/ChatGPT/DCP-Lab-SSH/config (Host ubuntu-dcp-admin -> 192.168.8.88; do not use the stale default ~/.ssh/config alias).
- Ubuntu DCP process and WebKitNetworkProcess/WebKitWebProcess were running; PHP server listened on 127.0.0.1:18765. However GET / and /api/status both returned HTTP 500.
- Exact PHP fatal: require(/home/amr/Applications/DCP/platform/app/Core/Database.php): Failed to open stream: No such file or directory, from platform/bootstrap/app.php:32. Confirms the installed published v1.0.0-alpha.9 Linux package layout defect; this is not yet a WebKit rendering-only issue.
- Corrected Linux staging fix exists in commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592 and CI run 45398600124 succeeded, but no superseding release has been approved/published. Preserve installed Ubuntu package and its logs until explicit replacement plan; do not claim Ubuntu passed.
- Windows static address 192.168.8.99/24 and Ubuntu 192.168.8.88/24 remain configured manually; SSH config uses verified known_hosts.
- User requested a DCP Chat feature proposal for a persistent live message queue with safe interrupt/checkpoint/context integration. Draft specification and OpenAI feature request prepared in conversation; implementation not started.
- Next: obtain approval for a superseding alpha release containing the Linux package fix; rebuild/install Ubuntu from that release and re-run visual + HTTP/API tests. Preserve Windows backup, DB and master.key. Continue Q005 before Q006.


## DC11 — post-install validation reconciliation (2026-11-24T15:50:02+02:00)
- Re-read the durable project queue, index, current state, Q005 and latest DC10 handoff before continuing. No network resume marker was present.
- GitHub release `v1.0.0-alpha.9` remains on `8b5bf08d6291897338ca322432ccf1962e0c2ed1` with six verified assets and all tag release workflows green. Branch HEAD is `0e3f41196e0819e94a8bfa99ecf95378a7a71592`; `main` remains behind at `25c81e23e0a6810a7c9b70a33d0794b00ed5de0d`.
- Linux fix commit `0e3f41196e0819e94a8bfa99ecf95378a7a71592` CI `45398600124` passed build, package assertions, HTTP smoke and artifact upload; artifact ID `14922751159` is not a release asset. Windows build `45398600263` and Cloud PHP `45398600090` passed.
- Windows Alpha 9 runtime/data checks: PHP 8.5.13 and verify-runtime pass; root/API 200 during launch; SQLite integrity `ok`; DB/key hashes match the pre-install baseline and both backups; Crypto key round-trip `ok`. No stored encrypted provider/settings/mail rows existed, so no real persisted ciphertext was available to decrypt. DC10 screenshot confirms Overview UI. Two rollback backups plus installer rollback copy retained.
- Ubuntu published Alpha 9 still returns HTTP 500 for root/API because `platform/app/Core/Database.php` is missing. Preserve the installation and logs; no superseding release has been approved.
- Mac smoke folder remains absent; original PHP/llama/Ollama listeners unchanged. Local network LaunchAgent reports 600-second interval, last exit 0; internet and gateway reachable in latest recorded check; no recovery marker.
- Next human checkpoint: approve a superseding complete alpha release with Linux fix. Keep tag Alpha 9 immutable; review `main` synchronization separately.

- 2026-11-24T15:51:37+02:00: reached a human approval gate. Paused the hourly DCP Alpha 9 Watchdog to avoid repeated blocked runs. The independent macOS network LaunchAgent remains enabled at 600-second intervals; it records network recovery but cannot reconnect ChatGPT.

## DC12 user decision: preserve alpha.9 version identity + archive feature proposals — 2026-11-24
- User explicitly chose to keep one common visible release identity `v1.0.0-alpha.9` across Windows, Ubuntu and macOS, rather than publish `alpha.10`, and asked to correct Linux and include auto-update additions. Do not change the visible semantic version without discussing again.
- Important release evidence: current GitHub release ID 456688871 is published, prerelease=true, immutable=false, and contains six assets. Its annotated tag `v1.0.0-alpha.9` currently resolves through tag object 71b8378a4989844ae764db0c3ce124f0153e2cdd to commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1. Current release branch HEAD is 0e3f41196e0819e94a8bfa99ecf95378a7a71592, a child of 8b5bf08, containing Linux packaging fix and a new Linux package smoke test (CI run 45398600124 green).
- Same-version republishing needs a separate package revision/build identifier so installed alpha.9 clients can distinguish corrected artifacts from old alpha.9 packages. Do not assume semantic-version equality will trigger auto-update. Existing Linux/macOS updater support has not yet been demonstrated; Windows has delta-manifest generation and update script, but end-to-end packaged auto-update path still needs audit.
- Published release workflows listen for tag pushes and overwrite release assets. Before retargeting the tag, archive old release asset checksums and tag target in project memory. Retargeting a published tag is a compatibility/integrity-sensitive action; preserve old source/release evidence and make a deliberate controlled update. Do not rewrite or replace any published asset until package smoke tests pass for all OSes.
- Saved the two requested proposals:
  1. `platform/docs/proposals/DCP-Chat-Live-Task-Inbox-Interrupt-Queue.md`
  2. `platform/docs/proposals/OpenAI-Feature-Request-Live-Message-Queue.md`
- Feature proposals are documentation only; implementation has not started.
- Next engineering work: audit current update flow on all OSes, add a cross-platform package revision mechanism (same user-facing alpha.9 version, increment package/build revision), ensure update metadata and updater compare both version and revision, add safe rollback and preserve DB/master keys, then build/test the corrected Linux and other platform artifacts. Do not stage or commit unrelated dirty project-memory files.

## DC12 cross-platform updater audit — 2026-11-24
- Confirmed Windows app has a background release updater in `platform/wrappers/windows/Program.cs`; it currently compares only semantic versions through `IsNewerRelease`, so it will skip a same-version alpha.9 repackaging. Windows delta manifests are generated in `platform/scripts/build-windows.ps1`; `platform/scripts/update-windows.ps1` validates SHA-256/size, protects user data paths, stages changes and rolls back file-level failures.
- Confirmed `GitUpdateService.php` is only a Git fast-forward updater for a development checkout on branch `main`; it returns `supported=false` when packaged `.git` is absent. It is not a packaged Linux/macOS updater. No packaged Linux/macOS updater entry point was found in the inspected source paths.
- Added `platform/docs/proposals/Cross-Platform-Auto-Update.md` defining same-visible-version packageRevision, manifest contract, base-revision-aware deltas, staged update/rollback and platform adapters.
- Do not claim cross-platform auto-update is implemented. Next: implement/test packageRevision in metadata and Windows updater, then build Linux/macOS update helpers and CI tests before any alpha.9 asset replacement.


## DC13 — user-approved alpha.10 and DC0005 closure mandate (2026-11-24T16:10:39+02:00)
- User changed release decision: publish a new unified `v1.0.0-alpha.10`; leave published `v1.0.0-alpha.9` untouched.
- User explicitly requires a real packaged Updater and Delta Update on every supported OS (Windows, Linux including Ubuntu/Kali compatibility, macOS), with integrity verification, safe staging, rollback, restart and preservation of SQLite/master keys/user data.
- Q005/DC0005 is the release gate. Do not start PHP feature development until Q005 acceptance is evidenced and closed; then begin PHP work as a separate safe milestone. Keep Q006 LocalAI and Q007 Cloud changes isolated.
- Baseline HEAD `0e3f41196e0819e94a8bfa99ecf95378a7a71592`; published alpha.9 tag/release remain historical. Linux package fix CI `45398600124` passed; Ubuntu installed alpha.9 is known broken (HTTP 500, missing `platform/app/Core/Database.php`).
- Automation `DCP Alpha 9 Watchdog` updated and enabled with alpha.10, all-platform updater, Q005 closure and safe PHP-development handoff instructions.


## DC13 engineering progress — 2026-11-24T16:13:54+02:00
- Set canonical `platform/version.json` to `1.0.0-alpha.10`, `releaseTag=v1.0.0-alpha.10`, `build=10`, `packageRevision=1`. Published alpha.9 tag/release were not changed.
- Windows: `DCPPackageRevision` is embedded as assembly metadata; `build-windows.ps1` passes revision into `dotnet publish`; generated delta manifest is schema 2 with `package_revision`. `Program.cs` accepts schema 1/2 and compares a higher package revision even when semantic versions are equal.
- Linux: package builder carries `packageRevision`; Linux build/release workflows assert a positive revision.
- macOS: release workflow preserves `packageRevision` in package version metadata and writes `DCPPackageRevision` into the app Info.plist; clean-package workflow checks app/manifest revision parity.
- Validation: `php platform/tests/run.php` ALL PLATFORM TESTS PASSED; all 11 GitHub workflow YAML files parse; Bash syntax passed; PowerShell parser passed; `platform/version.json` parses; `git diff --check` passed. Windows wrapper `dotnet build` succeeded on Mac with 0 errors and one existing WindowsBase reference-conflict warning. Generated AssemblyInfo confirms `DCPPackageRevision=1`.
- Existing Linux package fix CI `45398600124`, Windows build CI `45398600263`, Cloud PHP CI `45398600090` all completed success on baseline commit `0e3f411`. These do not test the new local alpha.10 changes yet.
- Not complete: no packaged Linux/macOS updater yet; Windows end-to-end delta update, rollback, full fallback, and alpha.9→alpha.10 test not run. No alpha.10 tag/release created. No target VM updated. Keep Q005 partial.
- Watchdog renamed `DCP Alpha 10 Release Watchdog`, enabled hourly, and now carries the alpha.10 + cross-platform updater + Q005 closure decision.


## DC14 — 2026-11-24T16:50:35+02:00: Windows updater transaction tests and Alpha10 CI reconciliation
- Re-read all required durable records before work. No NETWORK-RESUME-REQUEST.json marker existed. Local 600-second network LaunchAgent remains loaded; direct gateway ping and GitHub API HTTP 200 passed. It cannot reopen a ChatGPT session.
- Verified published v1.0.0-alpha.9 release ID 456688871, exact tag commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1, annotated tag object 71b8378a4989844ae764db0c3ce124f0153e2cdd, six assets. No release/tag/assets changed. Linux package still lacks platform/app/Core/Database.php; fix commit 0e3f411 and CI 45398600124 remain unpublished.
- Alpha10 branch feature/alpha10-package-revision now pushed through ec170a695a8729ff24edbbbf2a5e46b0d82b680c. Commit 8190261 fixed a real macOS test-workflow failure: test bundle did not set DCPPackageRevision before asserting revision parity. Retry 45416881645 is still queued overall; x64 PHP/llama jobs in progress and arm64 PHP/llama queued at 16:50 EET. Previous failure run 45413374893 must not be treated as the final result.
- On ec170a6, Windows build 45418031428, clean Windows package 45418031542, Linux build 45418031828 and Cloud PHP 45418031562 all passed. Windows package job step 6 confirms updater transaction tests passed.
- Windows updater hardening: legacy revision defaults 0; schema 2/revision checks; official HTTPS release URL and tag/version parity; hash/size checks; previous-manifest chain compares version+revision; user-data/path/reparse protections; correct packaged PHP path; pre-validate all files; reverse rollback; keep last two snapshots; update status JSON; 12-second startup process check. Local tests cover successful apply/data hash preservation, forced mid-apply rollback, and rejection of DCP-UserData replacement paths. PowerShell parser and C# build passed (one existing WindowsBase warning).
- Read-only Windows VM audit via dedicated SSH config at 192.168.8.99: installed SQLite/master.key and all three retained backup copies still match the known hashes; no DCP process running; no update/install performed. Ubuntu 192.168.8.88 remains published Alpha 9 with missing Database.php; no DCP/PHP server process running; no update/install performed.
- Important SSH correction: always use `ssh -F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config ...`; default `~/.ssh/config` aliases are stale and point to .62/.56. Both current endpoints .99/.88 and host keys were verified. Do not change guest static IPs without DHCP conflict checks.
- Q005 remains partial: Windows full-package fallback and signed/attested manifest remain missing; Linux/Ubuntu/Kali and macOS packaged updaters are not implemented; target VM end-to-end updates and rollback are not tested. No Alpha10 tag/release. Do not merge main automatically or begin PHP features.
- Durable details: PROJECT-MEMORY/handoffs/DC14-alpha10-windows-updater-transactional-tests-2026-11-24.md and CURRENT-STATE.json.

- 2026-11-24T16:54:11+02:00 CI follow-up: macOS retry 45416881645 advanced. Intel llama native artifact job 138525401176 completed success; Intel PHP job 138525400520 is compiling; Apple Silicon llama job 138525401200 is in progress; Apple Silicon PHP job 138525401096 remains queued. Do not mark macOS package green until both native package matrix jobs finish.


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


## DC05 CI green checkpoint — 2026-11-24T18:24:00+02:00
- User supplied GitHub screenshots confirming Linux distro workflow `45431191589` SUCCESS: Ubuntu 24.04 portable package build/archive/upload and Kali Rolling artifact download/extraction, runtime dependency/library checks and packaged HTTP/API smoke all green. The tar.gz transfer plus workspace-relative extraction preserves executable bits and works inside the Kali container.
- User supplied GitHub screenshots confirming macOS clean-package workflow `45425681044` SUCCESS: Intel x86_64 and Apple Silicon arm64 native package jobs green; managed PHP runtime and llama.cpp artifacts for both architectures green; 6 artifacts reported. The previous Apple Silicon queued state is superseded by this final success evidence.
- Windows build `45431191605` SUCCESS; Cloud PHP validation `45431191791` SUCCESS.
- On Amrs-Mac-Pro, `php platform/tests/run.php` completed with ALL PLATFORM TESTS PASSED, including signed schema-3 manifest tests: pinned public-key fingerprint, detached signature/tamper detection, platform binding, exact base revision selecting delta, mismatched base selecting full fallback, official GitHub origin, safe path rejection and full asset size/SHA-256 checks.
- `python3 platform/tests/update-package-build.py`: ALL UPDATE PACKAGE BUILD TESTS PASSED (tar.gz/zip delta contents, deletion index, user-data exclusion, schema-3 manifest binding). `bash platform/tests/sign-update-manifest.sh`: detached RSA signature verification and tamper rejection passed with an ephemeral test key. PHP lint, Python compilation and `git diff --check` passed.
- Windows transactional updater tests remain green from the prior checkpoint: `pwsh -NoProfile -File platform/tests/update-windows.ps1` passed schema-2 apply, SQLite/master.key preservation, retained rollback snapshot, forced mid-apply rollback and unsafe user-data path rejection.
- Important gap discovered during source audit: the new schema-3 signed manifest verifier/builders are currently local/uncommitted and not yet wired into the platform release workflows or Windows native updater. Windows still consumes the older schema-1/2 raw-file delta chain; Linux `GitUpdateService.php` remains checkout-only; macOS `main.m` still checks semantic version and delegates to the online installer. No real packaged Linux/macOS updater exists yet, and Windows full-package fallback is not implemented.
- No Alpha 10 release/tag/assets created; published Alpha 9 remains untouched. No target VM install/update performed. No commits or pushes made during this checkpoint. Q005/DC0005 remains partial and PHP feature development remains gated.
- Next engineering priority: integrate the signed schema-3 manifest and exact-base delta/full fallback into the actual release pipeline and native updaters; implement packaged Linux/macOS apply/rollback adapters; then run target-device Alpha 9→Alpha 10, forced rollback and data-preservation acceptance.


## DC05 signed-manifest CI guard — 2026-11-24T18:28:00+02:00
- Added `.github/workflows/test-update-manifest.yml` to run the schema-3 manifest verifier, delta/manifest builders and detached-signature/tamper tests on push, pull request and manual dispatch. The signing test generates an ephemeral test key and does not require or expose the production private key.
- Local verification: Ruby YAML parse passed; `php -l` and `php platform/tests/update-manifest.php` passed; `python3 platform/tests/update-package-build.py` passed; `bash platform/tests/sign-update-manifest.sh` passed; `git diff --check` passed. Workflow has not yet been pushed or run in GitHub Actions.
- This is a CI regression guard only; it does not mean the schema-3 contract is wired into production release workflows or platform updaters. Q005 remains partial.


## DC16 — Alpha 10 signed manifest and delta package foundation (2026-11-24 ~18:25 EET)
- Start-of-run records read: WORK-QUEUE, INDEX, CURRENT-STATE, latest DC15 handoff, Q005, network-state and network-watchdog.log. `NETWORK-RESUME-REQUEST.json` absent. Network monitor online at 16:17:17Z; monitor only logs transitions and cannot reopen ChatGPT.
- Branch `feature/alpha10-package-revision` HEAD `2f5a5d55a6e3c100e5425bdabbf8e8c4c6b9ddfe`, pushed successfully. Commit `2f5a5d5` adds schema-3 manifest verifier, pinned RSA public key, schema JSON, delta package builder, manifest generator, detached signing script and tests. Alpha 9 remains immutable (release 456688871, tag commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1, six assets).
- Manifest verifier enforces detached RSA-SHA256 signature, pinned public-key SHA-256, key ID, schema 3, version/release-tag parity, platform/architecture, commit SHA, exact official GitHub release URLs, exact version+packageRevision base, full fallback, safe paths, user-data/environment exclusions, and asset size/SHA-256.
- Delta builder compares complete base/target package trees, emits tar.gz/zip, preserves file mode, lists explicit deletions, and excludes DCP-UserData, SQLite/master.key, platform data/storage, .env, Windows old payload and update manifest self-reference.
- RSA-3072 key ID `dcp-alpha10-rsa3072-20261124`; public fingerprint `bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67`. Private key stored in macOS login Keychain service `DCP-Alpha10-Release-Signing-Key`. GitHub Actions secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` is NOT configured because GitHub connector secrets API is read-only and `gh` is absent; manual repository secret setup is required before signed release workflows.
- Local checks: PHP platform suite + manifest tests PASS; Python delta/manifest builder tests PASS; detached signing/tamper test PASS using ephemeral test key; Windows transactional updater tests PASS; PHP lint, Python compile, Bash/Zsh syntax, JSON/YAML parse and `git diff --check` PASS.
- GitHub CI on `2f5a5d5`: Cloud PHP `45435155294` PASS; Windows build `45435155119` PASS; clean Windows package `45435155128` PASS; Linux WebView build `45435155129` PASS; Ubuntu 24.04 + Kali Rolling package/HTTP/API smoke `45435155253` PASS. macOS clean package `45435155319` still in progress (Intel PHP/llama running; Apple Silicon PHP/llama queued). Earlier `45425681044` completed PASS for both Intel and Apple Silicon package jobs and both runtime architectures.
- Important: schema-3 is not yet wired into native updater clients or release workflows. Windows remains schema 1/2 and lacks full fallback; Linux GitUpdateService remains checkout-only; macOS still uses semantic-version/online-installer path. No target VM installs or updates performed. No Alpha 10 release created. Q005 remains partial; no PHP feature development.
- Next: implement Linux packaged updater adapter and tests; then Windows schema-3 signature/full fallback, macOS packaged apply/rollback, release workflow manifests/signatures and target Alpha9→Alpha10/forced rollback/data-preservation tests.

- Worktree reconciliation: untracked `.github/workflows/test-update-manifest.yml` was observed after commit `2f5a5d5` (mtime 18:18:46 EET). It is not part of HEAD, duplicates manifest test steps already wired into Linux/macOS workflows, and was deliberately left untouched/unstaged. Review before any later commit.


## DC17 — Linux packaged updater core (2026-11-24)
- Implemented locally: Linux transactional delta/full updater, safe tar extractor, synchronized signed schema-3 release verification across Windows/Linux/macOS Intel/Apple Silicon, full fallback, DB/key snapshots, PHP + HTTP /api/status health check and automatic rollback on health failure. Startup launcher prompts before applying; offline startup remains available.
- Added Linux updater transaction, extraction and API-health tests; integrated into platform suite, package smoke, Ubuntu and Kali CI workflows.
- Local validation: platform suite, update package builder, archive extraction, PHP lint, Bash syntax and git diff --check all PASS. Live GitHub check safely reports no complete synchronized signed release available.
- Not committed/pushed yet. Next: review/stage only Linux updater source/tests/workflow files; then implement Windows schema-3 signature/full fallback and macOS packaged updater while native CI runners complete. Configure production signing secret manually before release generation.
- Q005/DC0005 remains PARTIAL. No Alpha 10 release, no Alpha 9 mutation, no target VM update, no PHP feature work.


## DC18 — 2026-11-24T18:58:54+02:00: Alpha 10 Linux/Windows updater hardening
- Pushed `4ddb91e38a0a919cfe7d7bc01cfc5a74f1a88f50` to `feature/alpha10-package-revision`. Alpha 9 release ID 456688871/tag commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1 and its six assets remain unchanged. No Alpha 10 release/tag, no main merge, no target install.
- Linux: packaged signed-manifest updater now has a non-blocking lock, DCP process guard (refuses; never kills), disposable user-data health check, fsynced pre-mutation journal, atomic same-directory replacement, crash recovery, pending-health rollback, signed full-deletion list and missing-delta full fallback. Python 3 is now explicitly installed/checked for Ubuntu/Kali archive extraction.
- Windows: native schema-3 manifest client and exact base revision selection are wired; missing delta/base mismatch chooses full package. PowerShell updater uses portable atomic replacement; C# contract and PowerShell transaction tests are wired to CI.
- Local tests all passed: platform suite; manifest/signature/tamper; delta builder; Linux extraction/transaction/health; Windows PowerShell apply/full-fallback/rollback/data-preservation; C# Windows updater contract tests; Windows app build; PHP/JS/Python/Bash/Zsh/PowerShell syntax and diff check.
- GitHub on 4ddb91e: Windows build 45442801370 PASS; Windows clean package 45442801525 PASS; Linux build 45442801373 PASS; Cloud PHP 45442801543 PASS. Linux distro run 45442801351 has Ubuntu PASS and Kali dependency install still in progress. macOS run 45442801352 has Intel PHP/llama building and Apple Silicon PHP/llama queued; prior 45435155319 passed both architectures on 2f5a5d5.
- SSH read-only: Windows alias .99 authenticated as dcpadmin; Ubuntu alias .88 authenticated as amr, Ubuntu 24.04 x86_64 with Python3/PHP and no DCP process. No target files/packages changed.
- Untracked macOS updater prototype reviewed but not accepted: not wired to main.m, lacks crash journal/recovery and has an unsafe stale-PID kill path; redesign before commit. Production signing secret still requires manual setup.
- Next: finish Kali/macOS CI, build safe macOS adapter and integrate native wrapper, wire signed release assembly, then target Alpha9→Alpha10/forced rollback/data preservation. Q005 remains PARTIAL; no PHP feature work; Q006/Q007 isolated.
- Network resume marker absent. Local 600-second LaunchAgent only records transitions and cannot reopen ChatGPT.

## DC19 — Live monitor, Ubuntu package diagnosis and macOS process guard (2026-11-24)
- Added executable tools/dcp-watch.sh; Terminal command: cd /Volumes/Data/ChatGPT/DCP && bash tools/dcp-watch.sh (optional refresh seconds, default 3; Ctrl+C exits). It shows branch/HEAD, dirty files, queue tail, latest handoff, CURRENT-STATE, local build/test processes and recent logs. It does not reconnect ChatGPT or assert task liveness.
- Read-only SSH to Ubuntu 24.04 .88 confirms installed /home/amr/Applications/DCP remains Alpha 9; platform/app and Core/Database.php are absent; packaged Linux updater and pinned release key are absent; no DCP/PHP server process; ports 18765/18991 refuse connections. This proves the white window is caused by the incomplete published Alpha 9 archive, not merely WebView. No Ubuntu files or user data changed.
- Fixed macOS updater process ownership for /var vs /private/var argv[0] path aliases. Exact DCP app processes are recognized via ps args; stale unrelated PIDs remain untouched. Added regression tests. Commit a8bc81fd pushed.
- Local regression suite passed: platform tests; macOS updater 40 assertions; Linux package builder and safe extraction; Windows PowerShell apply/full fallback/rollback/data preservation; Windows C# contract tests; Windows app build (0 errors, one existing WindowsBase warning); PHP/Bash/Zsh syntax and git diff check.
- GitHub runs on a8bc81fd: 45447308256 manifest/delta, 45447308173 Linux build, 45447308527 Windows build in progress; 45447308397 native macOS package queued. Previous Linux distro run 45446122964 on d78a015c passed Ubuntu + Kali.
- Production signing preflight 45446629180 passed manifest/delta/ephemeral-signature tests but failed because repository Actions secret DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 is missing. Keychain item is present locally; GitHub CLI is absent; manual repository secret setup is required. Do not put private key in repo/logs.
- Alpha 9 unchanged, Alpha 10 unpublished, no main merge, no target installation/update. Q005 remains partial.
- Next: poll CI; configure production signing secret; wire synchronized signed release assets; inspect full packages; then target GUI/API and Alpha9-to-Alpha10 update/rollback/data-preservation acceptance on all systems.


## DC20 rollout checkpoint — 2026-11-24
- User reset the installation strategy: macOS is the only primary product folder. Test new macOS bundles in a temporary sibling directory; replace only the verified DCP.app bundle in the original project folder. Windows and Ubuntu app binaries are disposable test installs; back up DCP-UserData before replacement.
- macOS Alpha 10 (1.0.0-alpha.10, build 10, package revision 1) was built in /Volumes/Data/ChatGPT/DCP-Alpha10-Mac-Smoke.YiNqaK, visually rendered in Safari, and returned HTTP 200 for / and /api/status. The original /Volumes/Data/ChatGPT/DCP.app bundle was replaced only after the temporary bundle passed codesign --verify --deep --strict. A rollback copy remains at /Volumes/Data/ChatGPT/backups/DCP.app-alpha7-before-alpha10-20261124-195129. Original macOS dcp.sqlite and master.key remained byte-identical.
- Windows and Ubuntu remain on Alpha 9; no app/data files on those hosts have been deleted or replaced in this checkpoint. Their existing data paths/backups were inventoried.
- GitHub branch feature/alpha10-package-revision is at 3aa58eb (origin matches), 70 commits ahead of main; main is an ancestor and is unprotected. Creating a PR through the GitHub integration returned HTTP 403; no PR or main update has been made.
- GitHub run set for 3aa58eb: Linux Ubuntu 24.04 + Kali Rolling package/API smoke SUCCESS (45454116524); Windows build SUCCESS (45454116623); Windows clean package SUCCESS (45454116693); Cloud PHP SUCCESS (45454116522); signed manifest/delta/full-fallback contract SUCCESS (45454116773). Native macOS clean-package run 45454117132 is queued.
- Local updater tests: Windows PowerShell transaction/full fallback/rollback tests passed; Windows C# schema-v3 contract tests passed; macOS updater 44 assertions passed; Linux updater health and data-preservation tests passed; GitHub Atom feed tests passed; four-platform signed release assembly, exact Alpha 9 Delta base, full fallback, synchronized manifests and signatures passed.
- Local uncommitted update-monitor changes: macOS wrapper polls the signed feed every 300 seconds and suppresses duplicate prompts for the same release; Windows wrapper polls every five minutes, downloads/verifies the package before asking, and retains the staged package when deferred; Linux updater supports --download with a verified package cache and a --watch process that polls every 300 seconds and applies after DCP is closed. These changes are not yet pushed or validated against a published release. macOS still prompts before applying; its package download occurs after approval. Do not claim end-to-end update acceptance yet.
- Alpha 10 is not published; GitHub latest release remains immutable Alpha 9. No Alpha 10 tag or release assets have been created.
- After Alpha 10 is published and all three installations are verified, add a harmless test file under platform code, publish a subsequent prerelease, and verify Windows, Ubuntu and macOS each detect/download/apply it while preserving user data.


## DC21 — Alpha 10 update-monitor fixes and CI reconciliation — 2026-11-24T20:23:42+02:00
- Read required durable records at start; network marker absent. Network-state online at 18:17:47Z; LaunchAgent remains a transition logger only.
- Reconciled actual pushed branch HEAD to f59c086 (origin matches); recent commits 193cfa4 and f59c086.
- Local tests passed: platform suite; Linux updater health/transaction; macOS updater 52 assertions; Windows PowerShell and C# client; delta builder; Linux extractor; signature/tamper; release assembly; PHP lint; Bash/Zsh/PowerShell syntax; project JSON/YAML; diff check. Windows wrapper cross-target build passed with one existing WindowsBase warning.
- CI f59c086: Linux build 45458161076 PASS; Ubuntu+Kali 45458160914 PASS; Windows build 45458161409 PASS; signed manifest 45458161245 PASS; macOS native 45458161119 queued (four jobs). Windows clean package 45457125199 passed on 193cfa4.
- Read-only device checks: Mac Alpha10 app/codesign and source compile pass; data hashes recorded; existing Ollama/PHP/llama listeners unchanged. Ubuntu still Alpha9/no DCP listener. Windows AMRWIN11/no DCP process or listener; backup root exists. No VM install/update.
- Alpha9 release ID 456688871, target commit 8b5bf08 and six assets unchanged; Alpha10 unpublished. Signing secret missing. No merge, PR or release. Q005 partial; Q006/Q007 isolated; no PHP feature work.


## DC24 — Alpha 10 approval and Local AI continuation — 2026-11-24
- User explicitly approved adopting the first working Alpha 10 cross-platform updater version and instructed us to stop repeating the Alpha 9 test loop, remove obsolete non-working GitHub versions after Alpha 10 is published, and continue to the next project phases.
- Fast-forwarded `main` from `d47f2c0` to `db054a2` (Alpha 10 source checkpoint) and retargeted unpublished tag `v1.0.0-alpha.10` to that checkpoint. The tag push started signed release workflow `45495393689`; Linux and Windows package jobs succeeded. macOS Intel/ARM64 PHP and llama.cpp native builds are still running. No Alpha 10 Release assets have been published yet.
- Earlier Alpha 10 Windows ZIP root-layout fix `6376992` and Linux watcher refresh fix `469a028` are included in the adopted source checkpoint. Local Linux/Windows/macOS updater suites pass; Ubuntu+Kali, Windows, signed manifest/delta CI pass.
- Alpha 9 release `456688871` is still published temporarily as the Delta base while Alpha 10 assembly runs. Remove the obsolete Alpha 9 release/tag after Alpha 10 signed assets are confirmed; do not remove it before the new release is available.
- Main is now at `7df3acc06539654cb44d00cb90d8a5c6fb796301`, including Q006 fix: `LocalAIService` now discovers the established llama.cpp port `19123`. Regression test added. `php platform/tests/run.php` passes; live DCP API now correctly reports the existing llama.cpp service online at `http://127.0.0.1:19123/v1` with `managed=false`, so DCP does not offer to stop it.
- Mac runtime smoke: Ollama already running and reused without restart/reconfiguration; models DiGEMA-3B, DiGEMA-4B and DiGEMA-7B detected; managed Intel SSE4.2 llama-server binary reports version 0.3.0-dev; direct DiGEMA-4B generation returned `DCP_LOCALAI_OK`. DCP ChatService returned a model response (`DCP_HOME`) but did not match the exact-output probe, so do not claim the ChatService semantic-response test passed.
- Read-only install manifests on Windows and Ubuntu both show `1.0.0-alpha.10` / revision 1. During this read-only probe neither host exposed a current DCP API listener; no installed files, processes, databases or keys were changed. macOS DCP API at port 18991 returned `ok=true`.
- Q005 is accepted by user; finish the already-running signed release workflow and then move on. Q006 is active on main. Continue Local AI lifecycle checks on Windows/Ubuntu without replacing or stopping existing services. After Alpha 10 assets are live, proceed to the harmless platform-code updater probe as planned.

## DC25 — Q006 Local AI and Q007 Cloud hardening — 2026-11-24
- Main checkpoints: Alpha 10 approved source/tag at `db054a2`; Q006 llama discovery fix `7df3acc`; Qwen3 0.6B low-memory llama.cpp profile and UI button `1acefaa`; Q007 cloud endpoint hardening `e194324`. Main currently points to `e194324d116f9b5dea45b15cc5f9ef43b5140b13`.
- Alpha 10 signed release workflow `45495393689`: Windows package PASS, Linux package PASS, macOS Intel llama PASS, macOS Apple Silicon llama PASS, macOS Intel PHP PASS; macOS Apple Silicon PHP is still in progress. No release assets published yet.
- Q006 Mac: Ollama reused without restart; DiGEMA-3B/4B/7B discovered; direct DiGEMA-4B inference returned `DCP_LOCALAI_OK`. DCP ChatService returned a response but failed the exact-output probe; do not claim semantic chat acceptance.
- Q006 Ubuntu: installed managed Linux x64 llama.cpp runtime under `platform/dcp-env/engines/llama.cpp/linux-x64`; version `1.0.0-dev (build 12681, commit d141011c2)`; ldd shows no missing dependencies. Hardware detection reports 7.7 GB RAM and recommends Qwen3 0.6B. A disposable start smoke using official Qwen3 0.6B Q8 model was stopped because the Hugging Face download was too slow; only the temporary fixture and partial model cache were removed. Managed llama.cpp runtime remains installed; no app user data, SQLite or master.key was touched.
- Q006 code adds the Qwen3 0.6B Q4_K_M profile from public `mradermacher/Qwen3-0.6B-GGUF`, and the model card now offers Run with llama.cpp for 0.6B. Local platform suite and JS syntax checks pass. GitHub main CI on `1acefaa`: Cloud, Windows, Linux distro and Linux build PASS.
- Q007 hardening commit `e194324`: APP_KEY must be >=32 printable non-placeholder characters (the existing Hostinger key is 46 printable chars); rate limits added to device list, pairing decision and paired-device list; device revoke transaction revokes all pairings and expires pending pairing/connection requests; pair-scope, pairing and signal-poll paths reject revoked endpoints. Added 13 cloud-contract checks and wired them into test-cloud workflow.
- Q007 validation: PHP lint, all 13 cloud-contract checks, full platform suite, YAML parse and CLI valid/invalid APP_KEY guard checks PASS. GitHub Cloud CI `45498076165` PASS. Changes are not deployed to Hostinger; first verify the production APP_KEY format/length without printing its value, then deploy and run live health/pairing E2E.
- Alpha 9 release remains temporarily available as the Delta base. After Alpha 10 signed assets are published and verified, remove the obsolete Alpha 9 GitHub release/tag as requested. Do not delete the base before Alpha 10 is live.

## DC26 — Cloud desktop-client mock E2E — 2026-11-24
- Extended `CloudService` with an optional injectable API base URL; production defaults unchanged to `https://dcp.amradel.com/api/v1`.
- Expanded the isolated mock HTTP server and `platform/tests/run.php` to exercise anonymous device registration, heartbeat, account registration/linking, encrypted device token storage, pairing-code creation, pairing request, scoped approval, paired-device revoke, connection request, signaling send/poll, and account logout while preserving the device identity.
- Full local platform suite passes, including the new CloudService end-to-end path. Cloud contract suite (13 assertions) passes. PHP lint and workflow YAML parse pass.
- Commit `f25c389` pushed to main. GitHub Cloud CI `45498610512` PASS on this commit; manifest/delta CI `45498610369` PASS. Linux/Windows CI is still running.
- No Hostinger production files or database were changed. Before deployment, verify the production APP_KEY is >=64 hex characters without printing it; then run live health, account and pairing E2E.

## DC27 — Hostinger routing recovery and safe APP_KEY rotation plan — 2026-11-24
- Live Hostinger .htaccess was mode 600, so LiteSpeed did not apply rewrite rules and /api/v1/health returned 404 while the root landing page worked. Created backup /home/u163155085/dcp-backups/20261124T221644Z-cloud-hardening-before-deploy, changed .htaccess to 644, and confirmed root and health return 200; direct /.htaccess, /.env, schema files and legacy site.php return 403.
- Found the live APP_KEY is the example placeholder REPLACE_WITH_AT_LEAST_64_RANDOM_HEX_CHARACTERS (length 46); its value was never printed. A strict candidate correctly returned 503. Immediately restored the pre-deploy index.php from backup; health returned 200. Current live code and .env are unchanged from before the candidate deploy; only .htaccess mode 644 remains.
- Created fresh private backup /home/u163155085/dcp-backups/20261124T222224Z-cloud-key-rotation containing .env, index.php, .htaccess and a compressed MySQL dump; checksum verification passed.
- Main commit 5f903d7 adds APP_KEY_PREVIOUS compatibility and lazy HMAC re-hashing for account tokens, device tokens and pairing codes. It accepts old tokens while new tokens use a new random key. Commit 89b9446 fixes a literal .htaccess workflow grep; GitHub Cloud CI 45500943554 PASS. Local cloud contract and full platform tests pass.
- The connected remote-command safety guard blocked the production .env mutation. Do not work around the guard. The API remains up on the previous code with the placeholder key, and the new rotation code is not deployed. Ask for explicit approval to rotate APP_KEY, retaining the old placeholder as APP_KEY_PREVIOUS until clients migrate; then deploy index.php, token.php and .htaccess together and test old-token compatibility.
- Alpha 10 release retry is queued as workflow 45500762248 against approved tag commit db054a2; the prior failure was external download/DNS only. Alpha 9 release remains until Alpha 10 assets are live.

## DC28 — Header Cloud status and Devices pairing management — 2026-11-24
- Moved the DCP Cloud account/pairing panel from Settings into Devices. Sign-in/register, pairing-code creation, incoming approval and paired-device revoke controls remain wired to the existing CloudService endpoints.
- Added a compact header Cloud status button with account/guest/offline states; clicking opens Devices. Devices navigation refreshes local devices and Cloud pairing state. Header control remains visible on narrow screens.
- Added 11 cloud-ui-contract assertions. Local PHP lint, JavaScript syntax, cloud UI contract, cloud API contract and full platform suite all PASS. Local HTTP DOM probe confirms the header button, panel placement, authentication controls and pairing controls.
- Commit 8f9225f pushed to main. GitHub Cloud CI 45501817826 and Windows/Linux/macOS validation workflows are in progress.
- Cloud production deployment remains pending APP_KEY rotation approval; live API is still on the previous code and health is 200.
- Alpha 10 signed release retry 45500762248: Windows and Linux package jobs PASS; macOS Intel/ARM64 llama and managed PHP builds are still running.

## DC29 — Dedicated Mail page and local SMTP/IMAP protocol tests — 2026-11-24
- Added a dedicated Mail navigation item/page. SMTP/IMAP settings, connection-test actions and unread inbox now live under Mail rather than Settings; opening the page reloads saved mail settings and inbox.
- Fixed MailService partial-save behavior: omitted settings are preserved and blank SMTP/IMAP password fields do not overwrite encrypted credentials.
- Added isolated local protocol mocks: SMTP EHLO/AUTH LOGIN and DATA delivery; IMAP LOGIN/LIST/SELECT/UID SEARCH/UID FETCH/LOGOUT, header/preview parsing and invitation classification.
- Added tests for encrypted and masked SMTP/IMAP credentials, password preservation, invalid-recipient rejection and the 5 MB attachment limit. Added 8 Mail UI contract assertions.
- Local PHP lint, JavaScript syntax, Mail UI contract, Cloud UI/API contracts and full platform suite PASS. GitHub Cloud CI 45502521736 PASS on commit 6f31659; Windows/Linux/macOS CI is running.
- Real Titan endpoint validation still needs the user's SMTP/IMAP host credentials; multi-account storage and AI summarize/draft workflow remain open.

## DC30 — AI Mail summaries and reviewed drafts — 2026-11-24
- Added an AI Mail Assistant to the Mail page with configured-provider/model selection, unread-mail summary and draft generation.
- AI context is limited to the latest unread message excerpts and explicitly treated as untrusted input; embedded email instructions are not followed. Drafts remain editable. Sending is a separate explicit action with a confirmation dialog and an API-side confirmed=true requirement; no AI-generated email is sent automatically.
- API endpoints: POST /api/mail/ai-summary, /api/mail/ai-draft and /api/mail/send. Audit records capture provider/message counts or recipient/subject, not message body.
- Added contract checks for UI/API wiring, untrusted email context and explicit send confirmation. Full platform suite also exercises the configured ChatService provider with a mock AI response.
- Local PHP/JS lint, Mail UI, Cloud UI/API contracts and full platform suite PASS. Commit 1f4b58d pushed to main; GitHub Cloud CI 45503283670 PASS. Linux/Windows/macOS CI running.
- Remaining Q009: real Titan SMTP/IMAP validation requires credentials, and multi-account management remains open. Cloud APP_KEY rotation remains pending explicit approval.


## DC06 continuation checkpoint — 2026-11-25
- Current Q005/DC0005 stage: Alpha 10 is the first approved baseline; signed publication needs a retry for the macOS Intel SSE4.2 source-fetch network failure.
- The Alpha 10 candidate tag is pinned to the approved source checkpoint; no signed Release assets were published by run 45515068071.
- Fixed .env.example rejection in all updater layers, including Windows PowerShell; added tests. Release workflow now refuses superseded tags whose commit is not current default-branch HEAD.
- Full local platform, Linux extraction/transaction, Windows PowerShell/C# updater, and signed release assembly tests passed.
- GitHub signed release run 45515068071: Linux, Windows, macOS ARM64 llama.cpp and both managed PHP runtime jobs passed; macOS Intel SSE4.2 llama.cpp could not resolve github.com while cloning source, so assembly/publication were skipped. Continue development rather than waiting idle.
- Signing key backup remains outside the repository at /Volumes/Data/ChatGPT/DCP-KEYS/release-private.pem (0600); macOS Keychain copy retained. Never stage this folder.


## DC31 — Windows LocalAI lifecycle verified — 2026-11-25
- Investigated the Windows interactive-user Q006 smoke on AMRWIN11. The first attempts exposed a PowerShell 5.1 behavior: with ErrorActionPreference=Stop, normal native llama-server diagnostic output on stderr becomes a terminating NativeCommandError, preventing the server wrapper from running.
- Changed the generated Windows llama-server wrapper to use ErrorActionPreference=Continue, capture all output to the DCP user-data log, and explicitly propagate a non-zero LASTEXITCODE. This keeps native diagnostics from aborting startup while retaining process failure visibility.
- Windows managed process ownership now verifies the listener port, owning PID and executable path together; stop confirms the exact PID exited and removes the matching DCP scheduled task.
- Added cached GGUF selection for Qwen3 0.6B so an existing verified Hugging Face cache is launched by direct model path instead of repeating a network download.
- Real Windows smoke passed in the interactive AMRWIN11\amradeldotcom session: cached Qwen3 0.6B loaded, /v1/chat/completions returned DCP_QWEN06_WINDOWS_OK, and managed stop completed. Post-test probe: zero llama-server processes, no managed runtime state file, and no DCP test scheduled tasks.
- php platform/tests/run.php — ALL PLATFORM TESTS PASSED; PHP lint and git diff --check passed. One initial test warning caused by an unescaped PHP interpolation in the new static assertion was corrected and the clean suite rerun.
- Test touched only the isolated temporary LocalAIService harness and DCP user-data model/runtime/log paths. No SQLite or master.key was read or modified; existing unrelated Ollama/DCP services were not stopped or reconfigured.
- No release, tag, or installed application update was performed. Next: commit and push this focused Q006 improvement, then continue Linux live model lifecycle and UI integration while release CI runs.

| Q014 | Per-device workspaces, capability matrix and signed remote task protocol | active — Ed25519 Device Agent transport integrated and end-to-end tested locally | Device Agent auto-provisions its per-device workspace, registers/pins Ed25519 keys, verifies task-bound signed envelopes before allow-listed file actions, and signs results for server verification/replay acknowledgement. Older agents remain on explicit legacy compatibility; registered/revoked keys cannot downgrade. Signed/legacy state is shown in Devices UI. Full API E2E, tamper/downgrade/replay/expiry/path traversal tests pass. Remaining: real Windows/Ubuntu/macOS agent acceptance, updater preservation of keys/workspaces, task lease recovery and revocation/re-pair UI. See `PROJECT-MEMORY/tasks/Q014-device-capability-matrix.md`. |


## DC54 checkpoint — 2026-11-25
- Approved DCP Core/MCP/CLI/API/Node architecture documented in `platform/docs/DCP-CORE-MCP-CLI-API-ARCHITECTURE.md`; Desktop Commander reference findings in `platform/docs/research/desktop-commander-reference-review.md`.
- Official open-source `wonderwhy-er/DesktopCommanderMCP` cloned to `/Volumes/Data/DiGiPL/References/DesktopCommanderMCP`, pinned locally at `9a101b17`; upstream push URL disabled; no dependencies installed or reference files modified.
- DCP release install target corrected to `/Volumes/Data/DiGiPL/DCP`. Previous incomplete state preserved as `/Volumes/Data/DiGiPL/DCP-Preinstall-State`. Official installer completed with archive SHA-256 verification and no auto-launch. Stable `v1.0.0` bundle + platform runtime verified; PHP 8.5.9 required extensions pass; `/api/status` and `/api/bootstrap` returned HTTP 200 using disposable data under `/Volumes/Data/DiGiPL`, then that temporary data was removed. Production DCP-UserData remains empty (no SQLite/master.key copied or created).
- Q012 Task 4 first implementation slice added `ChatExecutionCoordinator`, shared registry proposal validation, plan/version/argument-hash/idempotency metadata and approval records. Detached worker now uses provider tool calling when supported and persists proposals without executing them. Full local platform suite and worker E2E pass.
- Remaining: installer verification; Task 5 approval-bound execution; Task 6 plan/approval review API/UI; Task 7 heartbeat and safe resume; cross-platform CI.
- Work remains local/uncommitted/unpushed.

## Current execution additions — 2026-11-25
- **Cloud diagnosis:** `https://dcp.amradel.com` TLS/HTTP is reachable; isolated test of the installed `1.0.0` backend `/api/cloud/status` successfully auto-registered a device and verified heartbeat. The installed release user-data root has no account token and no persistent production DB yet. User sign-in is still required to link the account. Keep source DB/token separate; do not transplant them.
- **Release-vs-source:** the installed `1.0.0` app bundle is dated before the current source changes; its JS differs from source. Do not edit installed release in place. Build/release a reviewed newer version when source is ready.
- **DP_MEMORY:** cloned three upstream repositories under `/Volumes/Data/DiGiPL/DP_MEMORY/sources/`; documented architecture/decisions/roadmap. This project is intentionally deferred; no dependencies installed, no source modifications, no runtime/build/deployment.
- **Temporary DCP memory:** added Markdown+JSON hierarchical memory service and REST/MCP read paths to DCP Source. Mutating MCP memory tools remain gated/disabled. Full tests pending.

- **DC56 — DCP Core MCP:** Local Streamable HTTP `/mcp` endpoint added on loopback with token scopes and Origin checks; same DcpToolRegistry as STDIO. Local token CLI: `platform/bin/mcp-token.php`; revoke with `platform/bin/mcp-token-revoke.php`. Cloud MCP endpoint `/api/v1/mcp` added for account-scoped read-only device/pairing metadata, plus 30-day owner API token create/revoke endpoints. Both support protocol 2025-11-25 and 2026-07-28 request header/meta validation; SSE subscriptions not yet implemented. Local MCP HTTP E2E PASS; Cloud static contract PASS. Cloud remote task execution remains not implemented and is not exposed.
- **DC55 memory integration:** Chat worker now prepends bounded DCP project-memory summaries (not whole files) from temporary Markdown+JSON hierarchy; memory unit test passes.
- **DC55 Cloud fix:** macOS wrapper normalizes `TMPDIR=/tmp`; source-built app Cloud `/api/cloud/status` now returns connected=true with verified heartbeat. Installed stable v1.0.0 remains unchanged; do not patch it in place.
- **DP_MEMORY:** Cognee history has now been fetched fully; all three checkouts are non-shallow, fetch-only upstreams, no deps/build/runtime.

- **DC57 — Q012 Task 5:** durable idempotency ledger and atomic step claim added; exact plan/version/argument hash, dependencies and approval revalidated; duplicate and expired/uncertain execution fail closed; one-time lease token required for result completion. Dedicated E2E passes. Actual tool dispatch remains disabled until capability grant resolver, artifact/result store and review API/UI are complete.


## DC63 checkpoint — 2026-11-25
- DCP Core Local + Cloud MCP parity expansion: 27 shared tools; full shell execution, persistent-in-host interactive sessions, bounded filesystem CRUD/search/edit/stat and process inventory/termination, Cloud status/pairing/signaling tools; independent HTTP token scopes. Local HTTP MCP E2E verifies terminal call and write-scope denial.
- Cloud landing page redesigned using existing PHP/HTML/CSS and supports all nine DCP locales (ar/en/nl/es/pt/fr/de/zh/ja). No new frontend framework, no production deployment.
- Q012 Task 6 review/approval API+UI implemented; Task 7 integrity-checked conversation-only resume implemented with no tool replay. Task 5 actual tool dispatch remains incomplete, so step-level resume remains disabled.
- Full platform suite after Task 6/7 changes: ALL PLATFORM TESTS PASSED; local HTTP MCP E2E PASS; Cloud contract PASS; PHP lint, JS syntax and git diff check PASS.
- No commit, push, release, Cloud production deploy, or stable v1.0.0 install modification. Continue Task 5 execution boundary before step-level resume; run fresh cross-platform CI for the expanded 31-tool registry.

- DC63 final revalidation after adding CloudService to the detached worker bootstrap: `php platform/tests/run.php` → ALL PLATFORM TESTS PASSED; `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` → DCP_CHAT_WORKER_BACKGROUND_E2E_PASS; `python3 platform/tests/chat-job-http-e2e.py` → DCP_CHAT_JOB_HTTP_E2E_PASS; local HTTP MCP E2E and Cloud contract PASS; 9 locale page renders PASS; PHP lint, JS syntax and git diff check PASS.

## DC64 checkpoint — 2026-11-25
- Expanded the shared local DCP registry to 31 tools with filesystem stat/exact atomic edit and process inventory/graceful termination. Added `process.read` / `process.terminate` HTTP MCP scopes; process listing is current-user scoped and termination checks ownership and protects DCP host/parent.
- Filesystem edit requires expected SHA-256 and exactly one matching occurrence; atomic same-directory replacement. Process termination sends graceful signal only and never force-kills.
- Focused MCP HTTP E2E passes after process scope additions. Full platform suite rerun pending after this DC64 slice.

## DC65 checkpoint — 2026-11-25
- Q012 Task 5 execution boundary implemented: transactional approval-created capability grants, resolver, immutable-plan-only invocation, one-time lease, private 0700/0600 result artifacts, 256 KB bounds, secret redaction, SHA-256, job-bound retrieval and audit. Explicit CSRF API + per-step Execute UI; approval alone never executes.
- Task 7 now permits reviewed dependency-aware per-step execution from the plan. Conversation resume still creates a new explicit turn. No automatic tool loop/replay; failures/uncertain outcomes fail closed.
- Chat provider tool schemas exclude interactive terminal session tools; they are STDIO-host scoped and cannot be retained by one-request PHP HTTP workers.
- `php platform/tests/run.php` ALL PLATFORM TESTS PASSED; MCP HTTP E2E PASS; Cloud contract PASS; worker E2E PASS; delayed-provider HTTP E2E PASS; JS/PHP syntax and diff checks pass. Cross-platform CI pending; no commit/push/deploy/release.


## DC66 checkpoint — 2026-11-25
- Completed: local MCP keepalive and DCP logo advertisement; php platform/tests/run.php returns ALL PLATFORM TESTS PASSED. See PROJECT-MEMORY/handoffs/DC66-dcp-mcp-keepalive-resource-mesh-2026-11-25.md.
- Q015 — DCP Resource Mesh & Universal AI Gateway: active, partially implemented. Local resource registry/broker, SQLite grants/invocation ledger, four MCP tools and local REST routes are implemented and tested. Cloud resource/team/grant schema, migration and authenticated registration/heartbeat/discovery/grant/revoke endpoints are implemented. Cloud target-side terminal request inbox and exact-command approve/reject are implemented; no dispatch occurs. Remaining: owner-vault credential resolver and real provider invocation adapters, device/team grant discovery parity locally, Hermes adapter, signed task/result delivery, target-device approval UI, native PTY/ConPTY + daemon crash recovery, Windows/Ubuntu acceptance.
- Next implementation order: durable PTY/session daemon shared by STDIO/HTTP; richer filesystem content/multi-file/metadata operations; resource registry and grants; Hermes + provider ingress; signed Cloud dispatch/ack/result; Windows and Ubuntu acceptance.
- Existing dcp_terminal_execute is a full shell for the current OS user, not the diagnostic allow-list. Keep OS permission boundaries; do not add privilege escalation or claim root/admin.
- Cross-platform DC66 focused acceptance: Ubuntu 24.04 PHP 8.3.6 and Windows 11 bundled PHP 8.5.13 both pass MCP host/test syntax and DCP_MCP_KEEPALIVE_LOGO_E2E_PASS. Full suite remains macOS-only; temporary fixtures cleaned.


## DC67 checkpoint — 2026-11-25
- User clarified that Q005 is closed and must not be reopened; Q012 Tasks 6 and 7 are complete. Updated queue accordingly.
- Removed the old restricted terminal interface. DCP exposes only dcp_terminal_execute for local full-shell use as the current OS user. The remote terminal remains disabled until the target device has a local approval flow bound to the exact command hash and signed task.
- Full platform suite passed after terminal consolidation: ALL PLATFORM TESTS PASSED.
- Began Q015 Resource Mesh: local resource registry/broker service, SQLite schema, local REST routes and four MCP tools added; Cloud MySQL schema + upgrade migration and authenticated resource/team/grant endpoints added.
- Added local registry and Cloud contract tests. Syntax checks passed for the resource service, DB schema, MCP registry, API controller, bootstrap and Cloud index after fixing SQL quoting. Re-run the complete suite after integrating resource tests; no release, commit, push, or Cloud production deployment.
- Next: complete resource invocation quotas/concurrency and team grant discovery; add Cloud resource contract tests to CI; build durable terminal session daemon; implement target-device terminal approval inbox and signed Cloud dispatch; then Hermes adapter and Windows/Ubuntu acceptance.


## DC67 checkpoint — 2026-11-25
- Q005 is closed per user confirmation. Do not reopen it or list it as a blocking task. Q012 Tasks 6 and 7 remain complete; Q012 Task 5 execution foundation remains implemented locally. No return to Q005.
- DCP terminal principle: one command tool only, dcp_terminal_execute. Removed dcp_run_safe_terminal and the restricted allow-list. Local commands run as the current OS user. Remote terminal is not executable by pairing or requester consent alone.
- Durable terminal session daemon now shares interactive shell sessions across independent TerminalService instances, STDIO MCP, local HTTP MCP and provider tool calls. HTTP MCP session start/read/stop E2E passes. Native PTY/ConPTY and daemon crash recovery remain open.
- Q015 local Resource Registry/Broker, SQLite grants/invocation ledger, four MCP tools and REST endpoints are implemented. Cloud resource/team/grant tables, upgrade migration and authenticated resource endpoints are implemented. Raw credentials never enter Cloud metadata.
- Cloud target terminal request inbox requires explicit terminal.request scope, exact command hash and target-side approval/rejection. Approval does not dispatch or execute. Signed delivery and target UI remain open.
- Full suite after these changes: ALL PLATFORM TESTS PASSED. No commit, push, release, Cloud production migration/deploy, or stable install modification.
- Next: target-device approval UI + signed remote dispatch/results; actual vault-backed resource invocation; Hermes adapter; Windows/Ubuntu validation of current DC67 changes; live Claude reconnect/logo check.

- DC67 follow-on: Added source-side Terminal target selector populated only from paired devices that granted terminal.request. Remote commands are sent as exact-hash approval requests and are not executed by the source. Target Devices page shows requester, exact command, working directory, shell, expiry and SHA-256; approve/reject is explicit and approval-only. Full platform suite passed after these additions.


## DC68 checkpoint — 2026-11-25
- User direction: commit the accumulated DCP work, close DCP MCP locally and in Cloud, then continue Q015 and later queue work without reopening Q005. Q005 remains closed; Q012 Tasks 6 and 7 remain complete.
- Commits created locally: `8967bc0` (DCP Core/MCP and Resource Mesh foundations), `c2998ec` (scoped read-only Cloud MCP control plane), `c9620e5` (owner-local AI resource invocation and signed remote-result UI). Branch is three commits ahead of origin; no push/release.
- Local MCP closure: STDIO keepalive/logo and loopback Streamable HTTP use the shared registry with 35 provider-neutral tools. Added `dcp_resource_invoke` for owner-local Ollama and configured OpenAI-compatible resources.
- Cloud MCP closure in source: five read-only scoped tools for account devices, pairings, resources, teams and grants. Added per-token scopes to fresh schema and idempotent migration; scoped MCP tokens cannot call ordinary Cloud REST APIs. Resource discovery filters active user/team/device grants and never returns owner-local secret references.
- Q015 progress: owner-local provider secret references resolve through encrypted ProviderService at invocation time; invocation ledger records success/failure and agent plans require exact approval. Signed remote terminal dispatch is bound to target approval/hash, verified on the target, executed once as current OS user, redacted and signed on return; source UI can retrieve/display the result.
- Validation: `php platform/tests/run.php` — ALL PLATFORM TESTS PASSED; PHP lint for all platform PHP files, `node --check platform/public/assets/app.js`, `git diff --check`, local MCP STDIO/HTTP E2E, Cloud MCP SQLite service/contracts, resource invocation tests and signed remote-terminal contracts pass.
- Cloud public health returned `{"ok":true,"database":"ok","schema":"ok"}`. This is the existing deployed health route and does NOT prove the new Cloud MCP code or token-scope migration is deployed. No production code upload or DB migration performed. The local MySQL daemon belongs to the existing DiGEMA data directory; it was not accessed or modified.
- Production gates: DCP Cloud must receive a protected `DCP_CLOUD_SIGNING_SEED`; `api_tokens.scopes_json` migration must be applied; Q007 APP_KEY approval remains pending. Never commit secrets or bypass the secret-file guard.
- Remaining Q015: Cloud owner-node resource invocation/proxy with signed result and quotas/concurrency; local team/device grant discovery parity; Hermes/provider-neutral universal gateway; real Mac↔Windows/Ubuntu two-device acceptance; native PTY/ConPTY and terminal daemon crash recovery.
- Stable installed v1.0.0 and existing DiGEMA MySQL/model data were not modified. `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json` remains untracked and was intentionally excluded.
