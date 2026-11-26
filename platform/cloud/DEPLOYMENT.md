# DCP Cloud deployment (Hostinger)

## Before uploading

1. Open Hostinger hPanel and confirm the MySQL host, database name, username and port.
2. Keep the existing `.env` file private. Production currently stores it outside the public document root at `~/dcp-private/.env`. For key rotation, set a new random `APP_KEY` and retain the old value as `APP_KEY_PREVIOUS`; existing device/account tokens are accepted through the previous key and lazily re-hashed with the new key. Remove `APP_KEY_PREVIOUS` only after all clients have migrated. Never upload or publish `.env`.
3. Back up the current database before applying a migration.

## Existing DCP Cloud installation

The current installation already has users, API tokens and devices. Preserve them.

1. Open phpMyAdmin or the MySQL console for the existing DCP Cloud database.
2. Import **`schema.migrate.sql`** before uploading matching PHP code. It is designed to be safe to re-run; existing account/device rows, pairing scopes, revocation state and token hashes are preserved.
3. Verify the required columns, especially `device_pairings.presence_paused_at DATETIME NULL` and the API-token scope fields. A successful database connection alone does not prove that the schema is current.
4. In Hostinger File Manager, open the actual document root for `dcp.amradel.com`.
5. Upload the updated `index.php`, `token.php` and `.htaccess` from this folder. Keep `index.php` and `token.php` mode 600 and `.htaccess` mode 644 so LiteSpeed can read the rewrite rules; the helper `token.php` is explicitly denied to direct HTTP requests.
6. Do not replace `.env`, and do not re-import `schema.mysql.sql` into the existing database. Keep schema files, deployment notes and archives outside the public document root.

## Brand-new installation

1. Create a MySQL database and user in hPanel.
2. Import **`schema.mysql.sql`**.
3. Copy `.env.example` to `.env` outside the public document root and set the exact Hostinger DB host, port, database name, username and password. Generate `APP_KEY` with `openssl rand -base64 48` (or another unique random printable secret of at least 32 characters); never use the example placeholder.
4. Upload `index.php`, `token.php` and the routing `.htaccess` into the domain document root. Keep schema files, deployment notes, archives and environment files outside it; deny direct HTTP access to `token.php`.

## Verify deployment

Open `https://dcp.amradel.com/api/v1/health`. A successful deployment must return HTTP 200 and JSON containing:

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

If the response is HTTP 503, check the Hostinger PHP error log and confirm that `schema.migrate.sql` was imported before the updated `index.php` was uploaded. Then register an anonymous device and verify an authenticated heartbeat; do not rely on the health endpoint alone.

## Device identity and pairing behavior

- Each DCP installation automatically registers a device identity with a unique device ID and a long-lived device token. The token is stored encrypted in the local DCP database and is never displayed or copied.
- To pair devices, the owner creates a one-time pairing code. It expires after 10 minutes and can be used once.
- The second device enters the code and sends a pairing request. The first device must approve or reject it.
- The owner chooses the supported permissions at approval time: device presence and signaling.
- Either paired endpoint may pause/resume the shared presence view only when presence was granted. Pausing hides online/last-seen state but does not revoke the pairing or change signaling permission; Revoke remains the separate full-access removal action.
- Email is optional for device pairing. Users can optionally create or sign into a DCP Cloud account inside the desktop app to link the device identity to a persistent account.
- Signing out unlinks the account but preserves the device identity and existing pairings.

## Windows PHP TLS

- Managed Windows PHP is built against OpenSSL and requires an explicit trusted CA bundle.
- `platform/resources/certs/cacert.pem` is the Mozilla CA bundle distributed by the official curl project. `CloudService` supplies it through `CURLOPT_CAINFO` on Windows; the managed PHP installer also configures `curl.cainfo` and `openssl.cafile`.
- Keep peer and hostname verification enabled. Never solve certificate problems by disabling TLS verification.
- Refresh the bundle from `https://curl.se/ca/cacert.pem` and update the recorded SHA-256 in `platform/resources/certs/README.md`.

## Relay limitation

A successful heartbeat proves Cloud API connectivity and device presence only. Direct peer data transport, relay and remote task execution are not implemented by this release. Until relay is deployed and independently tested, report `relay=false` and `transport=signaling-only`.

## Production incident record — 2026-11-26

Cloud heartbeat returned `cloud_backend_unavailable` because production `device_pairings` lacked `presence_paused_at`, although the heartbeat code selected it. Applied the matching nullable `DATETIME` column from `schema.migrate.sql`, verified its type/nullability, then confirmed successful Cloud heartbeats from Windows and Ubuntu VMs.
