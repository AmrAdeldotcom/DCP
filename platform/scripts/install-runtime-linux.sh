#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if ! command -v php >/dev/null 2>&1 || ! php "$ROOT/scripts/verify-runtime.php" >/dev/null 2>&1; then
 if command -v apt-get >/dev/null 2>&1; then
  echo "Installing PHP CLI extensions only; Apache and database services are not installed or started."
  sudo apt-get update
  sudo apt-get install -y php-cli php-curl php-intl php-mbstring php-sqlite3 php-xml php-gd php-zip php-sodium php-sodium
 elif command -v dnf >/dev/null 2>&1; then
  sudo dnf install -y php-cli php-common php-curl php-intl php-mbstring php-pdo php-sqlite3 php-xml php-gd
 else
  echo "Install PHP 8.2+ CLI with curl, intl, mbstring, PDO_SQLite and sodium."; exit 1
 fi
fi
php "$ROOT/scripts/verify-runtime.php"
echo "DCP Linux runtime requirements ready."
