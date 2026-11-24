#!/bin/zsh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP="$ROOT/DCP.app"
CONTENTS="$APP/Contents"
BUILD_TMP="$(mktemp -d "${TMPDIR:-/tmp}/dcp-macos-build.XXXXXX")"
trap 'rm -rf "$BUILD_TMP"' EXIT

mkdir -p "$CONTENTS/MacOS" "$CONTENTS/Resources"
cp "$ROOT/platform/wrappers/macos/Info.plist" "$CONTENTS/Info.plist"
# Keep the native bundle version and package revision sourced from the same
# manifest used by the platform package and updater.
APP_VERSION="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1], encoding="utf-8"))["version"])' "$ROOT/platform/version.json")"
APP_BUILD="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1], encoding="utf-8"))["build"])' "$ROOT/platform/version.json")"
APP_REVISION="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1], encoding="utf-8"))["packageRevision"])' "$ROOT/platform/version.json")"
/usr/libexec/PlistBuddy -c "Set :CFBundleShortVersionString $APP_VERSION" "$CONTENTS/Info.plist"
/usr/libexec/PlistBuddy -c "Set :CFBundleVersion $APP_BUILD" "$CONTENTS/Info.plist"
/usr/libexec/PlistBuddy -c "Add :DCPPackageRevision integer $APP_REVISION" "$CONTENTS/Info.plist" || /usr/libexec/PlistBuddy -c "Set :DCPPackageRevision $APP_REVISION" "$CONTENTS/Info.plist"
ICONSET="$BUILD_TMP/AppIcon.iconset"
mkdir -p "$ICONSET"
ICON_SOURCE="$ROOT/platform/public/assets/dcp-logo.png"
for spec in "16 16x16" "32 16x16@2x" "32 32x32" "64 32x32@2x" "128 128x128" "256 128x128@2x" "256 256x256" "512 256x256@2x" "512 512x512" "1024 512x512@2x"; do
  size="${spec%% *}"; name="${spec#* }"
  /usr/bin/sips -z "$size" "$size" "$ICON_SOURCE" --out "$ICONSET/icon_${name}.png" >/dev/null
 done
/usr/bin/iconutil -c icns "$ICONSET" -o "$CONTENTS/Resources/AppIcon.icns"

# Build exactly one native architecture. Universal/lipo builds are intentionally
# avoided so each package contains only the runtime matching its host.
ARCH="${DCP_MACOS_ARCH:-$(uname -m)}"
case "$ARCH" in
  x86_64|arm64) ;;
  *) echo "Unsupported DCP_MACOS_ARCH: $ARCH" >&2; exit 2 ;;
esac
/usr/bin/clang -arch "$ARCH" -mmacosx-version-min=12.0 -fobjc-arc \
  -framework Cocoa -framework WebKit \
  "$ROOT/platform/wrappers/macos/main.m" \
  -o "$CONTENTS/MacOS/DCP"
chmod 0755 "$CONTENTS/MacOS/DCP"

echo "Built and ad-hoc signed native $ARCH DCP.app at $APP"
