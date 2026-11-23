# DCP Work Queue

This file is the durable resume point. Update status, evidence and next action after every completed task. Do not restart completed work after a context or connection interruption.

## Active constraints
- Current machines in scope: Mac + Windows only. Ubuntu/Linux implementation is deferred; preserve existing Linux/Kali auto-update compatibility.
- Work from the Git repository, not the extracted installed package. Preserve the installed alpha.8 directory until the new layout is verified.
- No public tunnel exposure until private paths and terminal access are protected.
- Never place passwords, API keys, tokens, .env, private keys, SQLite data or encryption keys in Git or project Markdown.

## Queue
| ID | Task | Status | Evidence / exit criterion |
|---|---|---|---|
| Q000 | Read Speed Engineer and map current app | done | User-supplied SKILL.md read; initial stack/code scan captured |
| Q001 | Restore source-of-truth repository to requested DCP path | next | Git repo at /Volumes/Data/ChatGPT/DCP; original install copy preserved |
| Q002 | Install requested planning/review/security skills | done | 10 skill folders installed locally; upstream revisions recorded in platform/docs/ENGINEERING-SKILLS.md |
| Q003 | Write architecture, product, roadmap, security and performance docs | done | AGENTS.md, PRODUCT-VISION.md, ROADMAP.md, WORK-QUEUE.md and platform/docs specs created |
| Q004 | Harden web entry and create dcp-shortcut | blocked | Current URL is 404; require root index/.htaccess and authenticated tunnel |
| Q005 | Add dcp-env runtime manifests and bootstrap | pending | Mac + Windows runtime detection/installation tests pass |
| Q006 | Fix local chat and llama.cpp port/install lifecycle | pending | Ollama and llama.cpp chat smoke tests pass; no 8080 collision |
| Q007 | Cloud API contract and Hostinger deployment | pending | health/register/login/device endpoints pass against live DB |
| Q008 | Header auth + Devices pairing management | pending | Mac and Windows pair, scopes, pause/resume/revoke work |
| Q009 | Mail page and AI mail capability | pending | Titan SMTP/IMAP tests, multi-account and task workflow |
| Q010 | Providers, agents, terminal, notices and clipboard | pending | CRUD/lifecycle/permission and keyboard tests pass |
| Q011 | Custom GGUF + Ollama conversion/model manager | pending | import, run, convert, switch and delete tests pass |
| Q012 | Shared chat, remote agent, browser and memory | pending | authenticated end-to-end tests and capability policy |
| Q013 | Speed/security/code review and release | pending | all release gates green |

## Resume protocol
1. Read this file first.
2. Read AGENTS.md and the relevant plan/spec.
3. Check git status, last commit, active process/tool calls and WORK-QUEUE.md.
4. Continue the first non-blocked task; do not redo done tasks.
5. Update this file with exact file paths, commands, test results and next step.
