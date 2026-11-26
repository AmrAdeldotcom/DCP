# DCP Chat Composer UX Contract

## One composer only

AI chat and Tasks must share exactly one message-entry box. Do not render a second Task Inbox textarea or a separate task card below the chat.

## Layout

- Conversation history: left column, approximately 25% of workspace width.
- Chat area: right column, approximately 75%.
- Message history consumes the available vertical space without large cards above it.
- One full-width composer sits directly below message history.
- Context tabs sit below the composer at the bottom edge of the chat workspace.

## Bottom context bar

- AI
- Tasks (with queued count)
- Connected device tabs, each labeled by device name
- If none are online, show "No Device Online"
- Horizontal arrows scroll the device strip when it overflows.
- Unread messages show a small red DiGiPL/DCP accent dot on the relevant device tab.

## AI mode

- The shared composer provides Local AI and configured API providers, model selection, attachment support and @ mentions.
- @ opens a searchable list of online devices and eligible local resources.
- Selecting a device changes the execution context; it does not create another composer.

## Tasks mode

- Reuse the same composer textarea.
- Change the placeholder and submit action to "Queue task".
- Show a compact target selector in the composer: DP-Core, DC, DSH, and future registered agents.
- Persist the selected target with the queued task.
- Tasks remain queued for review and are not silently executed.
- Device/agent routing must use the explicit device pairing, permissions and approval workflow; a target selector alone does not grant permission.

## Device mode

- Selecting a device tab reuses the same message box.
- Local AI/API resources on that device are visible only when granted.
- Missing permission creates an approval request; it must not bypass the target device's policy.
