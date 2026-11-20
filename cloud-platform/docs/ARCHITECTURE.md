# Architecture

DCP Cloud is intended as a rendezvous/control plane, not a data plane. Devices initiate outbound HTTPS requests; the service tracks presence and exchanges short-lived signaling envelopes. Clients negotiate a direct authenticated encrypted connection and must never upload file/application payloads to this service.

The server cannot promise a direct path through all NAT/firewall combinations. ICE/STUN may fail with symmetric NAT, CGNAT, VPN policy or blocked UDP. This build deliberately has no TURN relay. On failure, clients should report that direct connectivity is unavailable.

Production requirements: account and ownership model, secure device enrollment, revocation, guest mode capped at 24 hours, cryptographic identity/signatures, E2E-protected signaling, robust rate limiting, cleanup jobs, audit/privacy policy, and client implementation of ICE with relay candidates disabled.

Shared hosting note: PHP request/response APIs can run on ordinary shared hosting. True persistent WebSocket connections generally need a separately supported service; heartbeat plus short HTTPS polling is the compatibility fallback.
