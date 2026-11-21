#!/bin/zsh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP="$ROOT/DCP.app"
CONTENTS="$APP/Contents"
BUILD_TMP="$(mktemp -d "${TMPDIR:-/tmp}/dcp-macos-build.XXXXXX")"
trap 'rm -rf "$BUILD_TMP"' EXIT

mkdir -p "$CONTENTS/MacOS" "$CONTENTS/Resources"
cp "$ROOT/platform/wrappers/macos/Info.plist" "$CONTENTS/Info.plist"

# Catalina-compatible Intel build; Apple Silicon starts at Big Sur.
 /usr/bin/clang -arch x86_64 -mmacosx-version-min=10.15 -fobjc-arc \
  -framework Cocoa -framework WebKit \
  "$ROOT/platform/wrappers/macos/main.m" \
  -o "$BUILD_TMP/DCP-x86_64"
/usr/bin/clang -arch arm64 -mmacosx-version-min=11.0 -fobjc-arc \
  -framework Cocoa -framework WebKit \
  "$ROOT/platform/wrappers/macos/main.m" \
  -o "$BUILD_TMP/DCP-arm64"
/usr/bin/lipo -create "$BUILD_TMP/DCP-x86_64" "$BUILD_TMP/DCP-arm64" \
  -output "$CONTENTS/MacOS/DCP"
chmod +x "$CONTENTS/MacOS/DCP"

echo "Built universal DCP.app at $APP"
