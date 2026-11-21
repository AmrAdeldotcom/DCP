#!/bin/zsh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP="$ROOT/DCP.app"
CONTENTS="$APP/Contents"
BUILD_TMP="$(mktemp -d "${TMPDIR:-/tmp}/dcp-macos-build.XXXXXX")"
trap 'rm -rf "$BUILD_TMP"' EXIT

mkdir -p "$CONTENTS/MacOS" "$CONTENTS/Resources"
cp "$ROOT/platform/wrappers/macos/Info.plist" "$CONTENTS/Info.plist"
ICONSET="$BUILD_TMP/AppIcon.iconset"
mkdir -p "$ICONSET"
ICON_SOURCE="$ROOT/platform/public/assets/dcp-logo.png"
for spec in "16 16x16" "32 16x16@2x" "32 32x32" "64 32x32@2x" "128 128x128" "256 128x128@2x" "256 256x256" "512 256x256@2x" "512 512x512" "1024 512x512@2x"; do
  size="${spec%% *}"; name="${spec#* }"
  /usr/bin/sips -z "$size" "$size" "$ICON_SOURCE" --out "$ICONSET/icon_${name}.png" >/dev/null
 done
/usr/bin/iconutil -c icns "$ICONSET" -o "$CONTENTS/Resources/AppIcon.icns"

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
