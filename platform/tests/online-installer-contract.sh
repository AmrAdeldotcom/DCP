#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SCRIPT="$ROOT/platform/Install-DCP-Online.sh"
bash -n "$SCRIPT"
grep -q 'DCP-Windows-update-v3.json' "$SCRIPT"
if grep -q 'DCP-Windows-update.json' "$SCRIPT"; then
  echo 'FAIL: installer still requires the obsolete Windows manifest name.' >&2
  exit 1
fi
grep -q 'DCP-macOS-Intel.zip.sha256' "$SCRIPT"
grep -q 'download_file "${DOWNLOAD_URL}.sha256" "$CHECKSUM"' "$SCRIPT"
grep -q 'macOS package SHA-256 verification failed' "$SCRIPT"
echo 'DCP_ONLINE_INSTALLER_CONTRACT_PASS'
