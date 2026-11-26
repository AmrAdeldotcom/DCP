#!/bin/bash
set -euo pipefail
if [ -x /Applications/Ollama.app/Contents/Resources/ollama ] || [ -x "$HOME/Applications/Ollama.app/Contents/Resources/ollama" ]; then echo "Ollama already installed."; exit 0; fi
command -v curl >/dev/null; command -v python3 >/dev/null
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
curl --http1.1 -fsSL --retry 4 --retry-all-errors --retry-delay 2 https://api.github.com/repos/ollama/ollama/releases/latest -o "$TMP/release.json"
python3 - "$TMP/release.json" "$TMP/url" "$TMP/digest" <<'PY'
import json,sys
d=json.load(open(sys.argv[1],encoding='utf-8'));a=next((x for x in d.get('assets',[]) if x.get('name')=='Ollama-darwin.zip'),None)
if not a or not a.get('digest','').startswith('sha256:'): raise SystemExit('Official Ollama macOS archive or SHA-256 digest is missing.')
open(sys.argv[2],'w').write(a['browser_download_url']);open(sys.argv[3],'w').write(a['digest'].split(':',1)[1])
PY
curl -fL --retry 4 --retry-all-errors "$(cat "$TMP/url")" -o "$TMP/ollama.zip"
[ "$(shasum -a 256 "$TMP/ollama.zip" | awk '{print $1}')" = "$(cat "$TMP/digest")" ] || { echo "Ollama SHA-256 verification failed." >&2; exit 1; }
unzip -q "$TMP/ollama.zip" -d "$TMP/unpacked"
APP="$(find "$TMP/unpacked" -maxdepth 4 -name Ollama.app -type d -print -quit)"
[ -n "$APP" ] || { echo "Ollama.app not found." >&2; exit 1; }
DEST="$HOME/Applications/Ollama.app"; mkdir -p "$HOME/Applications"; rm -rf "$DEST.new"; ditto "$APP" "$DEST.new"; rm -rf "$DEST"; mv "$DEST.new" "$DEST"
[ -x "$DEST/Contents/Resources/ollama" ] || { echo "Ollama executable missing." >&2; exit 1; }
echo "Ollama installed at $DEST; models remain in ~/.ollama."
