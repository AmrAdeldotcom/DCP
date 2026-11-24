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
mkdir -p "$OUT/platform"
touch "$OUT/DCP-UserData/Linux/data/.keep" "$OUT/DCP-UserData/Linux/storage/.keep"
cat > "$OUT/DCP-UserData/README.txt" <<'NOTICE'
DCP user data is device-local. Linux databases, encryption keys, logs, models and workspaces are stored under DCP-UserData/Linux. Never upload this directory.
NOTICE
cc "$ROOT/wrappers/linux/dcp-webview.c" -o "$OUT/DCP" $(pkg-config --cflags --libs gtk+-3.0 webkit2gtk-4.1)
chmod 0755 "$OUT/DCP"

for item in app bootstrap config locales plugins public docs integrations scripts update; do
  [[ ! -e "$ROOT/$item" ]] || cp -R "$ROOT/$item" "$OUT/platform/"
done
cp "$ROOT/version.json" "$OUT/platform/version.json"
VERSION="${DCP_VERSION:-$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1], encoding="utf-8"))["version"])' "$ROOT/version.json")}"
VERSION="${VERSION#v}"
if [[ "${GITHUB_REF_TYPE:-}" == "tag" ]]; then
  RELEASE_TAG="$GITHUB_REF_NAME"
else
  RELEASE_TAG="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1], encoding="utf-8")).get("releaseTag", ""))' "$ROOT/version.json")"
fi
python3 - "$OUT/platform/version.json" "$VERSION" "$RELEASE_TAG" <<'PY'
import json, re, sys
path, version, release_tag = sys.argv[1:]
channel = "alpha" if "-alpha" in version else ("beta" if "-beta" in version else "stable")
match = re.search(r"-(?:alpha|beta)\.(\d+)$", version)
build = match.group(1) if match else "1"
source = json.load(open(path, encoding="utf-8"))
revision = int(source.get("packageRevision", 1))
with open(path, "w", encoding="utf-8") as f:
    json.dump({"version": version, "releaseTag": release_tag, "channel": channel, "build": build, "packageRevision": revision}, f, indent=2)
    f.write("\n")
PY
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

# Signed update checks are fail-closed and never interrupt startup when offline.
# Applying an update requires an explicit local user confirmation.
UPDATE_LOG="$ROOT/DCP-UserData/Linux/storage/logs/updater.log"
mkdir -p "$(dirname "$UPDATE_LOG")"
if [[ "${DCP_DISABLE_UPDATE_CHECK:-0}" != "1" ]]; then
  if UPDATE_RESULT="$("$PHP_BIN" "$ROOT/platform/scripts/update-linux.php" --check 2>>"$UPDATE_LOG")"; then
    if python3 -c 'import json,sys; raise SystemExit(0 if json.load(sys.stdin).get("available") else 1)' <<<"$UPDATE_RESULT"; then
      UPDATE_VERSION="$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("version", "unknown"))' <<<"$UPDATE_RESULT")"
      UPDATE_STRATEGY="$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("strategy", "full"))' <<<"$UPDATE_RESULT")"
      ACCEPT_UPDATE=0
      if command -v zenity >/dev/null 2>&1; then
        zenity --question --title="DCP update available" --text="DCP $UPDATE_VERSION is available ($UPDATE_STRATEGY update). Apply it now?" && ACCEPT_UPDATE=1 || true
      elif command -v kdialog >/dev/null 2>&1; then
        kdialog --title "DCP update available" --yesno "DCP $UPDATE_VERSION is available ($UPDATE_STRATEGY update). Apply it now?" && ACCEPT_UPDATE=1 || true
      elif [[ -t 0 ]]; then
        if read -r -p "DCP $UPDATE_VERSION is available ($UPDATE_STRATEGY update). Apply now? [y/N] " ANSWER; then
          [[ "$ANSWER" =~ ^[Yy]$ ]] && ACCEPT_UPDATE=1
        fi
      else
        echo "DCP update $UPDATE_VERSION is available; run: php platform/scripts/update-linux.php --apply" >>"$UPDATE_LOG"
      fi
      if [[ "$ACCEPT_UPDATE" == "1" ]]; then
        if "$PHP_BIN" "$ROOT/platform/scripts/update-linux.php" --apply >>"$UPDATE_LOG" 2>&1; then
          "$PHP_BIN" "$ROOT/platform/scripts/verify-runtime.php"
        else
          echo "DCP update failed safely; continuing with the installed package. See $UPDATE_LOG" >&2
        fi
      fi
    fi
  else
    echo "DCP update check failed; continuing offline with the installed package." >>"$UPDATE_LOG"
  fi
fi

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
