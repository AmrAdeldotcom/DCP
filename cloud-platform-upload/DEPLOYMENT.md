# DCP Cloud deployment (Hostinger)

## Before uploading

1. Open Hostinger hPanel and confirm the MySQL host, database name, username and port.
2. Keep the existing `.env` file and its APP_KEY. Never upload or publish `.env`.
3. Back up the current database before applying the migration.

## Existing DCP Cloud installation

The current installation already has users, API tokens and devices. Preserve them.

1. Open phpMyAdmin for the existing DCP Cloud database.
2. Import **schema.migrate.sql** once. It makes device ownership nullable for anonymous devices and creates the one-time pairing-code, pairing-request and device-permission tables. Existing account/device rows and token hashes are preserved.
3. In Hostinger File Manager, open the actual document root for `dcp.amradel.com`.
4. Replace the deployed `index.php` with this folder's updated `index.php`.
5. Do not replace `.env`, and do not re-import `schema.mysql.sql` into the existing database.

## Brand-new installation

1. Create a MySQL database and user in hPanel.
2. Import **schema.mysql.sql**.
3. Copy `.env.example` to `.env` and set the exact Hostinger DB host, port, database name, username, password and a strong APP_KEY.
4. Upload the Cloud platform files into the domain document root.

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
