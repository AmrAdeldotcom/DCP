# DCP Plugin and Adapter Development Guide
Version 1.0.0-alpha.1

## Goal
Extend DCP by adding PHP files, plugin manifests, JSON locale entries and SQLite migrations. The desktop shells are intentionally thin and should not need rebuilding for normal platform, provider, service or UI changes.

## Plugin layout
Create a new directory under one of:
- plugins/providers/<plugin-id>/
- plugins/agents/<plugin-id>/
- plugins/mail/<plugin-id>/
- plugins/tools/<plugin-id>/

Every plugin directory contains plugin.json. Adapter plugins also include driver.php. Keep the ID lowercase and stable.

## Manifest
Example:
{
  "id": "acme-provider",
  "name": "Acme AI",
  "type": "provider",
  "driver": "openai-compatible",
  "version": "1.0.0",
  "capabilities": ["chat.completions", "models.list"],
  "defaults": {"base_url": "https://api.example.com/v1"}
}

The PluginManager validates and discovers plugin.json files recursively. Do not include secrets in a manifest.

## Provider adapter
Implement ProviderAdapter:
- models(array $provider): array
- chat(array $provider, string $model, array $messages): string

Return model IDs and a final text response. Do not log API keys or Authorization headers. Use HTTPS; HTTP is allowed only for loopback development endpoints. Store API keys only through ProviderService so they are encrypted by Crypto before they reach SQLite. Never return api_key_enc or the full API key to the browser.

## Agent adapter
Implement AgentAdapter:
- status(): array

Return id, name, installed, running, connected, detail and capabilities. Set connected=true only when an authenticated task channel is working. Process detection alone means running=true and connected=false. Future adapters may add start, stop, authenticate, submitTask, getTask, cancelTask and heartbeat methods.

## Mail adapter
Implement MailAdapter:
- build(PDO $db, Crypto $crypto): MailService

Use MailService for SMTP/IMAP credentials and network operations. Secrets must be stored with Crypto. Validate recipient/sender addresses and remove CR/LF from header values. Use TLS certificate verification.

## Tool adapter
Implement ToolAdapter:
- build(array $config): TerminalService

Tool adapters must enforce allow-lists. Never expose unrestricted shell execution to an AI tool call.

## Task executor
Implement TaskExecutor:
- execute(array $payload): array

Only execute after a human approval. Validate every action and path. File operations must remain under a dedicated workspace. Reject absolute paths, traversal, symlinks that escape the workspace, oversized payloads and unsupported action types. Return structured results and never run arbitrary shell strings.

## MCP host
The built-in MCP STDIO host is plugins/tools/mcp-host/server.php. It supports initialize, tools/list and tools/call. When adding a tool:
1. Add a tool definition and JSON inputSchema.
2. Add a matching handler.
3. Keep the tool's permissions and side effects explicit.
4. For write or execution actions, create an approval-required DCP task rather than bypassing approval.
5. Test with a JSON-RPC STDIO smoke test.

## Database changes
Database::migrate() in app/Core/Database.php creates base tables and performs additive migrations. For every schema change:
- Add an idempotent migration (inspect PRAGMA table_info before ALTER TABLE).
- Preserve existing rows.
- Add an index for frequently queried columns.
- Add a test that creates a fresh database and migrates an older schema.
- Do not store secrets as plaintext. Use Crypto::encrypt/decrypt.

## Add a locale variable
1. Add the key and English value to locales/en.json.
2. Add translations to ar, nl, es, pt, fr, de, zh and ja.
3. Mark the HTML element with data-i18n="key".
4. Use data-i18n-placeholder for placeholders.
5. Verify Arabic direction remains RTL.
6. Users can override values from Settings; database translations take precedence over JSON defaults.

## UI and styles
- Keep Bootstrap 5 local and use DCP design tokens in public/assets/app.css.
- Use workspace-enhancements.css for shared workspace/composer patterns.
- Do not add inline styles for new UI.
- Keep the interface responsive and RTL-safe.
- Never insert untrusted provider, mail or AI content as raw HTML. Escape it or use the safe Markdown renderer.

## API route
Add routes to ApiController. All POST routes require CSRF except device-agent endpoints that authenticate with a bearer device token. Validate JSON input and return JSON errors. Never expose database encryption material or full credentials.

## Tests
Run from platform/:
- php -l on every PHP file.
- node --check public/assets/app.js.
- php tests/run.php (provider encryption, provider chat, device pairing, terminal and reports).
- php tests/mail-smoke.php (SMTP and IMAP mock integration).
- php tests/task-smoke.php (local workspace executor and traversal protection).
- MCP STDIO test: pipe initialize, tools/list and tools/call JSON-RPC lines into php plugins/tools/mcp-host/server.php.

## Desktop shell rule
Normal feature work should change only platform PHP, JSON, JS and CSS files. Rebuild DCP.app only when the native macOS wrapper or bundle metadata changes. Rebuild the Windows/Linux WebView shell only when their native wrapper source changes.
