#!/bin/zsh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
/usr/bin/clang -arch x86_64 -arch arm64 -fobjc-arc -framework Cocoa -framework WebKit "$ROOT/platform/wrappers/macos/main.m" -o "$ROOT/DCP.app/Contents/MacOS/DCP"
chmod +x "$ROOT/DCP.app/Contents/MacOS/DCP"
echo "Built $ROOT/DCP.app"
