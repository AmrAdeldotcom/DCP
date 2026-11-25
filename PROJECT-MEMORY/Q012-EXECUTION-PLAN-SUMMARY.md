# Q012 — ملخص خطة التنفيذ ومراجع الأدوات

**المشروع:** DCP — Desktop Connector Platform
**المرحلة:** Stage 6 — Device Collaboration and Agents
**المهمة:** Q012 — Shared Chat, Task Inbox and Agent Execution Coordinator
**آخر نقطة:** DC65
**فرع المصدر:** `feature/local-ai-runtime-discovery`
**آخر Commit منشور:** `0522e14`
**الحالة:** Tasks 1–7 لها تنفيذ محلي حتى حدود التنفيذ الآمن الموضحة أدناه. لا يوجد تنفيذ تلقائي لمقترحات الموديل أو Loop متعدد الخطوات.

## 1. ملخص حالة المهام

| المهمة | الحالة | ما تم |
|---|---|---|
| Task 1 — Shared Tool Registry | مكتمل محلياً؛ CI سابق ثلاثي الأنظمة PASS | سجل موحد يخدم MCP STDIO وHTTP وChat Coordinator |
| Task 2 — Provider Contract | مكتمل محلياً | `ToolCallingProviderAdapter` و`ProviderToolCallResult` دون كسر `ProviderAdapter::chat()` |
| Task 3 — Provider Parsing | مكتمل محلياً | Ollama وOpenAI-compatible، رفض JSON المشوه، حدود الأدوات، Qwen3 no-thinking |
| Task 4 — Bounded Coordinator | مكتمل محلياً | يحول المقترحات إلى خطة/خطوات/موافقات بإصدار وبصمة Args ومفتاح idempotency؛ لا ينفذ |
| Task 5 — Results + Approval Execution | مكتمل محلياً | capability grants، مخزن نتائج خاص، استدعاء الأدوات من الخطة غير القابلة للتغيير، leases أحادية الاستخدام، audit، منع replay |
| Task 6 — Review APIs/UI | مكتمل محلياً | مراجعة الخطة والاعتماد/الرفض وتفاصيل Arguments؛ الموافقة لا تنفذ |
| Task 7 — Safe Resume | مكتمل محلياً حتى الاستئناف اليدوي الآمن | تحقق Checkpoint Hash، تنفيذ خطوة واحدة بعد مراجعة صريحة، تبعيات وموافقات؛ لا يوجد تشغيل تلقائي متعدد الخطوات |

**ما زال مفتوحاً:** worker heartbeat/crash recovery، multi-step coordinator loop التلقائي (غير مفعّل)، lifecycle events، واختبارات CI جديدة للأنظمة الثلاثة.

## 2. نموذج التنفيذ الآمن

1. الموديل يقترح Tool Calls؛ `ChatExecutionCoordinator` يتحقق من الاسم والـ Schema والحدود ويحفظ Plan بإصدار وArguments Hash.
2. المستخدم يراجع اسم الأداة والـ Arguments حرفياً في واجهة Chat.
3. الموافقة على خطوة تنشئ Capability Grant مرتبطاً بـ Job + Plan Version + Step + Tool + Capability. الرفض يلغي الـ Grant.
4. المستخدم يضغط Execute لهذه الخطوة تحديداً. لا يحدث تنفيذ بمجرد الموافقة.
5. `DcpToolExecutionService` يعيد قراءة الأداة والـ Arguments من الخطة المحفوظة فقط، ويتحقق من Registry Policy وHash والموافقة والـ Grant.
6. يتم حجز خطوة ذرياً باستخدام One-time Lease وIdempotency Key.
7. تنفذ الأداة من `DcpToolRegistry`، وتُحفظ النتيجة في Artifact خاص (0700 directory / 0600 file) بحد أقصى 256 KB، مع SHA-256 وتنقيح أسرار.
8. يُغلق الـ Lease وتُحفظ النتيجة كمرجع opaque. الفشل/النتيجة غير المؤكدة لا يعاد تشغيلها تلقائياً.

**ممنوع:** تشغيل مقترحات الموديل تلقائياً، استخدام Arguments من طلب HTTP بدلاً من الخطة، إعادة تشغيل side effects غير المؤكدة، أو اعتبار موافقة MCP Client وحدها Sandbox.

## 3. كتالوج DCP Core Local MCP — 31 أداة

### Platform / Chat / Agent
- `dcp_get_status`
- `dcp_list_providers`
- `dcp_list_devices`
- `dcp_list_tasks`
- `dcp_create_task`
- `dcp_read_unread_mail`

### Terminal / Sessions / Processes
- `dcp_run_safe_terminal` — تشخيص قراءة محدود.
- `dcp_terminal_execute` — Shell عام بحدود زمن/مخرجات، بصلاحية مستخدم النظام الحالي.
- `dcp_terminal_session_start`, `dcp_terminal_session_read`, `dcp_terminal_session_write`, `dcp_terminal_session_stop` — جلسات تفاعلية داخل عمر MCP STDIO Host فقط.
- `dcp_process_list`, `dcp_process_terminate` — عمليات المستخدم الحالي؛ إنهاء Graceful، دون Force Kill.

### Filesystem
- `dcp_fs_read`, `dcp_fs_list`, `dcp_fs_search`, `dcp_fs_write`, `dcp_fs_mkdir`, `dcp_fs_move`, `dcp_fs_delete`, `dcp_fs_stat`, `dcp_fs_edit`.
- المسارات نسبية إلى Workspace، symlink/traversal مرفوضان. Edit يتطلب SHA-256 مطابقاً وتطابقاً وحيداً ويكتب Atomic.

### Cloud Controls
- `dcp_cloud_status`, `dcp_cloud_pairing_code`, `dcp_cloud_request_pairing`, `dcp_cloud_decide_pairing`, `dcp_cloud_revoke_pairing`, `dcp_cloud_connect`, `dcp_cloud_send_signal`, `dcp_cloud_get_signals`.

**ملاحظة النقل:** STDIO يعرض 31 أداة. Local Streamable HTTP وProvider-neutral Chat schemas يعرضان 27؛ أدوات جلسات Terminal الأربع غير معروضة عبر HTTP/Chat لأن PHP request process لا يحتفظ بجلسة Process بين الطلبات. لا نعلن قدرة غير مدعومة.

## 4. صلاحيات Local Streamable HTTP MCP

Scopes مستقلة: `platform.read`, `terminal.safe-read`, `terminal.execute`, `filesystem.read`, `filesystem.write`, `filesystem.delete`, `process.read`, `process.terminate`, `cloud.status.read`, `cloud.pairing.manage`, `cloud.connection.manage`, `cloud.connection.read`, `sensitive.read`, `task.create`, `memory.read`.

- `tools/list` يرشح الأدوات حسب الـ scopes.
- `tools/call` يعيد التحقق من الـ scope.
- Token secrets لا تحفظ إلا كـ SHA-256، والـ CLI يعرض القيمة مرة واحدة.

## 5. DCP Cloud

- Cloud Backend الحالي PHP + MySQL، والواجهة HTML/CSS/JS الحالية، دون Node/Next.js.
- Cloud Landing Page مصممة بشكل responsive وتدعم `ar/en/nl/es/pt/fr/de/zh/ja`.
- Local DCP Core يتصل بـ Cloud عبر CloudService وأدوات Pairing/Presence/Signaling.
- Remote Cloud MCP endpoint `/api/v1/mcp` يظل authenticated/read-only لحالة الأجهزة وطلبات الاقتران. لا يوجد Remote Terminal/Task Dispatch حتى يتم دمج signed envelopes مع DCP Node وصلاحياته.
- لا يوجد Production Deployment لهذه التغييرات؛ APP_KEY rotation approval ما زال مطلوباً.

## 6. تخزين الخطط والنتائج والموافقات

| الجدول/المكوّن | المحتوى |
|---|---|
| `chat_job_execution_plans` | إصدار الخطة وJSON metadata |
| `chat_job_execution_steps` | ترتيب الخطوات والتبعيات والحالة |
| `chat_job_approvals` | قرار المستخدم مرتبط بإصدار الخطة والخطوة |
| `chat_job_capability_grants` | Grant مرتبط بالخطة/الخطوة/الأداة/القدرة |
| `chat_job_tool_executions` | Idempotency Key وArguments Hash وOne-time Lease وحالة التنفيذ |
| `chat_job_tool_results` | Opaque result reference + SHA-256 |
| `DcpToolResultArtifactStore` | ملف JSON خاص ومنقح من الأسرار، 256 KB كحد أقصى |
| `chat_job_checkpoints` | آخر Provider Call، المحادثة، Task Inbox، Execution Context وSHA-256 |

## 7. Safe Resume

- `GET /api/chat/jobs/execution-review?id=...` لعرض الخطة والتبعيات والموافقات والنتائج.
- `POST /api/chat/jobs/approvals/decision` لتسجيل الموافقة/الرفض دون تنفيذ.
- `POST /api/chat/jobs/tools/execute` لتنفيذ خطوة واحدة بعد مراجعتها وموافقتها.
- `GET /api/chat/jobs/tool-result` لقراءة Artifact مرتبط بالـ Job نفسه.
- `POST /api/chat/jobs/resume` لإنشاء دور محادثة جديد بعد التحقق من Checkpoint Hash؛ لا يعيد تشغيل Tool Calls القديمة.
- `resumable=true` فقط عند وجود خطة وتبعيات وعدم وجود Running/Claimed/Failed execution. `resume_requires_review=true` دائماً؛ `automatic_execution=false`.

## 8. الاختبارات

| الاختبار | النتيجة |
|---|---|
| `php platform/tests/run.php` | `ALL PLATFORM TESTS PASSED` |
| `php platform/tests/mcp-http-e2e.php` | `DCP_MCP_STREAMABLE_HTTP_LOCAL_E2E_PASS` |
| `php platform/tests/cloud-contract.php` | `ALL CLOUD CONTRACT CHECKS PASSED` |
| `DCP_RUN_CHAT_WORKER_E2E=1 php platform/tests/chat-worker-e2e.php` | `DCP_CHAT_WORKER_BACKGROUND_E2E_PASS` |
| `python3 platform/tests/chat-job-http-e2e.py` | `DCP_CHAT_JOB_HTTP_E2E_PASS` |
| `platform/tests/chat-tool-execution.php` | ضمن Full Suite؛ approved filesystem write + artifact + redaction + replay prevention PASS |
| PHP lint / JS syntax / JSON / `git diff --check` | PASS |

**CI:** Task 1 القديم كان PASS على Ubuntu/Windows/macOS. التوسعات الحالية 31 أداة وTask5 execution لم تُرفع بعد؛ لا تدّعِ أنها cross-platform حتى تشغيل المصفوفة الجديدة.

## 9. الملفات الأساسية

- `platform/app/Services/DcpToolRegistry.php`
- `platform/app/Services/TerminalService.php`
- `platform/app/Services/DcpFilesystemService.php`
- `platform/app/Services/DcpCapabilityGrantService.php`
- `platform/app/Services/DcpToolResultArtifactStore.php`
- `platform/app/Services/DcpToolExecutionService.php`
- `platform/app/Services/ChatExecutionCoordinator.php`
- `platform/app/Services/ChatJobService.php`
- `platform/app/Controllers/ApiController.php`
- `platform/cloud/index.php`
- `platform/plugins/tools/mcp-host/server.php`
- `platform/tests/chat-tool-execution.php`
- `PROJECT-MEMORY/tasks/Q012-shared-chat-task-inbox.md`
- `platform/docs/DCP-CORE-DC-PARITY-AND-CLOUD-ROADMAP.md`

## 10. مجلدات المشروع والقيود

| المجلد | الغرض |
|---|---|
| `/Volumes/Data/ChatGPT/DCP-Source` | مصدر التطوير؛ لا reset/clean |
| `/Volumes/Data/DiGiPL/DCP` | تثبيت Stable v1.0.0 مستقل؛ لا تعديل مباشر |
| `/Volumes/Data/DiGiPL/DP_MEMORY` | مشروع منفصل مؤجل |

لا Commit/Push/Release/Deploy دون موافقة. مساحة `/Volumes/Data` كانت نحو 9.2 GiB (99% مستخدمة)، فلا تنزّل runtimes أو dependencies ضخمة.
