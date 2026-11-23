# DCP Engineering Rules

## Mission
Build DCP as a portable, local-first, cross-platform AI workspace. Amr owns product decisions and design direction; ChatGPT translates them into tested software.

## Non-negotiable workflow
1. Plan and write/update the relevant Markdown specification.
2. Reproduce the bug or capture a baseline.
3. Make the smallest coherent change.
4. Run automated tests and a real runtime smoke test.
5. Review the diff and security boundaries.
6. Update WORK-QUEUE.md before ending a work session.

## Active skills
- .agents/skills/speed-engineer/SKILL.md: mandatory for database, list, fetching, caching, images, loading UX and performance work.
- .agents/skills/planning-and-task-breakdown/SKILL.md
- .agents/skills/incremental-implementation/SKILL.md
- .agents/skills/test-driven-development/SKILL.md
- .agents/skills/code-review-and-quality/SKILL.md and .agents/skills/magnus-code-review/SKILL.md
- .agents/skills/mikko-security-audit/SKILL.md
- .agents/skills/debugging-and-error-recovery/SKILL.md
- .agents/skills/browser-testing-with-devtools/SKILL.md

## Product boundaries
- Current implementation scope: macOS + Windows + Ubuntu 24.04 x86_64, as explicitly authorized by Amr. Preserve Linux/Kali update compatibility. Validate Mac/Windows milestones and Ubuntu clean-install packaging in CI before publishing synchronized releases.
- Detect and reuse an existing system service before installing a DCP-managed copy. Never replace, stop, reconfigure or hijack an existing user service.
- Keep DCP-managed runtimes under platform/dcp-env. Keep user data, databases, keys, logs and workspaces outside the public web root.
- Never commit credentials, .env files, API keys, tokens, mail passwords, private keys, SQLite databases or runtime state.
- The local machine terminal is explicitly local and runs as the signed-in OS user. Remote devices use isolated per-device workspaces and capability-scoped execution only.
- Remote Full Trust is symmetric: both parties must explicitly acknowledge and approve the same trust level; never silently infer consent.
- Browser collaboration, remote execution, file transfer and live chat must not be shown as connected until the corresponding authenticated transport is implemented and tested.

## Performance profile (provisional)
- Tier: S (provisional; assume fewer than 1,000 users until Amr confirms).
- Growing data: chat_messages, audit_log, notifications, mail_messages, tasks.
- Freshness: real-time for chat/presence; normal caching for static catalog/config.
- No Redis or paid cache service without measured need and explicit approval.
- Enforce indexes, zero N+1, bounded pagination, cache invalidation and skeleton/empty/error states.

## Release gate
PHP lint + platform tests + JS syntax + Windows build/CI + macOS build/smoke + cloud API contract tests + code review + security audit. No release from a dirty or unreviewed tree.
