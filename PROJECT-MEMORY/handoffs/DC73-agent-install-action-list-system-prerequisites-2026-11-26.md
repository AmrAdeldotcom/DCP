# DC73 — Agent prerequisite action list and system-level installers
Date: 2026-11-26

## User direction
- The Install DC / Install DSH action must open a combined modal showing the required action list before any install begins.
- The same modal must show real download progress, current stage, installer log and verification status.
- Install dependencies in normal system-wide locations. DCP-specific state/logs remain under DCP user data.
- Node.js is a system prerequisite, not a runtime bundled into DCP's private folders.
- Do not modify the stable installation at /Volumes/Data/DiGiPL/DCP.
- User asked to remove the Node.js versions previously installed by this work from the Ubuntu and Windows VMs.

## Implemented
- Added a combined Agent Installation Assistant modal in platform/public/assets/app.js.
- Added responsive checklist, stage/progress bar and live log styling in platform/public/assets/design-system-fixes.css.
- Install buttons now open the modal before calling the install API. Existing Start/Stop controls remain, and Install / Repair remains available even when an agent process is detected.
- The modal polls /api/agents and only shows completion after the package is found in its system global package directory. It parses real transfer percentage output where the installer exposes it.
- Node.js Unix installer downloads the official Node 24.22.3 archive, validates it against the official SHA-256 manifest, and installs into /usr/local/lib/nodejs/node-v24.22.3-<platform>-<arch>. It does not overwrite an existing /usr/local/bin/node.
- Windows Node installer downloads the official Node 24.22.3 MSI, validates SHA-256, requests administrator approval, installs system-wide under Program Files, and verifies node/npm.
- Unix DC/DSH installer uses the versioned system Node runtime when present and installs agent packages into /usr/local/lib/node_modules with administrator approval.
- Windows DC/DSH installer requests elevation and installs packages into the system global package directory under Program Files.
- SystemAgentRuntime now detects versioned system Node first and considers only system global package directories installed; npm npx cache entries no longer create false installed status.
- Added local AI UI contract checks for modal, progress and system-level Node paths.

## Node removal verification
- Ubuntu VM 192.168.8.88: removed only /home/amr/.local/share/dcp/runtimes/node-v24.22.3. PATH lookup showed no node; DCP-managed runtime path was absent.
- Windows VM 192.168.8.99: uninstalled the Node.js 24.22.3 MSI product (exit code 0). Verified node absent from PATH, MSI registration absent and C:\Program Files\nodejs\node.exe absent.
- Mac existing Node v22.11.0 at /usr/local/bin/node was detected during testing and intentionally left untouched. The new Unix installer uses a separate versioned system directory to avoid replacing it.
- Qwen3 GGUF source and all DiGEMA/model assets were not touched.

## Validation
- php platform/tests/run.php — ALL PLATFORM TESTS PASSED.
- PHP lint across platform passed.
- node --check platform/public/assets/app.js passed.
- bash -n passed for both Unix installer scripts.
- php platform/tests/local-ai-ui-contract.php — ALL LOCAL AI UI CONTRACT CHECKS PASSED.
- git diff --check passed.
- Built a fresh x86_64 development DCP.app under /Volumes/Data/ChatGPT/DCP-Source/DCP.app and launched it. GET http://127.0.0.1:60776/api/bootstrap returned version 1.0.0.
- GET /api/agents returned the new system-install detection and agent status. The served /assets/app.js contains agentInstallModal.
- The Windows/Ubuntu app folders have NOT been updated with this DC73 code yet. The stable installed Mac DCP at /Volumes/Data/DiGiPL/DCP was not modified.

## Important remaining acceptance
- User must visually test the development DCP.app under /Volumes/Data/ChatGPT/DCP-Source/DCP.app: Connections → Agent adapters → Install / Repair → review modal, then decide whether to run Install all.
- Actual privileged Node/npm installation has not been run on Mac/Windows/Ubuntu after these script changes. Do not claim installer acceptance until each platform is exercised and versions/package paths are verified.
- Windows PowerShell/Ubuntu shell syntax could not be parsed on the VMs because the latest DC73 scripts have not been deployed there.
- User also asked that model downloads land in the device Downloads folder. Existing Ollama/native model store and llama.cpp cache behavior has not been changed in this DC73 pass; do not claim this requirement is complete. Any change must preserve existing model data and avoid silently moving it.
- No commit/push/release or production deployment performed in this checkpoint.
- Preserve PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json as untracked and never stage it.

## DC73 follow-up — model download destinations
- Updated LocalAIService model destinations: llama.cpp/Hugging Face cache defaults to `~/Downloads/DiGiPL/Models/llama.cpp`; Ollama's `OLLAMA_MODELS` is set to `~/Downloads/DiGiPL/Models/Ollama` when DCP launches its own Ollama server. Windows uses `%USERPROFILE%\Downloads\DiGiPL\Models\Ollama`.
- Existing explicit `LLAMA_CACHE` override remains supported. Existing Ollama model stores are never moved or copied.
- Ollama model pulls are blocked unless the active server has a valid DCP-managed process record pointing at the Downloads model directory. If an external Ollama server is already running, user must stop it through DCP and start the DCP-managed service before pulling; this prevents silently downloading into an unknown/default model store.
- UI copy now names the Downloads destinations rather than implying the native hidden cache.
- `php platform/tests/local-ai-ui-contract.php` passes. Full platform suite `php platform/tests/run.php` passes, including new assertions for both Downloads destinations and the Ollama guard.
- This change is source-only so far. No models were downloaded, no existing model data was moved, and no privileged agent install was run.
- Remaining acceptance: rebuild and launch the development Mac app; user visually tests DC/DSH modal; verify the Downloads paths with a small controlled model download; then deploy updated source to Windows/Ubuntu VMs and validate their paths and installer UI.


## DC74 — 2026-11-26 follow-up
- Fixed chat vertical sizing at the correct layout level: `.content.chat-page-active` is a bounded flex container; `#page-chat.active` fills the flex space; `.chat-layout` grows to the available height. The old 100%-height child was resolving inside an auto-height page, causing the blank area below chat.
- `showPage('chat')` toggles the parent content class; navigating away removes it. Added a contract test for this exact relationship.
- Full platform suite and local AI UI contract pass; PHP lint for all app/tests files, JS and installer shell syntax, and diff check pass.
- Live Cloud health: public `/api/v1/health` returns database/schema ok but relay=false and signaling-only. Mac development `/api/cloud/status` returns connected=true with heartbeat verified and anonymous Guest identity. Therefore public Cloud API is online; relay is not deployed/enabled.
- VM reachability: Ubuntu and Windows SSH aliases work. Ubuntu DCP app was not running on port 60776 when inspected; updater watcher and Ollama processes were running. Windows DCP port 60776 was not responding at initial check. VM UI Offline likely reflects app process/source or per-device credentials, not a public Cloud outage.
- Do not modify stable DCP install, do not alter VM user data/models, do not run privileged Node/DC/DSH installation. Preserve untracked NETWORK-RESUME-REQUEST.json.


## DC74 Cloud latency fix — 2026-11-26
- `CloudService::status()` now treats paired-device and pairing-request lists as best-effort snapshots: one attempt, 3s connect / 5s total timeout. Primary heartbeat keeps its retry policy. This prevents optional UI detail calls from holding up the connection state. Regression assertion added to `platform/tests/run.php`; full suite passes.
- Ubuntu VM: Ollama process had two listeners: 11434 (API) and 60776 (runner). DCP fixed-port startup refused to choose a random port. Gracefully stopped the orphaned Ollama process and system service to free 60776; existing `~/.ollama/models` directory remains present and untouched. Started DCP in the active GNOME/Wayland session. `/api/bootstrap` HTTP 200. `/api/cloud/status` HTTP 200 with connected=true, anonymous Guest identity and heartbeat verified. Cloud health endpoint returns DB/schema ok; public health connection from VM took ~8.5 seconds. Optional paired-device details may be unavailable; heartbeat remains verified.
- The system Ollama service requires interactive authentication to start again; it remains stopped. No model files were moved/deleted.
- Synced chat layout and CloudService source to Windows DCP package directory; Windows DCP process was not running. Do not launch as SSH account `dcpadmin` because user `amradeldotcom` has the active console session and different user data.
- Cloud public health reports relay=false / signaling-only. Relay is not deployed/enabled.
