# DCP Performance Audit — Initial Evidence

Mode: initial read-only audit, using the supplied Speed Engineer skill. No Redis or paid cache service is proposed. Provisional tier S (<1,000 users) pending confirmation.

## Stack map
- PHP 8.2+ local PHP CLI application, PDO SQLite, raw SQL, cURL and libsodium.
- Vanilla JavaScript/CSS, local Bootstrap assets, WebView wrappers (WKWebView, WebView2; Linux deferred).
- SQLite growth tables: chat_messages, tasks, audit_log, notifications, mail_messages, reports.
- Local engines: Ollama HTTP API and llama.cpp OpenAI-compatible server.

## Findings
| Area | Initial status | Evidence | First action |
|---|---|---|---|
| Indexes | Needs work | Database.php only creates idx_chat_messages_session; tasks/device/inbox/audit/notifications filters and sorts need matching indexes | Audit actual queries; add one migration for proven indexes |
| N+1 | Needs review | ApiController task poll updates each returned task individually; chat history is one bounded query | Verify task list update pattern and device status queries |
| Pagination | Partial | notifications 100, tasks 100, chats 50, device tasks 5, IMAP 20; chat message history 30 | Add cursor/offset pagination for mail, devices and message history where needed |
| Images | Mostly small | local assets total about 860 KB; logos 60–72 KB; no upload/image-heavy feed identified | Keep local assets cached; lazy-load only if new below-fold imagery appears |
| Caching | Partial | hardware cache exists; provider/model and health requests refresh repeatedly | Cache stable model/catalog metadata with explicit invalidation; never cache secrets/presence/chat |
| Loading UX | Needs work | load functions swallow errors or toast; chat has a generic Connecting bubble; several sections lack skeleton/retry states | Add per-panel loading/empty/error/retry and request deduplication |

## Reproduced symptoms / concrete defects
- Local AI chat displays "Error: Load failed" in the supplied screenshot. Need capture browser console, /api/chat response, provider /api/providers/models response and Ollama logs before changing chat behavior.
- llama.cpp is hard-coded to port 8080 in LocalAIService status/start response; this conflicts with Apache on the Mac. Select a free loopback port and persist the managed process/port.
- Local AI UI reports localhost:8080 and refresh can show install again; status is based on process/port probes, not a durable managed-engine registry.
- Agent adapters can show Running but not connected; UI has no lifecycle controls. Status semantics need a single adapter health contract.
- Cloud UI shows not_found while account registration says email_already_registered; route/base URL/version contract must be diagnosed on the live server.
- Current CloudService hard-codes https://dcp.amradel.com/api/v1; user needs editable Cloud base URL.
- Current mail settings combine SMTP/IMAP and inbox under Settings; Mail is not a first-class page.

## Required measurements
- Record app first-paint, /api/status, bootstrap and page-specific API timings on Mac and Windows.
- Count SQL queries per screen; seed 100k synthetic rows locally for growing tables before index/pagination decisions.
- Capture local model request timings and exact provider/engine error bodies (redact secrets).
- Compare before/after numbers in the next audit revision.
