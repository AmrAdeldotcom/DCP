#!/bin/bash
set -euo pipefail

REPO="AmrAdeldotcom/DCP"
ASSET="DCP-macOS-universal.zip"
INSTALL_ROOT="${DCP_INSTALL_DIR:-$HOME/Applications/DCP}"
WORK_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/dcp-install.XXXXXX")"
ARCHIVE="$WORK_ROOT/$ASSET"
EXTRACT="$WORK_ROOT/extracted"
API="https://api.github.com/repos/$REPO/releases?per_page=10"

cleanup() { rm -rf "$WORK_ROOT"; }
trap cleanup EXIT

command -v curl >/dev/null 2>&1 || { echo "curl is required."; exit 1; }
command -v ditto >/dev/null 2>&1 || { echo "macOS ditto is required."; exit 1; }

echo "Finding the latest DCP macOS release..."
RELEASE_JSON="$WORK_ROOT/releases.json"
curl -fsSL -H "Accept: application/vnd.github+json" "$API" -o "$RELEASE_JSON"
DOWNLOAD_URL="$(/usr/bin/python3 - "$RELEASE_JSON" "$ASSET" <<'PY'
import json, sys
releases=json.load(open(sys.argv[1], encoding='utf-8'))
asset_name=sys.argv[2]
for release in releases:
    if release.get('draft'):
        continue
    for asset in release.get('assets', []):
        if asset.get('name') == asset_name:
            print(asset['browser_download_url'])
            raise SystemExit(0)
raise SystemExit("No published macOS package was found in the latest releases.")
PY
)"

mkdir -p "$EXTRACT" "$(dirname "$INSTALL_ROOT")"
echo "Downloading $ASSET..."
curl -fL --retry 3 "$DOWNLOAD_URL" -o "$ARCHIVE"
ditto -x -k "$ARCHIVE" "$EXTRACT"

PACKAGE="$EXTRACT"
if [[ ! -d "$PACKAGE/DCP.app" || ! -d "$PACKAGE/platform" ]]; then
    echo "Invalid DCP package: DCP.app and platform/ must be at the archive root."
    exit 1
fi

# Preserve user data and encryption material across upgrades.
SAVED="$WORK_ROOT/user-state"
mkdir -p "$SAVED"
if [[ -d "$INSTALL_ROOT/platform/data" ]]; then
    ditto "$INSTALL_ROOT/platform/data" "$SAVED/data"
fi
if [[ -d "$INSTALL_ROOT/platform/storage" ]]; then
    ditto "$INSTALL_ROOT/platform/storage" "$SAVED/storage"
fi

BACKUP=""
if [[ -d "$INSTALL_ROOT" ]]; then
    BACKUP="${INSTALL_ROOT}.backup.$(date +%Y%m%d-%H%M%S)"
    mv "$INSTALL_ROOT" "$BACKUP"
fi

if ! mkdir -p "$INSTALL_ROOT" || ! ditto "$PACKAGE/DCP.app" "$INSTALL_ROOT/DCP.app" || ! ditto "$PACKAGE/platform" "$INSTALL_ROOT/platform"; then
    echo "Install copy failed; restoring the previous installation."
    rm -rf "$INSTALL_ROOT"
    if [[ -n "$BACKUP" && -d "$BACKUP" ]]; then mv "$BACKUP" "$INSTALL_ROOT"; fi
    exit 1
fi

mkdir -p "$INSTALL_ROOT/platform/data" "$INSTALL_ROOT/platform/storage"
[[ ! -d "$SAVED/data" ]] || ditto "$SAVED/data" "$INSTALL_ROOT/platform/data"
[[ ! -d "$SAVED/storage" ]] || ditto "$SAVED/storage" "$INSTALL_ROOT/platform/storage"
chmod -R u+rwX "$INSTALL_ROOT"
echo "Checking the PHP CLI runtime..."
if ! "$INSTALL_ROOT/platform/scripts/install-runtime-macos.sh"; then
    echo "PHP runtime setup failed. Restoring the previous installation if available."
    rm -rf "$INSTALL_ROOT"
    if [[ -n "$BACKUP" && -d "$BACKUP" ]]; then mv "$BACKUP" "$INSTALL_ROOT"; fi
    exit 1
fi
echo "Launching DCP..."
open "$INSTALL_ROOT/DCP.app"
echo "DCP installed at: $INSTALL_ROOT"
