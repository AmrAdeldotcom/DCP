# DCP Cloud — device rendezvous and signaling

PHP 8.2+ / MySQL 8 or MariaDB. Deployment target: `https://dcp.amradel.com`.

## Scope
The cloud is a control plane only: device registration, device presence, connection request routing and short-lived signaling envelopes. It does not proxy application payloads and does not provide TURN relay. DCP clients must establish and encrypt the direct peer transport themselves (ICE/STUN + authenticated DTLS or another reviewed transport). A signaling API alone does not create a tunnel.

## Install
1. Create a MySQL database and a dedicated user with privileges only on that database.
2. Import `database/schema.mysql.sql` in phpMyAdmin.
3. Copy `.env.example` to `.env` in the project root, set DB values and generate a unique APP_KEY (32+ random bytes).
4. Set the subdomain document root to `cloud-platform/public`.
5. Require HTTPS. Keep `.env`, `src`, `database`, `docs` and `tests` outside the public document root. If your host requires the project root to be public, move the public entrypoint into the subdomain root and keep private folders above it.
6. Test `GET /api/v1/health`.

## API MVP
- `POST /api/v1/devices/register` JSON `{ "label":"Workstation", "platform":"macOS" }` returns a one-time device token. Store it securely in the DCP client.
- `POST /api/v1/devices/heartbeat` with `Authorization: Bearer DEVICE_TOKEN`.
- `GET /api/v1/devices` with the same authorization.
- `POST /api/v1/connections/request` JSON `{ "target_device":"UUID" }`.
- `POST /api/v1/connections/{request_id}/signal` JSON `{ "envelope":"..." }`.
- `GET /api/v1/connections/{request_id}/signals?after=0`.

## Important limitations before production
This is an API scaffold, not a finished Cloudflare Tunnel replacement. Account registration/login, user ownership and device enrollment flows, signed device identities, guest mode with a strict 24-hour expiry, rate limits, abuse prevention, WebSocket/polling worker and client-side ICE/P2P implementation remain to be completed. Current register endpoint creates unowned devices; do not expose it publicly as a production service until enrollment/authentication is added. Heartbeat is HTTPS request/response, not a persistent socket. Shared hosting can use short polling; persistent WebSockets require host support. Direct P2P is not guaranteed through symmetric NAT/CGNAT or restrictive firewalls. No relay fallback is provided.

See `docs/ARCHITECTURE.md`. MIT License.
