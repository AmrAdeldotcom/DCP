# DC08 — Network watchdog and VM connectivity

Timestamp: 2026-11-24T15:27:40+02:00 (Africa/Cairo)

## Verified network configuration
- Windows 11: 192.168.8.99/24, gateway/DNS 192.168.8.1, MAC 00-1C-42-FA-1E-9B, manual IPv4. SSH fingerprint confirmed locally; alias `windows-dcp-admin` tested.
- Ubuntu 24.04: 192.168.8.88/24, gateway/DNS 192.168.8.1, interface enp0s5, MAC 00:1c:42:72:ea:56, NetworkManager IPv4 manual. SSH fingerprint confirmed locally; alias `ubuntu-dcp-admin` tested.

## Network watchdog
- LaunchAgent: `com.digipl.dcp-network-watchdog`, StartInterval 600 seconds.
- Script: `~/Library/Application Support/DCP/watchdog/network-watchdog.sh`.
- State/log: `PROJECT-MEMORY/runtime/network-state.json` and `network-watchdog.log`.
- On offline-to-online transition it writes `PROJECT-MEMORY/NETWORK-RESUME-REQUEST.json` for the next agent run.
- Verified plist lint, bash syntax, launchctl bootstrap/kickstart, and first successful HTTPS reachability check.

## Hard limitation
The local monitor can test internet reachability and record recovery, but cannot refresh or reopen ChatGPT, revive a disconnected model session, or force an assistant run. The saved DCP Alpha 9 automation remains hourly because the scheduling service does not support a 10-minute cadence.

## Continue
Read `WORK-QUEUE.md`, `PROJECT-MEMORY/INDEX.md`, `PROJECT-MEMORY/CURRENT-STATE.json`, Q005 and this handoff. Check the network resume marker/log. Continue Q005 without repeating completed steps. Do not alter original Mac services or overwrite published Alpha 9.
