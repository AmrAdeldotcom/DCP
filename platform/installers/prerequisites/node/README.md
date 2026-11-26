# DCP Node.js prerequisite

DCP installs Node.js 24.22.3 in a user-scoped runtime directory when DC or DSH needs it.
The archive is checked against the official Node.js SHASUMS256.txt before extraction.

- macOS/Linux: bash install-unix.sh
- Windows: powershell -NoProfile -ExecutionPolicy Bypass -File install-windows.ps1

This does not overwrite an existing system Node.js installation or change project-local Node versions.
