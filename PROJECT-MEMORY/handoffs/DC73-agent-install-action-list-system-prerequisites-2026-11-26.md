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
