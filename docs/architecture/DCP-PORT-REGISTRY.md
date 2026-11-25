# DCP local port registry

Status: Reserved DCP service map.
Default binding: 127.0.0.1 (loopback only).
Active fixed listener: 60776/tcp for DCP desktop UI and local API.

The six-digit values supplied during design are retained as logical service codes. They are not valid TCP/UDP ports because a port is a 16-bit number and cannot exceed 65535. The selected ports are in IANA's Dynamic/Private range (49152–65535), which has no IANA service assignments. This is a local DCP convention, not a worldwide reservation or an IANA registration.

| Original logical code | DCP service | TCP port | State |
|---:|---|---:|---|
| 210776 | DCP local UI/API | 60776 | Active, fixed on all desktop OSes |
| 310707 | DCP MCP | 60707 | Reserved |
| 270603 | DP-Core local orchestration API | 60603 | Reserved |
| 780908 | Desktop Commander adapter bridge | 60908 | Reserved |
| 770808 | DSH Agent | 60808 | Reserved |
| 901214 | DCP device bridge / local signaling IPC | 60214 | Reserved |
| 121216 | DCP provider-neutral AI gateway | 60216 | Reserved |

## Rules

1. Do not reuse these ports for unrelated services.
2. All local services bind to 127.0.0.1 by default, never 0.0.0.0.
3. DCP desktop wrappers use fixed port 60776 on Windows, macOS and Linux. They fail with a clear conflict message if another process owns it; they never silently choose a random port.
4. The other six ports are registry reservations. A service must run a preflight bind check before activation. Until implemented, no listener is opened.
5. Local AI engines retain their own standard ports (for example Ollama 11434); llama.cpp model-server ports remain managed separately until the AI gateway owns the local endpoint.
6. Remote devices communicate through authenticated Cloud HTTPS, not by exposing local ports to LAN/Internet.
7. Port conflicts are reported with PID/process identity where the OS permits. Never kill an unrelated process to free a DCP port.

## Port selection

The chosen numbers are in the Dynamic/Private range, so IANA does not assign them to registered services. This does not guarantee that no locally installed application can bind one; DCP therefore checks for a listener at startup and reports conflicts. The original six-digit proposals are retained as logical identifiers in the registry.
