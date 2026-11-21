# DCP — Desktop Connector Platform

The active product is the PHP + SQLite platform in `platform/`. The macOS desktop shell is `DCP.app`; Windows and Linux WebView wrapper sources are in `platform/wrappers/`.

Start here:
- `platform/README.md` — quick start
- `platform/PROJECT-ARCHITECTURE.md` — architecture, database schema, plugins and API routes
- `platform/INSTALL.md` — platform installation commands
- `platform/USER-GUIDE.md` — user documentation
- `platform/wrappers/README.md` — desktop builds

DCP runs its own PHP CLI server on `127.0.0.1:18765`, stores application data in its own SQLite database, and does not alter Apache, MySQL, PostgreSQL, Node.js, Python or C# services.

## Install from Git

Git is the source of truth for development installs. These commands clone the current `main` branch; subsequent updates are performed with `git pull --ff-only`. Review the scripts in the repository before running them.

## Install on Windows 10/11 (one command)

Open PowerShell and paste this single command:

```powershell
irm https://raw.githubusercontent.com/AmrAdeldotcom/DCP/main/platform/Install-DCP-Online.ps1 | iex
```

No Git installation is required. The bootstrap downloads the latest published `DCP-Windows-x64.zip` GitHub Release asset, installs it under `%LOCALAPPDATA%\\DCP`, checks the bundled PHP runtime and WebView2 prerequisites, creates a desktop shortcut, and launches DCP. Git is not required and the source repository is not cloned onto the user's machine.

For developers who want a source checkout, Git-based installation remains available below.

### Developer install (optional)

Install Git for Windows first, then run:

```powershell
git clone https://github.com/AmrAdeldotcom/DCP.git "$env:LOCALAPPDATA\\DCP-source"
Set-Location "$env:LOCALAPPDATA\\DCP-source"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\\platform\\Install-DCP.ps1
```

The developer script provisions DCP's private PHP CLI runtime. Building the desktop wrapper from source requires the .NET SDK; end users should use the one-command installer above. Installed Windows builds check the latest official GitHub Release before starting the local PHP service. When a newer release is available, DCP stages it, closes, applies the update and reopens. The updater preserves `platform/data` and `platform/storage` (including the SQLite database and encryption key), and does not require Git on the user's PC.
### macOS

```bash
git clone https://github.com/AmrAdeldotcom/DCP.git "$HOME/DCP"
cd "$HOME/DCP"
chmod +x platform/scripts/*.sh
./platform/scripts/install-runtime-macos.sh
```

To update: `git -C "$HOME/DCP" pull --ff-only origin main`.

### Linux (Ubuntu/Debian)

```bash
git clone https://github.com/AmrAdeldotcom/DCP.git "$HOME/DCP"
cd "$HOME/DCP"
chmod +x platform/scripts/*.sh
./platform/scripts/install-runtime-linux.sh
```

To build the Linux desktop wrapper, run `./platform/scripts/build-linux.sh` after the documented GTK3, WebKit2GTK 4.1, pkg-config and compiler prerequisites are installed.

## Desktop browser security policy

The desktop shell keeps the DCP UI embedded. External web links open in the operating system's default browser, never inside the DCP WebView. The desktop shell communicates with its private PHP service on loopback only.

## Desktop distributions

- macOS x64: `DCP-macOS-x64.zip` (DCP.app + platform payload).
- Windows x64: `DCP-Windows-x64.zip` (self-contained .NET WebView shell + private PHP CLI runtime + startup GitHub Release updater).
- Linux x64: the Ubuntu 24.04 GitHub Actions workflow builds and publishes a Linux x64 artifact.

All wrappers use the same PHP application and SQLite data model. Setup does not start, stop, or reconfigure Apache, MySQL, PostgreSQL, Node.js or Python services.

## Device pairing and network architecture

The local pairing implementation stores invitations and paired-device credentials in the SQLite database of the DCP instance that creates the invitation. The hosted DCP Cloud control-plane MVP is deployed at `https://dcp.amradel.com`; its health endpoint is live and its API supports account/device registration, same-account device discovery, online presence, connection requests and short-lived signaling.

The hosted control plane is separate from **device-to-device data transport**:

1. A cloud control plane stores user accounts, device public identities, invitation state, accepted trust relationships, capabilities, revocation state and last-seen metadata.
2. Devices authenticate using unique per-device credentials (preferably public-key based), rotate short-lived access tokens and send outbound HTTPS/WebSocket heartbeats. No inbound router port-forwarding is required for basic online presence.
3. The control plane authorizes task delivery. Each device independently validates the sender, scope, expiry and approval policy before executing a task.
4. Direct WebRTC or WireGuard connectivity can be added for large transfers and low-latency sessions. An authenticated relay is the fallback when NAT/firewalls prevent a direct path.
5. Each device keeps its own SQLite application data. The cloud stores coordination metadata, not users' local databases or private keys.

**Security rules:** invitation acceptance only establishes a trust relationship; it does not grant unrestricted remote shell access. Use least-privilege capabilities, explicit approval for sensitive actions, encrypted transport, hashed/revocable device credentials, replay protection, audit events and per-device revocation.

The desktop clients now support DCP Cloud account registration/sign-in, encrypted local token storage, device registration, heartbeats and same-account device discovery. The current API remains signaling-only: the WebRTC data-channel client, payload relay, remote task transport and remote command execution are not yet complete. Do not represent two devices as connected for task execution until authenticated peer transport and task authorization are implemented and tested.

## Local AI manager

DCP detects the current device's OS, architecture, memory and graphics adapter, checks Ollama / llama.cpp availability, recommends a model and offers explicit engine installation and model downloads. Model licences apply; check free disk space before downloading large models.
