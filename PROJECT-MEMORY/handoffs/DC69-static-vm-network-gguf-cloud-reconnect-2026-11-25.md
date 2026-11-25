# DC69 — Static VM network, GGUF import, terminal UX and Cloud reconnect

Date: 2026-11-25
Repository: `/Volumes/Data/ChatGPT/DCP-Source`
Branch: `feature/local-ai-runtime-discovery`

## Authoritative VM network configuration

The user corrected the fixed static IPv4 addresses. Preserve these exact values; do not revert to DHCP or the previous `.56`/`.62` addresses.

| VM | Static IPv4 | Prefix | SSH user |
|---|---:|---:|---|
| Ubuntu 24.04 | `192.168.8.88` | `/24` | `amr` |
| Windows 11 | `192.168.8.99` | `/24` | `dcpadmin` |

Both VMs are running in Parallels. SSH port 22 responded on both. Guest Ed25519 host-key fingerprints were read from inside each VM and compared with the SSH key scan before strict host-key checking was enabled. SSH aliases in `~/.ssh/config` now use the new static addresses and dedicated known-hosts file `/Volumes/Data/ChatGPT/DCP-Lab-SSH/dcp-lab-known-hosts`. Private key remains outside the repository.

## Source changes

- DCP Terminal uses persistent shell sessions with connect/disconnect/clear controls, prompt, state display and bounded output; terminal page styled closer to Termius. Native PTY/ConPTY is still future work.
- Application startup rotates old application logs to timestamped `.previous` files and starts fresh log files; engine start/install opens fresh engine logs. Windows/macOS native wrappers truncate their desktop-wrapper logs at launch.
- GGUF import UI now selects a local file and uploads it to the app. The app copies it to private model storage, validates GGUF header and SHA-256, and adds it to the custom model inventory. Upload cap is 1 GB in all three desktop PHP launchers. PHP multipart temporary filenames are not `.gguf`, so validation uses the original uploaded filename.
- Imported GGUF files expose `Run with llama.cpp` and `Convert to Ollama`. Custom llama.cpp start rechecks the stored file SHA-256 before launching.
- First suggested offline starter is `Qwen3 0.6B · GGUF Q4_K_M`. Upstream: `unsloth/Qwen3-0.6B-GGUF`, file `Qwen3-0.6B-Q4_K_M.gguf`, 397 MB, SHA-256 `ac2d97712095a558e31573f62f466a3f9d93990898b0ec79d7c974c1780d524a`.
- Cloud client retries transient network errors for GET and idempotent heartbeat only; unsafe mutations are not retried. UI polls Cloud every 10 seconds and checks immediately on browser `online` events.

## Verified execution

- macOS DCP native app rebuilt at `DCP.app` and launched. `/api/status` returned `ok: true`.
- Live multipart GGUF upload smoke test on macOS passed: disposable test GGUF returned HTTP 200, appeared in custom model inventory with SHA-256, then was deleted through the API.
- Focused model-manager, Local AI UI, security contracts, PHP lint and JS syntax checks passed after the upload extension fix. Full platform suite passed before the final extension fix; rerun the full suite before commit.
- Ubuntu app root is `/home/amr/Applications/DCP`; changed platform source tarball was copied and extracted successfully.
- Ubuntu native DCP wrapper currently requires `pkg-config`, `libgtk-3-dev`, `libwebkit2gtk-4.1-dev` and build-essential; apt installation is running so the new launcher can be compiled.
- Windows has a staged Alpha10 ZIP at `C:\DCP-Install-Staging\Alpha10\DCP-Windows-x64.zip`, previous full application backup at `C:\DCP-Backups\DCP-pre-alpha9-20261124-152849`, and user data at `C:\Users\dcpadmin\AppData\Local\DCP\DCP-UserData`. No active DCP.exe was found in the common install paths. Preserve user data and backup; locate/prepare the install root before deploying.
- GGUF download is running on the Mac under `/Volumes/Data/DiGiPL/DCP-Models` with Hugging Face Xet retries. Do not copy until the full file exists and SHA-256 equals the upstream hash above.

## Remaining acceptance

1. Finish and verify the Mac GGUF download; copy the exact file once to each VM (Ubuntu Downloads and Windows Downloads), verify SHA-256 on all three, and leave the user to select/import it in each app.
2. Finish Ubuntu build dependency installation; compile updated `platform/wrappers/linux/dcp-webview.c`, install/restart the Ubuntu DCP app, and verify health plus multipart import.
3. Build the updated Windows app executable from `platform/wrappers/windows/Program.cs`, unpack the staged ZIP into a controlled install root, preserve/copy Windows user data only after timestamped backup and hash verification, then verify health and multipart import.
4. Rebuild/verify macOS app with updated source; confirm native file picker opens.
5. Verify Cloud status heartbeat on all three apps, temporary offline recovery (without disrupting the user's WAN), and device-to-device resource discovery/invocation.
6. Run `php platform/tests/run.php`, PHP lint, `node --check`, shell/PowerShell syntax and `git diff --check`; commit only scoped source/docs/tests. No Git push or Cloud production deployment without separate authorization/gates.

## Safety boundaries

- Never alter Cloud production secrets, migrations or APP_KEY approval gates.
- Do not overwrite stable `/Volumes/Data/DiGiPL/DCP` or the Windows rollback backup.
- Do not stage `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json`; keep user secrets and private SSH keys outside Git.
