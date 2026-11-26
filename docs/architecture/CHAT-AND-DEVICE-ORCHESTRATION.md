# DCP Chat, Tasks and Device Orchestration

Status: Product architecture and implementation contract.
Owner: DCP Core.
Scope: DCP, DP-Core, Desktop Commander (DC), DSH Agent and future adapters.

## Product model

DCP is the shared workspace and policy/control plane. DP-Core owns identity, task orchestration, device registry, capability grants, messaging and coordination. Desktop Commander (DC) is an optional execution plugin/adapter, not the identity or control plane. DSH is an independent agent adapter that can register with DCP Core. Future execution modules register through the same adapter contract.

The Chat workspace is one conversation surface with multiple execution contexts. Switching a context must not create a separate conversation unless the user explicitly starts a new chat.

## Workspace geometry

- Conversation history occupies 25% of the horizontal workspace.
- Active conversation occupies 75%.
- History scrolls independently; messages scroll independently.
- Composer remains anchored at the bottom of the active conversation.
- Messages and composer take the main vertical space.
- AI provider/model selectors stay in the composer; large Local AI/API provider cards are not rendered above the conversation.
- Optional Tasks panel appears only when Tasks is selected and remains below the composer.
- Context dock is at the very bottom, after the composer and optional Tasks panel.
- Context dock contains AI, Tasks, then one tab per online paired device using its friendly name.
- If no paired device is online, show No Device Online.
- Device tabs scroll horizontally with left/right arrows.
- A new unread item for a context displays a small red dot using DCP's accent palette. Opening that context clears its unread marker.
- Sidebar hamburger collapses/expands the main navigation and persists the user's choice.

## AI context

The AI tab groups local Ollama models, local llama.cpp models (including imported GGUF), and configured remote API providers. The composer shows the selected provider/model.

Typing @ opens a context picker. In AI context it can offer local engines/providers and online paired devices. Selecting a device changes the active context to that device; it must not silently bypass capability grants.

## Task context

A task can target DP-Core, DC, DSH Agent or any future registered agent/module.

Typing @ in Tasks opens a target picker. After choosing a device, the picker lists only modules actually registered on that device (for example DP-Core, DC, DSH). A task sent to another device creates a signed task envelope and an approval request on the destination. It remains pending until the destination owner approves it. No remote task executes merely because it was composed or delivered.

The existing local Task Inbox remains a local queue for follow-up instructions while the current assistant response is running. It is distinct from cross-device task dispatch.

## Device context

Each online paired device has a named tab. Opening it provides a conversation with that device's DCP Core/agent, its registered agents/adapters, Local AI resources, API providers and permitted tools/resources such as terminal, files, processes and workspaces, subject to grants.

A resource request follows this sequence:
1. Source device selects a resource and action.
2. DCP Core checks the destination grant for source device, capability and operation.
3. If a valid permanent grant exists, DCP sends a signed request to the owner node.
4. If approval-required policy exists, DCP sends a request to the owner device and shows pending approval.
5. If denied or revoked, reject before execution.
6. Owner node resolves its own local provider-secret reference and executes locally; provider secrets never move to the requesting device.
7. Owner signs the result; Cloud validates the envelope/result and makes it available to the source device.
8. Source conversation displays result and audit metadata.

## Permissions and resource sharing

Device owners manage access from Devices → Resource access. Each capability can be configured per paired source device as Always allow, Ask every time (default for sensitive capabilities), or Deny.

Grants are least-privilege and scoped by device, resource, operation and optional quota/concurrency. Revocation takes effect for future claims and prevents an unclaimed request from executing. Every grant, approval, rejection, execution and revocation is audited.

No grant means no access. Pairing presence alone is not a resource grant. A device must not receive raw API keys, local master keys or provider secrets.

## Protocol and API contracts (planned)

These are target contracts, not production-ready routes until implemented and tested:
- GET /api/chat/contexts — local providers, engines, online devices and registered modules.
- GET /api/devices/{device_id}/capabilities — owner-published capability inventory.
- GET|POST /api/devices/{device_id}/grants — per-device capability policy.
- POST /api/tasks/dispatch — signed task envelope for DP-Core/DC/DSH or future adapter.
- POST /api/remote-requests — request owner approval for a resource/action.
- POST /api/remote-requests/{id}/decision — owner approve/reject.
- GET /api/remote-requests/{id} — status and signed result.

Cloud is the coordination/transport layer, not the execution host. Execution and secret resolution stay on the owner device. Remote traffic uses authenticated HTTPS Cloud endpoints; local agent ports remain loopback-only unless a separately reviewed transport requires otherwise.

## External agent installation

DC and DSH remain independently installed OS-level agents/services. DCP must not bundle their runtimes inside its application directory or make their installation a prerequisite for opening DCP. The Devices/Connections UI should detect installed agents, offer an explicit install/open-official-installer action when absent, and open the operating system's default browser for their normal sign-in/login flow. Registration with DCP Core occurs after the agent is installed and the user authorizes its local adapter connection. Secrets remain in the owning agent's protected store. No silent installation or background login.

## Acceptance tests

- 25/75 layout and independent history/message scrolling.
- AI and Tasks tabs remain at bottom; device tabs scroll with arrows.
- No online devices shows exact No Device Online state.
- Unread context displays and clears its red dot.
- @ picker filters providers, devices and modules by active context.
- Local AI/API chat sends by button and Enter; Shift+Enter inserts newline.
- Task Inbox queues instructions without executing them.
- Remote task/resource requests are blocked without a grant, require approval when configured, and run only after approval.
- Permanent grants are per source device and per capability; revocation prevents new claims.
- Owner provider secrets remain local; signed results return to source conversation.
- Hamburger works on Windows, macOS and Linux without JavaScript errors.
- DC and DSH can be installed independently and then registered through their adapters.
