#!/usr/bin/env bash
set -euo pipefail
# Run from a real terminal so sudo can request the user's approval.
if ! command -v cmake >/dev/null 2>&1 || ! command -v c++ >/dev/null 2>&1; then
  if command -v apt-get >/dev/null 2>&1; then
    sudo apt-get update
    sudo apt-get install -y cmake build-essential git
  else
    echo "Install CMake, a C++ compiler and Git with your distribution package manager, then rerun."
    exit 1
  fi
fi
DEST="$HOME/.cache/dcp-llama.cpp"
mkdir -p "$(dirname "$DEST")"
if [ ! -d "$DEST/.git" ]; then git clone --depth 1 https://github.com/ggml-org/llama.cpp.git "$DEST"; else git -C "$DEST" pull --ff-only; fi
cmake -S "$DEST" -B "$DEST/build" -DCMAKE_BUILD_TYPE=Release -DLLAMA_BUILD_SERVER=ON
cmake --build "$DEST/build" --config Release --target llama-server -j2
mkdir -p "$HOME/.local/bin"
install -m 0755 "$DEST/build/bin/llama-server" "$HOME/.local/bin/llama-server"
echo "Installed: $HOME/.local/bin/llama-server"
echo 'Add $HOME/.local/bin to PATH if DCP cannot discover llama-server.'
