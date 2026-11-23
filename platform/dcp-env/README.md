# DCP Managed Environment

DCP follows a system-first, managed-fallback policy. A healthy existing Ollama, PHP or llama.cpp service is reused without changing its installation, data directory, port or startup configuration.

Only when a dependency is missing or the user explicitly requests installation does DCP provision a managed copy below this directory. Managed engines are launched as DCP-owned processes; logs and model data live in DCP-UserData. Runtime updates are separate from application updates.

- runtime/php/<os-arch>: PHP CLI fallback and required extensions.
- python/<os-arch>: OS-specific virtual environment; never copy a venv across operating systems.
- engines/ollama/<os-arch>: optional standalone Ollama distribution.
- engines/llama.cpp/<os-arch>: llama-server and adjacent runtime libraries.
- agents/desktop-commander and agents/deepseek-harness: optional adapters.
- browser/chromium/<os-arch>: managed Chromium/Playwright runtime (future phase).

Never overwrite or uninstall a system installation. Never start a duplicate server when a healthy system service is online. Managed server ports are allocated dynamically and recorded in DCP-UserData. Downloads must be verified against upstream SHA-256 digests. User GGUF files and Ollama models live in DCP-UserData/models, not here.


## Managed macOS PHP

- The macOS release workflow builds separate native Intel and Apple Silicon CLI runtimes using the pinned StaticPHP v2.8.5 source commit recorded in `manifests/php-runtime.json`.
- PHP source version and SHA-256 are pinned in that manifest. The CI build verifies the downloaded PHP source before compilation, verifies the required extensions, performs a real HTTPS/TLS request, checks linked libraries and checks the macOS deployment target.
- Release packaging embeds each architecture-specific runtime under `runtime/php/macos-x64` or `runtime/php/macos-arm64`, with `php.ini`, `conf.d/`, license notices, build metadata and a SHA-256 file for the PHP executable.
- System PHP remains first choice. DCP verifies the checksum before using its managed fallback. The managed `PHPRC` and `PHP_INI_SCAN_DIR` are set only when DCP selects its own runtime; system PHP configuration is never changed.
- The build and package workflows are configured, but they are not considered verified until GitHub Actions completes successfully on both native macOS runners and the clean-install package is tested.
