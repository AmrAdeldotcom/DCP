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
