# DCP installation and first-run requirements

## One-command GitHub installation

After a verified GitHub Release is published, use the platform-specific command below. Each installer downloads the matching release asset, verifies the Linux SHA-256 where supplied, preserves existing user data and keeps a rollback copy of the previous installation.

### Windows PowerShell

```powershell
irm https://raw.githubusercontent.com/AmrAdeldotcom/DCP/main/platform/Install-DCP-Online.ps1 | iex
```

### macOS and Ubuntu 24.04 (x86_64)

```bash
curl -fsSL https://raw.githubusercontent.com/AmrAdeldotcom/DCP/main/platform/Install-DCP-Online.sh | bash
```

The shell installer detects macOS or Linux. Linux installs the published `DCP-Linux-x64.tar.gz` package and required Ubuntu GTK/WebKit/PHP runtime packages. The Linux release is not considered available until its tagged GitHub Actions release workflow has published both the archive and checksum.

## Package layout

Keep the desktop executable, runtime/, platform/ and DCP-UserData/ together in one writable application folder. The wrapper selects an OS-specific DCP-UserData directory (Windows, macOS or Linux); databases, master keys, logs, models and workspaces belong there, never inside platform/. Do not install the portable package under a protected system folder such as Program Files unless you separately configure a writable per-user data directory.

## macOS

The macOS shell uses the operating system WebKit framework; no browser runtime download is needed. Run platform/scripts/install-runtime-macos.sh from Terminal. It checks for PHP 8.2+ and the required extensions. If PHP is missing, it installs the PHP CLI formula through Homebrew only; it does not run brew services or start Apache/MySQL/PostgreSQL. Then open DCP.app beside the platform/ folder.

## Windows

Open PowerShell in the extracted package and run:

    .\Install-DCP.ps1

This installs a private PHP CLI runtime under runtime/php/ (not into PATH and not as a Windows service), enables DCP's required extensions, verifies them, and installs the Microsoft Edge WebView2 Evergreen Runtime only if missing. Then run DCP.exe. Keep DCP.exe, runtime/, and platform/ together.

## Linux (Ubuntu 24.04 build target)

Install/build prerequisites and PHP CLI extensions with:

    chmod +x platform/scripts/*.sh
    platform/scripts/install-runtime-linux.sh
    platform/scripts/build-linux.sh

The runtime installer uses only PHP CLI packages; it does not install Apache or start database services. The WebView build needs GTK3, WebKit2GTK 4.1, pkg-config and a C compiler. The GitHub Actions workflow builds and publishes the Linux x64 artifact on Ubuntu 24.04. PHP 8.2+ with curl, intl, mbstring, PDO_SQLite, sodium and fileinfo is required at runtime.

## Isolation and safety

DCP starts its own PHP built-in server bound only to 127.0.0.1:18765, and uses its own SQLite database. It does not reconfigure or start Apache, MySQL, PostgreSQL, Node.js, or Python services. Package managers may install missing CLI libraries, but no existing service is intentionally stopped, restarted, or reconfigured.


## Local AI engines and model downloads

Open **Local AI** inside DCP to inspect the current device's OS, architecture, memory and graphics adapter. The manager recommends a model from a curated free-to-download catalogue, then can install Ollama and download the selected model with the Ollama CLI. Model licences still apply.

llama.cpp is offered as an alternative runtime. On macOS it uses the Homebrew formula; on Windows it uses WinGet; on Linux the bootstrap uses the included terminal installer, which requests sudo approval if CMake / compiler packages are missing and builds the server from the official ggml-org/llama.cpp source. The selected GGUF is fetched from its Hugging Face model repository and exposed through the OpenAI-compatible local server at 127.0.0.1:8080. The DCP provider is then saved automatically.

All installs and model downloads are explicit user actions. Model files can be several gigabytes; check available disk space before downloading. On Linux, package installation may require sudo approval. On Windows, WinGet / the runtime installer may request elevation.
