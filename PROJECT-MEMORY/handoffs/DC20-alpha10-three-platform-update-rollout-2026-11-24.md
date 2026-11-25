# DC20 — Alpha 10 three-platform rollout and update acceptance

Date: 2026-11-24 (Africa/Cairo)

## User's clarified deployment strategy
- macOS is the only primary product folder. Test new app bundles in a temporary sibling directory; replace only the verified DCP.app bundle in /Volumes/Data/ChatGPT/DCP after visual/runtime acceptance.
- Windows and Ubuntu app binaries are disposable test installs. Replace them with fresh Alpha 10 packages after backing up any DCP-UserData. Do not spend time preserving old broken app binaries.
- After all three Alpha 10 installs work, publish a harmless test file under platform code in a subsequent prerelease and verify all three installed clients download/apply it from GitHub.

## macOS Alpha 10 local install
- Built in /Volumes/Data/ChatGPT/DCP-Alpha10-Mac-Smoke.YiNqaK, not in the original project folder.
- Temporary app version/build/revision: 1.0.0-alpha.10 / 10 / 1.
- codesign --verify --deep --strict passed.
- Temporary app returned HTTP 200 for / and /api/status; visual screenshot showed the DCP dashboard rendered correctly in Safari.
- Replaced only /Volumes/Data/ChatGPT/DCP/DCP.app after the temp checks passed.
- Rollback bundle retained at /Volumes/Data/ChatGPT/backups/DCP.app-alpha7-before-alpha10-20261124-195129.
- Original macOS DCP-UserData/macOS/data/dcp.sqlite and DCP-UserData/macOS/storage/keys/master.key remained byte-identical before/after.

## Windows and Ubuntu
- Both remain on Alpha 9; no app or data files were deleted/replaced in this checkpoint.
- Windows data paths and three existing backup locations were inventoried. Existing SQLite/key backups remain available.
- Ubuntu /home/amr/Applications/DCP and DCP-UserData were inventoried read-only; app remains the defective Alpha 9 copy. ~/.local/share/DCP browser state was also identified; preserve it unless explicitly safe to remove.

## Source branch and CI
- Branch feature/alpha10-package-revision, local/origin head f59c086 (includes commits 193cfa4 and f59c086).
- main (25c81e2) is an ancestor of the feature branch; branch is 70 commits ahead. main is unprotected.
- GitHub integration PR creation returned HTTP 403 (Resource not accessible by integration); no PR was created.
- CI on 3aa58eb: Linux Ubuntu 24.04 + Kali Rolling 45454116524 SUCCESS; Windows build 45454116623 SUCCESS; Windows clean package 45454116693 SUCCESS; Cloud PHP 45454116522 SUCCESS; signed updater contract 45454116773 SUCCESS; macOS clean package 45458161119 QUEUED on f59c086; previous run 45454117132 remains queued on 3aa58eb.
- Alpha 10 is not published. Latest GitHub release remains immutable v1.0.0-alpha.9.

## Local updater validation
- Windows PowerShell transaction/full fallback/rollback tests passed.
- Windows C# schema-v3 updater contract tests passed.
- macOS updater contract tests passed (52 assertions), including five-minute polling, pre-download staging, exact-base Delta selection and Full fallback.
- Linux updater health and protected-data tests passed.
- GitHub Atom release feed tests passed.
- Four-platform signed release assembly passed: exact Alpha 9 Delta base, full-package fallback, synchronized manifests and signatures.
- Windows wrapper local build succeeded with 0 errors and one existing WindowsBase reference conflict warning. Linux package smoke on f59c086 passed, and the signed updater contract workflow passed.
- PHP lint, Bash syntax and git diff --check passed for current local edits.

## Current local (not yet pushed) update-monitor edits
- macOS wrapper polls the signed release feed every 300 seconds and suppresses duplicate prompts for the same tag/revision. It still prompts before applying; package download currently occurs after approval.
- Windows wrapper checks every five minutes. Existing PrepareAsync downloads/verifies the package before prompting; if deferred, the staged package is retained in its temporary staging folder and further checks pause for that app session.
- Linux updater supports --download to fetch/cache the signed package under DCP-UserData/Linux/storage/updates/pending; --watch polls every 300 seconds and applies the verified update after the DCP process is closed. It does not mutate a live app.
- These changes are pushed on f59c086 and passed local syntax/contract tests plus Linux package smoke and signed updater CI. They have not yet been tested against a published Alpha 10 release. Do not claim real end-to-end update acceptance yet.

## Next steps
1. Finish review/tests for local five-minute update-monitor edits.
2. Commit/push source changes and ensure all CI, including native macOS clean package, passes.
3. Fast-forward the unprotected main to the validated feature branch so the release workflow's tag-ancestor guard can pass.
4. Publish v1.0.0-alpha.10 only after all required platform packages and signed manifests are green.
5. Back up Windows and Ubuntu DCP-UserData, replace their app binaries with fresh Alpha 10 packages, then verify version, runtime, HTTP/API and GUI.
6. After all three Alpha 10 installs pass, publish a harmless platform-code probe update in the next prerelease and verify each client detects, downloads, verifies and applies it. Record exact tag, probe hash and installed version/revision.


## Additional DC20 update — 2026-11-24 20:20 EET
- Latest source commits: 193cfa4 (five-minute cross-platform update monitor) and f59c086 (reuse a single verified macOS release snapshot and add Linux package-watcher smoke assertions). Both pushed to origin/feature/alpha10-package-revision.
- Latest CI on f59c086: Linux WebView/package build 45458161076 SUCCESS; signed update manifest/delta/full-fallback contract 45458161245 SUCCESS; clean native macOS package 45458161119 QUEUED. Windows build/package and Linux Ubuntu+Kali distro checks passed on 193cfa4; no Windows production source changed in f59c086.
- macOS updater contract suite now passes 52 assertions. Linux updater health suite passes its watcher/download/cache checks; Linux package smoke and signed release tooling pass in GitHub Actions.
- Rebuilt current macOS source in temporary folder /Volumes/Data/ChatGPT/DCP-Alpha10-Mac-Monitor-Smoke.e6EapI, verified Alpha 10/build 10/revision 1 and codesign. Temporary native app served HTTP 200 for / and /api/status, loaded all UI assets (200 responses), and the native DCP window was confirmed on display 2. Browser-rendered dashboard screenshot: /Volumes/Data/ChatGPT/DCP-Alpha10-Mac-Monitor-Smoke.e6EapI/browser-render.png; native display screenshot: /Volumes/Data/ChatGPT/DCP-Alpha10-Mac-Monitor-Smoke.e6EapI/native-display-2.png.
- Replaced only the original DCP.app bundle with the latest monitor-enabled build after temp build and source comparison. Current rollback bundle: /Volumes/Data/ChatGPT/backups/DCP.app-alpha10-revision1-before-monitor-20261124-201938. Alpha 7 rollback remains at /Volumes/Data/ChatGPT/backups/DCP.app-alpha7-before-alpha10-20261124-195129. SQLite/master.key hashes unchanged.
- The native app's updater attempt triggered a third-party firewall prompt for the PHP process to connect to github.com. Do not dismiss it as an accessibility permission. Avoid repeatedly launching test instances; if the user is prompted on first normal launch, allow only github.com if that is their chosen policy. No permission button was clicked by the agent.
- Original and temporary test app processes and their PHP listeners were stopped after screenshot/testing. The latest original Alpha 10 app bundle is installed but left closed to avoid repeated firewall prompts.


## Alpha 10 tag and pre-install data backups — 2026-11-24 20:25 EET
- Fast-forwarded origin/main from 25c81e2 to d47f2c0 after verifying main was an ancestor of the tested feature branch. No merge conflict and no unrelated divergence.
- Created and pushed annotated tag v1.0.0-alpha.10 at d47f2c0. Release workflow run 45459081495 started. At checkpoint: Linux and Windows release jobs in progress; macOS Intel llama, Apple Silicon llama and both managed PHP runtime jobs queued/in progress. No Alpha 10 release assets are published yet. Do not install until release workflow publishes all required assets and signed manifests.
- Main-branch validation on d47f2c0: Linux package build 45459033306 in progress; Linux Ubuntu/Kali distro test 45459033103 in progress; Windows build 45459032951 in progress; Windows clean package 45459033163 in progress; Cloud PHP 45459033202 SUCCESS; signed updater contract 45459033297 SUCCESS. Branch validation before main push: Linux package 45458563855 SUCCESS; Windows build 45458563851 SUCCESS; Linux Ubuntu+Kali 45458563801 SUCCESS; signed updater contract 45458563988 SUCCESS.
- Windows user-data backup verified at C:\DCP-Backups\DCP-UserData-before-alpha10-20261124-2025-EET. 8 files / 161,768 bytes. Robocopy exit code 1 (files copied, no failure); source and backup file counts/sizes match. SQLite SHA-256 a2f6252cb0c6984bc2c7b1a30f65e49f0d9a69f2ac2950d613e7c54ecb93fa45; master.key SHA-256 b1d13ad07a1dffc595cb63ee49f6ab0bd748c5155a142d753dd273a4ac10e00d. DCP.exe was not running. Existing older Windows rollback backups remain.
- Ubuntu user-data backup verified at /home/amr/DCP-Backups/DCP-UserData-before-alpha10-20261124-2025-EET.tar.gz (SHA-256 63b5fd7add14ec13c39da263acf27a59894e123dbaea18bc9f60f4c17a7d214a). Browser/local share backup at /home/amr/DCP-Backups/DCP-local-share-before-alpha10-20261124-2025-EET.tar.gz (SHA-256 b2776f5ae0630d2a30a91aa3ae75c2849268fb0280adbda544c57887cac067a6). Archive listing verified. No DCP app process was running. No Ubuntu app/data files deleted or replaced.
- Windows and Ubuntu old app binaries remain installed until Alpha 10 assets are published. After install, restore the backed-up DCP-UserData into the new app root and compare hashes/integrity. Keep Linux ~/.local/share/DCP state intact.


## Three-platform Alpha 10 test installs — 2026-11-24 20:50 EET
- Downloaded the Windows and Linux package artifacts from the Alpha 10 release workflow run 45459081495 (artifact IDs 14951852462 and 14950448690). Package hashes verified against each artifact's SHA-256 sidecar:
  - Windows DCP-Windows-x64.zip: bd0bb554d38ff41d20da849b9807bd3a4e2762ef4eb352a610c5c58739bb8a61.
  - Linux DCP-Linux-x64.tar.gz: 66d3c3bb3691b2e148f4c2cf3ba1725f70818eda3b5ae5fd4ed36defec342651.
- Windows fresh Alpha 10 install completed at C:\Users\amradeldotcom\AppData\Local\DCP. The old active app folder was replaced, its temporary rollback copy was removed after verification, and the independent user-data backup remains at C:\DCP-Backups\DCP-UserData-before-alpha10-20261124-2025-EET. Installed metadata 1.0.0-alpha.10/build 10/revision 1. Managed PHP 8.5.13 verified. SQLite PRAGMA integrity_check=ok. master.key hash still b1d13ad07a1dffc595cb63ee49f6ab0bd748c5155a142d753dd273a4ac10e00d, matching backup. API 200 on port 61699, root 200 (25,667 bytes), app process PID 11968. Screenshot at /Volumes/Data/ChatGPT/DCP-Install-Staging/alpha10/windows-alpha10-window.png visibly shows DCP Overview, DCP service online, local database Ready, and PHP 8.5.13. Ollama offline and Desktop Commander Remote not connected are separate service states, not DCP startup failures.
- Windows temporary install tasks and stage directory removed; old Alpha 9 app binary copy created for this install removed. User-data backup retained.
- Ubuntu fresh Alpha 10 install completed at /home/amr/Applications/DCP. Old active app folder replaced; temporary Alpha9 app copy and install staging directory removed after verification. User-data archive and ~/.local/share/DCP backup retained. Installed metadata 1.0.0-alpha.10/build 10/revision 1. PHP 8.3.6 verifier passed. API 200 on 127.0.0.1:18765; root 200; app reports SQLite and 23 plugins. Background watcher PID 799817 is running; log says it waits 30 seconds after UI startup and then polls every 300 seconds. UI screenshot capture via GNOME D-Bus was denied by screenshot permission, so no screenshot claim; HTTP/API and launcher log evidence are available.
- macOS original DCP.app is Alpha 10/build 10/revision 1, codesign verified. Native UI rendered on display 2, but the local PHP child process was terminated by Little Snitch before it could start the service. The visible prompt says “DCP via php wants to connect to github.com”; selected scope is Forever + Only domain github.com. Do not click Allow on user's behalf. Ask the user to allow this exact domain-only rule, then restart the DCP app and recheck local API. The prompt is a Little Snitch network rule, not an accessibility/Terminal permission.
- Alpha 10 release workflow 45459081495: Linux and Windows package jobs SUCCESS; macOS arm64 managed PHP SUCCESS; macOS x64 managed PHP and Intel llama jobs IN_PROGRESS; Apple Silicon llama job QUEUED. No public Alpha 10 Release assets published yet. After publication, compare official asset digests to the Windows/Linux package hashes above and install the official Intel macOS bundle into the original project folder after a temp-folder smoke test.
- Next gate: user allows github.com on macOS; finish macOS Release; verify all three Alpha10 clients; then add a harmless file under platform/ and publish Alpha 11 to prove all three update clients download/apply it. Do not claim end-to-end auto-update until the Alpha11 probe is present on all three systems.
