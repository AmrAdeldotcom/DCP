#!/bin/bash
set -euo pipefail
PLATFORM_ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
ARCH="$(uname -m)"
if [ "$ARCH" = "arm64" ]; then
  RUNTIME="macos-arm64"; ASSET_HINT="macos-arm64"
elif [ "$ARCH" = "x86_64" ]; then
  FEATURES="$(/usr/sbin/sysctl -n machdep.cpu.leaf7_features 2>/dev/null || true) $(/usr/sbin/sysctl -n machdep.cpu.features 2>/dev/null || true)"
  if printf '%s' "$FEATURES" | grep -qi 'AVX2'; then RUNTIME="macos-x64-avx2"; ASSET_HINT="macos-x64"
  else RUNTIME="macos-x64-sse42"; ASSET_HINT="macos-x64-sse42"; fi
else
  echo "Unsupported Mac architecture: $ARCH" >&2; exit 1
fi
DEST="$HOME/.local/bin/llama-server"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
if [ -x "$DEST" ] && "$DEST" --version >/dev/null 2>&1; then
  echo "Compatible DCP llama.cpp runtime already installed: $RUNTIME"; exit 0
fi
if [ "$RUNTIME" = "macos-x64-sse42" ]; then
  command -v git >/dev/null || { echo "Git is required for the generic Intel build." >&2; exit 1; }
  command -v cmake >/dev/null || { echo "CMake is required for the generic Intel build." >&2; exit 1; }
  command -v make >/dev/null || { echo "Make is required for the generic Intel build." >&2; exit 1; }
  echo "No AVX2 detected; compiling the generic CPU server from official upstream source in a temporary folder."
  git clone --depth 1 https://github.com/ggml-org/llama.cpp.git "$TMP/llama-source"
  cmake -S "$TMP/llama-source" -B "$TMP/llama-build" -DCMAKE_BUILD_TYPE=Release -DGGML_NATIVE=OFF -DGGML_AVX2=OFF -DGGML_AVX=OFF -DGGML_FMA=OFF -DGGML_F16C=OFF -DLLAMA_BUILD_SERVER=ON
  cmake --build "$TMP/llama-build" --config Release --target llama-server -j 4
  SERVER="$TMP/llama-build/bin/llama-server"
  [ -x "$SERVER" ] || { echo "Generic Intel llama-server build failed." >&2; exit 1; }
  mkdir -p "$HOME/.local/bin"; install -m 0755 "$SERVER" "$DEST.new"; mv -f "$DEST.new" "$DEST"
  echo "System llama.cpp installed to $DEST."
  exit 0
fi
curl -fsSL 'https://api.github.com/repos/ggml-org/llama.cpp/releases?per_page=1' -o "$TMP/releases.json"
python3 - "$TMP/releases.json" "$ASSET_HINT" "$TMP/url" "$TMP/digest" <<'PY'
import json,sys
releases=json.load(open(sys.argv[1],encoding='utf-8'))
assets=releases[0].get('assets',[]) if releases else []
needle=sys.argv[2]
a=next((x for x in assets if needle in x.get('name','') and x.get('name','').endswith('.tar.gz')),None)
if not a or not a.get('digest','').startswith('sha256:'): raise SystemExit('Official llama.cpp archive or SHA-256 digest is missing.')
open(sys.argv[3],'w').write(a['browser_download_url']);open(sys.argv[4],'w').write(a['digest'].split(':',1)[1])
PY
curl -fL --retry 2 "$(cat "$TMP/url")" -o "$TMP/llama.tar.gz"
EXPECTED="$(cat "$TMP/digest")"; ACTUAL="$(shasum -a 256 "$TMP/llama.tar.gz" | awk '{print $1}')"
[ "$EXPECTED" = "$ACTUAL" ] || { echo "llama.cpp SHA-256 verification failed." >&2; exit 1; }
mkdir "$TMP/unpacked"; tar -xzf "$TMP/llama.tar.gz" -C "$TMP/unpacked"
SERVER="$(find "$TMP/unpacked" -type f -name llama-server -print -quit)"
[ -n "$SERVER" ] || { echo "llama-server was not found in the official archive." >&2; exit 1; }
STAGE="$TMP/llama-server"; cp "$SERVER" "$STAGE"
chmod +x "$STAGE"; "$STAGE" --version >/dev/null
mkdir -p "$HOME/.local/bin"; install -m 0755 "$STAGE" "$DEST.new"; mv -f "$DEST.new" "$DEST"
echo "System llama.cpp installed to $DEST."
