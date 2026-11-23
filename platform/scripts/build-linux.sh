#!/bin/bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
command -v cc >/dev/null || { echo 'Install build-essential first.' >&2; exit 1; }
command -v pkg-config >/dev/null || { echo 'Install pkg-config first.' >&2; exit 1; }
pkg-config --exists gtk+-3.0 webkit2gtk-4.1 || {
  echo 'Missing GTK3/WebKit2GTK 4.1 development packages.' >&2
  echo 'Debian/Kali/Ubuntu: sudo apt install build-essential pkg-config libgtk-3-dev libwebkit2gtk-4.1-dev' >&2
  exit 1
}
"$ROOT/scripts/install-runtime-linux.sh"
for required in "$ROOT/wrappers/linux/dcp-webview.c" "$ROOT/README.md" "$ROOT/INSTALL.md" "$ROOT/public/assets/dcp-logo.png"; do
  [[ -s "$required" ]] || { echo "Required Linux build input is missing: $required" >&2; exit 1; }
done

OUT="$ROOT/dist/linux"
rm -rf "$OUT"
mkdir -p "$OUT/DCP-UserData/Linux/data" "$OUT/DCP-UserData/Linux/storage"
touch "$OUT/DCP-UserData/Linux/data/.keep" "$OUT/DCP-UserData/Linux/storage/.keep"
cat > "$OUT/DCP-UserData/README.txt" <<'NOTICE'
DCP user data is device-local. Linux databases, encryption keys, logs, models and workspaces are stored under DCP-UserData/Linux. Never upload this directory.
NOTICE
cc "$ROOT/wrappers/linux/dcp-webview.c" -o "$OUT/DCP" $(pkg-config --cflags --libs gtk+-3.0 webkit2gtk-4.1)
chmod 0755 "$OUT/DCP"

for item in app bootstrap config locales plugins public docs integrations scripts; do
  [[ ! -e "$ROOT/$item" ]] || cp -R "$ROOT/$item" "$OUT/platform/"
done
# Include runtime manifests/installers but never developer caches, built engines or virtual environments.
if [[ -d "$ROOT/dcp-env" ]]; then
  mkdir -p "$OUT/platform/dcp-env"
  tar -C "$ROOT/dcp-env" \
    --exclude='./build' --exclude='./runtime' \
    --exclude='./engines/llama.cpp/source' --exclude='./engines/llama.cpp/linux-x64' \
    --exclude='./engines/llama.cpp/macos-*' --exclude='./engines/llama.cpp/windows-x64' \
    --exclude='./engines/ollama/linux-x64' --exclude='./engines/ollama/macos-*' \
    --exclude='./engines/ollama/windows-x64' \
    --exclude='./python/*/venv' --exclude='./python/*/.venv' \
    -cf - . | tar -C "$OUT/platform/dcp-env" -xf -
fi
# Never ship the developer machine SQLite database, encryption keys or local logs.
cp "$ROOT/README.md" "$OUT/platform/README-DCP.md"
cp "$ROOT/INSTALL.md" "$OUT/platform/INSTALL.md"
cp "$ROOT/public/assets/dcp-logo.png" "$OUT/DCP.png"

cat > "$OUT/Run-DCP.sh" <<'RUNNER'
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
mkdir -p "$ROOT/DCP-UserData/Linux/data" "$ROOT/DCP-UserData/Linux/storage"
chmod 0700 "$ROOT/DCP-UserData/Linux" "$ROOT/DCP-UserData/Linux/data" "$ROOT/DCP-UserData/Linux/storage"
"$ROOT/platform/scripts/install-runtime-linux.sh"
PHP_BIN="${DCP_PHP_BIN:-php}"
"$PHP_BIN" "$ROOT/platform/scripts/verify-runtime.php"
"$ROOT/Install-DCP-Launcher.sh"
exec "$ROOT/DCP"
RUNNER
chmod 0755 "$OUT/Run-DCP.sh"

cat > "$OUT/Install-DCP-Launcher.sh" <<'INSTALLER'
#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
ICON_DIR="$HOME/.local/share/icons/hicolor/256x256/apps"
APP_DIR="$HOME/.local/share/applications"
mkdir -p "$ICON_DIR" "$APP_DIR"
install -m 0644 "$ROOT/DCP.png" "$ICON_DIR/dcp.png"
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
chmod 0755 "$APP_DIR/dcp.desktop"
command -v update-desktop-database >/dev/null 2>&1 && update-desktop-database "$APP_DIR" || true
echo "DCP launcher installed. Find DCP in the Applications menu."
INSTALLER
chmod 0755 "$OUT/Install-DCP-Launcher.sh"

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
chmod 0644 "$OUT/DCP.desktop"

echo "Linux package ready: $OUT"
