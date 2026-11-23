# Q005 — Managed PHP runtime and clean packages

Status: PARTIAL — active task
Source of truth: `WORK-QUEUE.md`
Scope: macOS Intel + Apple Silicon, plus clean Windows package build/CI. Linux implementation remains deferred.

## Goal
Ship DCP with a pinned, reproducible, checksum-verified managed PHP runtime for clean Macs while preserving system-first behavior. Finish Windows private-runtime packaging with a clean CI package verification.

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
- Native runner matrix: `macos-15-intel` for x86_64 (10.15 minimum) and `macos-15` for arm64 (11.0 minimum).
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
