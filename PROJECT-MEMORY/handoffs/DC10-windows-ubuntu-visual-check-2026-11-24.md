# DC10 Handoff — Windows and Ubuntu visual validation
Date: 2026-11-24 15:46:00 EET

## Confirmed
- Windows 11 Alpha 9 main Overview UI visibly rendered in user's screenshot. DCP service online; PHP 8.5.13; local database Ready; local PHP service online.
- Windows Ollama shown Offline and Desktop Commander Remote shown Not connected; do not conflate app UI pass with those service states.
- Ubuntu screenshot shows blank white window. SSH inspection via explicit config to 192.168.8.88 found DCP and WebKit processes alive, PHP server 127.0.0.1:18765, but HTTP / and /api/status both 500.
- Root cause from PHP log: missing /home/amr/Applications/DCP/platform/app/Core/Database.php required at platform/bootstrap/app.php:32.
- This matches known Linux layout defect in published v1.0.0-alpha.9. Fix commit 0e3f41196e0819e94a8bfa99ecf95378a7a71592, CI run 45398600124 passed. Corrected release still requires approval.
- Do not rewrite published tag/assets or claim Ubuntu passed.

## Next actions
1. Get user approval for a new superseding alpha release containing the Linux package fix.
2. Build/publish the corrected complete release and verify all required assets.
3. Update/install Ubuntu from the corrected release; preserve current install/logs until safe replacement plan.
4. Re-test HTTP root, /api/status, GUI rendering and restart persistence.
5. Keep Windows DB/master.key backup evidence and do not delete rollback backup.

## Related product proposal
User requested DCP Chat Live Task Inbox & Interrupt Queue: persistent message queue while an agent is working, with queue-later, integrate-at-checkpoint, interrupt-now, separate-task options; safe checkpoints, context merge, approval gates, durable recovery. A full draft spec and OpenAI feature request were prepared in the conversation. No code implementation started.
