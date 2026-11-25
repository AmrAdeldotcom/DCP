# DC06 — Alpha 9 Linux package failure and scoped repair

Date: 2026-11-24T14:57:19+02:00 (Cairo)
Repository: /Volumes/Data/ChatGPT/DCP
Branch: fix/alpha7-localai-cloud-pairing
Release baseline: v1.0.0-alpha.9 -> 8b5bf08d6291897338ca322432ccf1962e0c2ed1


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


## Exact next actions
1. Review git diff for only .github/workflows/build-linux.yml, .github/workflows/release-linux.yml, platform/scripts/build-linux.sh, and platform/tests/linux-package-smoke.sh.
2. Run the full local validation again, stage only those four files, commit and push the focused fix to fix/alpha7-localai-cloud-pairing.
3. Inspect the new Build DCP Linux WebView run and require package layout assertions plus HTTP smoke to pass.
4. Do not rewrite Alpha 9 or publish Alpha 10 without Amr's decision. The default main branch still needs synchronization after review.
5. Retry Windows VM connectivity. If unavailable, request that the VM/network/agent be brought online; do not change Windows files.
