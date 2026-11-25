# DC15 — Alpha 10 macOS metadata fix and Kali CI

Date: 2026-11-24 17:34 EET
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `feature/alpha10-package-revision`
HEAD: `46a0753335e50c4882534bcc8300ce11fe541c14`

## User direction
Continue the approved Alpha 10 unified release work. Preserve published `v1.0.0-alpha.9` unchanged. Do not publish Alpha 10 or close Q005 until updater, delta/full fallback, rollback, target installation and data-preservation acceptance tests pass.

## Fixes pushed
- `d1e6f1a`: native macOS build script sources bundle version/build/packageRevision from `platform/version.json`; clean-package workflow asserts all three metadata fields match the package manifest.
- `59257c0`: Kali validation explicitly uses Bash.
- `46a0753`: package artifact is transported as tar.gz to preserve executable permissions across GitHub Actions artifact transfer.

## CI evidence
- `45426353230` Windows build on `46a0753`: passed.
- `45426353842` Cloud PHP on `46a0753`: passed.
- `45426353224` Linux distro workflow: Ubuntu build at archive upload; Kali stage pending at checkpoint.
- `45425681044` macOS clean-package workflow on `d1e6f1a`: Intel PHP and Intel llama build in progress; Apple Silicon jobs queued.
- `45426144391` Kali first attempt failed at `test -x DCP`; log confirms downloaded artifact did not preserve executable mode. Fixed with tar archive in `46a0753`.

## Local validation
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- PHP lint across `platform`: passed.
- `zsh -n platform/wrappers/build-macos.sh`: passed.
- `bash -n platform/scripts/build-linux.sh platform/tests/linux-package-smoke.sh`: passed.
- `platform/version.json` JSON and modified workflow YAML parsed successfully.
- `git diff --check`: passed.

## Remaining work
1. Verify Kali package extraction, ldd, PHP runtime and HTTP/API smoke on run `45426353224`.
2. Verify both Intel and Apple Silicon native macOS package jobs on `45425681044`; if stale/cancelled, trigger a fresh run on latest branch.
3. Continue true packaged Updater + Delta Update on Linux (Ubuntu/Kali) and macOS; current GitUpdateService remains git-checkout-only and macOS still routes through online installer.
4. Finish Windows full-package fallback and common signed/attested update manifest.
5. Execute real Alpha 9 → Alpha 10 update, forced rollback and SQLite/master-key/settings/workspace preservation checks on target devices.
6. Only then publish v1.0.0-alpha.10, close Q005/DC0005 and begin PHP work as a separate phase.

No Alpha 10 release published. No Alpha 9 tag or assets modified.


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
