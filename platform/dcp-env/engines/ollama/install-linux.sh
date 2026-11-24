#!/usr/bin/env bash
set -Eeuo pipefail
if command -v curl >/dev/null 2>&1; then
  if curl -fsS --max-time 2 http://127.0.0.1:11434/api/tags >/dev/null 2>&1; then
    echo "A healthy Ollama service is already running; DCP will reuse it."
    exit 0
  fi
elif command -v wget >/dev/null 2>&1; then
  if wget -q --timeout=2 -O /dev/null http://127.0.0.1:11434/api/tags 2>/dev/null; then
    echo "A healthy Ollama service is already running; DCP will reuse it."
    exit 0
  fi
else
  echo "curl or wget is required." >&2
  exit 1
fi
if ! command -v curl >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y curl
  else
    echo "Ollama's official installer requires curl; install it with your system package manager first." >&2
    exit 1
  fi
fi
curl -fsSL --retry 2 https://ollama.com/install.sh | sh
command -v ollama >/dev/null || { echo "Ollama installer completed but the executable is not on PATH." >&2; exit 1; }
echo "Ollama installation completed. DCP will reuse the local service."
