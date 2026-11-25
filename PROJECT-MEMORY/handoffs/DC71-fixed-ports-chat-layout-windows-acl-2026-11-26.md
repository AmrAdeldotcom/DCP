# DC71 — fixed ports, chat context dock and Windows startup repair

Date: 2026-11-26
Branch: feature/local-ai-runtime-discovery
Repository: /Volumes/Data/ChatGPT/DCP-Source
VMs: Ubuntu 192.168.8.88; Windows 192.168.8.99

## User request

- Replace random desktop PHP ports with one DCP-owned fixed port shared by Windows/macOS/Linux.
- Reserve a dedicated port map for DCP UI/API, MCP, DP-Core, DC adapter, DSH, device bridge and AI gateway.
- Fix Windows startup access denied and ensure updates do not revert to the previous wrapper.
- Move chat context tabs to the bottom; history/chat split approximately 25/75; tabs AI, Tasks and online device names; show No Device Online; horizontal device scrolling and unread dots.
- AI context groups local AI and API providers; @ picker offers devices/providers.
- Tasks context targets DP-Core, DC, DSH or future modules; @ can select a device and its registered modules.
- Device context exposes owner resources only with grants; owner can configure permanent access or per-request approval.
- Fix Send and hamburger JavaScript wiring.
- Document the cross-device chat/resource orchestration architecture.
- Clarify independent installation of DC and DSH.

## Port registry

Six-digit user values are retained as logical service codes; they cannot be TCP ports. Ports are 16-bit values and must be <=65535. DCP uses private/dynamic-range ports:
- DCP local UI/API: 60776/tcp (active)
- DCP MCP: 60707/tcp (reserved)
- DP-Core: 60603/tcp (reserved)
- DC adapter: 60908/tcp (reserved)
- DSH: 60808/tcp (reserved)
- device bridge: 60214/tcp (reserved)
- AI gateway: 60216/tcp (reserved)

All bind to loopback by default. These are local conventions, not worldwide or IANA reservations. All seven were bind-tested free on Mac and Ubuntu; Windows netstat showed no listeners on them. Windows excluded port ranges do not include 60776.

## Fixed port implementation

- platform/config/ports.json stores the logical code, service, TCP port, bind address and active/reserved state.
- platform/config/app.php now identifies 60776 as the DCP port.
- Windows Program.cs, macOS main.m and Linux dcp-webview.c now test fixed port 60776 and do not silently select a random port.
- Linux DCP was rebuilt and relaunched; 127.0.0.1:60776 is listening and /api/status returns ok.
- macOS DCP.app was rebuilt with the fixed port wrapper; a fresh 60776 runtime acceptance is still pending.
- Windows DCP.exe, DCP.dll and the complete .NET publish output were deployed together (not only the apphost). This fixed the earlier stale-DLL issue where the executable still chose a random port.
- Windows user-data folder ACL was updated to grant the actual interactive account AMRWIN11\amradeldotcom full control while retaining Administrators and SYSTEM.
- Windows Program.cs now prefers portable DCP-UserData beside the app when writable and falls back to the user's LocalAppData on UnauthorizedAccessException; existing files are copied to fallback only if absent. WebView2 data uses per-user LocalAppData. Windows updater checks both fallback and portable runtime-port paths.
- Latest Windows process DCP.exe was observed, but PHP did not bind to 60776 and runtime-port/log acceptance is still unresolved. Do not claim Windows fixed-port acceptance yet.

## Chat UI and JS

- PageController chat markup now uses a 25/75 history/workspace split.
- Bottom context dock contains AI, Tasks and per-device tabs with scroll arrows; no online device state is No Device Online.
- AI context combines Local AI and API provider cards.
- Tasks panel includes DP-Core/DC/DSH target selector and retains local Task Inbox.
- Device context shows selected device status and declared scopes; access policy is managed through Devices.
- Added @ picker suggestions for local AI/providers, online devices and DP-Core/DC/DSH task targets.
- Added red unread-dot presentation state for device tabs. Backend notification-to-context routing is not yet complete.
- Wired Send button click and Enter (Shift+Enter remains newline) to sendChat.
- Wired hamburger to persistent sidebar collapse/expand.
- The current front-end context picker is an initial UI surface; it does not yet dispatch signed remote tasks or bypass/implement grants.

## Architecture docs

- docs/architecture/CHAT-AND-DEVICE-ORCHESTRATION.md defines the unified chat, AI/Tasks/device contexts, @ routing, DP-Core/DC/DSH targets, signed remote request flow, owner approval, permanent grants, revocation, audit, secret locality, planned APIs and acceptance criteria.
- docs/architecture/DCP-PORT-REGISTRY.md defines fixed ports, loopback policy, reserved service map and conflict handling.
- DC and DSH are specified as independently installed OS-level agents/services, with DCP detecting/registering them and opening the default browser for normal sign-in; DCP must not bundle their runtimes into its app folder.

## Validation

- Full platform test suite: ALL PLATFORM TESTS PASSED.
- Local AI UI contract tests: ALL LOCAL AI UI CONTRACT CHECKS PASSED.
- PHP lint and JavaScript syntax checks passed.
- Linux wrapper compiled on Ubuntu; DCP PHP API responded on 60776.
- Windows .NET publish completed and complete publish output copied to C:\DCP; Windows interactive acceptance remains pending.
- Mac DCP.app wrapper build completed successfully; existing deprecation warning for allowedFileTypes remains.
- Port listener checks: all seven ports free on Mac and Ubuntu before binding; Windows netstat showed no listeners and excluded ranges do not include 60776.

## Outstanding

1. Diagnose why latest Windows DCP process did not create PHP listener/runtime-port after the complete wrapper publish; check the current UI exception details if it remains visible.
2. Launch Mac DCP.app and verify 60776/API; visually verify bottom dock and Send/hamburger.
3. Visually verify Ubuntu chat dock and Send/hamburger on the live WebView.
4. Implement remote task dispatch, device-specific module inventory, owner approval, permanent grants, unread event routing and resource execution as Q015.
5. Implement independent DC/DSH installation detection and registration UX.
6. Continue Q015 signed Cloud owner-node execution and approval flow; Cloud account pairing still required.
7. Commit and push DC71 source/docs after validation; do not publish a signed release until the feature branch is safely integrated into main.
