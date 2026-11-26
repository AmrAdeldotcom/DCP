#!/usr/bin/env bash
set -Eeuo pipefail
VERSION="24.22.3"
OS="$(uname -s | tr '[:upper:]' '[:lower:]')"
ARCH="$(uname -m)"
case "$ARCH" in x86_64|amd64) ARCH="x64";; arm64|aarch64) ARCH="arm64";; *) echo "Unsupported Node.js architecture: $ARCH" >&2; exit 2;; esac
case "$OS" in darwin) PLATFORM="darwin"; ARCHIVE="node-v$VERSION-$PLATFORM-$ARCH.tar.gz";; linux) PLATFORM="linux"; ARCHIVE="node-v$VERSION-$PLATFORM-$ARCH.tar.xz";; *) echo "Unsupported OS: $OS" >&2; exit 2;; esac
DEST="$HOME/.local/share/dcp/runtimes/node-v$VERSION"
BIN="$DEST/bin/node"
if [ -x "$BIN" ] && "$BIN" --version | grep -qx "v$VERSION"; then echo "Node.js v$VERSION already verified at $BIN"; exit 0; fi
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
BASE="https://nodejs.org/dist/v$VERSION"
echo "[1/5] Downloading official Node.js $VERSION archive: $ARCHIVE"
curl --http1.1 -fL --retry 5 --retry-all-errors --retry-delay 2 "$BASE/$ARCHIVE" -o "$TMP/$ARCHIVE"
echo "[2/5] Downloading official SHA-256 manifest"
curl --http1.1 -fsSL "$BASE/SHASUMS256.txt" -o "$TMP/SHASUMS256.txt"
EXPECTED="$(awk -v f="$ARCHIVE" '$2==f {print $1}' "$TMP/SHASUMS256.txt")"
[ -n "$EXPECTED" ] || { echo "Official SHA-256 entry missing for $ARCHIVE" >&2; exit 3; }
ACTUAL="$(shasum -a 256 "$TMP/$ARCHIVE" | awk '{print $1}')"
[ "$EXPECTED" = "$ACTUAL" ] || { echo "Node.js archive SHA-256 mismatch" >&2; exit 4; }
echo "[3/5] SHA-256 verified; extracting into user runtime directory"
mkdir -p "$TMP/unpacked" "$(dirname "$DEST")"
case "$ARCHIVE" in *.gz) tar -xzf "$TMP/$ARCHIVE" -C "$TMP/unpacked";; *.xz) tar -xJf "$TMP/$ARCHIVE" -C "$TMP/unpacked";; esac
SOURCE="$TMP/unpacked/node-v$VERSION-$PLATFORM-$ARCH"
[ -x "$SOURCE/bin/node" ] || { echo "Node binary missing after extraction" >&2; exit 5; }
STAGE="$DEST.stage-$$"; rm -rf "$STAGE"; mv "$SOURCE" "$STAGE"; rm -rf "$DEST"; mv "$STAGE" "$DEST"
mkdir -p "$HOME/.local/bin"
for name in node npm npx corepack; do [ ! -e "$DEST/bin/$name" ] || ln -sfn "$DEST/bin/$name" "$HOME/.local/bin/$name"; done
export PATH="$DEST/bin:$HOME/.local/bin:$PATH"
echo "[4/5] Verifying Node and npm"
"$BIN" --version
"$DEST/bin/npm" --version
echo "[5/5] Node.js runtime installation verified: $BIN"
