# DC05 — Alpha 9 release and install validation handoff

Date: 2026-11-24 (Cairo)
Repository: `/Volumes/Data/ChatGPT/DCP`
Branch: `fix/alpha7-localai-cloud-pairing`
Source commit/tag: `8b5bf08d6291897338ca322432ccf1962e0c2ed1` / `v1.0.0-alpha.9`

## Release
- GitHub release ID: `456688871`; published prerelease; six assets verified.
- Linux release run `45387266328` success.
- Windows release run `45387266284` success; clean Windows package `45387266218` success.
- macOS release run `45387266140` success; clean package test `45387266141` success. Intel and ARM64 runtime + package matrix jobs all green.

## Intel Mac smoke install
- Official installer from tagged source used with `DCP_INSTALL_DIR="$HOME/Applications/DCP-Alpha9-Smoke"`.
- App version 1.0.0-alpha.9, Mach-O x86_64, app PID 77765, PHP PID 77768, port 50641.
- HTTP root 200; `/api/status` ok=true; PHP 8.4.28; SQLite; Ollama online; 23 plugins.
- Bundled PHP hash matches manifest; `verify-runtime.php` passed. llama-server x86_64 `--version` and `--help` passed.
- 56 tracked app/config/routes/resources/public/bootstrap files match the source; zero diffs. Five tests intentionally absent from release package.
- Baseline original services still active: PHP 92383:18991, llama-server 99659:19123, Ollama 2101/2106. New app isolated on 50641. Source hashes unchanged.
- Smoke install removal still pending. Kill only app PID 77765 (and its child PHP PID 77768) before deleting only `~/Applications/DCP-Alpha9-Smoke`; do not touch 92383, 99659, 2101, 2106 or `/Volumes/Data/ChatGPT/DCP`.

## Ubuntu
- Host `amr-ubuntu24`, Ubuntu 24.04 x86_64, target absent, 48 GiB free, sudo -n works, graphical Wayland session active.
- Official tagged installer downloaded from `raw.githubusercontent.com` and launched with `DCP_INSTALL_DIR=/home/amr/Applications/DCP`.
- `apt-get update` passed; apt install prerequisites was running at checkpoint. Remote Mac process PID 77540 owns SSH command; poll with `read_process_output` on Amrs-Mac-Pro.

## Windows
- SSH admin `.62` and legacy `.54` both timed out; ping .62 100% loss; Desktop Commander device list shows only Mac. No Windows files changed. Restore access before timestamped backup and installer invocation under interactive `amradeldotcom` user.

## Watchdog
- Hourly automation `DCP Alpha 9 Watchdog` created, timezone Africa/Cairo. It is a scheduled hourly follow-up, not a continuously running process.

## Next actions
1. Poll Ubuntu installer process PID 77540 until complete; verify `/home/amr/Applications/DCP`, package version, `php` and extensions, `verify-runtime.php`, launcher, HTTP/API health and GUI process/log.
2. Test Windows VM reachability and complete backup/install/data hash checks only after connectivity returns.
3. Finish Mac source/package comparison and remove only isolated smoke target, leaving original project folder and services untouched.
4. Update memory and queue with final evidence; do not close Q005 until all feasible target gates are complete. No Apple Silicon physical device available; CI native build/package success is the current evidence.
