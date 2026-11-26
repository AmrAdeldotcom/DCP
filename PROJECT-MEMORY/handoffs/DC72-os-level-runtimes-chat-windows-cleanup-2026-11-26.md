# DC72 — OS-level runtimes, chat workspace, VM cleanup and Windows repair

Date: 2026-11-26
Branch: feature/local-ai-runtime-discovery
Build: 1.0.0 / 11
Previous pushed branch head: 7483027
Repository: /Volumes/Data/ChatGPT/DCP-Source

## User decisions

- DCP app contains DCP-Core and MCP; DB_Memory is a future in-app module.
- Ollama, llama.cpp, DC and DSH are OS-level services/apps, not portable runtime bundles inside DCP.
- Models stay in native engine model stores. GGUF import is optional.
- New devices install DCP from GitHub, then detect/install external services in their normal paths.
- Chat history/workspace is 25/75; conversation and composer occupy the main area; all AI/Tasks/device context tabs are at the bottom. AI model/provider selectors stay in the composer.

## Implemented

### Runtime separation
- Moved installer scripts to platform/installers/engines.
- Removed platform/dcp-env/engines runtime/source bundle from DCP source package.
- LocalAIService detects system PATH and standard OS locations and saves discovered paths in storage/runtime/engine-paths.json.
- Ollama install paths: Linux /usr/local/bin (official installer with privilege prompt), macOS ~/Applications/Ollama.app (official archive + SHA check), Windows %LOCALAPPDATA%/Programs/Ollama (official archive + SHA check).
- llama.cpp install paths: Linux/macOS ~/.local/bin/llama-server, Windows %LOCALAPPDATA%/Programs/llama.cpp.
- llama.cpp cache defaults to OS Hugging Face cache. Ollama models stay in Ollama's own model directory.
- Ollama Start/Stop added; Linux uses systemd through pkexec when present. llama.cpp Stop verifies PID and executable before termination.
- Desktop Commander agent card exposes install/start/stop actions using the current Node global prefix. DSH can be detected/launched/quit as a normal macOS app; no verified Windows/Linux installer is available.
- Agent process detection filters actual Node processes to avoid matching the current shell command.

### Model/chat fixes
- Bounded chat history to latest 8 messages and 10,000 characters.
- Ollama requests set num_ctx=8192; llama.cpp Qwen3 context set to 8192.
- Ollama HTTP errors include response-body detail.
- Model catalogue Qwen3 0.6B remains first suggestion but downloads through the selected engine; GGUF import is optional.
- Last-loaded design-system-fixes.css sets the 25/75 split, full-height conversation, composer below messages, optional Tasks panel below composer, and AI/Tasks/device dock at the very bottom. Large Local AI/device cards are hidden from the chat surface.

### Cleanup
- Removed Qwen3 0.6B GGUF from Mac DCP-Models and DCP-Source user data; manifest is empty.
- Removed temporary Qwen3 model from Mac Ollama (other user models preserved).
- Ubuntu: removed temporary Qwen Ollama model, /usr/local/bin/ollama, DCP-managed llama-server, bundled llama runtime, GGUF and Downloads copy. Ollama/llama no longer installed.
- Windows: removed copied GGUF from Downloads; no independent Ollama/llama/DC/DSH installs were detected. DCP user data and rollback backups preserved.
- Stable /Volumes/Data/DiGiPL/DCP v1.0.0 remains untouched.

### Windows repair and deployment
- Found desktop shortcut C:\Users\amradeldotcom\Desktop\DCP.lnk pointed to old C:\Users\dcpadmin\AppData\Local\DCP\DCP.exe; updated to C:\DCP\DCP.exe, working directory C:\DCP.
- Granted active Windows user AMRWIN11\amradeldotcom full control over C:\DCP\DCP-UserData.
- Scheduled task result -2147450730 maps to .NET FrameworkMissingFailure (0x80008096). Built and deployed a self-contained win-x64 wrapper.
- Verified DCP.exe and php.exe running under the interactive user; PHP 8.5.13 listens on 127.0.0.1:60776; /api/status returns ok, Ollama offline, no model copies, no DC/DSH installed.

### Validation
- PHP lint passed for LocalAIService, ChatService, AgentService, DC/DSH drivers, API/Page controllers and Ollama provider.
- platform/tests/local-ai-ui-contract.php: ALL LOCAL AI UI CONTRACT CHECKS PASSED.
- platform/tests/run.php: ALL PLATFORM TESTS PASSED.
- node --check platform/public/assets/app.js and git diff --check passed.
- Mac DCP PHP service responds on 127.0.0.1:60776.
- Ubuntu DCP API responds on 127.0.0.1:60776.
- Windows DCP API responds on 127.0.0.1:60776 after self-contained wrapper deployment.

## Remaining

1. Final sync of the last DC process-detection/PageController deltas to Ubuntu and Windows; verify /api/agents.
2. Visually confirm bottom dock in each WebView after hard refresh.
3. DSH Windows/Linux distribution is not verified; do not attempt guessed installs.
4. DC is process-detected and controllable, but its authenticated DCP task channel is not yet paired.
5. Cloud account pairing remains Guest/Offline. After the user pairs all three devices, run heartbeat/reconnect and signed cross-device acceptance.
6. Q015 signed task/resource dispatch, owner approval, permanent grants, quotas/concurrency, revocation and result delivery remain open.
7. Commit and push DC72 source/docs after final sync; no signed release published.

## Network
- Ubuntu static IP: 192.168.8.88
- Windows static IP: 192.168.8.99
- DCP local UI/API: 60776/tcp, loopback only
