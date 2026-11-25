# DC53 — Q012 Provider Tool Calls and Workspace Separation

Date: 2026-11-25
Source branch: `feature/local-ai-runtime-discovery`
Last pushed commit: `0522e14`
Working source path after preservation: `/Volumes/Data/ChatGPT/DCP-Source`
Fresh release install path: `/Volumes/Data/ChatGPT/DCP`

## Q012 progress
- Tasks 1–3 are implemented. Task 1 shared registry/policy/schema validation is cross-platform green in GitHub Actions run `45589846000` (Ubuntu 24.04, Windows Server 2025, macOS 15).
- DC53 adds `ToolCallingProviderAdapter`, `ProviderToolCallResult`, provider schema export from the shared `DcpToolRegistry`, `ChatService::sendWithTools()`, and structured parsing for Ollama + OpenAI-compatible providers. Existing `ProviderAdapter::chat()` remains backward-compatible.
- Parser tests cover valid calls, malformed arguments, refusal, empty response, excess calls, invalid shapes and Qwen3 thinking disablement.
- Detached worker bootstrap was fixed to load the optional tool-calling interface/result types.
- Local `php platform/tests/run.php`, worker E2E, delayed-provider HTTP E2E, PHP lint, JS syntax and `git diff --check` pass. Tasks 2–3 cross-platform CI is pending.
- Tasks 4–7 remain: bounded coordinator, durable result artifacts/idempotency and approval execution boundary, review APIs/UI, worker heartbeat and crash-safe step resume. `resumable=false` remains mandatory.

## Workspace safety
- Existing `/Volumes/Data/ChatGPT/DCP` contains a dirty Git worktree: 6 modified tracked files and 39 untracked files at inspection, including provider tool-call work, project memory artifacts, app bundle, user data and local runtimes. Preserve the entire directory as `/Volumes/Data/ChatGPT/DCP-Source`; do not clean/reset or run the release installer against it.
- The official macOS one-line installer `Install-DCP-Online.sh` accepts `DCP_INSTALL_DIR`. Use it with `DCP_INSTALL_DIR=/Volumes/Data/ChatGPT/DCP` so the published stable package, runtime and new user-data root are isolated from source. The installer opens `DCP/DCP.app`; verify its release version, runtime and HTTP health after installation.
- DCP starts with a fresh SQLite DB/master key; do not copy the source DB or key into it. The updater in DCP should only update DCP.

## Next
1. Finish the source move and isolated DCP release install; verify package and runtime health.
2. Run updated provider tool-call workflow matrix on Ubuntu/Windows/macOS before marking Tasks 2–3 cross-platform complete.
3. Implement Task 4 bounded coordinator; never execute provider-proposed tools until registry policy/capability/approval/plan-version/argument-hash/idempotency gates are connected.
