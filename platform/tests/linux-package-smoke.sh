#!/usr/bin/env bash
set -euo pipefail

PACKAGE_ROOT="${1:-platform/dist/linux}"
PACKAGE_ROOT="$(cd "$PACKAGE_ROOT" && pwd)"
PLATFORM="$PACKAGE_ROOT/platform"
SMOKE_DATA="$(mktemp -d "${TMPDIR:-/tmp}/dcp-linux-package-smoke.XXXXXX")"
PORT="${DCP_SMOKE_PORT:-18765}"
SERVER_PID=""

cleanup() {
  if [[ -n "$SERVER_PID" ]]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
  rm -rf "$SMOKE_DATA"
}
trap cleanup EXIT

required_files=(
  "$PACKAGE_ROOT/DCP"
  "$PACKAGE_ROOT/Run-DCP.sh"
  "$PLATFORM/bootstrap/app.php"
  "$PLATFORM/config/app.php"
  "$PLATFORM/app/Core/Database.php"
  "$PLATFORM/app/Core/Crypto.php"
  "$PLATFORM/app/Controllers/ApiController.php"
  "$PLATFORM/public/router.php"
  "$PLATFORM/scripts/verify-runtime.php"
  "$PLATFORM/scripts/update-linux.php"
  "$PLATFORM/scripts/lib/UpdateManifest.php"
  "$PLATFORM/scripts/lib/LinuxUpdateTransaction.php"
  "$PLATFORM/scripts/extract-update-tar.py"
)
for required in "${required_files[@]}"; do
  [[ -s "$required" ]] || {
    echo "Linux package is missing required file: $required" >&2
    exit 1
  }
done

command -v python3 >/dev/null 2>&1 || {
  echo "Python 3 is required for verified Linux update archive extraction." >&2
  exit 1
}
[[ -x "$PACKAGE_ROOT/DCP" && -x "$PACKAGE_ROOT/Run-DCP.sh" ]] || {
  echo "Linux launcher files are not executable." >&2
  exit 1
}
if ldd "$PACKAGE_ROOT/DCP" | grep -q 'not found'; then
  echo "Linux DCP binary has unresolved shared-library dependencies." >&2
  ldd "$PACKAGE_ROOT/DCP" >&2
  exit 1
fi
php "$PLATFORM/scripts/verify-runtime.php"
php -l "$PLATFORM/scripts/update-linux.php" >/dev/null
php -l "$PLATFORM/scripts/lib/LinuxUpdateTransaction.php" >/dev/null
python3 -c 'import sys; compile(open(sys.argv[1], encoding="utf-8").read(), sys.argv[1], "exec")' "$PLATFORM/scripts/extract-update-tar.py"

mkdir -p "$SMOKE_DATA/data" "$SMOKE_DATA/storage"
DCP_USER_DATA_DIR="$SMOKE_DATA" php -S "127.0.0.1:$PORT" \
  -t "$PLATFORM/public" "$PLATFORM/public/router.php" \
  > "$SMOKE_DATA/server.log" 2>&1 &
SERVER_PID=$!

STATUS="$SMOKE_DATA/status.json"
ROOT_PAGE="$SMOKE_DATA/root.html"
for attempt in $(seq 1 30); do
  if curl --fail --silent "http://127.0.0.1:$PORT/" -o "$ROOT_PAGE" \
    && curl --fail --silent "http://127.0.0.1:$PORT/api/status" -o "$STATUS"; then
    break
  fi
  sleep 1
done

if [[ ! -s "$ROOT_PAGE" || ! -s "$STATUS" ]]; then
  echo "Linux package HTTP smoke did not return a root page and API status." >&2
  cat "$SMOKE_DATA/server.log" >&2 || true
  cat "$SMOKE_DATA/storage/logs/php-error.log" >&2 || true
  exit 1
fi

php -r '
$status = json_decode((string) file_get_contents($argv[1]), true);
if (!is_array($status) || ($status["ok"] ?? false) !== true) {
    fwrite(STDERR, "DCP /api/status smoke failed.\n");
    exit(1);
}
printf(
    "DCP Linux package HTTP smoke passed; PHP=%s; plugins=%s\n",
    $status["php"] ?? "unknown",
    $status["plugins"] ?? "unknown"
);
' "$STATUS"

if [[ -f "$SMOKE_DATA/storage/logs/php-error.log" ]] \
  && grep -E 'PHP (Warning|Fatal error)' "$SMOKE_DATA/storage/logs/php-error.log"; then
  exit 1
fi
