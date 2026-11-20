# DCP — Desktop Connector Platform

The active product is the PHP + SQLite platform in `platform/`. The macOS desktop shell is `DCP.app`; Windows and Linux WebView wrapper sources are in `platform/wrappers/`.

Start here:
- `platform/README.md` — quick start
- `platform/PROJECT-ARCHITECTURE.md` — complete architecture, database schema, plugin/adapter contracts and API routes
- `platform/USER-GUIDE.md` — user documentation
- `platform/wrappers/README.md` — desktop builds

The former Python prototype and old launcher files have been removed from the active project tree. Verified recovery archives are maintained separately from the source repository. DCP 1.x does not start or depend on the old prototype.

DCP runs its own PHP CLI server on `127.0.0.1:18765`, stores its data in its own SQLite database, and does not alter Apache, MySQL, PostgreSQL, Node.js, Python or C# services.


## Desktop browser security policy

DCP's desktop shell keeps its own UI embedded. External web links are opened using the operating system's default browser, never navigated inside the DCP WebView. DCP backend integrations (for example, a user-configured DeepSeek API provider) may still make their required Internet connections. The desktop shell itself uses the loopback PHP service on `127.0.0.1:18765`.

See `platform/wrappers/README.md` for platform wrapper details.

## Desktop distributions and first-run setup

- macOS x64: DCP-macOS-x64.zip (DCP.app + platform payload). Run platform/scripts/install-runtime-macos.sh once if PHP prerequisites are missing.
- Windows x64: DCP-Windows-x64.zip (self-contained .NET WebView shell + private PHP CLI runtime). Run Install-DCP.ps1 from PowerShell before launching DCP.exe; it provisions VC++ and WebView2 prerequisites when needed.
- Linux x64: source wrapper and build/install scripts are in platform/wrappers/linux and platform/scripts. The Ubuntu 24.04 build workflow is `.github/workflows/build-linux.yml`; it publishes a downloadable Linux x64 build artifact.

All wrappers use the same PHP application and SQLite data model. The desktop PHP server binds to loopback only. Setup does not start, stop, or reconfigure Apache, MySQL, PostgreSQL, Node.js or Python services.

## Local AI manager and refreshed workspace design

DCP now includes a Local AI manager that detects the local OS, architecture, RAM and GPU, checks Ollama / llama.cpp availability, recommends a suitable free-to-download model, and offers one-click engine installation and model downloads. Ollama models are connected to the existing chat provider automatically; selected llama.cpp GGUF models are started as a loopback OpenAI-compatible server and registered as a DCP provider.

The alpha.3 UI refinement fixes the Bootstrap modal-backdrop collision, gives native select controls a consistent custom treatment, restructures the chat composer, and makes translation variables searchable and readable.

Linux WebKitGTK source/build scripts and an Ubuntu 24.04 GitHub Actions workflow are included. The Kali Linux VM can also build the application directly after GTK3, WebKit2GTK 4.1, and PHP 8.2+ dependencies are installed.
