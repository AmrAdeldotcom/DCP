#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
"$ROOT/platform/scripts/install-runtime-macos.sh"
