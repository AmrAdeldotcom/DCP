# DCP User Guide
Version: see platform/version.json (single source of truth)

## First launch
1. Open DCP.app on macOS. The app starts a PHP service on 127.0.0.1:18765 and opens the dashboard in WKWebView.
2. DCP creates its own SQLite database and encryption key inside platform/. No Apache configuration is changed.
3. Open Settings and choose a language and appearance.
4. Open Connections. Ollama appears as a built-in local provider. Select it in AI Chat and choose one of your installed Ollama models.

## Add an API provider
1. Open Connections → Add provider.
2. Give the provider a friendly display name, such as “DeepSeek Work”.
3. Choose OpenAI-compatible or DeepSeek adapter.
4. Enter the base API URL and exact model ID.
5. Paste the API key. DCP encrypts it before writing it to SQLite.
6. The provider card shows a masked preview only. When editing, leave the key blank to keep the saved secret.
7. Use Test to verify model discovery.

OpenAI API billing is separate from a ChatGPT subscription. Use a provider key issued by that service.

## AI Chat
- Choose provider and model in the toolbar.
- Send with Enter; Shift+Enter inserts a new line.
- Conversations and messages are stored locally in SQLite.
- Switching providers does not share chat history with another provider.
- The current chat request waits for the provider response; long local models may take time to load.

## SMTP and IMAP
1. Open Settings → Email and invitations.
2. Enter SMTP host, port, encryption mode, username, password, sender email and sender name.
3. Enter IMAP host, port, encryption mode, username and password.
4. Save. Passwords are encrypted with libsodium. Enable Automatically check unread mail every 5 minutes to poll the inbox while DCP is open.
5. Test SMTP verifies server greeting, TLS and authentication. Read inbox connects to IMAP and retrieves unread message headers.
6. Set a reachable HTTPS pairing base URL only if the DCP service is reachable through a trusted VPN/reverse proxy/relay. Do not publish the built-in PHP development server directly.

## Invite a device
1. Configure SMTP and a trusted HTTPS pairing URL.
2. Open Devices → Invite device.
3. Enter the recipient email and device name.
4. DCP sends a single-use invitation that expires after 24 hours.
5. The recipient opens the link and pairs the device. The device token is shown once and should be stored securely. On the paired computer, install the DCP platform folder and run the PHP device agent with the server URL, token and a workspace path: php plugins/agents/dcp-node/device-agent.php https://your-dcp-host.example TOKEN /path/to/workspace. The agent polls approved tasks and sends results back. Keep the token private.

Pairing credentials and device records do not themselves create a remote execution tunnel. Remote task transport is planned separately.

## Safe Terminal
The terminal supports pwd, ls, date, whoami, php -v, php -m and git status. Other commands are blocked. It runs inside the DCP workspace and does not expose arbitrary shell execution.

## Tasks and approval
- Create a task with a title and description.
- New tasks are marked approval_required.
- Approve marks the task approved.
- The local DCP Node executes approved file tasks inside platform/data/workspace. A paired device agent can execute approved tasks targeted to its device ID over the configured HTTPS route. Supported actions are create_directory, write_file, read_file and list_directory. Tasks assigned to a paired device are queued for that device agent; remote execution requires a reachable HTTPS/VPN/reverse-proxy URL.

## Bug reports
Open Diagnostics, describe the issue and optionally enter a recipient email. DCP creates a JSON report in platform/storage/reports. If SMTP is configured and a recipient is entered, it also attempts to email the report.

## Languages and translation variables
The app ships JSON dictionaries for English, Arabic, Dutch, Spanish, Portuguese, French, German, Chinese and Japanese. Choose a language in the top bar. In Settings → System translation variables, select a locale, click a key, edit its translation and save. Overrides are stored in SQLite and take precedence over JSON defaults. Arabic switches the layout to RTL.

## Backup and restore
Back up the complete platform/data directory and platform/storage/keys/master.key together. Keep the master key private. Restoring only the database without the master key makes encrypted API and mail credentials unreadable.

## Troubleshooting
- If the app says PHP is missing, install PHP 8.2+ with PDO_SQLite, cURL, OpenSSL, Sodium, mbstring and intl.
- If port 18765 is occupied, change the port in platform/config/app.php and the WebView wrapper.
- Check platform/storage/logs/php-server.log and desktop-wrapper.log.
- If Ollama is offline, verify the Ollama service and its local API at 127.0.0.1:11434.
- If provider test returns 401, check the API key and provider permissions.
- If invitations cannot be sent, configure SMTP and a reachable HTTPS pairing URL.

## Connect MCP clients
DCP includes a PHP STDIO MCP host at plugins/tools/mcp-host/server.php. Example configuration files are in integrations/mcp-examples/ for Codex, VS Code and Antigravity. Update the PHP executable and absolute project path for your installation, then restart the client. MCP-created tasks remain approval_required until a user approves them in DCP.
