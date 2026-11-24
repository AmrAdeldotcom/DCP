#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
"$ROOT/scripts/install-runtime-macos.sh"
cd "$ROOT/.."
"$ROOT/wrappers/build-macos.sh"
codesign --force --deep --sign - DCP.app
codesign --verify --deep --strict DCP.app
echo "macOS native app built for $(uname -m). Keep DCP.app beside platform/."
