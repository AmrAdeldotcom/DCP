# Q006 Windows LocalAI lifecycle — DC31

Date: 2026-11-25 (Cairo)
Repository: /Volumes/Data/ChatGPT/DCP
Branch at validation: feature/local-ai-runtime-discovery
Starting HEAD: 30fe3fd

## User constraints
- Alpha 10 is the first approved baseline.
- Do not spend time on upgrade tests against a previous Alpha build.
- Continue implementing remaining stages while GitHub Actions runs.
- Push each completed, locally validated stage to the repository before moving on.
- Do not stop, replace, or reconfigure unrelated existing local services.
- Preserve user SQLite and master.key.

## Windows failure diagnosis
The Windows interactive smoke initially failed before the llama.cpp server could start. The generated PowerShell 5.1 runner set ErrorActionPreference=Stop and invoked llama-server with merged output redirection. PowerShell 5.1 promoted native stderr diagnostics (including ordinary INFO startup lines) to NativeCommandError records; Stop terminated the wrapper before the server finished initialization.

## Changes
- Generated Windows runner now uses ErrorActionPreference=Continue around the native llama-server invocation, captures all streams in the DCP user-data log, and propagates a non-zero LASTEXITCODE.
- Windows process ownership verification now checks the listener port, its owning PID, and the exact executable path together using Get-NetTCPConnection and Win32_Process.
- Managed stop confirms the exact PID exits before clearing state and removes only the corresponding DCP-Managed-Llama task.
- Qwen3 0.6B selects an existing cached GGUF snapshot directly by model path; it does not re-download the model when the verified cache is present.
- The model cache remains under DCP-UserData/Windows/models/llama.cpp.

## Real-device acceptance
Machine: AMRWIN11, Windows 11 Pro build 26100.
Interactive identity: AMRWIN11\amradeldotcom, session 1.
Runtime binary: existing system llama-server.exe; DCP reused it without replacing it.
Model: mradermacher/Qwen3-0.6B-GGUF Q4_K_M.
Cached model file size: 484,220,448 bytes.
Result:
1. DCP started llama-server under the interactive user.
2. The server loaded the cached GGUF and listened on 127.0.0.1:65423 during the passing run.
3. OpenAI-compatible chat returned exact probe DCP_QWEN06_WINDOWS_OK.
4. DCP stopped the exact managed PID and cleared the runtime state.
5. Post-test verification: zero llama-server processes, zero DCP-Managed-Llama / DCP-Q006 scheduled tasks, no llama-server.json state file, model cache retained.

The smoke used an isolated temporary PHP harness for LocalAIService and the DCP user-data runtime/log/model paths. It did not access or modify SQLite or master.key. Existing unrelated Ollama/DCP services were left untouched.

## Validation
- php -l platform/app/Services/LocalAIService.php — PASS.
- php -l platform/tests/run.php — PASS.
- php platform/tests/run.php — ALL PLATFORM TESTS PASSED.
- python3 -m json.tool PROJECT-MEMORY/CURRENT-STATE.json — PASS.
- git diff --check — PASS.
- Real Windows interactive start/chat/stop smoke — PASS.

## Release CI observation
GitHub signed release run 45515068071 completed with failure. Linux, Windows, macOS ARM64 llama.cpp and both managed PHP runtime jobs passed. The macOS Intel SSE4.2 llama.cpp job failed while cloning ggml-org/llama.cpp because the runner could not resolve github.com (DNS/network failure), before compilation. This is an external fetch failure, not a source compile/test failure. The signed assembly and publication jobs were skipped. Do not wait idle on this; continue Q006/Q007 work and retry release assembly only after the release tag/branch gate is current.

## Next
1. Commit and push the focused LocalAIService.php + platform/tests/run.php change and this task note.
2. Run equivalent Linux live Qwen3 0.6B start/chat/stop using the existing cached model without disturbing unrelated services.
3. Verify the Windows UI's Run with llama.cpp action against the managed API and confirm stop/status refresh.
4. Continue Q007 and remaining queued phases. No release or target app update is authorized by this checkpoint.
