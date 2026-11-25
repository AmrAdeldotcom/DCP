# DC70 — GGUF runtime, chat context UI, Windows/Ubuntu fixes

Date: 2026-11-25
Branch: feature/local-ai-runtime-discovery
Repository: /Volumes/Data/ChatGPT/DCP-Source
VMs: Ubuntu 192.168.8.88; Windows 192.168.8.99

## User-reported defects and verified causes

1. Windows app looked unchanged: current platform source was stale relative to DCP-Source. Latest changed platform files were synced to C:\\DCP; the active process was not present during the remote check, so interactive GUI verification remains open.
2. Ubuntu blank white window: the old Linux wrapper hard-coded 127.0.0.1:18765, which was already owned by Ollama. The wrapper connected to the wrong service. Linux now reserves a dynamic loopback port; rebuilt DCP launched and started PHP at 127.0.0.1:35937. PHP served assets successfully (HTTP 200). Ollama's 18765 listener was left untouched.
3. DiGiPL app showed Cloud Guest and path-only GGUF import: /Volumes/Data/DiGiPL/DCP is the preserved stable v1.0.0 baseline and was not overwritten. Guest means connected anonymous device, not Cloud Offline. Current DCP-Source supports the native file chooser. Upgrade of the stable install must arrive through a signed release after branch integration.
4. DCP-Source Cloud Offline: current Cloud health endpoint returned 200; anonymous device registration returned 201; source app /api/cloud/status returned connected=true, account_linked=false, and heartbeat verified. A transient/offline UI state should recover via the 10-second poll/online event. Account remains Guest.
5. GGUF import succeeded but used PHP temp filename: manifest stored a random temporary upload name. Import fallback now uses the original filename; the known starter file is normalized by SHA-256 to Qwen3 0.6B · GGUF Q4_K_M so it is recognized in the first catalogue card.
6. Chat UI clutter: Task Inbox was a large independent box. Chat now has tabs for Tasks, Local AI, API providers and Devices; compact panels expose engine/import status, providers and paired-device/Cloud status. Device resource execution is still gated by Q015.
7. llama.cpp process identity error: Intel Mac managed llama-server had an LC_RPATH to a stale /Volumes/Data/ChatGPT/DCP build folder. Required libllama-server-impl.dylib exists beside the binary. Added @loader_path to this DCP-managed runtime binary; llama-server --version now succeeds. Do not apply this to unrelated/system binaries.

## Real model acceptance

- Source GGUF: /Volumes/Data/DiGiPL/DCP-Models/Qwen3-0.6B-Q4_K_M.gguf
- Size: 396,705,472 bytes
- SHA-256: ac2d97712095a558e31573f62f466a3f9d93990898b0ec79d7c974c1780d524a
- DCP private model manifest recognizes the imported model by SHA.
- DCP started custom GGUF on 127.0.0.1:50248; Qwen3 uses --chat-template-kwargs {"enable_thinking":false} to return user-facing content rather than only reasoning.
- llama.cpp chat completion returned DCP_QWEN3_OK in message.content, finish_reason=stop.
- Ollama conversion created dcp-qwen3-0.6b-q4-k-m:latest (396 MB); status reached ready; Ollama chat returned DCP_OK.
- Ollama remained running and was not reconfigured.

## Code changes

- platform/wrappers/linux/dcp-webview.c: allocate dynamic loopback port; detect PHP child exit and show startup error instead of indefinite blank screen.
- platform/app/Services/LocalAIService.php: Windows PowerShell calls use argument-vector proc_open with bypass_shell=true; canonical executable path comparison is case-insensitive; Qwen3 llama.cpp chat-template kwargs disable thinking.
- platform/app/Services/CustomModelManager.php: uploaded display name falls back to original filename; repair known Qwen3 starter display name by SHA; Windows conversion/PID checks use safe PowerShell argv invocation.
- platform/app/Controllers/PageController.php, platform/public/assets/app.js, platform/public/assets/app.css: Tasks / Local AI / API providers / Devices context tabs and compact responsive panels.
- platform/tests/local-ai-ui-contract.php, platform/tests/model-manager-contract.php: contracts cover Windows path invocation, Qwen3 thinking behavior, context tabs, dynamic Linux port and legacy GGUF name repair.
- Earlier DC69 changes in the same working tree remain included: multipart GGUF upload route, Cloud retry/heartbeat, wrapper log rotation, and Windows/macOS wrapper file chooser/upload support.

## Validation

- php platform/tests/run.php: ALL PLATFORM TESTS PASSED
- All platform PHP files linted successfully.
- node --check platform/public/assets/app.js: JS_SYNTAX_OK.
- git diff --check passed.
- Linux C wrapper compiled on Ubuntu 24.04; only existing WebKit deprecated API warning.
- Ubuntu DCP relaunched with dynamic port 35937; PHP asset requests returned 200.
- Mac direct llama.cpp inference and Ollama conversion/inference both passed.

## GitHub/release gate

- Current feature branch accumulated Q015/DC69 work and is seven commits ahead of its remote before DC70.
- .github/workflows/release.yml requires the release tag commit to equal the current default-branch HEAD.
- main is 119 commits behind this feature branch. Do not force-push or bypass the signed-release gate. Push the feature branch and use a reviewed PR/integration path; publish v1.0.1 only after integration and release CI.
- PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json is intentionally untracked and must not be staged.
- /Volumes/Data/DiGiPL/DCP stable v1.0.0 and its user data remain untouched.

## Remaining work

1. Verify Windows DCP.exe in the interactive desktop session, current Local AI file chooser, real imported model start, process identity, and Ollama conversion.
2. Visually verify Ubuntu app after the dynamic-port relaunch.
3. Verify macOS signed package's engine artifact has relocatable @loader_path RPATH before publishing.
4. Commit/push this branch and open a PR. Do not release until safely integrated into default branch.
5. Continue Q015: Cloud owner-node signed resource invocation, quotas/concurrency, team/device grant parity, Hermes adapter, real Mac↔Windows/Ubuntu resource invocation, then Cloud MCP deployment only after protected secret/migration approvals.
