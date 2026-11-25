# Q011 custom GGUF import and Ollama conversion — DC35

Date: 2026-11-25
Branch: feature/local-ai-runtime-discovery

## Real conversion acceptance
Source model: mradermacher/Qwen3-0.6B-GGUF Q4_K_M.
Source file: Qwen3-0.6B.Q4_K_M.gguf.
Size: 484,220,448 bytes.
SHA-256: 870bc8d16bfd608ebe38ef656766c32ee243d9401f360bb492625992bcf1c9e9.
GGUF header: accepted as supported GGUF v3 with bounded tensor/metadata counts.

Used a disposable DCP storage root under /tmp/DCP-Q011 and a unique Ollama model name dcp-qwen3-06-smoke-20261125. The existing Ollama service on 127.0.0.1:11434 was reused; no existing models were replaced or deleted.

Verified:
1. importGguf copied the valid source into the private managed GGUF directory and recorded its size/SHA-256.
2. The imported GGUF file was mode 0600.
3. createOllamaFromGguf wrote a private Modelfile and started ollama create in the background.
4. Ollama registered the model as dcp-qwen3-06-smoke-20261125:latest (484 MB).
5. A real /api/chat request returned exact probe DCP_Q011_OLLAMA_OK.
6. deleteCustomModel removed only the temporary Ollama model and imported GGUF/manifest entry.
7. Post-test ollama list contained only the original DiGEMA-7B, DiGEMA-3B and DiGEMA-4B models; /api/tags returned HTTP 200.
8. The temporary /tmp harness and copied source GGUF were removed.

Result: DCP_Q011_GGUF_IMPORT_CONVERT_CHAT_DELETE_PASS.

## Safety
- Existing Ollama models and the running Ollama service were preserved.
- No DCP SQLite database or master.key was accessed or changed.
- No production deployment or installed-app update was performed.
- No temporary model or source GGUF remains.

## Remaining Q011 work
- Validate custom GGUF import/convert/delete from the actual UI through the running app, not only the manager/service and UI contracts.
- Direct custom-GGUF launch with llama.cpp remains a separate future capability; current custom conversion target is Ollama.
- Re-run the full suite and commit/push this evidence.


## Real DCP app API/UI flow — 2026-11-25
Used the running DCP app server on 127.0.0.1:18991 with its isolated DCP_USER_DATA_DIR=/tmp/DCP-LocalAI-Test. This is the actual PageController/ApiController/LocalAIService path, authenticated through the app's session cookie and X-CSRF-Token. The app's isolated test database and master.key were not changed by the model workflow.

Passing sequence:
1. POST /api/local-ai/models/import imported the 484,220,448-byte GGUF by local path and returned the expected SHA-256.
2. POST /api/local-ai/models/ollama created the Ollama model and Modelfile.
3. The UI inventory poll observed the model transition from creating to ready.
4. The Ollama provider adapter itself returned exact DCP_Q011_OLLAMA_ADAPTER_OK using the selected custom model.
5. POST /api/local-ai/models/delete removed the imported GGUF and manifest entry; Ollama no longer listed the temporary model.
6. Ollama /api/tags remained HTTP 200 and only the three original DiGEMA models remained.

This exposed two genuine UI-path issues and both are fixed:
- The conversion process can exit just before Ollama's /api/tags exposes its manifest. The old code immediately marked the model failed. CustomModelManager now stores ollama_started_at and retains creating/verifying for up to 120 seconds after process exit; it becomes failed only after that grace window if the model never appears.
- Qwen3 Ollama chat can emit only hidden thinking and an empty message.content under a short token budget. The Ollama provider now sends think=false for Qwen3 models. A real ProviderAdapter::chat call returned DCP_Q011_OLLAMA_ADAPTER_OK.

The initial API-only smoke also confirmed that import can exceed a short HTTP client timeout for a 484 MB file; the successful full-flow test used a 180-second import request timeout. The actual UI request uses the app's fetch path and was able to finish.

Final result: DCP_Q011_REAL_APP_UI_API_FLOW_PASS. Post-test DCP custom model inventory is empty; the temporary GGUF, Modelfile and Ollama model were removed. Existing DiGEMA models and Ollama service remain intact.

## Updated validation
- system-tools-contract.php — ALL SYSTEM TOOLS CONTRACT CHECKS PASSED.
- model-manager-contract.php — ALL MODEL MANAGER CONTRACT CHECKS PASSED.
- full platform suite — ALL PLATFORM TESTS PASSED, including the 120-second conversion-status grace regression.
- PHP lint and git diff --check — PASS.
