#!/bin/zsh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP="$ROOT/DCP.app"
CONTENTS="$APP/Contents"

mkdir -p "$CONTENTS/MacOS" "$CONTENTS/Resources"
cp "$ROOT/platform/wrappers/macos/Info.plist" "$CONTENTS/Info.plist"

/usr/bin/clang -arch x86_64 -arch arm64 -fobjc-arc \
  -framework Cocoa -framework WebKit \
  "$ROOT/platform/wrappers/macos/main.m" \
  -o "$CONTENTS/MacOS/DCP"
chmod +x "$CONTENTS/MacOS/DCP"

echo "Built universal DCP.app at $APP"
