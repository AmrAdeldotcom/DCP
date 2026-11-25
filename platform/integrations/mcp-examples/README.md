# Connect Codex, VS Code or Antigravity to DCP

DCP includes a local MCP host using JSON-RPC over STDIO. It exposes platform status, masked provider listing, device/task listing, full terminal execution, unread IMAP mail, and task creation that always waits for user approval in the DCP dashboard.

1. Confirm PHP CLI is installed and DCP platform is in a stable location.
2. Copy the matching example configuration into the MCP configuration file used by your client.
3. Replace the PHP executable path if needed (Apple Silicon Homebrew commonly uses /opt/homebrew/bin/php).
4. Replace the DCP project path with the actual path on that computer.
5. Restart the MCP client and confirm that DCP tools appear.

The MCP server does not expose full API keys, mail passwords, arbitrary shell execution, or a tool that bypasses the DCP task approval queue.


## DCP Cloud MCP (remote, read-only control plane)

Endpoint: `https://dcp.amradel.com/api/v1/mcp`

Create a 30-day scoped token from the authenticated Cloud account using `POST /api/v1/mcp-tokens`, then configure the MCP client with the returned bearer token. The token is restricted to device, resource, team and grant read scopes; revoke it with `DELETE /api/v1/mcp-tokens/{id}`. Do not paste account passwords or device tokens into MCP client configuration.

Cloud MCP exposes device/pairing/resource/team/grant discovery only. Use Local DCP Core MCP for local terminal, filesystem, process and model tools; the Cloud endpoint intentionally does not provide remote shell execution.
