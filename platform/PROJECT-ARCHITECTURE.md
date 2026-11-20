# DCP — Desktop Connector Platform
Version 1.0.0-alpha.1

## Product definition
DCP is a local-first desktop platform that connects AI providers, agent runtimes, MCP tools, local terminals, mail, and paired devices. The PHP platform is the product. Desktop applications are thin WebView shells that start the private PHP service and display the same web application.

## Architecture principles
- One PHP codebase is shared by macOS, Windows, and Linux.
- SQLite is the default embedded database. No external database server is required.
- PHP's built-in server listens on 127.0.0.1:18765 only. DCP does not edit Apache virtual hosts, modules, ports, or configuration.
- DCP does not start, stop, reconfigure, or bind to existing MySQL, PostgreSQL, Node.js, Python, or C# services.
- API keys and mail passwords are encrypted using libsodium secretbox. The master key is stored separately with owner-only file permissions.
- Providers, agents, mail services, and tools are plugins/adapters described by plugin.json manifests and PHP driver files.
- UI strings are keyed variables. JSON locale files provide defaults; database overrides are editable in Settings.
- Agent status is not represented as Connected unless a verified adapter session exists.

## Runtime components
- PHP 8.2+ CLI with PDO_SQLite, cURL, OpenSSL, Sodium, mbstring, intl.
- SQLite database at platform/data/dcp.sqlite.
- Local PHP HTTP server at 127.0.0.1:18765.
- Bootstrap 5.3.3 local CSS asset.
- WebView shell: WKWebView on macOS, WebView2 on Windows, WebKit2GTK on Linux.
- Ollama is an optional local AI provider at 127.0.0.1:11434.

## Directory map
- app/Core: database, encryption, plugin discovery, adapter contracts.
- app/Controllers: page and JSON API controllers.
- app/Services: chat, provider, mail, terminal, device, report and agent services.
- bootstrap: application bootstrap and dependency wiring.
- config: runtime configuration.
- data: SQLite database and WAL files.
- locales: JSON locale dictionaries: en, ar, nl, es, pt, fr, de, zh, ja.
- plugins/providers: Ollama, generic OpenAI-compatible, DeepSeek adapters.
- plugins/agents: DCP Node, Desktop Commander, DeepSeek Harness adapters.
- plugins/mail: SMTP/IMAP adapter manifest.
- plugins/tools: safe terminal tool manifest.
- public: PHP router, UI, CSS, JS, local Bootstrap and brand assets.
- storage/keys: encryption key.
- storage/logs: local logs.
- storage/reports: generated bug reports.
- tests: PHP integration smoke tests and local mock provider.
- wrappers: desktop WebView source for macOS, Windows and Linux.

## Database schema
- settings(key,value,updated_at): preferences and encrypted mail credentials.
- providers(id,name,driver,base_url,model,api_key_enc,enabled,created_at,updated_at): encrypted provider secrets.
- translations(locale,key,value,updated_at): user-editable translation overrides.
- tasks(id,title,description,status,payload,result,target_device_id,created_at,updated_at): approval queue for local or paired devices.
- devices(id,name,platform,status,token_hash,created_at,last_seen): paired devices.
- invitations(id,email,device_name,token_hash,status,expires_at,created_at): one-time pairing invitations.
- reports(id,title,body,created_at,email_status): bug reports.
- audit_log(id,event,details,created_at): security and operation audit trail.
- chat_sessions(id,title,provider_id,created_at,updated_at) and chat_messages(id,session_id,role,content,created_at): local chat history.

## Provider adapter contract
A provider plugin includes:
- plugin.json with id, name, type=provider, driver, version, capabilities, defaults.
- driver.php returning an object implementing ProviderAdapter.
- ProviderAdapter::models(array $provider): array.
- ProviderAdapter::chat(array $provider,string $model,array $messages): string.
Credentials are supplied to the driver only for the request and are never returned by list APIs. The UI receives api_key_masked only.

## Agent adapter contract
An agent plugin includes plugin.json with type=agent and a driver.php returning AgentAdapter.
- AgentAdapter::status(): array returns id, name, installed, running, connected, detail, capabilities. MailAdapter and ToolAdapter factory plugins build the SMTP/IMAP and safe-terminal services. TaskExecutor plugins perform only approved, allow-listed workspace actions. The DCP Local Node and remote PHP device-agent runner support create_directory, write_file, read_file and list_directory inside an isolated workspace.
- Future methods should include authenticate, start, stop, submitTask, getTask, cancelTask, heartbeat, and getCapabilities.
- An agent may report running while connected=false. This means the application process exists but DCP has no authenticated task session.

## Mail adapter
SMTP supports SSL/TLS or STARTTLS and optional AUTH LOGIN. IMAP supports SSL/TLS or STARTTLS, login, unread inbox headers, and a short body preview. Credentials are encrypted in settings. A public HTTPS pairing base URL must be configured before invitations can be sent. The app remains loopback-only by default; a trusted VPN/reverse proxy/relay is needed for another device to reach the pairing page.

## Terminal security
The embedded terminal is an allow-list runner, not a general shell. Current commands: pwd, ls, date, whoami, php -v, php -m, git status. Arguments are validated and commands are launched without a shell. Destructive or arbitrary command execution is not exposed in this build.

## API routes
GET /api/bootstrap — CSRF token and app preferences.
GET /api/status — runtime health.
GET /api/providers — provider list with masked API keys.
GET /api/providers/catalog — installed provider plugins and their default endpoints/model discovery capabilities.
GET /api/notifications — persistent notifications and unread count.
POST /api/notifications/read — mark one notification or all notifications as read.
POST /api/providers/save — create/update a provider.
POST /api/providers/delete — remove a provider.
POST /api/providers/models — discover models.
POST /api/providers/test — test provider model endpoint.
POST /api/chat — send a chat message and persist the conversation.
GET /api/chats — list conversations.
GET /api/chat/messages?id=... — read conversation messages.
GET /api/plugins — discover installed plugins.
MCP STDIO server: plugins/tools/mcp-host/server.php (initialize, tools/list, tools/call). Example configurations for Codex, VS Code and Antigravity-compatible clients are under integrations/mcp-examples/.
GET /api/agents — get truthful adapter status.
GET/POST /api/settings — read/save preferences.
GET /api/locales?locale=ar — load merged JSON + database translations.
POST /api/locales/save — edit a translation variable.
POST /api/mail/save — save SMTP/IMAP settings encrypted.
POST /api/mail/test — test SMTP or read IMAP inbox.
GET /api/mail/inbox — read unread messages.
POST /api/invitations — create and email a one-time device invitation.
POST /api/pair — redeem a one-time invitation.
POST /api/device/heartbeat — authenticate a paired device token.
GET /api/device/tasks — poll approved tasks for a paired device.
POST /api/device/task-result — submit the execution result from a paired device.
POST /api/terminal/run — run an allow-listed command.
GET /api/tasks — list tasks.
POST /api/tasks — create an approval-required task.
POST /api/tasks/approve — approve and execute a local safe task, or queue it for a paired device.
POST /api/tasks/approve — mark a task approved (does not execute it yet).
POST /api/reports — generate a local JSON bug report and optionally email it as a JSON attachment.
POST /api/reports/reveal — open the selected report in the operating system file manager.
POST /api/diagnostics/client-error — receive a redacted browser error and save an automatic diagnostic report.

All POST API requests require the session CSRF token except the device heartbeat route, which requires a bearer device token. Pairing uses a one-time invitation token and a CSRF token from the pairing page.

## Current implemented and verified
- PHP front controller and loopback-only service.
- SQLite database and schema creation.
- libsodium encryption for API keys and SMTP/IMAP passwords.
- Masked API key previews and edit-with-blank-key preservation.
- Ollama model discovery and local chat.
- OpenAI-compatible provider model discovery and chat.
- Provider plugin catalogue for OpenAI, DeepSeek, Gemini OpenAI-compatible endpoint, xAI, Mistral, Groq, Together AI, Fireworks, OpenRouter, Perplexity, Qwen/DashScope, Moonshot/Kimi, Z.ai/GLM, MiniMax and Cerebras. Live model discovery is preferred over stale hardcoded model lists.
- Persistent notification table and read-state API; task creation, approval, local completion/failure, remote result and invitation events create notifications.
- Chat sessions and messages stored locally.
- Agent/plugin discovery and truthful connection status.
- SMTP/IMAP configuration and socket adapters, unread inbox polling, invitation classification and optional five-minute background polling.
- One-time device invite token, pairing page, device token hash and heartbeat API. Paired device agent can poll approved tasks over HTTPS and report results.
- Safe terminal command allow-list.
- Task creation and explicit approval status.
- Bug report JSON creation, redacted automatic PHP fatal/browser error capture, OS file-manager reveal, and optional SMTP email with JSON attachment.
- JSON locale files for nine languages and database translation overrides.
- About page and uploaded Amr icon; blue on light theme, white via CSS filter on dark theme.
- macOS WKWebView app wrapper.
- Windows WebView2 and Linux WebKit2GTK wrapper source templates.

## Known limitations — do not present as completed
- Cross-device pairing requires a reachable HTTPS URL configured in Settings and a trusted network path. The default service binds to localhost. No automatic public relay/tunnel is created.
- Paired-device task transport and remote execution are not implemented yet.
- Desktop Commander and DeepSeek Harness are detected by adapters, but authenticated task submission is not implemented.
- Tasks can be created and approved, but no remote agent executes them yet.
- Provider catalogue currently uses the generic OpenAI-compatible adapter for the listed compatible endpoints; Anthropic native Messages API and direct Gemini native protocol adapters still require dedicated plugins.
- A DeepSeek live API test still requires the user to save a valid DeepSeek API key and then test model discovery/chat.
- AI Chat does not yet automatically invoke MCP tools. MCP clients can create approval-required tasks through DCP.
- SMTP/IMAP requires valid user-provided server credentials; live delivery depends on provider configuration. Auto-poll checks unread messages every five minutes only when enabled in Settings.
- Windows and Linux wrapper sources are included but have not been compiled or runtime-tested on those operating systems.
- macOS development build uses the installed PHP CLI. Release packaging should bundle a dedicated PHP runtime per OS.

## Security notes
- Keep platform/storage/keys/master.key private and backed up separately. If lost, encrypted credentials cannot be recovered.
- Do not copy the SQLite database without its master key, and do not share the master key.
- Keep public_base_url blank unless you have configured a trusted HTTPS VPN/reverse proxy/relay.
- Never expose the PHP built-in development server directly to the public internet.
- Review task payloads and approve only known actions.
