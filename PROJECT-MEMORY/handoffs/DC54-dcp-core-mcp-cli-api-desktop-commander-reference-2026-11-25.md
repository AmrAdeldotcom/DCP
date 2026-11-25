# DC54 — DCP Core/MCP/CLI/API architecture + Desktop Commander reference + Q012 coordinator

Date: 2026-11-25

## Decisions documented
- DCP is a provider-neutral Distributed AI & Device Orchestration Platform. DCP Core is canonical; MCP STDIO + Remote HTTP, CLI, REST API and DCP Node/Agent Runtime are first-class interfaces.
- Desktop Commander is an optional plugin; DSH Agent is supported through the provider-neutral Agent Runtime. Team devices, local AI, cloud APIs and tools cooperate under Core policy.
- Architecture: `platform/docs/DCP-CORE-MCP-CLI-API-ARCHITECTURE.md`.

## Reference repository
- Cloned official open-source local MCP source to `/Volumes/Data/DiGiPL/References/DesktopCommanderMCP`.
- Remote: `upstream` fetch URL is the official GitHub repository; push URL was set to `DISABLED`. HEAD `9a101b17e44577f0baefa4c7120f087881c5cc56`, release `v0.3.4`, date 2026-11-18, MIT, Node >=22.12.0.
- No npm install, build, code execution or modifications were made to the reference checkout.
- The separate `desktop-commander/remote-desktop-commander` repository contains public manifests/docs and issue tracking only; hosted service backend is proprietary.
- Study log: `platform/docs/research/desktop-commander-reference-review.md`.

## DCP folder separation
- Source remains `/Volumes/Data/ChatGPT/DCP-Source`.
- Release install target is `/Volumes/Data/DiGiPL/DCP`.
- The abandoned partial installer state was moved from `/Volumes/Data/ChatGPT/DCP` to `/Volumes/Data/DiGiPL/DCP-Preinstall-State` (it contained only project-memory/network resume state, no DCP.app).
- No SQLite or master.key was copied from source into the new release install.

## Q012 Task 4 progress
- Added `DcpToolRegistry::validateProposal()` for shared allow-list/schema/semantic validation.
- Added `ChatExecutionCoordinator` with 16 tool-call limit, 16 KB aggregate args and 32 KB plan metadata limit, duplicate ID/unknown tool rejection, canonical args hashes, deterministic idempotency keys, versioned plans and approval records.
- State-changing/sensitive tools become blocked pending approval; safe-read proposals remain planned. No provider-proposed tool is executed.
- `ChatService::supportsToolCalls()` added. Detached worker now uses structured calls when supported, persists proposals, and falls back to legacy chat for providers without structured tool calling.
- Local full platform suite: `ALL PLATFORM TESTS PASSED`. Worker E2E: `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS`. Syntax checks pass.
- Not yet committed/pushed. Cross-platform CI pending.

## Immediate next steps
1. Verify the release installer running with `DCP_INSTALL_DIR=/Volumes/Data/DiGiPL/DCP` completes; it was started as PID 27213 with `DCP_INSTALL_NO_LAUNCH=1` to avoid opening the app automatically.
2. Verify package checksum, DCP.app version, bundled platform/runtime, isolated fresh DCP-UserData and HTTP health; never copy source SQLite/master.key.
3. Add review/read APIs for execution plans and approvals, then Task 5 approval-bound execution with exact plan-version + canonical-argument-hash checks and idempotency.
4. Run `php platform/tests/run.php`, worker E2E, HTTP E2E, PHP lint, JS syntax and `git diff --check`; update the cross-platform workflow but do not push without approval.
5. Continue studying Desktop Commander source read-only; no dependencies installed in the reference repo.

## Safety
- Do not run updater/recovery against DCP-Source.
- Do not run `git reset`, `git clean`, commit or push.
- Keep reference repo separate from DCP packages.
- No tool execution based solely on provider output; all proposed calls require review and explicit approval policy.
