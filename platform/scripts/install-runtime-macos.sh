#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_BIN=""
for candidate in /usr/local/bin/php /opt/homebrew/bin/php; do
  if [ -x "$candidate" ]; then PHP_BIN="$candidate"; break; fi
done
if [ -z "$PHP_BIN" ] && command -v php >/dev/null 2>&1; then PHP_BIN="$(command -v php)"; fi
if [ -z "$PHP_BIN" ]; then
  if ! command -v brew >/dev/null 2>&1; then echo "PHP CLI missing. Install Homebrew: https://brew.sh"; exit 1; fi
  echo "Installing PHP CLI with Homebrew; no background services will be started."
  brew install php
  PHP_BIN="$(brew --prefix php)/bin/php"
fi
"$PHP_BIN" "$ROOT/scripts/verify-runtime.php"
echo "DCP runtime ready: $("$PHP_BIN" -r 'echo PHP_VERSION;')"
