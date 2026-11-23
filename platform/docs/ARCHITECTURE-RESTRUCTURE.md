# DCP Directory and Runtime Restructure

## Target repository layout
DCP/
  README.md  AGENTS.md  WORK-QUEUE.md
  DCP.app/                 # macOS universal bundle
  DCP.exe                  # Windows x64 desktop shell (release output)
  DCP-Linux/               # future Linux/Kali shell; preserve existing updater
  DCP-macos.command        # root launcher
  DCP-windows.ps1          # root launcher
  DCP-linux.sh             # root launcher (compatibility only in this phase)
  platform/
    index.php              # web entry point
    .htaccess              # front-controller routes + deny private paths
    router.php             # built-in PHP server router
    app/ bootstrap/ config/ plugins/ locales/ public/ cloud/ scripts/
    dcp-env/
      manifests/ python/ php/ engines/ agents/ browser/
    data/                  # local runtime only; never served or shipped
    storage/               # keys/logs/cache/reports; never served or shipped

## Web entry and deployment
- platform/index.php is the Apache/shared-hosting front controller.
- platform/.htaccess routes application requests and denies direct access to app, bootstrap, config, cloud, dcp-env, data, storage, scripts, tests, .env and key/database files.
- Static assets are served from platform/public/assets through explicit rewrite rules.
- /Volumes/Data/DiGEMA/public_html/dcp-shortcut is a symlink to /Volumes/Data/ChatGPT/DCP/platform, after the entry point and deny rules are tested.
- Public tunnel testing must be protected by Cloudflare Access or equivalent authentication. Never expose local shell, provider secrets, private keys or personal SQLite data on an unauthenticated public route.
- The local desktop app remains loopback-only and uses a separate data directory from the web preview.

## Portable runtime
- dcp-env is an application-managed dependency store, not a shared cross-OS Python venv.
- Bootstrap creates one Python virtual environment per OS/architecture and installs only pinned requirements.
- PHP runtime, llama.cpp, Ollama helpers, Desktop Commander and DeepSeek Harness each have OS/architecture manifests, install/update/verify scripts and checksums.
- Prefer an existing system installation when healthy. DCP-managed runtime is the fallback and is never allowed to overwrite a system installation.
- GGUF and model data live in a user data/model directory, not in the application bundle and not in Git.
- llama.cpp assets are split by architecture; the existing llama-b10625 macOS x64 build is a legacy Intel asset, not a universal binary.
- Ollama is generally an external service/application. DCP installs it only on explicit user action and manages only the DCP-owned instance.
- Runtime updates are independent from application updates and are atomic/rollback-capable.

## Configuration
- Cloud base URL defaults to https://dcp.amradel.com and is user-editable.
- Mail accounts support multiple named profiles and encrypted secrets.
- Runtime paths and ports are discovered/reserved dynamically; never assume port 8080 or bind DCP to Apache's port.
- Secrets use OS keychain/credential manager when available, otherwise encrypted local storage with owner-only permissions.
