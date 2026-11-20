DCP CLOUD — SHARED HOSTING PACKAGE
==================================

THIS IS ONE UPLOAD FOLDER. Upload the CONTENTS of this folder to the subdomain document root for dcp.amradel.com. Keep the folder structure intact.

1) In your hosting control panel create a MySQL database and a dedicated database user.
2) Open phpMyAdmin, select that database, and import schema.mysql.sql.
3) Copy .env.example to .env in this same folder. Fill DB_HOST, DB_NAME, DB_USER, DB_PASSWORD. Set APP_KEY to a new random value of at least 64 hexadecimal characters. Do not share .env or its secrets.
4) Ensure PHP 8.1+ with PDO MySQL is enabled, HTTPS is active, and Apache allows .htaccess.
5) Open https://dcp.amradel.com/ and https://dcp.amradel.com/api/v1/health.

The .htaccess denies direct web access to .env and schema/README files. Do not remove it. If your host uses Nginx without Apache compatibility, configure equivalent deny rules before deployment.

API (JSON):
POST /api/v1/register {email,password,display_name} — password minimum 12 characters; returns user access token.
POST /api/v1/login {email,password} — returns user access token (30 days).
POST /api/v1/devices/register {label,platform} with Authorization: Bearer USER_TOKEN — returns device token once.
GET /api/v1/devices with Authorization: Bearer USER_TOKEN.
POST /api/v1/devices/{uuid}/revoke with Authorization: Bearer USER_TOKEN.
POST /api/v1/devices/heartbeat with Authorization: Bearer DEVICE_TOKEN.
POST /api/v1/connections/request {target_device} with Authorization: Bearer DEVICE_TOKEN.
POST /api/v1/connections/{request_uuid}/signal {envelope} with Authorization: Bearer DEVICE_TOKEN.
GET /api/v1/connections/{request_uuid}/signals?after=0 with Authorization: Bearer DEVICE_TOKEN.

IMPORTANT SCOPE: This is a shared-hosting control-plane MVP. It supports account/device identity, device presence, same-account device discovery, connection requests and short-lived signaling. It does NOT implement the DCP desktop client's ICE/STUN/WebRTC data channel, guest pairing, persistent WebSockets, automatic expiry cleanup, abuse/rate limiting or full production security review. Signaling messages are short-lived metadata only; do not send files, commands or application payloads here. Direct P2P can fail behind symmetric NAT/CGNAT/firewalls; this service does not silently relay user traffic. Do not expose it to production users until the remaining controls and client integration are implemented and tested.
