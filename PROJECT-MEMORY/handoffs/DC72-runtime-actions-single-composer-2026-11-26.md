# DC72 — Runtime action pipeline, single composer, Qwen source restore

Date: 2026-11-26
Branch: feature/local-ai-runtime-discovery
Implementation commit pushed: be12318

## User corrections and safety requirements

- Preserve the Qwen3 GGUF source asset; remove only test engine installations when requested.
- Never delete DiGEMA models, DCP user data, or backups without explicit approval.
- Stop and ask before any destructive or ambiguous operation.
- Do not claim an installer or model pull succeeded without checking the installed executable, package directory, native model inventory, and health endpoint.

## Qwen3 source restore

- Source: unsloth/Qwen3-0.6B-GGUF, Qwen3-0.6B-Q4_K_M.gguf
- Mac path: /Volumes/Data/DiGiPL/DCP-Models/Qwen3-0.6B-Q4_K_M.gguf
- Ubuntu path: ~/Downloads/Qwen3-0.6B-Q4_K_M.gguf
- Windows path: %USERPROFILE%/Downloads/Qwen3-0.6B-Q4_K_M.gguf
- SHA-256: ac2d97712095a558e31573f62f466a3f9d93990898b0ec79d7c974c1780d524a
- The three copies were verified byte-identical. It is a source test asset, not installed in Ollama.

## Implemented in source during DC72

- Added user-scoped Node.js 24.22.3 prerequisite installers for macOS/Linux and Windows, using official SHASUMS256.txt verification.
- Added staged DC/DSH agent installers that check Node/npm, install the package, verify the global package directory, and write progress state/logs.
- Added shared SystemAgentRuntime status/install/start/stop implementation.
- Added DSH Install support and progress/log display to Connections UI.
- Fixed SystemAgentRuntime package detection to include npm npx cache locations as well as global package roots.
- Reworked chat UI to a single shared composer for AI and Tasks; context panels are hidden, task target selector is inside the composer, and bottom context tabs remain below it.
- Added persistent task_inbox.target_agent with backward-compatible SQLite migration; task queue API accepts dp-core, dc, dsh.
- Fixed Windows Ollama Start-Process stdout/stderr redirection to separate files for server start and model pull; Ollama installer now logs stages and verifies ollama.exe --version.
- Added runtime action and chat composer architecture docs.

## Verified facts

- Node.js 24.22.3 is installed in the Mac user-scoped runtime and verified.
- Windows machine-wide Node.js 24.22.3 MSI installation completed and node/npm version checks passed. The DCP user-scoped Node prerequisite remains the app-managed path.
- Qwen3 GGUF SHA-256 matches across Mac, Ubuntu and Windows.
- Mac SystemAgentRuntime detects DC and DSH packages in npm npx cache and confirms DSH HTTP service on 127.0.0.1:3080.
- The full platform test suite passed after updating the single-composer UI contract: ALL PLATFORM TESTS PASSED. Commit be12318 was pushed to origin/feature/local-ai-runtime-discovery.

## Still open — do not mark complete

1. Rerun full platform tests after the task-inbox UI contract update.
2. Verify Ollama install on Windows with the DCP UI, then verify ollama.exe --version and /api/tags.
3. Verify Ollama pull on Windows after fixing separate stdout/stderr redirects; verify exact model in native inventory and chat inference.
4. Verify the DC/DSH install action from the DCP UI on Windows and Ubuntu; ensure live progress and actual process/session verification.
5. Verify the new single-composer layout visually on Mac, Ubuntu and Windows; check AI/Tasks/device modes and no duplicate textarea.
6. Cloud remains unresolved: Mac public endpoint probe to /api/v1/signing-key returned HTTP 404 (not an availability proof for the registration/heartbeat routes). Inspect the Cloud API deployment and compare actual /devices/anonymous/register and /devices/heartbeat behavior before claiming connected.
7. Deploy updated source to all three devices only after tests pass; preserve each DCP-UserData and all backups.
