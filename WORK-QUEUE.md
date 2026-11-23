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
| Q005 | Add dcp-env runtime manifests and bootstrap | partial | Windows 11 PHP 8.5.13 NTS x64 archive SHA-256 verified; isolated runtime and package-copy smoke passed. macOS system-first installer and managed fallback are implemented; six isolated tests pass (system reuse, missing/incompatible system fallback, tampered/missing checksum rejection, unsupported architecture). macOS wrapper verifies managed PHP checksum and required extensions. StaticPHP v2.8.5 and PHP 8.4.28 source hash are pinned; native Intel/ARM64 runtime build, clean macOS package CI, Windows package CI and release embedding workflows are configured but not run. Q005 remains open until GitHub Actions and clean-machine tests pass |
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

## Q005 validation checkpoint — 2026-11-23
- `php platform/tests/run.php`: ALL PLATFORM TESTS PASSED.
- PHP lint across platform and `node --check platform/public/assets/app.js`: passed.
- `bash platform/tests/runtime-macos-installer.sh`: 6/6 passed.
- macOS Objective-C wrapper compiled for x86_64 (10.15) and arm64 (11.0).
- YAML/JSON syntax checks and `git diff --check`: passed. `actionlint` is not installed locally.
- GitHub Actions workflows are configured but not yet executed; no release or deployment was performed.
