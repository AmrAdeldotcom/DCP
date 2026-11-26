# OS-level local runtime installation and model storage

Status: DC72 architecture decision and initial implementation.

## Boundary

DCP is the control plane and workspace. The DCP application contains DCP-Core and its MCP server. DB_Memory is a future in-app module. Ollama, llama.cpp, Desktop Commander (DC), and DSH are independent OS-level installations and must never be copied into or executed from the DCP application directory.

DCP may contain installer scripts and adapters, but not engine executables, runtime bundles, model weights, or vendor application runtimes.

## Engine discovery and paths

DCP searches the current OS PATH, standard install locations, and a private user-data registry at storage/runtime/engine-paths.json. Every discovered executable path is saved and reused. The registry is per-user and must not contain API secrets.

| Component | Windows | macOS | Linux |
|---|---|---|---|
| Ollama | %LOCALAPPDATA%/Programs/Ollama | /Applications/Ollama.app or ~/Applications/Ollama.app | /usr/local/bin/ollama |
| llama.cpp | %LOCALAPPDATA%/Programs/llama.cpp | ~/.local/bin/llama-server | ~/.local/bin/llama-server |
| Desktop Commander | current Node global npm prefix | current Node global npm prefix | current Node global npm prefix |
| DSH | no verified installer in this build | normal macOS application | no verified installer in this build |

DCP checks whether an engine is installed and whether its local endpoint/process is running. For installed engines it offers Start and Stop. Ollama uses its OS service when present, otherwise DCP starts the installed executable. llama.cpp is started as a local server and stopped only after verifying the listener PID and executable identity. A port match alone is never enough to terminate a process.

## Model storage

- Ollama models stay in Ollama's native model directory.
- llama.cpp Hugging Face downloads stay in the user's standard Hugging Face cache (~/.cache/huggingface/hub, or the Windows equivalent).
- DCP does not mirror models into the application directory.
- GGUF import is optional. Imported files are stored in private DCP user data, outside the application install folder. It is not the default model installation path and not required to run catalog models.
- Model bytes keep a stable SHA-256 across devices. Device identity is separate.

## Install flows

- Ollama Linux: official installer, invoked through the OS privilege prompt, to the standard system path.
- Ollama macOS: official app archive, SHA-256 checked, installed in the user's Applications folder.
- Ollama Windows: official release archive, SHA-256 checked, installed under LocalAppData.
- llama.cpp Linux/macOS: official upstream release binary to ~/.local/bin; Intel macOS without AVX2 may compile upstream source in a temporary directory, never inside DCP.
- llama.cpp Windows: official upstream release to LocalAppData.
- Desktop Commander: npm install -g @wonderwhy-er/desktop-commander@latest in the current Node global prefix, then npx remote to start.
- DSH: this DCP build can detect/start/stop its macOS app if present. No verified Linux/Windows installer is bundled; DCP must report unsupported rather than invent an installer.

## Service lifecycle and security

- Start/Stop actions target only the selected service.
- Ollama uses its native model directory and loopback endpoint.
- llama.cpp binds loopback and is stopped only after process identity verification.
- Provider keys remain on the device that owns the provider.
- Installing or starting an agent does not pair it with Cloud or grant it cross-device permissions.
- DCP agent status detection is not the same as an authenticated DCP task channel. DC/DSH task execution adapters still require Q015 acceptance tests.

## Chat workspace

- History is 25%; active conversation is 75%.
- Messages and composer occupy the main workspace.
- AI provider/model choices stay in the composer; no large local-engine cards above the conversation.
- The order is toolbar, conversation, composer, optional Tasks panel, then the bottom context dock.
- The dock contains AI, Tasks and online device names; device tabs scroll horizontally.
