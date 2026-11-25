# Implementation Plan: Q012 Agent Execution Coordinator and Safe Tool Loop

Source spec: `platform/docs/proposals/DCP-Chat-Live-Task-Inbox-Interrupt-Queue.md`
Operational tracker: `WORK-QUEUE.md` (Q012 remains the task authority)
Current checkpoint: DC52 / Stage 6

## Overview

Connect DCP Chat's existing background jobs and schema-v2 execution-plan checkpoint foundation to a bounded agent/tool coordinator. The coordinator must preserve the current safe defaults: queued instructions remain manual, model-proposed actions are untrusted, every tool call is allow-listed and schema-validated, sensitive or mutating actions require explicit human approval, and no checkpoint is reported as resumable until the coordinator can safely resume it.

## Architecture decisions

- Reuse one DCP tool catalog for both the existing stdio MCP host and the in-process Chat coordinator; do not maintain separate tool definitions or permission rules.
- Keep `ProviderAdapter::chat()` backward-compatible. Add a separate structured tool-call capability rather than changing the current string-returning method.
- Normalize tool calls into a provider-neutral contract. Validate tool name, JSON arguments, schema, user/session scope, capability and policy before dispatch.
- Tool annotations such as `readOnlyHint` are descriptive only; they are not authorization. Each tool receives an explicit policy classification (safe-read, sensitive-read, workspace-write, external-side-effect, unsupported).
- Read-only tools are bounded by explicit DCP capabilities. Sensitive reads and all writes/external side effects require a persisted approval decision tied to the current plan version and step.
- Approval decisions never execute a tool by themselves. A separate coordinator action re-checks the approval, task state, capability and idempotency key before execution.
- Bound each run (maximum tool iterations, total tool calls, per-tool timeout, result bytes and plan steps). Stop safely on provider refusal, malformed arguments, policy denial, timeout or unresolved approval.
- Persist only opaque tool-result references and SHA-256 hashes in checkpoint context. Raw results live in bounded, private workspace artifacts; secrets are redacted before persistence or model re-entry.
- Tool output and external content are untrusted data, never new system instructions. No tool may grant itself additional scopes.
- Task Inbox remains manual-review only. No implicit merge, automatic send, automatic deployment, arbitrary terminal execution or silent remote trust.
- Cross-platform target remains macOS, Windows and Ubuntu 24.04; preserve Linux/Kali packaging compatibility.

## Task list

### Phase 1 — Shared tool contract
- [x] Task 1: Extract the MCP tool catalog and dispatch contract into a reusable service. Keep stdio MCP behavior unchanged. Add explicit policy classes and schema validation.
- [x] Local Checkpoint 1: Existing MCP stdio contract, shared-registry policy/schema tests, and isolated MCP host E2E pass. Cross-platform CI remains pending.

### Phase 2 — Provider tool-call contract
- [x] Task 2: Add provider-neutral structured tool-call results without breaking ordinary chat responses. (Local implementation + fixtures pass; cross-platform CI pending.)
- [x] Task 3: Implement tool-call parsing for Ollama and OpenAI-compatible providers, including malformed/refusal/empty response handling. (Local implementation + fixtures pass; cross-platform CI pending.)
- [x] Local Checkpoint 2: Provider fixtures prove normalized Ollama/OpenAI-compatible calls and strict malformed/refusal/empty/bounds handling. Cross-platform CI remains pending.

### Phase 3 — Bounded coordinator
- [ ] Task 4: Implement a coordinator that creates a versioned plan, records steps, enforces dependency order and dispatches only policy-allowed tools.
- [ ] Task 5: Persist bounded result artifacts, opaque references and approval requests; enforce idempotency and safe stopping.
- [ ] Checkpoint 3: End-to-end tests prove a read-only flow, an approval-blocked write, explicit approval followed by a separately initiated execution, and no duplicate side effect after retry.

### Phase 4 — User review and recovery
- [ ] Task 6: Add APIs and UI for pending approvals, plan review, step status and explicit resume. Keep pending queue items manual.
- [ ] Task 7: Add worker lease heartbeat, crash recovery and idempotent step resume; only then may eligible snapshots report resumable=true.
- [ ] Checkpoint 4: Kill/restart tests prove completed steps are not repeated and unresolved approvals remain blocked.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Model emits malformed or adversarial tool arguments | Unauthorized or unsafe action | Strict JSON schema, allow-list, capability check and policy gate before dispatch |
| Tool result contains prompt injection or secrets | Instruction hijack or data leak | Treat as untrusted data; redact; store raw data only in private workspace artifacts |
| Provider retries a tool call | Duplicate side effects | Stable idempotency keys and durable per-step result state |
| Worker crashes during a tool action | Unknown partial completion | Mark step outcome uncertain; inspect result reference before retry; never blindly replay |
| Approval is stale after plan changes | Wrong action approved | Bind approval to plan version, step and argument hash |
| Cross-platform process/ACL differences | Security or reliability regression | Full platform contract suite on Ubuntu, Windows and macOS CI |

## Open questions

- Which tool categories should be enabled by default for a newly configured provider? Safe default: none until the user enables a capability in DCP.
- Should approval UI require approval of the exact canonical argument hash, not just the step summary? Proposed: yes for all non-read-only tools.

## Definition of done

- Existing ordinary chat and MCP stdio contracts remain backward-compatible.
- No tool call bypasses allow-list, schema, capability, approval, plan-version or idempotency checks.
- No automatic execution of queued user instructions.
- Full platform suite, provider contract tests, worker E2E and HTTP E2E pass on macOS, Windows and Ubuntu 24.04.
- Security review covers tool argument validation, prompt-injection boundaries, result storage, approval freshness and crash recovery.
- `WORK-QUEUE.md`, Q012 task file, CURRENT-STATE and a versioned handoff are updated at each checkpoint.
