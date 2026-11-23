# DCP Product Vision

## Product promise
One portable DCP folder can be carried to a compatible Mac or Windows machine and launched without changing that machine's existing Apache, PHP, Ollama, Python, Node, MySQL or other services. DCP detects what is already installed and only provisions missing dependencies inside its own managed environment.

## Main experiences
- Home / Overview: honest health of the local platform, engines, providers, agents, cloud and remote peers.
- Chat: one conversation surface for local models, API providers and invited devices/agents; mentions such as @dc and @dsh; approval cards; persistent hierarchical memory.
- Local AI: discover system Ollama/llama.cpp first, then DCP-managed runtimes; custom GGUF import, run, convert to Ollama, switch engine, delete safely.
- Devices: local identity, cloud status, account sign-in, pairing invitations, incoming approvals, online presence, permissions, pause/resume/revoke and per-device workspace.
- Connections: API providers and agent adapters with install/start/stop/restart/login/test/uninstall actions and truthful status.
- Mail: dedicated inbox/outbox/drafts/sent/failed, multiple sender/recipient identities, SMTP/IMAP, attachments, AI task-based composition, replies and delivery history.
- Tasks: approvals, execution state, logs, target device and result.
- Terminal: full local-user shell on the local machine only; remote terminal is scoped to the remote workspace and granted capability.
- Settings: cloud base URL, language/theme, email accounts, runtime policy, updates, permissions and diagnostics.

## User experience rules
- Account/login icon in the global header; no cloud sign-in buried only in Settings.
- Pairing and device lifecycle live in Devices.
- Mail is a first-class page; inbox is not hidden in Settings.
- System messages are persistent stacked notices with copy/close and sensible auto-dismiss, and are also written to the usage/audit log.
- Every async section has loading skeleton, empty state, retryable error state and bounded requests.
- Keyboard copy/paste uses native WebView behavior and context menus remain available.

## Attribution
About page credit: "Product ideas, workflows, application direction and visual style by Amr Adel. ChatGPT assisted in translating Amr's ideas into software implementation."
