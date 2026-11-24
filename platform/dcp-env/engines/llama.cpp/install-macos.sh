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
DEST="$PLATFORM_ROOT/dcp-env/engines/llama.cpp/$RUNTIME"
if [ -x "$DEST/llama-server" ] && "$DEST/llama-server" --version >/dev/null 2>&1; then
  echo "Compatible DCP llama.cpp runtime already installed: $RUNTIME"; exit 0
fi
if [ "$RUNTIME" = "macos-x64-sse42" ]; then
  BUILD_SCRIPT="$PLATFORM_ROOT/dcp-env/engines/llama.cpp/build-macos-generic.sh"
  if [ -f "$PLATFORM_ROOT/dcp-env/engines/llama.cpp/source/CMakeLists.txt" ] || [ -n "${DCP_LLAMA_SOURCE:-}" ]; then
    exec /bin/bash "$BUILD_SCRIPT"
  fi
  echo "This Intel CPU has no AVX2. The release must include the generic SSE4.2 runtime; refusing to download an incompatible AVX2 binary." >&2
  exit 1
fi
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
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
STAGE="$DEST.new"; rm -rf "$STAGE"; mkdir -p "$STAGE"; cp -R "$(dirname "$SERVER")/." "$STAGE/"
chmod +x "$STAGE/llama-server"; "$STAGE/llama-server" --version >/dev/null
PREVIOUS="$DEST.previous"; rm -rf "$PREVIOUS"
if [ -d "$DEST" ]; then mv "$DEST" "$PREVIOUS"; fi
if ! mv "$STAGE" "$DEST"; then
  [ ! -d "$PREVIOUS" ] || mv "$PREVIOUS" "$DEST"
  exit 1
fi
rm -rf "$PREVIOUS"
echo "Compatible DCP-managed llama.cpp installed to $DEST."
