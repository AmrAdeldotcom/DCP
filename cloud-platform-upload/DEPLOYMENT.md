# DCP Cloud deployment and health verification

1. Upload the contents of this directory to the DCP Cloud document root on the hosting account.
2. Copy `.env.example` to `.env` in the same directory. Keep `.env` private and never commit it.
3. In the hosting control panel, open the MySQL database details and copy the exact database host, name, username and password into `.env`. The example uses `DB_HOST=localhost`; do not truncate it to `localhos`.
4. Import `schema.mysql.sql` into the configured database.
5. Open `https://dcp.amradel.com/api/v1/health`. A healthy response must be HTTP 200 with `{"ok":true,"database":"ok","schema":"ok",...}`. If MySQL cannot be reached, the endpoint must return HTTP 503 with `database: unavailable` and the detailed exception is written only to the server error log.
6. Test login with a real account from the DCP desktop Settings > DCP Cloud screen. Do not put passwords or API tokens in URLs, screenshots or support messages.

The cloud API currently provides account/device registration, device heartbeats and short-lived signaling only. It does not provide payload relay or completed remote task execution.
