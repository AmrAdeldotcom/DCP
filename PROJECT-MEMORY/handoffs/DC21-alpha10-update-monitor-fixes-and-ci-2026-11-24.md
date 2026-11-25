# DC21 — Alpha 10 update-monitor fixes, regression coverage and CI reconciliation

Timestamp: 2026-11-24T20:23:42+02:00 (Amrs-Mac-Pro local time)

## Start-of-run continuity
- Read `WORK-QUEUE.md`, `PROJECT-MEMORY/INDEX.md`, `CURRENT-STATE.json`, latest handoff DC20, Q005, `runtime/network-state.json` and `network-watchdog.log` before acting.
- `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json` is absent. No resume marker was cleared or archived.
- Network monitor reports online at `2026-11-24T18:17:47Z`; the log records one offline transition at 16:27Z and recovery at 16:37Z, then stable online. The 600-second LaunchAgent only records transitions and cannot reopen ChatGPT.

## Branch and pushed source state
- Branch: `feature/alpha10-package-revision`; local and origin HEAD both `f59c086dac22789d6c5b316b5fbd03066cb0c8d0` (`f59c086`).
- Recent pushed commits: `193cfa4` (`feat(updater): poll and stage signed releases every five minutes`) and `f59c086` (`fix(updater): reuse verified release snapshot and test Linux watcher`). The prior branch head in older prompts (`ec170a6`) was stale; this run reconciled the actual local/origin branch head.
- Linux launcher now defaults `DCP_DISABLE_UPDATE_WATCH` safely under `set -u`, exposes signed `--download`, caches verified packages in DCP-UserData, and the watcher retries every 300 seconds without killing a live DCP process. Linux package smoke now checks generated launcher syntax/default and the download mode.
- macOS wrapper polls every 300 seconds and downloads/stages before prompting. Fixed a real cache predicate bug (`hasPendingPackage()` returns bool; `if ($cached !== null)` incorrectly treated `false` as cached). `checkUpdate()` now accepts the already verified release snapshot so `downloadUpdate()` does not perform a second feed lookup and risk mixing two release snapshots. Tests cover absent cache, exact-base Delta cache, base mismatch and Full fallback cache.
- Windows wrapper polls every five minutes; cancellation is handled safely when monitoring stops. No Windows updater/install action was run in this checkpoint.

## Local validation evidence
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- `php platform/tests/update-macos.php`: ALL MACOS UPDATE CONTRACT TESTS PASSED (52 assertions).
- `php platform/tests/linux-updater-health.php`: PASS, including signed download mode, five-minute watcher and safe default flag.
- `php platform/tests/linux-update-transaction.php`: PASS, including full fallback, crash recovery and protected data.
- `pwsh -NoProfile -File platform/tests/update-windows.ps1`: ALL WINDOWS UPDATER TESTS PASSED, including full fallback, data preservation and forced rollback.
- `dotnet run --project platform/tests/windows-update-client/DCP.WindowsUpdater.Tests.csproj --configuration Release`: ALL WINDOWS UPDATE CLIENT CONTRACT TESTS PASSED.
- Windows wrapper cross-target build `dotnet build platform/wrappers/windows/DCP.WebView.csproj --configuration Release -p:EnableWindowsTargeting=true`: succeeded with 0 errors and one existing WindowsBase reference-conflict warning. The first attempt without `EnableWindowsTargeting` failed with NETSDK1100 because the host is macOS; this was a host-targeting flag issue, not a source failure.
- Delta builder, Linux archive extractor, detached signature/tamper tests, four-platform signed release assembly, PHP lint, Bash/Zsh syntax, PowerShell parse, project-owned JSON/YAML parse and `git diff --check`: PASS.
- Compiled current macOS `main.m` to an object with Apple Clang without rebuilding PHP or llama runtimes: PASS.

## GitHub Actions on f59c086
- Linux WebView build `45458161076`: SUCCESS.
- Ubuntu 24.04 + Kali Rolling distro/package/API/updater smoke `45458160914`: SUCCESS. Kali steps include transaction/rollback tests, updater health, package extraction, library verification, HTTP/API smoke and packaged updater check.
- Windows build `45458161409`: SUCCESS.
- Signed manifest/delta contract `45458161245`: SUCCESS.
- Native macOS clean-package run `45458161119`: QUEUED; all four jobs (Intel PHP, Intel llama, Apple Silicon PHP, Apple Silicon llama) remain queued. Earlier runs `45457127109` and `45454117132` are also queued. GitHub connector supports read-only Actions access; no cancel/rerun/write endpoint is available in this environment.
- Windows clean package `45457125199` on `193cfa4`: SUCCESS. Do not claim a fresh Windows clean-package run on f59c086; the f59c086 Windows build and client tests passed.

## Device and release state (read-only)
- macOS `/Volumes/Data/ChatGPT/DCP/DCP.app`: version `1.0.0-alpha.10`, build `10`, package revision `1`; `codesign --verify --deep --strict` passed. Current `main.m` compiled. SQLite SHA-256 `66a301d372f1c39f17a0021a374875f6dd443cdc7ccee0812a58be5858c68374`; master.key SHA-256 `69d3daec2f37bad0a63850e41f61c04ebc2715d230f2373e1b94aafe89d36dbd`. Existing listeners remain Ollama PID 2106:11434, PHP PID 92383:18991, llama-server PID 99659:19123. No services were stopped or reconfigured.
- Ubuntu alias `ubuntu-dcp-admin`: host `amr-ubuntu24`, Ubuntu 24.04 LTS x86_64; installed version manifest remains Alpha 9. No listeners on 18765/18991. No install/update performed.
- Windows alias `windows-dcp-admin`: host `AMRWIN11`; SSH user `AMRWIN11\dcpadmin`, interactive user `AMRWIN11\amradeldotcom`; no DCP process or 18765/18991 listener. Existing `C:\DCP-Backups\DCP-pre-alpha9-20261124-152849` exists. No install/update performed and no new backup/hash baseline was taken because replacement was not attempted.
- GitHub latest releases list contains Alpha 9 as latest only. Release ID `456688871`, tag target commit `8b5bf08d6291897338ca322432ccf1962e0c2ed1`, six assets. Tag ref still points to annotated tag object `71b8378a4989844ae764db0c3ce124f0153e2cdd`. Alpha 9 remains unchanged; Alpha 10 is not published.

## Blockers and next actions
- Production GitHub Actions secret `DCP_RELEASE_SIGNING_PRIVATE_KEY_B64` remains unconfigured. The GitHub integration is GET-only for secrets and cannot set it; the local private key remains in Keychain and no key material is stored in the repository or memory.
- Native macOS CI is queued; wait for all four architecture/runtime jobs to execute and pass. Do not compile large runtimes locally.
- User explicitly said not to merge or release yet. No PR, main merge, Alpha 10 tag/release or target Windows/Ubuntu replacement was performed.
- After explicit release authorization and manual signing-secret setup: finish macOS native CI, run signed release preflight, inspect all assets, then create fresh timestamped Windows/Ubuntu user-data backups and hashes before any install. Only after all three Alpha 10 installs are verified should the harmless platform probe update and end-to-end update/rollback/data-preservation tests run.
- Q005 remains PARTIAL; Q006/Q007 remain isolated; no PHP functional feature work started.
