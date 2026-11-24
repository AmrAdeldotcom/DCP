#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if ! command -v python3 >/dev/null 2>&1; then
 if [[ $EUID -eq 0 ]]; then SUDO=(); else command -v sudo >/dev/null 2>&1 || { echo "sudo is required to install Python 3."; exit 1; }; SUDO=(sudo); fi
 if command -v apt-get >/dev/null 2>&1; then
  "${SUDO[@]}" apt-get update
  "${SUDO[@]}" apt-get install -y python3
 elif command -v dnf >/dev/null 2>&1; then
  "${SUDO[@]}" dnf install -y python3
 else
  echo "Python 3 is required for verified DCP package extraction."; exit 1
 fi
fi
if ! command -v php >/dev/null 2>&1 || ! php "$ROOT/scripts/verify-runtime.php" >/dev/null 2>&1; then
 if [[ $EUID -eq 0 ]]; then SUDO=(); else command -v sudo >/dev/null 2>&1 || { echo "sudo is required to install PHP runtime dependencies."; exit 1; }; SUDO=(sudo); fi
 if command -v apt-get >/dev/null 2>&1; then
  echo "Installing PHP CLI and required extensions only; Apache and database services are not installed or started."
  "${SUDO[@]}" apt-get update
  "${SUDO[@]}" apt-get install -y python3 php-cli php-curl php-intl php-mbstring php-sqlite3 php-xml php-gd php-zip
 elif command -v dnf >/dev/null 2>&1; then
  "${SUDO[@]}" dnf install -y python3 php-cli php-common php-curl php-intl php-mbstring php-pdo php-sqlite3 php-xml php-gd
 else
  echo "Install PHP 8.2+ CLI with curl, intl, mbstring, PDO_SQLite, sodium and fileinfo."; exit 1
 fi
fi
php "$ROOT/scripts/verify-runtime.php"
echo "DCP Linux runtime requirements ready."
