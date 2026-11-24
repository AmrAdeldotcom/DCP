#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/dcp-sign-test.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT HUP INT TERM
umask 077
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$WORK/private.pem" >/dev/null 2>&1
openssl pkey -in "$WORK/private.pem" -pubout -out "$WORK/public.pem" >/dev/null 2>&1
printf '{"schema":3,"test":"signing"}\n' > "$WORK/manifest.json"
PRIVATE_B64="$(python3 - "$WORK/private.pem" <<'PY'
import base64,sys
print(base64.b64encode(open(sys.argv[1],'rb').read()).decode('ascii'))
PY
)"
DCP_RELEASE_SIGNING_PRIVATE_KEY_B64="$PRIVATE_B64" DCP_UPDATE_PUBLIC_KEY_PATH="$WORK/public.pem" \
  bash "$ROOT/platform/scripts/sign-update-manifest.sh" "$WORK/manifest.json" "$WORK/manifest.json.sig"
openssl dgst -sha256 -verify "$WORK/public.pem" -signature "$WORK/manifest.json.sig" "$WORK/manifest.json"
printf 'tampered' >> "$WORK/manifest.json"
if openssl dgst -sha256 -verify "$WORK/public.pem" -signature "$WORK/manifest.json.sig" "$WORK/manifest.json" >/dev/null 2>&1; then
  echo 'Tampered manifest unexpectedly verified.' >&2
  exit 1
fi
echo 'PASS: detached RSA signature verifies and detects byte tampering'
