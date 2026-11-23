#!/bin/bash
set -euo pipefail

PLATFORM_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PROJECT_ROOT="$(cd "$PLATFORM_ROOT/.." && pwd)"
if ! printenv DCP_ROOT >/dev/null 2>&1; then
  DCP_ROOT="$PROJECT_ROOT"
fi
export DCP_ROOT

ARCH="$(uname -m)"
case "$ARCH" in
  arm64) RUNTIME_ARCH="macos-arm64" ;;
  x86_64) RUNTIME_ARCH="macos-x64" ;;
  *)
    echo "Unsupported macOS architecture: $ARCH" >&2
    exit 2
    ;;
esac

# DCP_PHP_SYSTEM_CANDIDATES is an optional colon-separated override for isolated tests.
SYSTEM_CANDIDATES=()
if [ "${DCP_PHP_SYSTEM_CANDIDATES+x}" = x ]; then
  IFS=: read -r -a SYSTEM_CANDIDATES <<< "$DCP_PHP_SYSTEM_CANDIDATES"
else
  SYSTEM_PHP="$(command -v php || true)"
  if [ -n "$SYSTEM_PHP" ]; then
    SYSTEM_CANDIDATES+=("$SYSTEM_PHP")
  fi
  SYSTEM_CANDIDATES+=("/usr/local/bin/php" "/opt/homebrew/bin/php")
fi

for PHP_BIN in "${SYSTEM_CANDIDATES[@]-}"; do
  [ -n "$PHP_BIN" ] && [ -x "$PHP_BIN" ] || continue
  if "$PHP_BIN" "$PLATFORM_ROOT/scripts/verify-runtime.php"; then
    echo "DCP runtime ready; reusing compatible system PHP: $PHP_BIN"
    exit 0
  fi
done

MANAGED_ROOT="$PLATFORM_ROOT/dcp-env/runtime/php/$RUNTIME_ARCH"
MANAGED_CANDIDATES=("$MANAGED_ROOT/bin/php" "$MANAGED_ROOT/php")
for PHP_BIN in "${MANAGED_CANDIDATES[@]}"; do
  [ -x "$PHP_BIN" ] || continue

  CHECKSUM_FILE="$MANAGED_ROOT/php.sha256"
  if [ ! -s "$CHECKSUM_FILE" ]; then
    echo "DCP-managed PHP checksum file is missing: $CHECKSUM_FILE" >&2
    continue
  fi

  EXPECTED_SHA256="$(tr -d '[:space:]' < "$CHECKSUM_FILE")"
  if [[ ! "$EXPECTED_SHA256" =~ ^[a-f0-9]{64}$ ]]; then
    echo "DCP-managed PHP checksum file is invalid: $CHECKSUM_FILE" >&2
    continue
  fi

  ACTUAL_SHA256="$(shasum -a 256 "$PHP_BIN" | awk '{print $1}')"
  if [ "$ACTUAL_SHA256" != "$EXPECTED_SHA256" ]; then
    echo "DCP-managed PHP SHA-256 verification failed: $PHP_BIN" >&2
    continue
  fi

  PHP_INI="$MANAGED_ROOT/php.ini"
  PHP_INI_SCAN_DIR="$MANAGED_ROOT/conf.d"
  if [ ! -s "$PHP_INI" ] || [ ! -d "$PHP_INI_SCAN_DIR" ]; then
    echo "DCP-managed PHP configuration is incomplete: $MANAGED_ROOT" >&2
    continue
  fi

  if PHPRC="$PHP_INI" PHP_INI_SCAN_DIR="$PHP_INI_SCAN_DIR" "$PHP_BIN" "$PLATFORM_ROOT/scripts/verify-runtime.php"; then
    echo "DCP runtime ready; verified managed PHP: $PHP_BIN"
    exit 0
  fi
done

echo "No compatible PHP 8.2+ runtime with DCP extensions was found." >&2
echo "DCP did not install or modify Homebrew, system PHP, Apache, or any background service." >&2
echo "Install a compatible system PHP or restore the verified DCP-managed runtime under platform/dcp-env/runtime/php/$RUNTIME_ARCH." >&2
exit 1
