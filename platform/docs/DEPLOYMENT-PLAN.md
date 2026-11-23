# DCP Deployment Plan

## Local portable application
- A release archive and the root Terminal installer must produce the same directory layout and version.
- Each release contains only application source and signed/checksummed runtime assets; never developer data, .env, encryption keys, logs or user model files.
- Startup checks local services first, starts DCP-managed fallback services only when needed, and uses a dynamic loopback port.
- Runtime update and application update are separate transactions with manifest validation, checksum verification, atomic replacement and rollback.

## Mac development web preview
- Target path: /Volumes/Data/DiGEMA/public_html/dcp-shortcut -> /Volumes/Data/ChatGPT/DCP/platform
- Target URL: https://macpro.digipl.com/dcp-shortcut
- Before creating the symlink, add platform/index.php and .htaccess; verify Apache honors symlinks and deny rules.
- Use an isolated preview database and key, not the desktop user's production SQLite DB/key.
- Add Cloudflare Access or HTTP Basic Auth before making the app reachable through the public tunnel.

## Hostinger Cloud API
- SSH alias: dcp-hostinger (host 145.223.105.238, port 65002, user u163155085). Password is not stored in this repository.
- Application path: domains/amradel.com/public_html/dcp
- Discover current deployment, PHP entry point, .htaccess, .env variable names, schema and DB connectivity before modifying.
- Back up current files and DB before migration. Do not print or copy .env secrets into chat.
- Run schema migration only after validating it against the actual database version; deploy API source and run health/register/login/pairing contract tests.
- Keep rollback package and migration plan.
