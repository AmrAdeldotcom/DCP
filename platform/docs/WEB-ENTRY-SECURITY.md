# Web Entry Security

- Apache entry: platform/index.php; static files are mapped only from platform/public/assets.
- The web preview uses DCP_MODE=web-preview and its own DCP-UserData/WebPreview database, key and storage.
- Application source, cloud code, runtime packages, scripts, tests, SQLite files, keys, logs and environment files are denied by platform/.htaccess.
- The desktop app still serves platform/public through its loopback-only PHP server and uses per-OS user data.
- The preview route must remain behind Cloudflare Access or HTTP Basic Auth before creating the dcp-shortcut symlink. Never expose the unrestricted local terminal or local database.
- This first Apache entry is not a substitute for authentication. Verify Access policy and all forbidden paths before the symlink is created.
