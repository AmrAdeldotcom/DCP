# DCP Project Memory

This folder is the durable continuity layer for ChatGPT and other authorized engineering agents. It is subordinate to source code and the project’s active specifications; it must never override them.

## Read order at the start of every session
1. `PROJECT-MEMORY/INDEX.md`
2. `PROJECT-MEMORY/CURRENT-STATE.json`
3. `WORK-QUEUE.md`
4. `AGENTS.md`
5. The active task file referenced by CURRENT-STATE.json
6. Relevant architecture/specification and latest version/handoff notes

## Memory map
- `CURRENT-STATE.json`: machine-readable current checkpoint and next action.
- `DECISIONS.json`: durable decisions with rationale and status.
- `CHANGELOG.md`: chronological memory changes.
- `versions/`: immutable session/version checkpoints; add a new file, do not rewrite old checkpoints.
- `tasks/`: focused task plans, acceptance criteria, verification and blockers.
- `architecture/`: architecture notes and cross-component contracts.
- `bugs/`: reproducible bug records and diagnostics.
- `testing/`: test baselines and verification evidence.
- `handoffs/`: concise handoff notes for new conversations.

## Update protocol
- After each meaningful task: update CURRENT-STATE.json, append CHANGELOG.md, and create a timestamped checkpoint in versions/.
- Update WORK-QUEUE.md as the operational task authority. Memory mirrors its state; it does not replace it.
- Record exact paths, commands, test outputs, commit IDs and known blockers. Separate verified facts from hypotheses.
- Never store credentials, tokens, API keys, .env contents, private keys, SQLite databases, personal data or other secrets here.
- Never claim a task is complete without its acceptance tests and evidence.
- Never commit, push, publish or deploy without the project’s approval gates.
- If connection drops, do not retry a side-effecting call while it is pending. Check its call status first, then resume from CURRENT-STATE.json.
