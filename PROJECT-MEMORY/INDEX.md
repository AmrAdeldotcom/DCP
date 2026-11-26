# DCP Project Memory

This folder is the durable continuity layer for ChatGPT and other authorized engineering agents. It is subordinate to source code and the project’s active specifications; it must never override them.

## Read order at the start of every session
1. `PROJECT-MEMORY/INDEX.md`
2. `PROJECT-MEMORY/CURRENT-STATE.json`
3. `WORK-QUEUE.md`
4. `AGENTS.md`
5. The active task file referenced by CURRENT-STATE.json
6. Relevant architecture/specification and latest version/handoff notes
7. `PROJECT-MEMORY/runtime/network-state.json` and `network-watchdog.log`; inspect `NETWORK-RESUME-REQUEST.json` if present and acknowledge it before clearing only after recording evidence

## Memory map
- `CURRENT-STATE.json`: machine-readable current checkpoint and next action.
- `DECISIONS.json`: durable decisions with rationale and status.
- `CHANGELOG.md`: chronological memory changes.
- `versions/`: immutable session/version checkpoints; add a new file, do not rewrite old checkpoints.
- `tasks/`: focused task plans, acceptance criteria, verification and blockers.
- `architecture/`: architecture notes and cross-component contracts.
- `bugs/`: reproducible bug records and diagnostics.
- `testing/`: test baselines and verification evidence.
- `handoffs/`: concise handoff notes for new conversations. Latest checkpoint: `handoffs/DC65-dcp-core-mcp-q012-execution-2026-11-25.md`.

## Update protocol
- After each meaningful task: update CURRENT-STATE.json, append CHANGELOG.md, and create a timestamped checkpoint in versions/.
- Update WORK-QUEUE.md as the operational task authority. Memory mirrors its state; it does not replace it.
- Record exact paths, commands, test outputs, commit IDs and known blockers. Separate verified facts from hypotheses.
- Never store credentials, tokens, API keys, .env contents, private keys, SQLite databases, personal data or other secrets here.
- Never claim a task is complete without its acceptance tests and evidence.
- Never commit, push, publish or deploy without the project’s approval gates.
- If connection drops, do not retry a side-effecting call while it is pending. Check its call status first, then resume from CURRENT-STATE.json.

- `ACCESS-INVENTORY.md` — safe inventory of configured GitHub, Hostinger and lab SSH routes; never stores secret values.

- Latest handoff: PROJECT-MEMORY/handoffs/DC42-stable-tag-and-release-run-2026-11-25.md — main fast-forward to cb3087e, v1.0.0 stable tag at exact main HEAD, Alpha10 Delta base, release run 45538884432 progress and post-publish cleanup workflow.

- Latest Alpha 10 cross-platform rollout checkpoint: PROJECT-MEMORY/handoffs/DC20-alpha10-three-platform-update-rollout-2026-11-24.md (Windows and Ubuntu installed; macOS Little Snitch github.com allow pending; signed release workflow still building).

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC43-task-inbox-queue-mvp-2026-11-25.md — Stage 6 remains open; Q012 persistent Task Inbox first slice implemented locally and fully tested.

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC44-stage6-capability-matrix-2026-11-25.md — Stage 6 remains open; Q012 Task Inbox and Q014 read-only per-device capability matrix slices pass local tests.

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC45-stage6-workspaces-signed-envelopes-2026-11-25.md — isolated workspaces and Ed25519 envelope foundation pass local tests; legacy Device Agent is available but not signed-envelope integrated.

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC46-stage6-signed-device-agent-e2e-2026-11-25.md — signed remote Device Agent task/result protocol passes local end-to-end tests; real OS acceptance remains.

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC47-stable-release-cleanup-and-stage6-2026-11-25.md — stable v1.0.0 and cleanup verified; Q005 live device acceptance remains; Stage 6 continues.

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC48-chat-background-jobs-safe-checkpoint-e2e-2026-11-25.md — DCP Chat background jobs and provider-boundary checkpoint; local E2E pass, cross-platform CI pending.

- Latest checkpoint: PROJECT-MEMORY/handoffs/DC52-shared-mcp-tool-registry-2026-11-25.md — Stage 6 / Q012 shared MCP tool registry, capability/approval policy, full test-runner correction, and MCP stdio E2E. Local full platform + worker + HTTP tests pass; cross-platform CI run 45589253032 pending. Provider tool-call contract and agent coordinator remain open.


## DC53 Q012 execution plan summary
- Read `PROJECT-MEMORY/Q012-EXECUTION-PLAN-SUMMARY.md` for the user-facing phase/task table, stored plan/checkpoint/tool references, exact seven-tool catalog, provider contract, test evidence and DEP-Source/DEP isolation.


## Latest handoff
- `handoffs/DC57-q012-idempotent-execution-ledger-2026-11-25.md` — current Q012 Task 5 progress, MCP local/Cloud, temporary memory, tests and next work.

- Latest immutable checkpoint: `versions/2026-11-25-DC65-dcp-core-q012.json`.


- Latest handoff: handoffs/DC66-dcp-mcp-keepalive-resource-mesh-2026-11-25.md; latest immutable checkpoint: versions/2026-11-25-DC66-mcp-keepalive-resource-mesh.json.
- Resource Mesh / Universal AI Gateway architecture: platform/docs/DCP-RESOURCE-MESH-AND-UNIVERSAL-AI-GATEWAY.md.


- Latest DC67 handoff: handoffs/DC67-dcp-core-cloud-resource-mesh-terminal-sessions-2026-11-25.md; latest immutable checkpoint: versions/2026-11-25-DC67-dcp-core-cloud-resource-mesh-terminal-sessions.json.


- **Current handoff (DC68):** `handoffs/DC68-local-cloud-mcp-q015-2026-11-25.md`; immutable checkpoint: `versions/2026-11-25-DC68-local-cloud-mcp-q015.json`. Local/Cloud MCP source implementations are committed; Q015 continues with Cloud owner-node resource invocation, team/device grant parity, Hermes and cross-platform acceptance.


## 2026-11-25 — DC69
- Static VM addresses, verified SSH access, GGUF import workflow, terminal UX, Cloud reconnect and cross-platform acceptance status: `handoffs/DC69-static-vm-network-gguf-cloud-reconnect-2026-11-25.md`.


- Current handoff (DC70): PROJECT-MEMORY/handoffs/DC70-gguf-runtime-chat-context-windows-ubuntu-fixes-2026-11-25.md; real Qwen3 llama.cpp/Ollama inference passes, Ubuntu dynamic-port blank-screen fix deployed, chat context tabs implemented. Windows GUI acceptance and GitHub default-branch integration remain open.


- Current handoff (DC71): PROJECT-MEMORY/handoffs/DC71-fixed-ports-chat-layout-windows-acl-2026-11-26.md; fixed DCP port registry, bottom chat context dock, Send/hamburger handlers and device-orchestration architecture docs. Windows runtime acceptance remains open.

- Model portability decision: docs/architecture/MODEL-PORTABILITY-AND-DEVICE-IDENTITY.md — GGUF SHA remains stable; device UUID/signing identity rotates on clone; Cloud re-pair required.

- DC72 handoff: PROJECT-MEMORY/handoffs/DC72-os-level-runtimes-chat-windows-cleanup-2026-11-26.md; OS-level engine paths, native model caches, conversation-first chat, VM cleanup and self-contained Windows wrapper.
- Runtime installation contract: docs/architecture/LOCAL-RUNTIME-INSTALLATION.md.

- DC72 version snapshot: PROJECT-MEMORY/versions/2026-11-26-DC72-os-level-runtimes.json — build 11, all three DCP local API ports, engine cleanup and remaining Cloud/Q015 acceptance.

- Runtime installation pipeline: docs/architecture/RUNTIME-ACTION-PIPELINE.md — prerequisites, verified installs, progress/error reporting, and native model stores.

- Chat composer UX: docs/architecture/CHAT-COMPOSER-UX.md — one shared AI/Tasks composer with bottom context tabs and device tabs.

- DC72 handoff: PROJECT-MEMORY/handoffs/DC72-runtime-actions-single-composer-2026-11-26.md — Qwen3 source restore, implementation, test status and remaining acceptance work.
