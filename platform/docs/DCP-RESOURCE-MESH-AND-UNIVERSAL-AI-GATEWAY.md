# DCP Resource Mesh & Universal AI Task Gateway

Date: 2026-11-25
Status: product architecture approved for implementation planning; not yet implemented as a complete feature.

## Product objective

DCP must be broader than Desktop Commander. Desktop Commander is a local computer-control tool. DCP is a distributed resource and execution fabric that can connect:

- Local AI runtimes and models on macOS, Windows and Linux machines.
- Cloud AI providers and compatible API endpoints.
- Team-owned API credentials and private services, without copying secrets to the Cloud control plane.
- DCP Nodes, devices, workers and agent runtimes.
- Hermes Agent and other external agents through adapters.
- Major AI clients/providers through MCP, authenticated HTTP APIs and provider-specific connectors.
- Human users, teams and organizations with shared tasks, artifacts, policies and audit.

## Core architecture

1. **Resource Registry** — canonical inventory of local models, inference engines, API providers, tools, devices, agents and their live health/capacity.
2. **Resource Broker** — selects an eligible resource using owner grants, locality, model/context compatibility, VRAM/RAM, latency, cost, queue depth and privacy classification.
3. **DCP Node Runtime** — outbound authenticated node process on each machine. It owns local credentials and executes only locally granted capabilities.
4. **Task Gateway** — accepts requests from MCP clients, DCP API clients, Hermes and provider connectors. Every request becomes a durable task with an issuer, target, policy, expiry, idempotency key and result contract.
5. **Coordinator** — builds a versioned dependency graph, routes work to agents/tools/models, records progress and handles retries only when the operation is safe and idempotent.
6. **Team Sharing** — publishes resource *capabilities and invocation handles*, not raw credentials. Resource owners grant access by team/member/client/device and can set concurrency, quota, expiry and allowed operations.
7. **Cloud Control Plane** — PHP + MySQL + existing JS/CSS. It stores account/device/team/resource metadata, grants, task envelopes, acknowledgements, status, audit and artifact references. It is not a remote shell host.
8. **MCP + API Surface** — one shared DCP Core service contract exposed through local STDIO, local Streamable HTTP, authenticated Cloud MCP and REST APIs. CLI and UI use the same service layer.

## Resource sharing model

A resource record should identify:
- Stable resource ID and owner (user/team/device).
- Kind: local model, inference engine, external API, MCP server, tool, agent or dataset.
- Provider/engine, model identifier, endpoint class and supported operations.
- Device location, platform, hardware/capacity and health heartbeat.
- Data classification, locality requirements and allowed egress.
- Secret reference stored only in the owner device's encrypted credential vault.
- Published team grants, quota, concurrency, expiry and revocation generation.

Never send API keys, provider passwords, private keys or raw credential material to another node or to Cloud. A node can accept a signed invocation envelope and resolve the secret locally. For team-owned cloud APIs, the designated owner node acts as the credential-holding execution proxy.

## Team and machine authorization

Capabilities are separate and revocable:
- `resource.discover`
- `resource.invoke`
- `resource.manage`
- `resource.share`
- `task.submit`
- `task.cancel`
- `task.approve`
- `artifact.read`
- `agent.invoke`
- `device.pair`

Grant dimensions: owner, organization/team, member, client, device, resource, operation, expiry, quota and policy version. A client’s MCP consent does not override DCP grants. Destructive operations remain reviewable and explicitly approved; safe reads and inference can be automated when the owner has granted that exact scope.

## Universal AI ingress

- **MCP:** Claude and other MCP clients can discover/call DCP Core tools.
- **REST API:** external systems can submit tasks, poll status, cancel, approve and retrieve artifacts using scoped API tokens.
- **OpenAI-compatible tool calls:** normalize provider function calls into the same versioned DCP plan; provider output is a proposal, never an implicit grant.
- **Provider adapters:** support Anthropic, OpenAI, Gemini and compatible endpoints without coupling the coordinator to one vendor.
- **Agent adapters:** Hermes Agent and other external agents implement a common lifecycle contract (discover, health, submit, poll, cancel, receive event, return artifact). An adapter must not claim connected until authenticated task submission and result retrieval are verified.
- **DCP-to-DCP:** signed, short-lived task envelopes routed to the intended device, with target binding, issuer trust, replay protection, acknowledgements, offline state and signed results.

## Task lifecycle

`received → authenticated → policy_checked → planned → awaiting_approval (when required) → dispatched → acknowledged → running → result_staged → verified → completed`

Terminal states include rejected, expired, cancelled, failed and uncertain. An uncertain side effect is not automatically retried. Every transition records actor, task ID, plan version, target, resource, policy/grant version, timestamps and artifact hashes.

## Execution strategy

- Route compute to the nearest eligible resource where privacy policy permits.
- Prefer local inference when data must remain on-device.
- Allow remote provider use only when the data classification and owner grant permit it.
- Schedule work by resource health, capability, context size, memory/VRAM, queue depth, cost and latency.
- Keep long-running processes under a durable Node-owned session manager, not a short-lived PHP HTTP request.
- Expose terminal control only on a paired and explicitly authorized device; never execute a remote shell inside the Cloud web host. A remote terminal request must be presented to the user on the target device with the requesting identity, exact command/script, working directory, shell and expiry. The target user must explicitly approve or reject it locally before any process is started. Approval is bound to the exact command hash, target device, issuer, one-time task ID and expiry; edits require a new approval. No blanket/standing remote-terminal consent is implied by pairing, MCP consent or approval on the requesting device.

## Delivery phases

1. **Foundation (next):** canonical resource schema, registry service, device heartbeat/capacity report, local API/CLI and MCP discovery tools.
2. **Local execution:** model/API invocation through owner-local credential references; durable PTY/session manager shared by STDIO and HTTP; process and filesystem parity.
3. **Team sharing:** team/member/client/device grants, quotas, expiry, revoke, audit and resource availability UI.
4. **Agent integration:** Hermes adapter plus generic AgentAdapter lifecycle and signed task/result contracts.
5. **Universal gateway:** Cloud task ingress, OpenAI-compatible/Anthropic/Gemini adapters, webhook events and MCP remote tools.
6. **Distributed orchestration:** scheduler, dependency graph, queue/leases, offline retry policy, artifact transfer, progress streaming and worker recovery.
7. **Acceptance:** real macOS, Windows and Ubuntu nodes; multi-user/team grants; adversarial authorization/replay tests; live provider and Hermes integration; signed release and production Cloud deployment.

## Current implementation facts and gaps

Already present locally:
- 31-tool shared local STDIO DCP Core registry, including full foreground shell, STDIO interactive sessions, filesystem and process tools, Cloud pairing/signaling.
- Local Streamable HTTP MCP with separate scopes; it currently exposes 27 tools because PHP request lifetimes do not own interactive process handles.
- Provider-neutral tool-call normalization for OpenAI-compatible and Ollama drivers; proposals become versioned plans and require explicit review/execute.
- Signed Device Agent task-envelope and signed-result foundations, replay/expiry/target binding, plus legacy approval-based workspace task polling.
- DCP Core Cloud status/pairing/signaling. Cloud remote MCP currently exposes read-only device/pairing tools.
- Q012 plan-bound grants, explicit per-step execution, private redacted artifacts and integrity-checked resume.

Not yet complete:
- Shared resource registry and resource broker across teams/devices.
- Team-level resource grants and quota/revocation UX.
- Durable PTY/session daemon shared across transports and restart recovery.
- Signed task dispatch integrated with the current Cloud Node transport for general DCP tool/model/API work, including target-device approval UI for remote terminal commands.
- Verified Hermes adapter and Anthropic/Gemini ingress.
- Live Windows/Ubuntu acceptance of the newest DCP Core changes.
- Production Cloud deployment remains gated by APP_KEY rotation and deployment review.

## Non-negotiable boundaries

DCP should feel unrestricted in capability to an authorized user, but it cannot silently gain OS administrator/root rights, defeat macOS TCC, bypass Windows UAC, evade Linux permissions, or use another team's secrets without a grant. "Full terminal" means arbitrary commands as the DCP service identity with the same rights that identity actually has. High-impact changes, remote execution, credential access and cross-team sharing remain auditable and revocable.


## DC67 terminal request workflow
- A source DCP Terminal may select only a paired device that granted the separate terminal.request scope.
- Source submits the exact command, working directory, shell and bounded timeout to Cloud. Cloud stores a canonical SHA-256 binding to request ID, source/target devices and all command parameters, with a five-minute expiry.
- Target DCP Devices page displays requester identity, exact command, working directory, shell, timeout, expiry and hash. Target user explicitly approves or rejects. Approval is recorded against the same hash and target device and does not itself execute or dispatch.
- Remote execution remains disabled until signed Cloud delivery, target-side verification and signed result handling are implemented. Pairing, source-side confirmation or an approval record alone never executes a remote shell.
