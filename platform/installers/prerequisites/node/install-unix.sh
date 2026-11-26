#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="24.22.3"
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"
case "$ARCH" in x86_64|amd64) ARCH="x64";; arm64|aarch64) ARCH="arm64";; *) echo "Unsupported Node.js architecture: $ARCH" >&2; exit 2;; esac
case "$OS" in darwin) PLATFORM="darwin"; EXT="tar.gz"; HASH="shasum -a 256";; linux) PLATFORM="linux"; EXT="tar.xz"; HASH="sha256sum";; *) echo "Unsupported OS: $OS" >&2; exit 2;; esac
ARCHIVE="node-v$VERSION-$PLATFORM-$ARCH.$EXT"; BASE="https://nodejs.org/dist/v$VERSION"
SYSTEM_ROOT="/usr/local/lib/nodejs"; DEST="$SYSTEM_ROOT/node-v$VERSION-$PLATFORM-$ARCH"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
echo "[1/6] Downloading official Node.js $VERSION ($ARCHIVE)"
curl --http1.1 -fL --retry 5 --retry-all-errors --retry-delay 2 --progress-bar "$BASE/$ARCHIVE" -o "$TMP/$ARCHIVE"
echo "[2/6] Downloading official SHA-256 manifest"; curl --http1.1 -fsSL "$BASE/SHASUMS256.txt" -o "$TMP/SHASUMS256.txt"
EXPECTED="$(awk -v f="$ARCHIVE" '$2==f {print $1}' "$TMP/SHASUMS256.txt")"; [ -n "$EXPECTED" ] || { echo "Official SHA-256 entry missing" >&2; exit 3; }
ACTUAL="$($HASH "$TMP/$ARCHIVE" | awk '{print $1}')"; [ "$EXPECTED" = "$ACTUAL" ] || { echo "Node.js archive SHA-256 mismatch" >&2; exit 4; }
echo "[3/6] SHA-256 verified; preparing system installation"; mkdir -p "$TMP/unpacked"
case "$EXT" in tar.gz) tar -xzf "$TMP/$ARCHIVE" -C "$TMP/unpacked";; tar.xz) tar -xJf "$TMP/$ARCHIVE" -C "$TMP/unpacked";; esac
SOURCE="$TMP/unpacked/node-v$VERSION-$PLATFORM-$ARCH"; [ -x "$SOURCE/bin/node" ] || { echo "Node binary missing after extraction" >&2; exit 5; }
echo "[4/6] Installing into $DEST (administrator approval may be requested)"
if [ "$OS" = "darwin" ]; then
  ADMIN_CMD="mkdir -p $SYSTEM_ROOT && rm -rf $DEST && cp -R $SOURCE $DEST"
  osascript -e "do shell script \"$ADMIN_CMD\" with administrator privileges"
else
  ADMIN_CMD="mkdir -p $SYSTEM_ROOT && rm -rf $DEST && cp -R $SOURCE $DEST"
  if command -v pkexec >/dev/null 2>&1; then pkexec /bin/bash -c "$ADMIN_CMD"; else sudo -n /bin/bash -c "$ADMIN_CMD" || { echo "Administrator approval is required; pkexec or an authenticated sudo session is needed." >&2; exit 6; }; fi
fi
echo "[5/6] Verifying system Node.js and npm"; "$DEST/bin/node" --version; "$DEST/bin/npm" --version
echo "[6/6] Node.js system installation verified: $DEST/bin/node"
