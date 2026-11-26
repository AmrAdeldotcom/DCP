# DCP Runtime Action Pipeline

## Principle

DCP Core/MCP is the application-owned runtime. Ollama, llama.cpp, Desktop Commander (DC), DeepSeek Harness (DSH), and Node.js are operating-system-level dependencies. Models live in their engine-native stores. Importing a GGUF into DCP is optional and is not required to run a model through Ollama.

## Required action sequence

Every Install/Download/Start/Stop button must be an action pipeline, not a fire-and-forget notification.

### Node.js prerequisite (DC and DSH)

1. Detect the OS, architecture, installed Node path and version.
2. If Node is missing or below 24.22.3, download the official Node.js 24.22.3 archive.
3. Download the official SHASUMS256.txt manifest and verify the archive before extraction.
4. Install in a user-scoped runtime directory; do not overwrite the user's system Node.
5. Verify node --version and npm --version.
6. Only then proceed to the requested agent package.

### Desktop Commander

1. Ensure Node.js and npm are verified.
2. Run npm install --global --no-audit --no-fund @wonderwhy-er/desktop-commander@latest.
3. Verify the package exists in npm's global root.
4. Start the remote adapter.
5. Verify the matching process and active session before displaying Connected.
6. Stop only the matching DC process.

### DeepSeek Harness

1. Ensure Node.js 24.22.3 and npm are verified.
2. Install @deepseek-ai/dsh from npm.
3. Verify the package in npm's global root.
4. Start the official web command on 127.0.0.1:3080.
5. Verify the HTTP service and matching process before displaying Running/Connected.
6. Open the default browser only after the service is ready.
7. Stop only the matching DSH process.

### Ollama

1. Detect an installed executable at supported OS paths.
2. If absent, download the official OS package and verify its integrity where an official digest is available.
3. Extract/install to the normal OS-level program location.
4. Verify the executable with --version and resolve the actual model directory.
5. Start/stop the Ollama service explicitly and verify /api/tags before displaying Online.
6. Pull models through Ollama's native store; show pull output and verify the model appears in /api/tags before displaying Downloaded.

### llama.cpp

1. Detect an installed llama-server executable and supported backend.
2. If absent, install a verified OS-appropriate binary or build using documented dependencies.
3. Verify the binary with --version.
4. Start the server and verify the child process identity and health endpoint.
5. Never report a successful launch based only on a PID or a shell command being accepted.

## Progress and error reporting

- Each install action records stages in a per-agent state file: queued, checking prerequisites, downloading, verifying checksum, extracting, installing, validating, complete, or failed.
- Each action writes stdout and stderr to separate log files.
- UI polls state while installation is active and shows the current stage plus the last useful log lines.
- "Installed" requires a verified executable/package path.
- "Running" requires a verified process or health endpoint.
- "Downloaded" requires the model to be returned by the engine's native inventory API.
- Failures must show the exact failing stage and exit code; never show a success toast before verification.
- Windows PowerShell Start-Process must not redirect stdout and stderr to the same file. This causes process-launch errors; use distinct stdout/stderr logs.

## Model source vs engine model

The Qwen3-0.6B-Q4_K_M.gguf source test asset is stored separately at /Volumes/Data/DiGiPL/DCP-Models and copied to each test VM's Downloads directory. It is a source file for manual import/testing, not an Ollama-installed model. Deleting an engine installation must never delete this source asset or any DiGEMA model.

## Safety

- Do not remove user model stores, DiGEMA models, DCP user data, credentials, or backups as part of engine cleanup.
- An uninstall action must name the exact binary/package/service and show the target path before removal.
- If a destructive action could affect user data, stop and ask before proceeding.
