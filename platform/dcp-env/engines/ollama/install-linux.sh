#!/usr/bin/env bash
set -Eeuo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLATFORM_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)/platform"
ARCH="$(uname -m)"
case "$ARCH" in
  x86_64|amd64) ASSET="ollama-linux-amd64.tar.zst" ;;
  aarch64|arm64) ASSET="ollama-linux-arm64.tar.zst" ;;
  *) echo "Unsupported Ollama Linux architecture: $ARCH" >&2; exit 1 ;;
esac
DEST="$PLATFORM_ROOT/dcp-env/engines/ollama/linux-x64"
[ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ] && DEST="$PLATFORM_ROOT/dcp-env/engines/ollama/linux-arm64"
if curl -fsS --max-time 2 http://127.0.0.1:11434/api/tags >/dev/null 2>&1; then
  echo "A healthy Ollama service is already running; DCP will reuse it."
  exit 0
fi
for tool in curl python3 tar sha256sum; do command -v "$tool" >/dev/null || { echo "$tool is required to install Ollama." >&2; exit 1; }; done
mkdir -p "$(dirname "$DEST")"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
RELEASE_JSON="$TMP/release.json"
echo "Checking the official Ollama release metadata for $ASSET..."
curl --http1.1 -fsSL --retry 4 --retry-all-errors --retry-delay 2 -H 'Accept: application/vnd.github+json' -H 'User-Agent: DCP-Runtime-Installer' 'https://api.github.com/repos/ollama/ollama/releases/latest' -o "$RELEASE_JSON"
python3 - "$RELEASE_JSON" "$ASSET" "$TMP/url" "$TMP/digest" "$TMP/version" <<'PYTHON'
import json,sys
release=json.load(open(sys.argv[1],encoding='utf-8'))
name=sys.argv[2]
asset=next((x for x in release.get('assets',[]) if x.get('name')==name),None)
if not asset or not str(asset.get('digest','')).startswith('sha256:'):
    raise SystemExit('Official Ollama Linux archive or SHA-256 digest is missing.')
open(sys.argv[3],'w',encoding='utf-8').write(asset['browser_download_url'])
open(sys.argv[4],'w',encoding='utf-8').write(asset['digest'].split(':',1)[1].lower())
open(sys.argv[5],'w',encoding='utf-8').write(str(release.get('tag_name','latest')))
PYTHON
URL="$(cat "$TMP/url")"; EXPECTED="$(cat "$TMP/digest")"; VERSION="$(cat "$TMP/version")"
ARCHIVE="$TMP/ollama.tar.zst"
echo "Downloading official Ollama $VERSION over HTTP/1.1 with retries..."
curl --http1.1 -fsSL --retry 6 --retry-all-errors --retry-delay 3 --connect-timeout 20 "$URL" -o "$ARCHIVE"
ACTUAL="$(sha256sum "$ARCHIVE" | awk '{print $1}')"
[ "$ACTUAL" = "$EXPECTED" ] || { echo "Ollama SHA-256 verification failed; refusing to install the archive." >&2; exit 1; }
echo "Archive verified. Extracting into DCP-managed engine storage..."
STAGE="$TMP/unpacked"; mkdir -p "$STAGE"
tar --zstd -xf "$ARCHIVE" -C "$STAGE"
[ -x "$STAGE/bin/ollama" ] || { echo "Verified archive does not contain bin/ollama." >&2; exit 1; }
NEW="$DEST.new"; PREVIOUS="$DEST.previous"
rm -rf "$NEW" "$PREVIOUS"; mkdir -p "$NEW"; cp -a "$STAGE/." "$NEW/"
"$NEW/bin/ollama" --version
if [ -d "$DEST" ]; then mv "$DEST" "$PREVIOUS"; fi
if ! mv "$NEW" "$DEST"; then
  [ ! -d "$PREVIOUS" ] || mv "$PREVIOUS" "$DEST"
  echo "Could not activate the verified DCP-managed Ollama runtime." >&2
  exit 1
fi
rm -rf "$PREVIOUS"
echo "DCP-managed Ollama $VERSION installed and executable verified at $DEST/bin/ollama."
