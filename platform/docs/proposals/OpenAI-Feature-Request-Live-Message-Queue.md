# Feature Request: Live Message Queue and Mid-Execution Task Injection in ChatGPT

**Suggested feature name:** Live Task Inbox / Interrupt Queue

Hello OpenAI Product Team,

I would like to suggest a capability that could significantly improve ChatGPT's experience when working on long-running tasks, especially in agentic workflows involving research, coding, file operations, browser navigation, and multi-step execution.

## The problem

When ChatGPT is executing a long task, users often need to provide additional information, corrections, priorities, or new requirements before the task is complete.

Today, the interaction can feel too sequential: the user may need to wait for the current response or execution cycle to finish, interrupt the work, or start another conversation. This creates unnecessary friction and can break the continuity of complex tasks.

## The proposed solution

Introduce a persistent **Live Task Inbox** that allows users to send new messages while ChatGPT is actively working.

New messages would enter a visible queue instead of being lost, ignored, or forcing an immediate interruption.

The user should be able to choose how each queued message is handled:

1. **Queue for later:** Keep working on the current task and process the message afterward.
2. **Integrate at the next checkpoint:** Pause at the next safe execution boundary, review the new instruction, update the plan, and continue.
3. **Interrupt now:** Safely pause the current execution, preserve its state, and review the new message immediately.
4. **Run separately:** Start an independent task without changing the current one.

## Suggested user experience

While ChatGPT is working, a compact Task Inbox panel or input area remains available.

Each queued message displays:
- Its timestamp and priority.
- The task it belongs to.
- Its current status: Queued, Reviewing, Ready to Integrate, Integrated, Executing, or Completed.
- A short explanation of how it affects the current plan.

Users can reorder, edit, remove, defer, or promote queued messages.

For example:

A user asks ChatGPT to build and test a software application. While the agent is editing files and running tests, the user sends:

- "Add support for Ubuntu."
- "Do not modify the existing database."
- "Prioritize the installer before the UI redesign."

The agent should receive these messages immediately, preserve them, and integrate them at a safe checkpoint without requiring the user to stop the entire workflow.

## Important execution behavior

The queue should be separate from the agent's execution loop.

Receiving a new message should not automatically cancel the current operation. Instead, the agent should finish the current safe unit of work, checkpoint its progress, inspect the new instruction, and decide whether the execution plan needs to change.

For urgent interruptions, ChatGPT should:
- Stop starting new operations.
- Safely pause cancellable operations.
- Preserve completed work and current state.
- Summarize what has and has not been completed.
- Re-plan after reviewing the new instruction.

Destructive or sensitive actions should still require explicit user approval.

## Persistence and recovery

The queue and task state should survive:
- Browser refreshes.
- Closing and reopening the conversation.
- Temporary network interruptions.
- Application restarts.

When the session is restored, ChatGPT should be able to recover the pending messages, the last confirmed checkpoint, and the current task plan.

The system should verify which operations actually completed before resuming, to avoid duplicate actions.

## Why this matters

This would make ChatGPT feel less like a sequence of request-and-response turns and more like a collaborative workspace where the user and AI can continuously coordinate during execution.

It would be especially valuable for:
- Software development and debugging.
- Long-running research.
- Multi-step browser and computer-use workflows.
- Document and data processing.
- Project management.
- Tasks that require ongoing human feedback.

The key idea is simple:

**Users should not have to wait for an AI agent to finish working before they can tell it something important.**

A persistent message queue with safe interruption and context-aware integration would make long-running agent workflows substantially more flexible, reliable, and collaborative.

Thank you for considering this suggestion.
