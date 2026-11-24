# Q005 — Managed PHP runtime and clean packages

Status: PARTIAL — active release gate (DC0005)
Source of truth: `WORK-QUEUE.md`
Scope: synchronized Windows, Linux (Ubuntu 24.04 and Kali compatibility), macOS Intel + Apple Silicon packages; managed PHP runtime; online installers; packaged Updater + Delta Update on every OS; install/launch/health/data-preservation and upgrade tests.

## Goal
Ship a synchronized, reproducible, checksum-verified DCP release across all supported desktop OSes. Include managed PHP runtimes, correct Linux package layout, complete online installers, and real packaged Updater + Delta Update on every OS. Preserve user data and system services.

## Verified existing behavior
- `platform/scripts/verify-runtime.php` requires PHP 8.2+ and curl, intl, mbstring, openssl, pdo_sqlite, sodium and fileinfo.
- Windows installer uses official PHP 8.5.13 NTS x64, verifies archive SHA-256, installs into isolated DCP_ROOT, and reuses the existing compatible runtime on a second run. Windows package-copy smoke passed previously.
- Existing system PHP and services must remain untouched; system-first / managed-fallback policy is accepted.

## DC03 implementation — completed locally
Files changed or added:
- `platform/scripts/install-runtime-macos.sh`
- `platform/tests/runtime-macos-installer.sh` (new)
- `platform/wrappers/macos/main.m`
- `platform/dcp-env/manifests/php-macos.craft.yml` (new)
- `platform/dcp-env/manifests/php-macos.ini` (new)
- `platform/dcp-env/manifests/php-runtime.json` (new)
- `platform/dcp-env/README.md`
- `.github/workflows/build-php-runtime-macos.yml` (new reusable + manual workflow)
- `.github/workflows/release-macos.yml`
- `.github/workflows/test-macos-package.yml` (new, non-publishing CI)
- `.github/workflows/test-windows-package.yml` (new)
- `platform/docs/IMPLEMENTATION-CHECKLIST.md`
- `ROADMAP.md`
- `WORK-QUEUE.md`
- `PROJECT-MEMORY/` continuity files

### Installer and wrapper behavior
- System PHP is checked first: PATH, `/usr/local/bin/php`, then `/opt/homebrew/bin/php`.
- Managed PHP is checked only after system candidates fail.
- Managed runtime requires a valid 64-character SHA-256 file, matching binary digest, `php.ini`, and `conf.d/` before it is executed.
- Unknown CPU architectures are rejected; only x86_64 and arm64 are mapped.
- The macOS wrapper checks the full DCP extension set, verifies the managed PHP SHA-256 using CommonCrypto, and sets `PHPRC`/`PHP_INI_SCAN_DIR` only for DCP-managed PHP. It does not override system PHP configuration.
- No Homebrew install, service startup, Apache change, global PATH change or user-data change is performed by this installer.

### Installer tests — verified locally
Command: `bash platform/tests/runtime-macos-installer.sh`

Result: **6/6 passed**
1. Compatible system PHP is preferred.
2. Managed PHP is selected when system PHP is absent.
3. Managed PHP is selected after an incompatible system PHP.
4. Managed PHP with mismatched SHA-256 is rejected.
5. Managed PHP without a checksum is rejected.
6. Unsupported architecture is rejected.

### StaticPHP and PHP build contract
- Builder: StaticPHP v2.8.5, immutable commit `4318ef8fa32a02460ec1554746674a7bc42b49fa`.
- StaticPHP v3 is documented as alpha; v2.8.5 is used for conservative CI validation.
- PHP target: 8.4.28, official PHP security release dated 2026-11-19.
- Official PHP tar.xz SHA-256: `0c8ec360e5dd8154f270b2fd50cd44ecc824e6ee39c85f911312b80d4daecd72`.
- StaticPHP v2.8.5 accepts patch-level PHP version strings and the PHP releases endpoint returned exactly 8.4.28 when queried by that version.
- v2 documentation lists PHP 8.4 as stable and PHP 8.5 as beta/partial; PHP 8.4.28 is chosen for compatibility with this pinned builder, not because it is the newest PHP branch.
- Required and additional extensions are declared in `php-runtime.json` and `php-macos.craft.yml`.
- Runtime ini points cURL/OpenSSL CA validation to macOS `/etc/ssl/cert.pem`; each CI runtime must pass a real HTTPS/TLS request before it is packaged.
- Native runner matrix: `macos-15-intel` for x86_64 and `macos-15` for arm64; both require macOS 12.0 minimum because the pinned prebuilt ICU dependency contains objects built for macOS 12.0.
- The local Mac has only ~8.3 GiB free; do not compile locally.

### CI and packaging — configured, not yet run
- `.github/workflows/build-php-runtime-macos.yml` checks out the pinned builder commit, builds PHP 8.4.28, verifies the official source SHA-256, required extensions, HTTPS/TLS, linked libraries, minimum deployment target and third-party license output. It uploads one runtime artifact per architecture.
- `.github/workflows/release-macos.yml` calls the reusable workflow, downloads both runtime artifacts, verifies checksums, embeds runtimes in the portable package, and checks that runtime binaries are present while build caches and source trees are excluded.
- `.github/workflows/test-windows-package.yml` builds a clean Windows package without publishing and checks required files, managed PHP verification, and developer key/database exclusion.
- DCP repository is public; release package can distribute the managed runtime payloads.
- These workflows have only been syntax-parsed locally. They are not verified until pushed and run in GitHub Actions.

## Local verification checkpoint — 2026-11-23
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- `php platform/scripts/verify-runtime.php`: host PHP 8.5.9 and all required extensions available.
- PHP lint across `platform/**/*.php`: passed.
- `node --check platform/public/assets/app.js`: passed.
- `bash -n` for macOS installer, macOS installer tests and bootstrap/build shell scripts: passed.
- `bash platform/tests/runtime-macos-installer.sh`: 6/6 passed.
- Objective-C wrapper compiled with clang for x86_64 (10.15 target) and arm64 (11.0 target): passed.
- Ruby YAML parsing passed for macOS runtime workflow, macOS release workflow, Windows package CI workflow and craft recipe.
- JSON parsing passed for runtime manifests.
- `git diff --check`: passed.
- `actionlint` is not installed; GitHub Actions workflow semantic validation remains pending.
- No commit, push, release, deployment, Apache change, tunnel exposure or alpha.8 modification.

## First GitHub Actions feedback — commit 4752f9d
- Windows workflow run `45270962681` failed at `Build clean portable Windows package`: compiler error CS0136 because a nested WebView2 local named `userData` shadowed the DCP data directory local. Fixed by renaming the WebView2 local to `webViewData`.
- macOS workflow run `45270962540`: ARM64 reached StaticPHP download but received HTTP 403 from the GitHub API endpoint for `static-php-cli-hosted` releases. Added `GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}` to the `spc download` step so the API request is authenticated with the workflow's read-only token. Intel was still installing builder dependencies at last check.
- The fixes require a follow-up commit and fresh CI runs. Neither initial run is considered passed.

## Second GitHub Actions feedback — commit 41cd3d3
- Windows clean-package run `45271261380` and existing Windows app build run `45271261254` both passed the C# compile and PHP 8.5.13 managed runtime installation, then failed at `platform/scripts/build-windows.ps1:84`: PowerShell `-replace` received a single backslash regex and rejected it. Fixed the line to use `\\` as the regex for a literal path separator; local PowerShell test converted `platform\foo\bar` to `platform/foo/bar` successfully.
- macOS run `45271261590` is still active after the `GITHUB_TOKEN` fix; inspect both architecture jobs through source download, compilation, HTTPS/TLS and package assembly.
- The Windows path fix is in the working tree and needs a focused commit/push to trigger fresh Windows CI.

## Third GitHub Actions feedback — commit 810834c
- Clean Windows package workflow `45271742595` passed: app/package build, PHP 8.5.13 managed runtime, runtime verifier, required package files, delta payload and developer key/database exclusion checks all succeeded.
- Existing Windows app workflow `45271742512` built the app and installed PHP successfully, but its final assertion still expected the legacy `platform/dist/windows/runtime/php/php.exe` path. Updated `.github/workflows/test-windows.yml` and `.github/workflows/release-windows.yml` to assert `platform/dcp-env/runtime/php/windows-x64/php.exe` and the new `DCP-UserData` boundaries.
- macOS run `45271261590`: both Intel and Apple Silicon passed builder setup, source download and PHP source SHA-256 verification; both are still compiling PHP. TLS, deployment target, license, artifact upload and package assembly remain pending.
- Updated Windows workflow assertions are in the working tree and need a focused commit/push for a fresh Windows app CI run.

## Fourth GitHub Actions feedback — commit dcfdaf4
- Windows clean-package run `45272037581` passed: the updated package builder, managed PHP 8.5.13, runtime verifier, payload checks and DCP-UserData exclusions all succeeded.
- Existing Windows app build run `45272037351` passed: C# app build, PowerShell parser checks, managed PHP and package assertions all succeeded with the corrected runtime path.
- Cloud PHP test run `45272037799` passed.
- macOS run `45271261590` is still compiling PHP 8.4.28 on both native architectures after passing dependency download and official source SHA-256 verification. Runtime extension/TLS/deployment-target checks and package assembly are still pending.

## Fifth GitHub Actions feedback — macOS runtime run 45271261590
- ARM64 PHP 8.4.28 compiled successfully. The runtime verifier passed all required extensions, the real HTTPS/TLS request passed, and the binary linked only to macOS system libraries.
- The verification step then failed only at StaticPHP's license dumper: its prebuilt OpenSSL archive (`openssl-aarch64-darwin.txz`) contains the library but not `LICENSE.txt`, while StaticPHP v2.8.5's source metadata expects that file and its dumper fallback is `buildroot/source-licenses/openssl/0.txt`.
- Added a workflow fix to read `OPENSSL_VERSION_TEXT` from the built PHP binary, fetch the exact matching OpenSSL upstream `LICENSE.txt` from the corresponding `openssl-<version>` tag, verify it contains the Apache License notice, and place it in StaticPHP's documented license fallback path before running `spc dump-license`. The exact OpenSSL 3.5.4 upstream license URL was independently verified.
- Intel build in run `45271261590` was still compiling at the time of this update. The license fix was committed and pushed in `64eaf1c`, but the next run `45272657258` failed earlier during `spc craft`: the prebuilt `pkg-config-aarch64-macos` download hit GitHub API HTTP 403. The `GITHUB_TOKEN` had been added to the earlier `spc download` step but not the separate `spc craft` step. Added the same read-only token to the compile step; this correction is in the working tree and needs a focused push/rerun. The OpenSSL license fallback has not yet been exercised by a full successful compile.

## DC13 user-approved release decision — 2026-11-24
- User explicitly approved publishing a new unified `v1.0.0-alpha.10`; leave published `v1.0.0-alpha.9` untouched as historical evidence.
- Every supported OS must have a real packaged Updater and Delta Update, not just an online installer or Git checkout pull.
- Required behavior: discover the latest complete release; compare version/revision; validate manifest and every file size/SHA-256; download only changed files when a compatible base exists (otherwise full update); stage outside live app; preserve DCP-UserData, SQLite and master keys; apply with rollback; health-check and restart; report update result.
- Required end-to-end upgrade test: install/retain alpha.9 baseline, upgrade to alpha.10 using updater on Windows and Ubuntu/Linux; macOS adapter must pass native package/update CI and Intel smoke. Kali compatibility must be covered by Linux package/installer checks or an explicit documented support boundary.
- Q005/DC0005 must not close until all package CI, target install/launch/API checks, updater/Delta Update, rollback and data-preservation criteria are evidenced. Only then start PHP feature development as a separate milestone.

## Remaining acceptance criteria
- [~] Pinned StaticPHP/PHP build recipe and source checksum verification are configured; validate actual native builds in CI.
- [ ] Build native Intel and Apple Silicon PHP archives on GitHub Actions.
- [ ] Verify extensions and real HTTPS/TLS request from each built runtime.
- [ ] Verify minimum macOS deployment targets and absence of Homebrew dylib dependencies.
- [~] Runtime artifact embedding and package exclusion checks are configured in both test and release workflows; validate the full macOS package in CI.
- [x] Installer verifies managed PHP SHA-256 before execution and preserves system-first behavior.
- [x] macOS wrapper sets private PHPRC only when the managed runtime is selected and verifies its SHA-256.
- [x] Isolated installer tests cover system-first, fallback, bad/missing checksum and unsupported architecture.
- [x] Clean Windows package CI and existing Windows app build CI both passed on commit `dcfdaf4`.
- [ ] Test clean installs and app launch on real Intel Mac, Apple Silicon Mac and Windows machine.

## Next actions
1. Final review of only the Q005 files; preserve all unrelated dirty changes.
2. Obtain explicit approval to commit/push the current branch so GitHub Actions can run the macOS runtime matrix and Windows package workflow.
3. Fix any remaining CI failures, especially StaticPHP build config, TLS CA trust, deployment target, package artifact transfer and Windows package paths.
4. Perform clean-machine launch tests on Intel Mac, Apple Silicon Mac and Windows.
5. Close Q005 only after all acceptance criteria pass; then begin Q006.

## Risks / blockers
- StaticPHP v2.8.5 + PHP 8.4.28 is a pinned candidate; no actual binary has yet been built or executed.
- Static binaries on macOS still link Apple system libraries; verify deployment target and dylib list.
- OpenSSL/cURL CA handling must pass the HTTPS smoke test.
- GitHub Actions workflow semantic validation and execution are pending.
- Existing working tree contains many unrelated modified, renamed and untracked files; never stage/commit wholesale.


## DC04 — 2026-11-24 update
- Windows build CI `45273110655` passed; Cloud PHP CI `45272657130` passed.
- macOS runtime CI `45273110645` built PHP 8.4.28 for Intel and ARM64 and passed extension, HTTPS/TLS, dylib and minimum deployment-target checks. Both jobs failed at license dumping.
- Reviewed the pinned StaticPHP v2.8.5 `LicenseDumper.php` source at commit `4318ef8fa32a02460ec1554746674a7bc42b49fa`. The loader first checks `SOURCE_PATH/openssl/LICENSE.txt`; its fallback is `BUILD_ROOT_PATH/source-licenses/openssl/0.txt`.
- Corrected local `.github/workflows/build-php-runtime-macos.yml` to install the exact version-matched OpenSSL license in both paths and assert both files exist before `spc dump-license`.
- Validation: Ruby YAML parse passed; `git diff --check` passed; `php -l platform/cloud/index.php` and `php -l platform/index.php` passed.
- The correction is local and uncommitted. It needs an approved focused push and fresh native Mac CI. Q005 remains partial.


## DC05 — 2026-11-24: GitHub installer and Ubuntu target
- User clarified that `amradel.com/dcp` is the document-root path serving `dcp.amradel.com`; treat it as one deployment, not two independent projects.
- User authorized replacing the old Windows application using the official GitHub online setup, and added Ubuntu 24.04 as a required GitHub online-install target. Preserve old Windows installation as rollback backup and carry forward its SQLite/key data until the new app is verified.
- Corrected macOS PHP workflow root cause: StaticPHP derives `SOURCE_PATH` and `BUILD_ROOT_PATH` from its current working directory. The previous license dump step ran from GitHub Workspace instead of `$SPC_DIR`. The workflow now changes to `$SPC_DIR` before invoking `bin/spc dump-license`; the matching OpenSSL license is populated in both supported locations.
- Fixed Linux package launcher bug: generated `Run-DCP.sh` previously invoked `install-runtime-linux.sh` through PHP even though it is a shell script. It now executes the script directly, then runs the PHP verifier.
- Linux package now includes managed environment manifests/installers but excludes local build caches, compiled engines and virtual environments.
- Reworked `platform/Install-DCP-Online.sh` to detect macOS/Linux. Linux installer selects a release containing both `DCP-Linux-x64.tar.gz` and its SHA-256, verifies checksum, preserves user data, keeps a rollback backup, installs Ubuntu 24.04 GTK/WebKit/PHP dependencies, installs the desktop launcher, and only launches when a graphical session exists.
- Added `.github/workflows/release-linux.yml` for tagged Ubuntu 24.04 x64 builds and release assets. Linux CI `.github/workflows/build-linux.yml` now runs on all branches. Serialized Windows/macOS/Linux tag release workflows with the same concurrency group.
- Windows online installer now invokes the package prerequisite setup with `-InstallSystemPrerequisites`, installing VC++/WebView2 only when missing.
- Local checks: bash syntax, PowerShell parser, YAML parsing for five workflows, PHP lint, runtime manifest JSON, current-state JSON, and `git diff --check` all passed.
- Ubuntu target: key SSH works; Ubuntu 24.04 LTS x86_64, 2 vCPU/7.7 GiB RAM, 49 GiB free; passwordless sudo is available. No app or packages installed yet. The host has WebKitGTK 4.1 runtime present, and Ubuntu 24.04 GTK runtime package is `libgtk-3-0t64`.
- Still pending: push this focused packaging change, run Mac Intel/ARM64 runtime and package CI, run Linux build/release CI, publish a new synchronized release (not alpha.8), then install/verify on Windows and Ubuntu.


## DC06 — 2026-11-24: per-OS user-data migration
- Critical review found that all desktop wrappers use `DCP-UserData/<OS>`, while legacy Windows data was under `platform/data` and `platform/storage`. Copying legacy files back into the old paths would make the new app create a blank profile.
- Updated the Windows GitHub installer to stop DCP gracefully, back up the old app directory, preserve the existing `DCP-UserData` tree, migrate legacy SQLite/storage into `DCP-UserData/Windows`, and verify the migrated `dcp.sqlite` and `keys/master.key` SHA-256 before setup/launch. If the app does not exit cleanly, the installer aborts before changing data.
- Updated the macOS online installer to preserve `DCP-UserData` and migrate legacy `platform/data` + `platform/storage` into `DCP-UserData/macOS`.
- Updated Linux package layout and online installer to use `DCP-UserData/Linux`, preserving existing DCP-UserData and migrating legacy directories only when the new database/key do not already exist.
- Windows package builder now creates empty `DCP-UserData/Windows/data` and `storage` directories. Linux package builder creates `DCP-UserData/Linux/data` and `storage`; macOS package workflows stage the corresponding directories.
- Updated Windows/Linux/macOS CI assertions to require per-OS data boundaries and exclude actual SQLite/master.key files from release packages.
- Updated AGENTS.md scope to include Ubuntu 24.04 x86_64 as explicitly authorized; updated WORK-QUEUE Q004 to correct the Hostinger document-root mapping and distinguish Cloud API root from the separate web-preview entry.
- Validation after changes: PowerShell parser, bash syntax, YAML parse for seven workflows, platform PHP tests (all passed), macOS runtime installer tests (6/6 passed), git diff --check. Native CI rerun still pending.


## DC07 — 2026-11-24: enforce synchronized multi-OS releases
- Added a complete-release gate to Windows, macOS and Linux online installers. A release is installable only if the same tag contains `DCP-Windows-x64.zip`, `DCP-Windows-update.json`, `DCP-macOS-universal.zip`, `DCP-Linux-x64.tar.gz` and its SHA-256 sidecar.
- Applied the same release completeness gate to the Windows and macOS background auto-updaters. A partially published tag is ignored; devices remain on the previous complete release rather than updating to different versions.
- Confirmed the currently published GitHub releases contain no complete synchronized package set; therefore the installers will refuse the existing alpha.8 release and will not replace any device with stale assets.
- Updated installer documentation to explain the complete-release gate.
- PowerShell parser, bash syntax, seven workflow YAML parses and macOS Objective-C syntax check passed. Windows C# project built successfully on Mac with 0 errors; one nullable warning from the new release-asset filter was corrected immediately and needs a clean rebuild.
- Remaining: commit/push DC07, verify Windows build CI, Mac/Ubuntu package CI and both native Mac runtime jobs, then proceed to Q006/Q007 before publishing a synchronized release.


## DC08 — 2026-11-24: verify data migration on all desktop platforms
- Extended the macOS and Linux online installers to byte-compare migrated `dcp.sqlite` and `keys/master.key` against the preserved source after copying into `DCP-UserData/macOS` and `DCP-UserData/Linux`. A mismatch aborts installation and triggers rollback.
- Windows already performs SHA-256 comparisons for the same two files after migration into `DCP-UserData/Windows`.
- Local Bash syntax check passed after adding `cmp -s` checks. This change is intentionally limited to the shared shell installer and does not alter the native PHP runtime build.


## DC09 — release version metadata drift found
- Source audit found inconsistent version strings: PHP config reports 1.0.0-alpha.8, About/app.js cache strings report 1.0.0-alpha.7, macOS Info.plist reports 1.0.0-alpha.9, and Windows csproj fallback is 1.0.0-alpha.7. Windows release builds override the csproj from the tag, but PHP/UI metadata remains stale.
- Before publishing a synchronized release, introduce one version source (e.g. platform/version.json) and have Windows/macOS/Linux package builders set it from the same Git tag; PHP bootstrap, About page, asset cache keys and desktop bundle metadata must all report the same version.
- Do not publish or install until version consistency checks pass for all three package artifacts.


## DC10 — 2026-11-24: correct macOS minimum OS target
- Native PHP build logs showed ICU static objects built for macOS 12.0 while the x64 runtime was linked with a 10.15 minimum. This is not safe to advertise as Catalina-compatible.
- The development Mac reports macOS 14.7.1, so the supported minimum is now explicitly macOS 12.0 for both Intel and Apple Silicon.
- Updated both matrix deployment targets to 12.0, both PHP runtime manifest minimumMacOS values, universal wrapper clang targets, DCP.app LSMinimumSystemVersion, macOS online installer preflight, INSTALL.md and clean/release package assertions.
- CI now asserts vtool minos 12.0 for PHP and PlistBuddy LSMinimumSystemVersion 12.0.0 for DCP.app.
- The old 10.15/11.0 run results remain historical only; new native builds are required after this correction.


## DC04 continuation — 2026-11-24 (latest native CI)
- On commit 8705c38, ARM64 managed PHP 8.4.28 build and all verification passed; artifact uploaded (23,324,090 bytes).
- Intel x64 managed PHP job 138122769491 is still compiling; the compile step began 2026-11-24T00:36:52Z. Captured logs show optional GNU FTP source timeouts for gettext/libunistring/libidn2 and compiler warnings, but no fatal CMake/compiler errors so far. Do not modify the build recipe unless the job fails with a specific root cause.
- macOS clean-package smoke remains queued behind both architecture artifacts. It will verify runtime checksums/embedding, universal app build/signing/version/minimum OS, authenticated Apple Silicon llama.cpp smoke, Intel SSE4.2 build and private-data/cache/source exclusions.
- Development Mac static verification: all 41 PHP files linted; JS/Bash/JSON/all 9 workflow YAML checks passed; platform suite all passed; macOS installer 6/6 passed; local system PHP reuse smoke passed.
- Windows and Ubuntu SSH access revalidated; Windows original user's SQLite/key files are present and untouched. Installer must run in amradeldotcom's interactive context, not dcpadmin SSH context. Ubuntu GUI Wayland session and passwordless sudo verified; no target machine modified.


## DC04 follow-up — both native managed PHP runtimes passed
- GitHub run 45297880160: Intel x64 job 138122769491 and Apple Silicon arm64 job 138122769825 both completed successfully.
- Both native builds compiled PHP 8.4.28, passed all required/additional extension verification, real HTTPS/TLS smoke, macOS system-dylib-only checks, minimum macOS 12.0 target, OpenSSL license recovery/dump, SHA-256 generation and artifact upload.
- Artifacts present: `dcp-php-runtime-macos-x64` 23,027,980 bytes and `dcp-php-runtime-macos-arm64` 23,324,090 bytes.
- The dependent `macos-package-smoke` job 138130032417 has started. Runtime artifact download, checksum verification, embedding and PHP verifier checks passed. It is currently installing PHP CLI/CMake; app build, llama.cpp smoke/build and final package inspection are pending.

- macOS package smoke job 138130032417 has completed runtime artifact embedding/checksum verification and PHP CLI/CMake setup. It is now building the universal macOS application; Apple Silicon llama.cpp smoke, Intel SSE4.2 build and final package inspection are still pending.

- macOS package smoke advanced: runtime embedding/checksum checks passed; PHP CLI/CMake setup passed; universal app build/signing/version/minimum-OS checks passed; official Apple Silicon llama.cpp download, SHA-256 verification and arm64 `llama-server --version` smoke passed. Intel SSE4.2 llama.cpp build is currently running; package assembly/private-data exclusion checks are queued after it.


## DC04 CI failure diagnosis — Intel SSE4.2 engine portability
- macOS package smoke job 138130032417 failed while linking the Intel SSE4.2 `llama-server`; unresolved OpenSSL/X509 symbols came from `libcpp-httplib.a`. The PHP runtime, universal app build and Apple Silicon llama.cpp smoke had already passed.
- Root cause: the x86_64 SSE4.2 engine was being cross-built on the arm64 `macos-latest` runner while CMake discovered that runner's Homebrew OpenSSL 4.1.0. The current local x86_64 engine confirms the portability risk: `otool -L` shows dynamic dependencies on `/usr/local/opt/openssl@4/lib/libssl.4.dylib` and `libcrypto.4.dylib`; its LC_RPATH points back into the development build directory. The prior `DYLD_LIBRARY_PATH` smoke masked the latter.
- Prepared focused fix locally (not committed/pushed): new reusable `.github/workflows/build-llama-macos-sse42.yml` builds on native `macos-15-intel`, compiles pinned OpenSSL 3.5.4 statically for macOS 12.0, verifies the pinned source commit, passes static libraries to CMake, and uploads a dedicated x86_64 engine artifact with both llama.cpp and OpenSSL licenses.
- `build-macos-generic.sh` now requires the pinned static OpenSSL prefix/license, links OpenSSL statically, removes build-tree rpaths, adds `@loader_path` to the executable and dylibs, rejects non-portable/Homebrew/OpenSSL dylib dependencies, and smoke-tests `llama-server --version` without `DYLD_LIBRARY_PATH`.
- Updated both `test-macos-package.yml` and `release-macos.yml` to consume the Intel engine artifact from the reusable native-Intel workflow; package jobs remain on Apple Silicon to verify both PHP architectures and the ARM64 engine smoke. Package checks now require the OpenSSL license file.
- Local validation after edits: Bash syntax, all 10 workflow YAML files, CURRENT-STATE JSON and `git diff --check` passed. Full platform tests and previous runtime installer tests remain green.
- No commit/push/release performed. Explicit approval is still required before committing/pushing this focused CI/build fix so the macOS package workflow can rerun.


## DC04 continuation — 2026-11-24
- User authorized completing the full release and validation sequence.
- Commit `76deefc` adds the native Intel static OpenSSL 3.5.4 + relocatable SSE4.2 llama.cpp build; static OpenSSL CI step passed in run `45301931389`, llama.cpp compile still running.
- Commit `1b07ec5` makes alpha/beta releases prereleases and prevents them becoming latest; commit `254fdce` isolates the concurrency group per release workflow/tag.
- Current branch head `254fdce`; version manifest is alpha.9/build 9. Local platform suite, PHP lint, JS/Bash syntax, all workflow YAML, version JSON and diff check pass.
- Windows app build and clean package CI passed on `254fdce` (`45302111689`, `45302111680`); Cloud PHP CI passed (`45302111830`).
- Native macOS PHP Intel/ARM64 jobs and Intel SSE4.2 llama.cpp build are running in `45301931389`; Ubuntu Linux build is running in `45301931567`. Latest macOS package workflow on `254fdce` is queued.
- No tag/release or target install has been made. Existing alpha.8 remains incomplete. Q005 remains partial until complete synchronized release assets and real target-device installs/launch/data checks pass.


## DC04 update — lipo syntax correction
- Intel SSE4.2 llama.cpp build now completed successfully on Intel runner; the previous CI failure was solely an invalid `lipo` argument order in the post-build architecture verification.
- Fixed and pushed as `9b531a4`: `lipo "$binary" -verify_arch x86_64`. Verified locally against an existing x86_64 Mach-O.
- Latest clean macOS package workflow `45303462099` on `9b531a4` is queued; its Intel native build job `138141437648` has passed checkout/CMake and is compiling pinned static OpenSSL; other PHP jobs queued.
- Linux build `45301931567` remains in its apt dependency-install step; no failure has been reported.
- Windows build and Cloud PHP on `9b531a4` passed.


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

## DC05 — Alpha 9 release and target installation validation (2026-11-24)
- Release `v1.0.0-alpha.9` is published as a prerelease (release ID `456688871`, draft=false, prerelease=true) with all six synchronized assets: Windows ZIP/update manifest, Intel macOS ZIP, Apple Silicon macOS ZIP, Linux tarball and SHA-256 sidecar.
- Release CI green on commit `8b5bf08`: Linux release run `45387266328`; Windows release `45387266284`; macOS release `45387266140`; macOS package test `45387266141`; Windows package test `45387266218`. Both macOS native package matrix jobs and publish-macos-release completed successfully.
- Intel Mac online installer test: ran `DCP_INSTALL_DIR="$HOME/Applications/DCP-Alpha9-Smoke" bash platform/Install-DCP-Online.sh` from the checked-out commit/tag source. Installer downloaded the 48.5 MiB `DCP-macOS-Intel.zip` from the complete GitHub release, installed version 1.0.0-alpha.9 and opened the app. App executable is Mach-O x86_64; app PID 77765; bundled PHP server PID 77768 on 127.0.0.1:50641.
- HTTP root returned 200; `/api/status` returned `ok:true`, PHP 8.4.28, SQLite, Ollama online, 23 plugins. The status endpoint also reports Desktop Commander and DSH as process-detected but not connected/configured; this is not a successful integration test.
- Bundled PHP runtime `/platform/dcp-env/runtime/php/macos-x64/bin/php` SHA-256 matched `php.sha256` (`8f64cba8886dab10e7ed2850b493118bbe8bb51045d293e2dcb6c7bdfec0139e`) and `verify-runtime.php` passed with PHP 8.4.28 and required extensions. Bundled llama-server is x86_64; `--version` and `--help` exit 0.
- Compared 56 tracked app/config/routes/resources/public/bootstrap source files against the installed package: zero differences. Five `platform/tests/*` files are not included in release package as expected.
- Before/after checks show original PHP service PID 92383 on 127.0.0.1:18991, original llama-server PID 99659 on 127.0.0.1:19123, and Ollama PIDs 2101/2106 remain running. New DCP app uses independent port 50641. Source hashes for `platform/version.json`, `platform/Install-DCP-Online.sh`, `platform/wrappers/macos/main.m` are unchanged from baseline. Do not terminate or reconfigure original services.
- Ubuntu online install launched via official v1.0.0-alpha.9 script downloaded from `raw.githubusercontent.com`; preflight: Ubuntu 24.04 x86_64, `/home/amr/Applications/DCP` absent, 48 GiB free, passwordless sudo available, graphical Wayland session active. `apt-get update` passed; prerequisite installation was still downloading/installing 76.6 MB of packages at checkpoint.
- Windows SSH attempts to 192.168.8.62 and legacy 192.168.8.54 both timed out; .62 ping had 100% packet loss. Desktop Commander device inventory shows only Amrs-Mac-Pro online. No Windows changes made. Reconnect before backup/installation; preserve and compare SQLite/master.key hashes.
- Created hourly `DCP Alpha 9 Watchdog` automation (Africa/Cairo). This is hourly scheduled follow-up, not a continuous sub-hour daemon; project files remain the durable source of truth.
- Remaining: finish Ubuntu install and PHP/launcher/HTTP/GUI checks; regain Windows VM access, timestamp backup, install under actual interactive user, verify DB/key hashes and launch; finish Mac comparison and remove only `~/Applications/DCP-Alpha9-Smoke`; retain original repo and services. No physical Apple Silicon device is available; native CI build/package passed.


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


## DC08 continuity infrastructure — 2026-11-24
- Windows and Ubuntu VM addresses and MACs were confirmed from the guest OSes; both SSH aliases now point to 192.168.8.99 and 192.168.8.88 and passed authentication from Amrs-Mac-Pro using locally verified ED25519 fingerprints.
- A macOS LaunchAgent `com.digipl.dcp-network-watchdog` was installed with `StartInterval=600`. It checks HTTPS reachability to a connectivity endpoint and GitHub, records state/logs under `PROJECT-MEMORY/runtime`, and writes `NETWORK-RESUME-REQUEST.json` on an offline-to-online transition.
- `plutil -lint`, `bash -n`, launchctl bootstrap/kickstart and initial HTTPS check passed. LaunchAgent is registered and waiting for its next 600-second interval; a one-shot execution naturally exits between checks.
- The DCP Alpha 9 scheduled automation remains hourly (platform minimum) and its prompt now reads the local network state/log/recovery marker and uses the verified SSH aliases.
- Limitation: neither the local LaunchAgent nor scheduled automation can refresh/reopen a disconnected ChatGPT browser/model session. The local monitor preserves recovery evidence and a durable resume marker; it does not guarantee continuous assistant connectivity.
- The two guest IPs are manually configured, not DHCP-reserved. Confirm the Parallels/router DHCP pool or exclusions to prevent address conflicts before treating them as conflict-proof permanent reservations.


## DC09 Windows install validation — 2026-11-24T15:36:15+02:00
- Windows VM 192.168.8.99 reachable. Created verified timestamped independent backup before install; 668 files / 228.66 MiB, zero failures. Original DB and master.key hashes match backup.
- Ran immutable v1.0.0-alpha.9 PowerShell installer under actual interactive user AMRWIN11\amradeldotcom after verifying installer SHA-256. Installer completed, preserved prior app in DCP.backup-20261124-153339, installed managed PHP 8.5.13 and launched DCP.exe.
- New DCP-UserData Windows SQLite DB and master.key byte hashes match pre-install baseline. Runtime verifier passed; HTTP root=200 and /api/status=200 with ok=true/database=sqlite/plugins=23. DCP.exe and managed php.exe are responsive; PHP listener 127.0.0.1:64792.
- GUI not yet accepted: remote process reported MainWindowHandle=0 and empty title. Require visual check and data/decryption confirmation before removing either rollback backup.

## DC10 — Windows visual confirmation and Ubuntu blank-window diagnosis (2026-11-24 15:46:00 EET)
- Windows screenshot confirms main DCP Overview UI rendered, service online, PHP 8.5.13, local database Ready, local PHP service online. Ollama Offline and Desktop Commander Remote Not connected remain separate outstanding states.
- Ubuntu screenshot shows a blank white DCP window. SSH inspection through explicit alias ubuntu-dcp-admin (192.168.8.88) shows app + WebKit processes alive and PHP server on 127.0.0.1:18765, but / and /api/status return HTTP 500.
- PHP error log confirms missing platform/app/Core/Database.php required by platform/bootstrap/app.php:32. This is the known defective published Alpha 9 Linux package layout, not yet evidence of a WebKit-only issue.
- Fix commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592 and CI 45398600124 are green; corrected release is not yet approved/published. Do not overwrite tag v1.0.0-alpha.9.
- Next: approval for a superseding release; install corrected Linux package and verify root/API + actual GUI rendering.


## DC11 — post-install validation reconciliation (2026-11-24T15:50:02+02:00)
- Release `v1.0.0-alpha.9` remains immutable at `8b5bf08d6291897338ca322432ccf1962e0c2ed1` with six uploaded assets. Native Intel/ARM64 macOS release/package jobs and Linux/Windows release jobs passed on the tag commit.
- Branch HEAD `0e3f41196e0819e94a8bfa99ecf95378a7a71592` contains the four-file Linux package fix. CI `45398600124` passed build, package-content checks, HTTP root/API smoke and artifact upload (artifact `14922751159`, 585,549 bytes). It is not attached to a published release.
- Windows Alpha 9 installed under `AMRWIN11\amradeldotcom`. PHP 8.5.13 runtime verifier passed; root and `/api/status` returned 200 during launch; SQLite integrity check returned `ok`; database and master-key hashes match original baseline and backups. Crypto key round-trip passed, but there were zero persisted encrypted provider/settings/mail values, so real stored-ciphertext decryption remains untested. User screenshot in DC10 confirms Overview UI. Retain all rollback backups.
- Ubuntu Alpha 9 remains broken: HTTP root/API 500, missing `platform/app/Core/Database.php`; corrected source fix is CI-green but no superseding release exists.
- Q005 remains partial. Do not close until corrected Ubuntu release/install/GUI checks and any expected legacy-secret validation are complete. Next human gate: approval to publish a superseding complete alpha release; keep published Alpha 9 immutable.

- 2026-11-24T15:51:37+02:00: reached a human approval gate. Paused the hourly DCP Alpha 9 Watchdog to avoid repeated blocked runs. The independent macOS network LaunchAgent remains enabled at 600-second intervals; it records network recovery but cannot reconnect ChatGPT.

## DC12 user decision and updater audit — 2026-11-24
- User explicitly prefers one visible version `1.0.0-alpha.9` on all platforms and asked to correct the Linux package while adding auto-update; do not publish alpha.10 unless the user changes the decision.
- Archived feature proposal docs under `platform/docs/proposals/` (Live Task Inbox spec, OpenAI feature request) and cross-platform updater design.
- Current published alpha.9 tag points to 8b5bf08; Linux fix commit 0e3f411 is a child and CI run 45398600124 passed. Existing release has six assets, but Linux archive is defective. Preserve old tag/assets/checksums before any controlled retarget/overwrite.
- Updater audit: Windows has background updater + delta manifest + apply/rollback script, but compares semantic version only and cannot detect same-version repackaging. GitUpdateService is Git-checkout-only and not a packaged updater. No packaged Linux/macOS updater was verified. Cross-platform auto-update remains unimplemented.
- Design in `platform/docs/proposals/Cross-Platform-Auto-Update.md`: keep semantic version 1.0.0-alpha.9, add monotonic `packageRevision` (target revision 2 after validating baseline), compare version+revision, use base-aware deltas/full fallback, preserve user data and support rollback on all OSes.


## DC14 — Alpha 10 updater progress — 2026-11-24T16:50:35+02:00
- User-approved target remains unified v1.0.0-alpha.10; published Alpha 9 tag/assets remain immutable. Current pushed branch feature/alpha10-package-revision HEAD ec170a695a8729ff24edbbbf2a5e46b0d82b680c.
- Windows updater now validates legacy revision 0/schema 2, official release URL/tag/version, file SHA-256/size, path/reparse safety and user-data exclusions. Apply script uses the packaged PHP path, verifies all files before mutation, restores changed files in reverse order on failure, keeps the two most recent rollback snapshots, writes update status and checks the new process remains alive for 12 seconds.
- New PowerShell transaction tests pass locally: successful apply with SQLite/master.key hashes preserved and rollback snapshot retained; forced mid-apply failure restores original files and records rolled_back; DCP-UserData replacement is rejected. PowerShell parse passed; dotnet build succeeded with one existing WindowsBase warning. GitHub clean Windows package run 45418031542 passed its updater test step.
- GitHub run 45418031428 Windows build, 45418031828 Linux build and 45418031562 Cloud PHP all succeeded on ec170a6. macOS test run 45413374893 failed because test workflow omitted DCPPackageRevision in the test bundle; fixed in 8190261. Retry 45416881645 remained queued overall at 16:50 EET with Intel PHP/llama in progress and Apple Silicon PHP/llama queued.
- VM read-only checks: Windows at .99 reachable using the dedicated external SSH config; installed DB/master.key and all retained backups match original hashes; no install performed. Ubuntu at .88 reachable; published Alpha 9 still lacks Database.php; no DCP process running; no install performed.
- Still open: Windows full-package fallback and cryptographic manifest signature/attestation; packaged Linux Ubuntu/Kali and macOS updater/delta adapters; full end-to-end Alpha 9→Alpha 10 update/rollback/data-preservation on all OSes. Do not publish Alpha 10 or close Q005 yet. Do not start PHP feature work.

- 2026-11-24T16:54:11+02:00 macOS CI follow-up: Intel llama job 138525401176 in retry run 45416881645 completed success; Intel PHP job 138525400520 compiling; Apple Silicon llama job 138525401200 in progress; Apple Silicon PHP job 138525401096 queued. Both native package matrix jobs still pending.


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


## DC16 — schema-3 signed manifest and delta package groundwork (2026-11-24 ~18:25 EET)
- Commit `2f5a5d55a6e3c100e5425bdabbf8e8c4c6b9ddfe` pushed to `feature/alpha10-package-revision`.
- Added schema-3 shared update manifest contract, RSA-SHA256 detached signature verifier with pinned public-key fingerprint, official release origin/platform/architecture checks, exact base version+packageRevision delta selection, full-package fallback selection, path/user-data protection and asset size/SHA-256 validation.
- Added tar.gz/zip delta builder comparing complete package trees, explicit deletion index, manifest generator and detached signing script. Linux builder includes `platform/update`; Windows/macOS package checks require public key/schema.
- Signing key ID `dcp-alpha10-rsa3072-20261124`; public fingerprint `bd56b87af22d81bdff536dfa332d2cdc4bf27da7f387af84cb4bdd4d201fdd67`. Private key is stored only in the Mac login Keychain (service `DCP-Alpha10-Release-Signing-Key`). GitHub Actions secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` remains unconfigured; manual repository secret setup is required. No key material is recorded here.
- Local PHP suite, manifest verifier tests, Python delta/manifest tests, ephemeral signing/tamper tests and Windows transaction tests all pass. Bash/Zsh/Python/PHP syntax, JSON/YAML parse and diff check pass.
- CI `45435155253` Ubuntu/Kali PASS; `45435155129` Linux build PASS; `45435155119` Windows build PASS; `45435155128` clean Windows package PASS; `45435155294` Cloud PHP PASS. macOS run `45435155319` is still running/queued by architecture. Prior `45425681044` passed both Intel and Apple Silicon native package jobs.
- This is foundation only: schema 3 not yet wired into native clients/release assets. Windows full fallback/signature integration, Linux packaged updater, macOS packaged updater, transaction/rollback/relaunch/health and target-device Alpha9→Alpha10 tests remain open. Do not publish Alpha10 or close Q005.

- Worktree reconciliation: untracked `.github/workflows/test-update-manifest.yml` was observed after commit `2f5a5d5` (mtime 18:18:46 EET). It is not part of HEAD, duplicates manifest test steps already wired into Linux/macOS workflows, and was deliberately left untouched/unstaged. Review before any later commit.


## DC18 — 2026-11-24T18:58:54+02:00: Linux updater transaction + Windows schema-3 client
- Commit `4ddb91e38a0a919cfe7d7bc01cfc5a74f1a88f50` pushed to `feature/alpha10-package-revision`. It contains Linux crash-safe transaction/recovery, Windows native signed schema-3 updater client, full fallback selection, PowerShell atomic replacement and tests/workflow integration. Explicit 23-file source/test/workflow commit only.
- Linux updater now uses a per-install flock; process guard refuses if DCP binary or PHP router is running and never kills anything. Health check uses temporary disposable user data. Journal marks operations recoverable before mutation, fsyncs before atomic rename, records temp files, auto-recovers interrupted apply and rolls back committed-but-unhealthy updates. Full-package deletion list is signed and explicit; arbitrary root files and DCP-UserData are preserved. Missing optional delta asset falls back to full.
- Windows client verifies pinned schema-3 signed manifests and synchronized platform manifests, official GitHub HTTPS release assets, platform/architecture, SHA-256/size, package revision and exact delta base. Full fallback selected on base mismatch or missing delta asset. C# contract tests cover semantic version/revision ordering, missing delta fallback, path protections and manifest validation. PowerShell apply/rollback tests pass.
- Local evidence: `php platform/tests/run.php`, `php platform/tests/update-manifest.php`, `python3 platform/tests/update-package-build.py`, `python3 platform/tests/extract-linux-update.py`, `bash platform/tests/sign-update-manifest.sh`, `php platform/tests/linux-update-transaction.php`, `php platform/tests/linux-updater-health.php`, `pwsh platform/tests/update-windows.ps1`, `dotnet run --project platform/tests/windows-update-client/DCP.WindowsUpdater.Tests.csproj -c Release`, Windows app `dotnet build`, PHP lint, JS syntax, Python compile, Bash/Zsh syntax, PowerShell parser and `git diff --check` all passed.
- CI on 4ddb91e: Windows build 45442801370 PASS; Windows clean package 45442801525 PASS; Linux build 45442801373 PASS; Cloud PHP 45442801543 PASS. Linux distro run 45442801351: Ubuntu build PASS, Kali dependency installation in progress. macOS native run 45442801352: Intel PHP/llama building, Apple Silicon PHP/llama queued. Earlier macOS run 45435155319 passed Intel and Apple Silicon on 2f5a5d5.
- Kali failure `45439878530` on 33bf482 was missing Python3 in the minimal container; 4ddb91e adds Python3 to Kali and Ubuntu/runtime/online installer dependencies. Wait for 45442801351 before marking distro compatibility green.
- Mac: untracked `platform/scripts/update-macos.php` is a prototype only. Not wired to `main.m` old updater flow; no durable journal/recovery and stale-PID kill path is unsafe. It is not staged. Must be replaced/reworked with shared transaction and verified DCP process ownership, wrapper recovery, runtime-port health and code-sign verification.
- Device checks are read-only only: Windows SSH .99 as dcpadmin; Ubuntu .88 as amr, Ubuntu 24.04, Python3/PHP available and no DCP process. No Windows install/replacement; no fresh rollback backup captured in this run because no install was attempted. Ubuntu Alpha9 remains defective and unchanged. Mac original project/services unchanged.
- Release ID 456688871 / tag v1.0.0-alpha.9 / commit 8b5bf08 / six assets unchanged. Alpha10 unpublished; no main merge; no PR (403). GitHub secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` still needs manual setup. Signed release assembly workflow not wired.
- Q005 remains PARTIAL. Do not publish Alpha10 or begin PHP feature development until macOS updater, signed release assembly, all CI, and target update+forced rollback/data-preservation acceptance pass. Q006/Q007 remain isolated.


## DC19 — 2026-11-24: monitor, Ubuntu package diagnosis and macOS updater guard
- Added tools/dcp-watch.sh to display live repository/queue/memory/branch/dirty-file/process state from Mac Terminal.
- Read-only Ubuntu SSH confirms old Alpha 9 package lacks platform/app and Core/Database.php; packaged updater and pinned key are absent; no DCP/PHP server is running. White screen is explained by missing application files. No target files/data changed.
- Fixed macOS updater process guard to recognize exact app argv[0] under both lexical /var and canonical /private/var roots. Added regression tests. macOS updater tests pass 40 assertions.
- Local platform, Linux package/extraction, Windows PowerShell and C# updater tests, Windows .NET build, syntax and diff checks all passed.
- Commit a8bc81fd pushed; CI runs 45447308256 manifest, 45447308173 Linux build, 45447308527 Windows build in progress; 45447308397 macOS package queued. Linux distro run 45446122964 passed Ubuntu and Kali.
- Production signing secret DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 remains missing. Preflight run 45446629180 failed only at this missing secret after all manifest/delta/ephemeral-signature tests passed. Keychain key exists; GitHub CLI absent; user must add the repository Actions secret manually.
- Alpha 9 unchanged; Alpha 10 unpublished; no target install/update. Q005 remains partial.
