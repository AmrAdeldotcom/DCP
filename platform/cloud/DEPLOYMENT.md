# DCP Cloud deployment (Hostinger)

## Before uploading

1. Open Hostinger hPanel and confirm the MySQL host, database name, username and port.
2. Keep the existing `.env` file private. For key rotation, set a new random `APP_KEY` and retain the old value as `APP_KEY_PREVIOUS`; existing device/account tokens are accepted through the previous key and lazily re-hashed with the new key. Remove `APP_KEY_PREVIOUS` only after all clients have migrated. Never upload or publish `.env`.
3. Back up the current database before applying the migration.

## Existing DCP Cloud installation

The current installation already has users, API tokens and devices. Preserve them.

1. Open phpMyAdmin for the existing DCP Cloud database.
2. Import **schema.migrate.sql** once. It makes device ownership nullable for anonymous devices and creates the one-time pairing-code, pairing-request and device-permission tables. Existing account/device rows and token hashes are preserved.
3. In Hostinger File Manager, open the actual document root for `dcp.amradel.com`.
4. Upload the updated `index.php`, `token.php` and `.htaccess` from this folder. Keep `index.php` and `token.php` mode 600 and `.htaccess` mode 644 so LiteSpeed can read the rewrite rules; the helper `token.php` is explicitly denied to direct HTTP requests.
5. Do not replace `.env`, and do not re-import `schema.mysql.sql` into the existing database.

## Brand-new installation

1. Create a MySQL database and user in hPanel.
2. Import **schema.mysql.sql**.
3. Copy `.env.example` to `.env` and set the exact Hostinger DB host, port, database name, username and password. Generate APP_KEY with `openssl rand -base64 48` (or another unique random printable secret of at least 32 characters); preserve the existing production key during upgrades. Never use the example placeholder.
4. Upload `index.php`, `token.php` and the routing `.htaccess` into the domain document root. Keep schema files, deployment notes, archives and environment files outside it; deny direct HTTP access to `token.php`.

## Verify deployment

Open:

`https://dcp.amradel.com/api/v1/health`

A successful deployment must return HTTP 200 and JSON containing:

```json
{
  "ok": true,
  "database": "ok",
  "schema": "ok",
  "service": "DCP Cloud",
  "relay": false,
  "transport": "signaling-only"
}
```

If the response is HTTP 503, check the Hostinger PHP error log and confirm that the migration was imported before the new `index.php` was uploaded.

## Device identity and pairing behavior

- Each DCP installation automatically registers a device identity with a unique device ID and a long-lived device token. The token is stored encrypted in the local DCP database and is never displayed or copied.
- To pair devices, the owner creates a one-time pairing code. It expires after 10 minutes and can be used once.
- The second device enters the code and sends a pairing request. The first device must approve or reject it.
- The owner chooses the supported permissions at approval time: device presence and signaling.
- Email is optional for device pairing. Users can optionally create or sign into a DCP Cloud account inside the desktop app to link the device identity to a persistent account.
- Signing out unlinks the account but preserves the device identity and existing pairings.

DCP Cloud currently provides account/device identity, presence and signaling only. Direct peer data transport, relay and remote task execution are not implemented by this release.
