# DCP Desktop WebView wrappers

All desktop shells load the same PHP platform from `platform/`. The wrapper owns only the PHP built-in server process and the WebView window. It never edits, reloads, or binds to Apache, MySQL, PostgreSQL, Node.js, or Python services.

## Shared runtime contract
- Bind address: `127.0.0.1` only.
- Port: `18765` (configurable in `platform/config/app.php`).
- Document root: `platform/public`.
- Router: `platform/public/router.php`.
- Database: `platform/data/dcp.sqlite`.
- Logs: `platform/storage/logs/`.
- Server arguments use PHP CLI `-S`; Apache is not used.

## macOS
The current build is `DCP.app`. It starts PHP if the health endpoint is not already responding, opens WKWebView, and terminates only the PHP process it created when the window closes. PHP candidates: `/usr/local/bin/php`, then `/opt/homebrew/bin/php`.

Build: `clang -fobjc-arc -framework Cocoa -framework WebKit platform/wrappers/macos/main.m -o DCP.app/Contents/MacOS/DCP`.

## Windows
Build `windows/DCP.WebView.csproj` with the .NET 10 SDK and `EnableWindowsTargeting` (the source can be cross-compiled from macOS). The release script bundles a private PHP distribution and the platform payload. On startup, the installed Windows shell checks the latest GitHub Release, stages a newer package, then applies it after the running process exits. `platform/data` and `platform/storage` are preserved during updates. Native runtime acceptance still requires a Windows device.

## Linux
Requires GTK3, WebKit2GTK 4.1, libsoup 3, and PHP CLI with PDO_SQLite, cURL, OpenSSL, Sodium, mbstring and intl. Build: `cc dcp-webview.c -o dcp-webview $(pkg-config --cflags --libs gtk+-3.0 webkit2gtk-4.1 libsoup-3.0)`. Set `DCP_ROOT` to the directory containing `platform/`.

## Runtime isolation
For distributable builds, bundle a dedicated PHP runtime per OS in `runtime/php/`. Do not install PHP globally as part of DCP setup. The current macOS development build uses the existing PHP CLI executable, but launches an independent loopback-only process with its own SQLite database and storage directory.


## External navigation and network policy

All desktop wrappers keep the DCP interface on its local loopback origin (`http://127.0.0.1:18765`). Navigation to any non-loopback URL is cancelled inside the embedded WebView and handed to the operating system's default browser. This applies to normal links and links requesting a new window.

This policy does **not** block outbound requests made by DCP's PHP backend to configured AI providers or other explicitly configured integrations. Those service requests remain part of the single DCP application workflow. Avoid broad Little Snitch allow rules; allow only the required app/service destinations.
