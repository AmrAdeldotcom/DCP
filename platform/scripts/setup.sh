#!/bin/bash
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
case "$(uname -s)" in
 Darwin) exec "$HERE/install-runtime-macos.sh" ;;
 Linux) exec "$HERE/install-runtime-linux.sh" ;;
 *) echo 'Use install-runtime-windows.ps1 from PowerShell.'; exit 2 ;;
esac
