#!/usr/bin/env bash
set -Eeuo pipefail
if curl -fsS --max-time 2 http://127.0.0.1:11434/api/tags >/dev/null 2>&1; then echo "Ollama already running; reusing it."; exit 0; fi
command -v curl >/dev/null || { echo "curl is required." >&2; exit 1; }
echo "Installing official Ollama into its standard Linux system location..."
curl --http1.1 -fsSL --retry 4 --retry-all-errors --retry-delay 2 https://ollama.com/install.sh | sh
command -v ollama >/dev/null
ollama --version
echo "Ollama installed system-wide; model files remain in the standard Ollama model directory."
