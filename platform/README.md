# DCP — Desktop Connector Platform
Version: see platform/version.json (single source of truth)

DCP is a local-first PHP platform with SQLite, encrypted credentials, JSON locales, provider/agent plugins and thin desktop WebView wrappers.

## Run on macOS
Double-click `DCP.app`. The app title is **DCP — Desktop Connector Platform**. It starts its private PHP service at `127.0.0.1:18765` and embeds the web dashboard in WKWebView.

For a browser-based development session:
```bash
cd platform
/usr/local/bin/php -S 127.0.0.1:18765 -t public public/router.php
```
Then open `http://127.0.0.1:18765`.

## Important isolation
DCP uses a separate PHP CLI process and SQLite database. It does not change Apache, MySQL, PostgreSQL, Node.js, Python or C# service settings. The built-in server binds to loopback only.

## Current implementation notes
- Cairo is bundled locally; the app does not request Google Fonts at runtime.
- DCP uses its own mark in the sidebar; the supplied DiGiPL mark appears only in About and footer.
- Provider presets are plugin-discovered and model lists are fetched live where supported.
- Notifications are persisted in SQLite and remain read after restart.

## Docs
- `PROJECT-ARCHITECTURE.md` — full system architecture, schema, plugin contracts and API routes.
- `docs/IMPLEMENTATION-CHECKLIST.md` — durable implementation and verification checklist.
- `USER-GUIDE.md` — setup and usage.
- `wrappers/README.md` — macOS, Windows and Linux WebView wrapper builds.

## Current alpha limits
Cloud account sign-in, device registration and presence are implemented. Cross-device WebRTC data transport and remote task execution are not implemented. DCP can create and approve tasks but will not claim an agent executed them. See the architecture document for the complete list of verified features and remaining work.
