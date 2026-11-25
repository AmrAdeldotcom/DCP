# DC07 — Linux package fix pushed and CI verified

Date: 2026-11-24T15:00:25+02:00 (Cairo)
Repository: /Volumes/Data/ChatGPT/DCP
Branch: fix/alpha7-localai-cloud-pairing
Current source commit: 0e3f41196e0819e94a8bfa99ecf95378a7a71592
Published Alpha9 tag remains: v1.0.0-alpha.9 -> 8b5bf08d6291897338ca322432ccf1962e0c2ed1


## DC07 — 2026-11-24T15:00:25+02:00: Linux package fix pushed; CI and corrected fixture pass
- Commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592 fixes the Linux staging root by creating OUT/platform before copying app/bootstrap/config/etc. It adds required app-path assertions and a real PHP root/API smoke test to both Linux workflows.
- Only four source/workflow files were staged and committed: .github/workflows/build-linux.yml, .github/workflows/release-linux.yml, platform/scripts/build-linux.sh, and platform/tests/linux-package-smoke.sh. Push to fix/alpha7-localai-cloud-pairing succeeded. No project-memory/roadmap files were staged.
- GitHub Actions run 45398600124 on 0e3f411 completed success: Build Linux WebView, Verify package contents, Smoke-test packaged Linux application, Upload Linux x64 artifact. Artifact DCP-Linux-x64 ID 14922751159 (585,549 bytes). Windows build 45398600263 and Cloud PHP 45398600090 also passed.
- Corrected temporary Ubuntu package fixture passed the same HTTP smoke and a full Run-DCP.sh launch under the active Wayland session: root HTTP 200, /api/status ok=true, PHP 8.3.6, 23 plugins; app PID 744649 and PHP PID 744674 were stopped, port 18765 closed, and /tmp fixture removed.
- Published Alpha9 Ubuntu install remains broken and retained at /home/amr/Applications/DCP for evidence. Its test app PIDs 742874/742899 were stopped. Do not claim Alpha9 Linux passes; do not overwrite or rewrite the published tag. A new approved alpha release is required to distribute the fix.
- Windows retry at 14:58 EET: SSH to 192.168.8.62:22 timed out, TCP probes to .62/.54 failed, ping .62 had 100% loss. Desktop Commander still lists only the Mac. No Windows files were changed.
- Default main remains commit 25c81e23e0a6810a7c9b70a33d0794b00ed5de0d and is not synchronized with the release branch. It lacks Install-DCP-Online.sh; its Windows installer is older. Tag-pinned scripts were used. Human review is required before merging/synchronizing main.
- Next human checkpoint: bring the Windows VM/agent/network online and decide whether to approve a superseding alpha release plus main synchronization. Apple Silicon physical testing is unavailable; native CI passed.


## Remaining work
1. Human: restore Windows VM network/SSH and Desktop Commander agent. Then take a timestamped rollback backup, hash old SQLite and master.key, run tag-pinned Windows installer under the interactive amradeldotcom user, compare hashes, launch and verify decryption.
2. Human: decide whether to approve a new superseding alpha release containing 0e3f41196e0819e94a8bfa99ecf95378a7a71592 and synchronize main. Do not rewrite Alpha9 or merge the accumulated branch into main automatically.
3. After approval, publish and run the corrected Ubuntu terminal installer again; verify the installed app and then clean only temporary test artifacts.
4. Apple Silicon remains CI-only until a physical device is available.
