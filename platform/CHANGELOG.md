# Changelog

## 1.0.0-alpha.1 — 2026-11-20
- Rebuilt the active platform as PHP 8.5 + SQLite.
- Added libsodium encryption for provider and SMTP/IMAP credentials.
- Added local Ollama and OpenAI-compatible provider adapters.
- Added nine JSON locales and editable translation overrides.
- Added SMTP/IMAP configuration, unread inbox reading, invitation classification and optional five-minute polling.
- Added one-time device pairing, heartbeat, remote task polling and PHP device-agent runner.
- Added approval-required tasks and isolated local/remote file task execution.
- Added safe terminal allow-list, bug reports, About page and DiGiPL logo theme behavior.
- Added PHP STDIO MCP host with Codex/VS Code/Antigravity example configurations.
- Added macOS WKWebView app and Windows WebView2/Linux WebKit2GTK wrapper sources.
- Adapted the chat workspace layout and composer patterns from MonkeyCode-26072801 while retaining DCP design tokens.
- Added architecture, user and plugin-development documentation.
## 1.0.0-alpha.2 — 2026-11-20
- Restored the DCP mark as the main sidebar identity; moved the supplied DiGiPL light/dark SVG marks to About and footer only.
- Added a dark DCP logo variant with the navy segment converted to white while preserving the red mark.
- Bundled Cairo Regular, Medium, SemiBold and Bold locally with the SIL Open Font License; removed the remote Google Fonts request.
- Improved dark active navigation contrast and native select/dropdown readability; added globe affordance to the language selector.
- Added persistent SQLite notifications, unread count, mark-read and mark-all-read endpoints, and task/invitation event notifications.
- Added live-discovery provider presets for DeepSeek, OpenAI, Gemini OpenAI-compatible, xAI, Mistral, Groq, Together, Fireworks, OpenRouter, Perplexity, Qwen, Moonshot, Z.ai, MiniMax and Cerebras.
- Updated DeepSeek defaults to the official API endpoint and current model IDs (deepseek-flash, deepseek-v4-pro).
- Added founder biography and social/contact links based on AmrAdel.com.
- Moved legacy source and prior snapshots outside the active project to /Volumes/Data/ChatGPT/DCP-Backups/; removed the old prototype and launcher files from the active tree.
- Added implementation checklist and regression tests for model-less provider presets and persistent notification read state.
- Verified the existing PHP test suite, provider catalog API, notification read API and a real DCP-to-Ollama chat call.
- Added automatic redacted browser-error reports and PHP fatal-error report capture.
- Added OS-specific report reveal action and multipart JSON attachment support for SMTP bug reports.
- Replaced the macOS app icon with the red/white DCP mark and applied alpha.2 cache-busting to web assets.
- Removed the unused Amr-icon asset from the active UI; pairing uses the DCP mark.

## 2026-11-20 — Desktop runtime packaging
- Rebuilt macOS WebKit wrapper and packaged x64 app with first-run PHP verification.
- Published Windows x64 WebView2 wrapper self-contained and bundled official PHP NTS runtime.
- Added Windows first-run installer for PHP extensions, VC++ runtime and WebView2 runtime.
- Added Linux GTK/WebKitGTK build and PHP CLI setup scripts plus Ubuntu 22.04 CI workflow.
- Added runtime verification and deployment documentation.
- Linux binary and real Windows-device launch remain pending platform-native validation.

## 2026-11-20 — alpha.3 design and Local AI manager
- Added Local AI hardware / engine status API and dedicated manager page.
- Added Ollama installation/start, curated model download catalogue, memory-fit recommendations, and automatic chat provider model refresh.
- Added llama.cpp installation and GGUF server launch for Qwen3 1.7B / 4B and Llama 3.2 3B; llama.cpp server is registered as an OpenAI-compatible DCP provider.
- Added download/install activity logs and background model-ready polling.
- Fixed Bootstrap modal-backdrop collision by using a DCP-owned opaque modal overlay.
- Refined chat composer selects, buttons, focus states and spacing.
- Replaced grey translation blocks with searchable, keyboard-accessible key/value rows.
- Added Linux terminal-based llama.cpp source build helper.
- Added local AI hardware/catalog/engine smoke tests.
