# DCP Implementation Checklist
Last updated: 2026-11-20 16:20 Africa/Cairo
Project: `/Volumes/Data/ChatGPT/DCP`
Product: **DCP — Desktop Connector Platform**

Legend: [x] implemented and verified in this session; [~] exists but needs deeper verification or completion; [ ] not done; [!] blocked/needs user test.

## 0. Continuity and safety
- [x] Confirm remote Mac is online and project path is reachable.
- [x] Confirm active product is PHP + SQLite platform; old Python prototype is archived under `legacy/`.
- [x] Run existing PHP test suite; all current tests pass.
- [x] Test a real DCP-to-Ollama chat request using DiGEMA-3B; DCP returned a valid model response (42.65 seconds).
- [x] Confirm DCP PHP server uses loopback `127.0.0.1:18765`, separate from Apache.
- [x] Confirm Ollama service is already running; do not restart or reconfigure it.
- [x] Keep this checklist updated after every meaningful work session.
- [ ] Keep a session handoff note with last completed step, changed files, test results and next action.

## 1. Branding and design system
- [x] Restore the official DCP logo as the product/app/sidebar/header identity using the existing DCP AppIcon artwork; dark-theme asset changes the navy segment to white while retaining the red segment.
- [x] Use supplied DiGiPL mark only in footer and About page: blue/navy on light theme, white variant on dark theme.
- [x] Set window/app display name to `DCP — Desktop Connector Platform` on macOS (Info.plist and WKWebView window title verified); Windows/Linux still pending.
- [~] Set Windows/Linux executable product title to `DCP — Desktop Connector Platform` (source review pending).
- [x] Bundle Google Cairo font weights locally (Regular, Medium, SemiBold, Bold) and remove remote Google Fonts CSS request. Verify no external font fetch remains.
- [~] Initial audit: fixed dark active-nav contrast, dropdown option foreground/background, and globe/chevron language selector. Full-page design-system audit still pending.
- [ ] Ensure Arabic is RTL throughout, with aligned labels, menus, chat bubbles and forms.
- [ ] Make language picker a clear icon/button + accessible dropdown.
- [x] Add persistent notification center; task/invitation events create DB notifications and read state persists across app restarts (DB test passed). UI smoke test remains pending.
- [ ] Verify application icon artwork in macOS Dock/Finder and Windows/Linux launchers.

## 2. About page and founder links
- [x] Use source-backed bio: Web Developer, Networks Engineer, MCITP Enterprise Administrator, IT Manager, Digital Marketer, Photographer, Movie Editor, Graphic Designer; Founder & CEO of DiGiPL.
- [x] Add links verified from AmrAdel.com: X/Twitter, Behance, LinkedIn, Facebook, Instagram, TikTok, YouTube, Pinterest, GitHub, DiGiPL, plus Facebook/WhatsApp/Telegram contact links.
- [ ] Keep About page copy concise and editable through locale variables.

## 3. Providers and model router
- [x] Provider credentials are encrypted at rest in SQLite and masked in list responses (test passed).
- [x] Generic OpenAI-compatible provider adapter exists.
- [x] Ollama adapter exists; local model discovery was included in prior build.
- [x] DeepSeek provider manifest exists with default endpoint `https://api.deepseek.com/v1`.
- [~] Add live-discovery provider plugin presets for DeepSeek, OpenAI, Google Gemini OpenAI-compatible, xAI, Mistral, Groq, Together, Fireworks, OpenRouter, Perplexity, Qwen/Alibaba, Moonshot/Kimi, Z.ai/GLM, MiniMax and Cerebras. Anthropic native API and Cohere adapters still pending.
- [ ] Prefer live model discovery from each provider; show static presets only as editable fallback.
- [~] Provider/model selection persists in browser storage; verify real UI selection and model routing on the next manual pass.
- [ ] Verify DeepSeek API end-to-end with user-provided key; never fabricate a successful live test.
- [ ] Fix/verify TLS CA validation without disabling certificate verification.

## 4. Agents, MCP and task orchestration
- [~] Plugin manifests exist for ChatGPT Desktop Commander, DeepSeek Harness and DCP Node.
- [ ] Make agent status distinguish installed/running/authenticated/connected/task-capable.
- [ ] Implement truthful connect/disconnect/remove/reconnect flows.
- [ ] Verify task dispatch and results for ChatGPT Desktop Commander and DeepSeek Harness.
- [ ] Make Tasks page support direct agent selection and explicit approval.
- [ ] Verify MCP server integration for Codex, VS Code and Antigravity clients; include tested config snippets.
- [ ] Keep all external MCP/agent installs opt-in and show exact commands/permissions before running.

## 5. Devices and network
- [x] Local device invitation/pairing and heartbeat smoke tests pass.
- [ ] Implement authenticated device-to-device task transport and execution.
- [ ] Verify remote device can expose approved providers and agents only according to granted permissions.
- [ ] Verify revocation, token rotation, invitation expiry and offline behavior.
- [ ] Test two-device scenario (Mac + Windows laptop) after Windows build is ready.

## 6. Mail, notifications and diagnostics
- [x] SMTP/IMAP encrypted credential storage and basic tests pass.
- [x] Bug report file generation test passes.
- [~] Add JSON attachment to report email and OS-specific Reveal/Open Report Location action; code is in place but live SMTP attachment delivery needs user credentials to verify.
- [x] Automatically capture PHP fatal errors and uncaught browser errors into redacted JSON reports; tested the client-error endpoint and verified secret redaction. Non-fatal PHP warnings are logged but not all create report files.
- [ ] Add notification persistence with read/unread state in SQLite; mark read permanently.
- [ ] Verify SMTP and IMAP with real user credentials only when configured.

## 7. Local PHP platform and desktop launchers
- [x] PHP + SQLite platform exists and test suite passes.
- [x] macOS WKWebView source and app bundle exist.
- [~] Windows WebView2 source and a prior build output exist; must rebuild against current platform and test on Windows.
- [~] Linux WebKit2GTK source exists; must build/test on Linux.
- [~] Windows private PHP runtime packaging and macOS managed PHP build/installer workflows are implemented; native CI builds and clean-machine launch tests remain. Never change system Apache/MySQL/PostgreSQL/Node/Python/C# services.
- [ ] Remove generated build artifacts, duplicate legacy folders and unused files only after verified backup outside the DCP project.
- [ ] Verify clean install, app launch, shutdown and upgrade on each OS.

## 8. Documentation and release gate
- [x] Architecture, user guide and plugin development docs exist.
- [ ] Update full architecture document, DB schema, routes, plugin contracts, security model and known limitations after implementation.
- [ ] Add a single quick-start guide for Mac/Windows/Linux.
- [~] Add regression tests for provider saving without a model and persistent notification read-state; broader UI regression tests still pending.
- [ ] Do not call a feature complete until automated test + manual UI/real integration test pass.
- [!] User acceptance test: DeepSeek API chat, Ollama chat, task approval/dispatch, notification persistence, and clean reconnect.
- [ ] Windows laptop pairing test with Amr's approval.

## Verified baseline (2026-11-20)
- Existing command: `php platform/tests/run.php`
- Result: ALL PLATFORM TESTS PASSED.
- Passing checks: encrypted provider API key storage/decryption/masking/edit preservation; OpenAI-compatible mock model discovery/chat; SMTP/IMAP secret encryption; one-time pairing and heartbeat; safe terminal allow-list and blocked unsafe command; bug report generation; all nine locale JSON files parse.
- Not covered by this suite: live DeepSeek credentials, live Ollama chat from the current UI, real Desktop Commander/DSH task execution, two-device transport, desktop OS launchers on Windows/Linux, notification persistence across app restart.

## Session handoff
- Current active PHP server observed at `127.0.0.1:18765`, PID 46993. It was already running; do not stop it unless the user explicitly asks.
- Ollama server observed running; do not restart it.
- Current DCP.app uses WKWebView and private loopback PHP service; app bundle title is correct in Info.plist and macOS source.
- Branding pass: sidebar now uses the existing DCP app icon artwork (transparent PNG); footer/About use supplied DiGiPL light/dark SVG marks. Cairo TTF weights are bundled locally; Google Fonts @import removed. Dark active nav contrast corrected. About page has source-backed bio and social/contact links.
- Next action: audit dropdown/language selector and notification persistence; then build a provider preset catalogue with live discovery, and test the DeepSeek adapter with the user’s API key.


## Session update — 2026-11-20 16:30 Africa/Cairo
- [x] DCP sidebar and pairing page now use the DCP mark; DiGiPL mark is reserved for About/footer only.
- [x] Built a dark DCP logo variant (red + white) and replaced the macOS AppIcon.icns with the visible DCP mark; app display name remains correct.
- [x] About page includes the bio and verified public links from AmrAdel.com.
- [x] Local Cairo font files and OFL license bundled; no fonts.googleapis.com or fonts.gstatic.com references remain in active platform code.
- [x] Default theme confirmed and stored as light; dark active-nav contrast fixed.
- [x] Notification UI/API implemented; task creation, approval, completion/failure, remote result and invitation events create persistent notifications. Mark-read API smoke test passed; read state remained in SQLite.
- [x] Automatic browser error reports and PHP fatal-error shutdown capture implemented with secret redaction. Client-error endpoint smoke test created a JSON report and redacted a fake API key.
- [x] Added OS-specific report-location reveal endpoint; manual click is pending user-visible UI test.
- [~] SMTP report attachment support implemented as multipart JSON attachment; live SMTP delivery still untested.
- [x] Provider catalogue now exposes 17 provider entries including Ollama, DeepSeek, OpenAI, Gemini OpenAI-compatible, xAI, Mistral, Groq, Together, Fireworks, OpenRouter, Perplexity, Qwen, Moonshot, Z.ai, MiniMax and Cerebras.
- [x] DeepSeek defaults updated to official endpoint https://api.deepseek.com and model IDs deepseek-flash / deepseek-v4-pro. Live DeepSeek API test is blocked because no DeepSeek key is currently stored; current DB has one migrated OpenAI provider only.
- [x] Real DCP /api/chat -> Ollama -> DiGEMA-3B:latest test completed. DCP received a valid response after 42.65 seconds.
- [x] Old Python prototype and old launcher removed from active project. Verified archives moved outside project to /Volumes/Data/ChatGPT/DCP-Backups/.
- [x] PHP syntax checks, JavaScript syntax check and all platform tests pass after these changes.

### Immediate next steps
1. User refreshes/reopens DCP to visually verify alpha.2 branding, notification bell, About/footer marks, Cairo, dropdowns and the new provider list.
2. User saves a DeepSeek API key in Connections; then verify live model discovery and a real DeepSeek chat.
3. Verify SMTP attachment and report-folder reveal on the Mac.
4. Continue Desktop Commander / DSH task submission adapters, then two-device task transport.
5. Complete private PHP runtime packaging and real Windows/Linux wrapper builds; current Windows output exists, Linux is source-only, and both still require runtime verification.

## Desktop WebView packaging and first-run runtime (2026-11-20)
- [x] macOS WebKit wrapper rebuilt with external links delegated to the default browser.
- [x] macOS setup script checks PHP 8.2+ and required extensions; it installs Homebrew PHP CLI only if missing/incomplete and does not start brew services.
- [x] Windows WebView2 wrapper cross-published as a self-contained win-x64 .NET application.
- [x] Windows package includes a private PHP 8.5 NTS x64 runtime archive, php.ini and first-run PowerShell bootstrapper for PHP/VC++/WebView2 prerequisites.
- [x] Windows wrapper source routes external navigation and popups to the default browser.
- [x] Linux WebKitGTK wrapper source routes external navigation to the default browser and resolves its platform directory relative to the executable.
- [x] Linux setup/build scripts and Ubuntu 22.04 GitHub Actions build workflow added.
- [x] PHP runtime verification checks version, extensions and writable application data directories.
- [x] PHP platform automated test suite passes on the current Mac.
- [ ] Linux binary artifact requires an Ubuntu 22.04 build runner; this macOS host does not provide WebKit2GTK/GTK3 Linux build libraries.
- [ ] Windows executable and private PHP runtime require one real Windows-machine launch test; this Mac cannot execute the Windows binary.

## Local AI and design-system pass (2026-11-20)
- [x] Fixed translucent provider modal caused by Bootstrap modal-backdrop CSS collision.
- [x] Reworked chat composer and native select styling to match DCP tokens.
- [x] Reworked translation editor into searchable key/value list.
- [x] Added Local AI hardware detection (OS, architecture, RAM, GPU) and engine health.
- [x] Added Ollama install/start and curated Ollama model pull flow with chat refresh.
- [x] Added llama.cpp install and Hugging Face GGUF launch for supported models; DCP provider auto-registration.
- [x] Added Linux terminal helper to handle sudo prompts interactively.
- [x] Added local AI smoke tests.
- [ ] Linux binary still needs Ubuntu runner / VM validation; this Mac has no container runtime and only ~17 GB free disk space.
- [ ] Windows app and runtime still need a real Windows launch test.
- [ ] Visual regression screenshots should be captured on the target desktop WebView after launch.
