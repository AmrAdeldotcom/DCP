#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
command -v cc >/dev/null || { echo 'Install build-essential first.'; exit 1; }
command -v pkg-config >/dev/null || { echo 'Install pkg-config first.'; exit 1; }
pkg-config --exists gtk+-3.0 webkit2gtk-4.1 || {
 echo 'Missing GTK3/WebKit2GTK 4.1 development packages.'
 echo 'Debian/Kali/Ubuntu: sudo apt install build-essential pkg-config libgtk-3-dev libwebkit2gtk-4.1-dev'; exit 1
}
"$ROOT/scripts/install-runtime-linux.sh"
OUT="$ROOT/dist/linux"
rm -rf "$OUT"
mkdir -p "$OUT/platform/data" "$OUT/platform/storage"
touch "$OUT/platform/data/.keep" "$OUT/platform/storage/.keep"
cc "$ROOT/wrappers/linux/dcp-webview.c" -o "$OUT/DCP" $(pkg-config --cflags --libs gtk+-3.0 webkit2gtk-4.1)
chmod +x "$OUT/DCP"
for item in app bootstrap config data locales plugins public storage docs integrations scripts; do
 [ ! -e "$ROOT/$item" ] || cp -R "$ROOT/$item" "$OUT/platform/"
done
cp "$ROOT/README.md" "$OUT/platform/README-DCP.md"
cp "$ROOT/INSTALL.md" "$OUT/platform/INSTALL.md"
cp "$ROOT/public/assets/dcp-logo.png" "$OUT/DCP.png"
cat > "$OUT/Run-DCP.sh" <<'RUNNER'
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
mkdir -p "$ROOT/platform/data" "$ROOT/platform/storage"
chmod u+rwx "$ROOT/platform/data" "$ROOT/platform/storage"
PHP_BIN="${DCP_PHP_BIN:-php}"
"$PHP_BIN" "$ROOT/platform/scripts/install-runtime-linux.sh"
"$PHP_BIN" "$ROOT/platform/scripts/verify-runtime.php"
"$ROOT/Install-DCP-Launcher.sh"
exec "$ROOT/DCP"
RUNNER
chmod +x "$OUT/Run-DCP.sh"
cat > "$OUT/Install-DCP-Launcher.sh" <<'INSTALLER'
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
ICON_DIR="$HOME/.local/share/icons/hicolor/256x256/apps"
APP_DIR="$HOME/.local/share/applications"
mkdir -p "$ICON_DIR" "$APP_DIR"
install -m 644 "$ROOT/DCP.png" "$ICON_DIR/dcp.png"
cat > "$APP_DIR/dcp.desktop" <<EOF
[Desktop Entry]
Version=1.0
Type=Application
Name=DCP
Comment=Desktop Connector Platform
Exec="$ROOT/Run-DCP.sh"
Icon=$ICON_DIR/dcp.png
Terminal=false
Categories=Development;Utility;
StartupNotify=true
EOF
chmod 755 "$APP_DIR/dcp.desktop"
command -v update-desktop-database >/dev/null 2>&1 && update-desktop-database "$APP_DIR" || true
echo "DCP launcher installed. Find DCP in the Applications menu."
INSTALLER
chmod +x "$OUT/Install-DCP-Launcher.sh"
cat > "$OUT/DCP.desktop" <<'DESKTOP'
[Desktop Entry]
Version=1.0
Type=Application
Name=Install DCP Launcher
Comment=Install DCP into the Applications menu
Exec=/bin/bash -c 'DIR="$(dirname "$1")"; exec "$DIR/Install-DCP-Launcher.sh"' _ %k
Icon=application-x-executable
Terminal=true
Categories=Development;Utility;
DESKTOP
chmod 755 "$OUT/DCP.desktop"
echo "Linux package ready: $OUT."
