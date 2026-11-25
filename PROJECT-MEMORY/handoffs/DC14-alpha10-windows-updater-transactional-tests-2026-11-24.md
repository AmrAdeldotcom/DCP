# DC14 Handoff — Alpha 10 Windows updater hardening and CI audit

Timestamp: 2026-11-24T16:50:35+02:00
Repository: /Volumes/Data/ChatGPT/DCP
Branch: feature/alpha10-package-revision
HEAD: ec170a695a8729ff24edbbbf2a5e46b0d82b680c

## Start-of-run durable state and resume marker
- Read WORK-QUEUE.md, PROJECT-MEMORY/INDEX.md, PROJECT-MEMORY/CURRENT-STATE.json, Q005, newest prior handoff DC13, PROJECT-MEMORY/runtime/network-state.json and network-watchdog.log before acting.
- PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json was absent. No marker was archived or cleared.
- Network monitor is the local 600-second LaunchAgent com.digipl.dcp-network-watchdog. It cannot refresh or reopen a ChatGPT session. Direct checks at 16:49 EET: gateway ping 192.168.8.1 passed; api.github.com returned HTTP 200.

## Exact release baseline
- GitHub release v1.0.0-alpha.9, release ID 456688871, published 2026-11-24T11:47:15Z, prerelease, six assets.
- Exact tag commit 8b5bf08d6291897338ca322432ccf1962e0c2ed1; annotated tag object 71b8378a4989844ae764db0c3ce124f0153e2cdd.
- Asset names/sizes: DCP-Linux-x64.tar.gz (543,743 bytes), DCP-Linux-x64.tar.gz.sha256 (87), DCP-macOS-AppleSilicon.zip (54,137,048), DCP-macOS-Intel.zip (50,891,709), DCP-Windows-update.json (8,195), DCP-Windows-x64.zip (91,563,569).
- Release tag/assets were only read. No tag retarget, release overwrite, or Alpha 10 release was performed.
- Published Linux package remains defective: platform/app/Core/Database.php missing; root/API HTTP 500. Fix commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592 and CI 45398600124 are green, but the fix is not published.

## Alpha 10 branch and CI
- Branch feature/alpha10-package-revision; pushed commits: 3f37d4d (packageRevision metadata), 8190261 (macOS test bundle revision metadata), ec170a6 (Windows updater hardening).
- Full HEAD: ec170a695a8729ff24edbbbf2a5e46b0d82b680c.
- Commit 3f37d4d: Windows build 45413374795, clean Windows package 45413374899, Linux build 45413375192, Cloud PHP 45413374999 passed.
- macOS clean-package run 45413374893 exposed a real test-workflow failure: the test app Info.plist omitted DCPPackageRevision even though release-macos.yml sets it. Native PHP/llama artifacts, architecture checks and native app build passed before the package revision assertion failed.
- Fix 8190261 now writes DCPPackageRevision into the test app bundle. Retry 45416881645 was still queued overall at 16:50 EET; x64 PHP/llama jobs were in progress and arm64 PHP/llama jobs queued. Inspect both matrix jobs before treating macOS as green.
- On ec170a6: Windows build 45418031428 passed; clean Windows package 45418031542 passed; Linux build 45418031828 passed; Cloud PHP 45418031562 passed. Windows package job step 6 explicitly reports the updater transaction test passed.
- GitHub connector draft PR creation previously returned 403 Resource not accessible by integration. Branch push is verified; do not claim a PR was created or merge the branch.

## Windows updater implementation this run
Files committed in ec170a6:
- platform/wrappers/windows/Program.cs
- platform/scripts/update-windows.ps1
- platform/tests/update-windows.ps1
- .github/workflows/test-windows-package.yml

Changes: legacy missing packageRevision defaults to 0; schema 2 requires positive revision; validate official HTTPS GitHub release manifest URL, release tag/version parity, SHA-256 format and file sizes; compare previous delta chain by version+revision; reject user data paths; correct PHP runtime path to platform/dcp-env/runtime/php/windows-x64/php.exe; reject traversal/reparse paths; verify all payload files before writes; reverse-order rollback on mid-apply failure; retain last two rollback snapshots; write update-status.json; launch updated app and require it to remain alive for 12 seconds. Temp runner self-deletes only under DCP-Update-* path.

Local verification:
- pwsh platform/tests/update-windows.ps1: success/apply + rollback snapshot + SQLite/master.key preservation PASS; forced mid-apply failure/rollback/status PASS; user-data path rejection PASS.
- PowerShell parser passed for updater, tests and Windows builder.
- PHP platform suite ALL PLATFORM TESTS PASSED; PHP lint, JS syntax, Bash syntax, Windows workflow YAML and git diff --check passed.
- dotnet build DCP.WebView.csproj with Alpha 10 version/revision succeeded, 0 errors, one existing WindowsBase reference warning.
- GitHub Windows clean-package CI run 45418031542 passed its updater transaction test step.

## Target VM checks
- Must use `ssh -F /Volumes/Data/ChatGPT/DCP-Lab-SSH/config` with aliases windows-dcp-admin and ubuntu-dcp-admin. The default ~/.ssh/config is stale and points to .62/.56; do not use it.
- Windows alias reached 192.168.8.99; hostname AmrWin11; SSH identity amrwin11\\dcpadmin. Interactive DCP owner remains amradeldotcom. Read-only hash audit: installed DB and master.key, plus all three retained backup copies, still match SHA-256 values a2f6252cb0c6984bc2c7b1a30f65e49f0d9a69f2ac2950d613e7c54ecb93fa45 and b1d13ad07a1dffc595cb63ee49f6ab0bd748c5155a142d753dd273a4ac10e00d. No DCP process running. No install/update was performed.
- Ubuntu alias reached 192.168.8.88; hostname amr-ubuntu24; enp0s5 still has static 192.168.8.88/24. No DCP/PHP server process running in latest check. Published Alpha 9 still missing Database.php. No install/update was performed.
- Guest addresses are manually configured; do not alter without DHCP conflict check.

## Critical remaining work — Q005 stays partial
1. Complete macOS retry 45416881645 (Intel and Apple Silicon) and inspect any failure logs.
2. Implement packaged updater + delta/full fallback for Linux Ubuntu/Kali and macOS Intel/Apple Silicon.
3. Implement Windows full-package fallback when delta base does not match; current Windows code still uses previous-manifest chain/raw source files.
4. Implement cryptographic manifest signature/attestation; current checks use HTTPS official origin plus SHA-256/size, not a cryptographic signature.
5. Run full end-to-end Alpha 9 → Alpha 10 update and forced rollback on Windows, Ubuntu, Mac Intel; Apple Silicon via native CI. Verify SQLite, master key, settings, workspaces, models/logs preservation.
6. Only after all tests pass, publish Alpha 10. Never rewrite Alpha 9. Close Q005 only with exact CI, package, install, update, rollback and data-hash evidence; then start PHP feature work separately.

## Files updated locally
- PROJECT-MEMORY/CURRENT-STATE.json, WORK-QUEUE.md, Q005, CHANGELOG.md, ACCESS-INVENTORY.md, this handoff, and platform/docs/proposals/Cross-Platform-Auto-Update.md.
- These are intentional local documentation changes and were not staged or committed. Never use git add . or stage unrelated memory/proposal files wholesale.

## macOS CI follow-up — 2026-11-24T16:54:11+02:00
- Retry run 45416881645: Intel llama job 138525401176 completed success; Intel managed PHP job 138525400520 is compiling; Apple Silicon llama job 138525401200 is in progress; Apple Silicon managed PHP job 138525401096 is queued. Native package matrix has not completed; do not claim macOS CI green.
