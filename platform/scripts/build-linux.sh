#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
command -v cc >/dev/null || { echo 'Install build-essential first.'; exit 1; }
pkg-config --exists gtk+-3.0 webkit2gtk-4.1 libsoup-3.0 || {
 echo 'Missing GTK3/WebKit2GTK 4.1/libsoup3 development packages.'
 echo 'Kali/Debian/Ubuntu: sudo apt install build-essential pkg-config libgtk-3-dev libwebkit2gtk-4.1-dev libsoup-3.0-dev'; exit 1
}
"$ROOT/scripts/install-runtime-linux.sh"
OUT="$ROOT/dist/linux"
mkdir -p "$OUT/platform"
cc "$ROOT/wrappers/linux/dcp-webview.c" -o "$OUT/DCP" $(pkg-config --cflags --libs gtk+-3.0 webkit2gtk-4.1 libsoup-3.0)
chmod +x "$OUT/DCP"
for item in app bootstrap config locales plugins public storage docs integrations scripts; do
 [ ! -e "$ROOT/$item" ] || cp -R "$ROOT/$item" "$OUT/platform/"
done
cp "$ROOT/README.md" "$OUT/platform/README-DCP.md"
cp "$ROOT/INSTALL.md" "$OUT/platform/INSTALL.md"
cat > "$OUT/Run-DCP.sh" <<'RUNNER'
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
PHP_BIN="${DCP_PHP_BIN:-php}"
"$PHP_BIN" "$ROOT/platform/scripts/verify-runtime.php"
exec "$ROOT/DCP"
RUNNER
chmod +x "$OUT/Run-DCP.sh"
echo "Linux package ready: $OUT."
