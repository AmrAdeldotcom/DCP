#!/usr/bin/env bash
set -Eeuo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLATFORM_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)/platform"
DEST="$PLATFORM_ROOT/dcp-env/engines/llama.cpp/linux-x64"
command -v curl >/dev/null || command -v wget >/dev/null || { echo "curl or wget is required." >&2; exit 1; }
download() {
  local url="$1" output="$2"
  if command -v curl >/dev/null; then curl -fL --retry 2 "$url" -o "$output"
  else wget -q --tries=2 "$url" -O "$output"; fi
}
command -v python3 >/dev/null || { echo "Python 3 is required." >&2; exit 1; }
command -v tar >/dev/null || { echo "tar is required." >&2; exit 1; }
if [ -x "$DEST/llama-server" ] && "$DEST/llama-server" --version >/dev/null 2>&1; then
  echo "Compatible DCP-managed llama.cpp runtime already installed."
  exit 0
fi
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
download "https://api.github.com/repos/ggml-org/llama.cpp/releases?per_page=5" "$TMP/releases.json"
python3 - "$TMP/releases.json" "$TMP/url" "$TMP/digest" <<'PYTHON'
import json, re, sys
releases=json.load(open(sys.argv[1],encoding="utf-8"))
asset=None
for release in releases:
    for candidate in release.get("assets",[]):
        name=candidate.get("name","")
        if re.fullmatch(r"llama-b[0-9]+-bin-ubuntu-x64\.tar\.gz",name) and candidate.get("digest","").startswith("sha256:"):
            asset=candidate
            break
    if asset: break
if not asset:
    raise SystemExit("No official Ubuntu x64 llama.cpp archive with a SHA-256 digest was found.")
open(sys.argv[2],"w",encoding="utf-8").write(asset["browser_download_url"])
open(sys.argv[3],"w",encoding="utf-8").write(asset["digest"].split(":",1)[1])
PYTHON
download "$(cat "$TMP/url")" "$TMP/llama.tar.gz"
EXPECTED="$(cat "$TMP/digest")"
ACTUAL="$(sha256sum "$TMP/llama.tar.gz" | awk '{print $1}')"
[ "$EXPECTED" = "$ACTUAL" ] || { echo "llama.cpp SHA-256 verification failed." >&2; exit 1; }
mkdir "$TMP/unpacked"
tar -xzf "$TMP/llama.tar.gz" -C "$TMP/unpacked"
SERVER="$(find "$TMP/unpacked" -type f -name llama-server -print -quit)"
[ -n "$SERVER" ] || { echo "llama-server was not found in the official Ubuntu archive." >&2; exit 1; }
STAGE="$DEST.new"
rm -rf "$STAGE"
mkdir -p "$STAGE"
cp -R "$(dirname "$SERVER")/." "$STAGE/"
chmod 0755 "$STAGE/llama-server"
if ldd "$STAGE/llama-server" | grep -q "not found"; then
  ldd "$STAGE/llama-server" >&2
  echo "llama.cpp archive has unresolved shared-library dependencies." >&2
  exit 1
fi
"$STAGE/llama-server" --version
PREVIOUS="$DEST.previous"
rm -rf "$PREVIOUS"
if [ -d "$DEST" ]; then mv "$DEST" "$PREVIOUS"; fi
if ! mv "$STAGE" "$DEST"; then
  [ ! -d "$PREVIOUS" ] || mv "$PREVIOUS" "$DEST"
  exit 1
fi
rm -rf "$PREVIOUS"
echo "DCP-managed llama.cpp installed to $DEST."
