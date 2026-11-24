#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
  echo "Usage: sign-update-manifest.sh MANIFEST.json OUTPUT.sig" >&2
  exit 2
fi
MANIFEST="$1"
OUTPUT="$2"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PUBLIC_KEY="${DCP_UPDATE_PUBLIC_KEY_PATH:-$ROOT/platform/update/release-public.pem}"
: "${DCP_RELEASE_SIGNING_PRIVATE_KEY_B64:?DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 is required; no manifest was signed}"
[[ -s "$MANIFEST" ]] || { echo "Manifest file is missing or empty." >&2; exit 1; }
[[ -s "$PUBLIC_KEY" ]] || { echo "Pinned DCP release public key is missing." >&2; exit 1; }
command -v openssl >/dev/null 2>&1 || { echo "OpenSSL is required to sign release manifests." >&2; exit 1; }
command -v python3 >/dev/null 2>&1 || { echo "Python 3 is required to decode the protected signing secret." >&2; exit 1; }

WORK="$(mktemp -d "${TMPDIR:-/tmp}/dcp-sign-manifest.XXXXXX")"
chmod 0700 "$WORK"
cleanup() { rm -rf "$WORK"; }
trap cleanup EXIT HUP INT TERM
umask 077
python3 - "$WORK/private.pem" <<'PY'
import base64, os, pathlib, sys
encoded = os.environ.get('DCP_RELEASE_SIGNING_PRIVATE_KEY_B64', '').strip()
if not encoded:
    raise SystemExit('The DCP signing secret is empty.')
try:
    key = base64.b64decode(encoded, validate=True)
except Exception as exc:
    raise SystemExit('The DCP signing secret is not valid base64.') from exc
if b'-----BEGIN PRIVATE KEY-----' not in key and b'-----BEGIN RSA PRIVATE KEY-----' not in key:
    raise SystemExit('The DCP signing secret does not contain a PEM private key.')
pathlib.Path(sys.argv[1]).write_bytes(key)
PY
chmod 0600 "$WORK/private.pem"
openssl pkey -in "$WORK/private.pem" -check -noout >/dev/null
mkdir -p "$(dirname "$OUTPUT")"
openssl dgst -sha256 -sign "$WORK/private.pem" -out "$OUTPUT" "$MANIFEST"
openssl dgst -sha256 -verify "$PUBLIC_KEY" -signature "$OUTPUT" "$MANIFEST" >/dev/null
test -s "$OUTPUT"
echo "Signed and verified DCP update manifest: $OUTPUT"
