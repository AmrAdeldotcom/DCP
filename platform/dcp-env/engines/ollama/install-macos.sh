#!/bin/bash
set -euo pipefail
PLATFORM_ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
ARCH="$(uname -m)"
DEST="$PLATFORM_ROOT/dcp-env/engines/ollama/macos-x64"
[ "$ARCH" = "arm64" ] && DEST="$PLATFORM_ROOT/dcp-env/engines/ollama/macos-arm64"
if curl -fsS --max-time 2 http://127.0.0.1:11434/api/tags >/dev/null 2>&1; then
  echo "A healthy Ollama service is already running; DCP will reuse it."
  exit 0
fi
command -v curl >/dev/null || { echo "curl is required." >&2; exit 1; }
command -v python3 >/dev/null || { echo "Python 3 is required." >&2; exit 1; }
mkdir -p "$DEST"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
curl -fsSL 'https://api.github.com/repos/ollama/ollama/releases/latest' -o "$TMP/release.json"
python3 - "$TMP/release.json" "$TMP/url" "$TMP/digest" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'))
a=next((x for x in d.get('assets',[]) if x.get('name')=='Ollama-darwin.zip'),None)
if not a or not a.get('digest','').startswith('sha256:'): raise SystemExit('Official Ollama macOS asset or SHA-256 digest is missing.')
open(sys.argv[2],'w').write(a['browser_download_url'])
open(sys.argv[3],'w').write(a['digest'].split(':',1)[1])
PY
curl -fL --retry 2 "$(cat "$TMP/url")" -o "$TMP/ollama.zip"
EXPECTED="$(cat "$TMP/digest")"; ACTUAL="$(shasum -a 256 "$TMP/ollama.zip" | awk '{print $1}')"
[ "$ACTUAL" = "$EXPECTED" ] || { echo "Ollama SHA-256 verification failed." >&2; exit 1; }
unzip -q "$TMP/ollama.zip" -d "$TMP/unpacked"
APP="$(find "$TMP/unpacked" -maxdepth 3 -name Ollama.app -type d -print -quit)"
[ -n "$APP" ] || { echo "Ollama.app not found in official archive." >&2; exit 1; }
rm -rf "$DEST.new"; mkdir -p "$DEST.new"; ditto "$APP" "$DEST.new/Ollama.app"
rm -rf "$DEST"; mv "$DEST.new" "$DEST"
echo "DCP-managed Ollama installed to $DEST; system Ollama was not changed."
