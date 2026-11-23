#!/bin/bash
set -euo pipefail

REPO="AmrAdeldotcom/DCP"
API="https://api.github.com/repos/$REPO/releases?per_page=10"
INSTALL_ROOT="${DCP_INSTALL_DIR:-$HOME/Applications/DCP}"
WORK_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/dcp-install.XXXXXX")"
BACKUP=""
INSTALL_STARTED=0
INSTALL_COMPLETE=0
cleanup() {
  if [[ "$INSTALL_STARTED" == "1" && "$INSTALL_COMPLETE" != "1" ]]; then
    rm -rf "$INSTALL_ROOT"
    if [[ -n "$BACKUP" && -d "$BACKUP" ]]; then
      mv "$BACKUP" "$INSTALL_ROOT"
      echo "Previous DCP installation restored." >&2
    fi
  fi
  rm -rf "$WORK_ROOT"
}
trap cleanup EXIT

command -v curl >/dev/null 2>&1 || { echo "curl is required." >&2; exit 1; }
command -v python3 >/dev/null 2>&1 || { echo "Python 3 is required to read GitHub release metadata." >&2; exit 1; }

case "$(uname -s)" in
  Darwin)
    ASSET="DCP-macOS-universal.zip"
    ARCHIVE="$WORK_ROOT/$ASSET"
    EXTRACT="$WORK_ROOT/extracted"
    command -v ditto >/dev/null 2>&1 || { echo "macOS ditto is required." >&2; exit 1; }

    echo "Finding the latest DCP macOS release..."
    curl -fsSL -H "Accept: application/vnd.github+json" "$API" -o "$WORK_ROOT/releases.json"
    DOWNLOAD_URL="$(python3 - "$WORK_ROOT/releases.json" "$ASSET" <<'PY'
import json, sys
required = {"DCP-Windows-x64.zip", "DCP-macOS-universal.zip", "DCP-Linux-x64.tar.gz", "DCP-Linux-x64.tar.gz.sha256", "DCP-Windows-update.json"}
for release in json.load(open(sys.argv[1], encoding="utf-8")):
    if release.get("draft"):
        continue
    assets = {a["name"]: a["browser_download_url"] for a in release.get("assets", [])}
    if required.issubset(assets) and sys.argv[2] in assets:
        print(assets[sys.argv[2]])
        raise SystemExit(0)
raise SystemExit("No complete synchronized DCP release containing Windows, macOS and Linux packages was found.")
PY
)"
    mkdir -p "$EXTRACT" "$(dirname "$INSTALL_ROOT")"
    curl -fL --retry 3 "$DOWNLOAD_URL" -o "$ARCHIVE"
    ditto -x -k "$ARCHIVE" "$EXTRACT"
    PACKAGE="$EXTRACT"
    [[ -d "$PACKAGE/DCP.app" && -d "$PACKAGE/platform" ]] || { echo "Invalid macOS package." >&2; exit 1; }

    SAVED="$WORK_ROOT/user-state"
    mkdir -p "$SAVED"
    [[ ! -d "$INSTALL_ROOT/DCP-UserData" ]] || ditto "$INSTALL_ROOT/DCP-UserData" "$SAVED/DCP-UserData"
    [[ ! -d "$INSTALL_ROOT/platform/data" ]] || ditto "$INSTALL_ROOT/platform/data" "$SAVED/legacy-data"
    [[ ! -d "$INSTALL_ROOT/platform/storage" ]] || ditto "$INSTALL_ROOT/platform/storage" "$SAVED/legacy-storage"
    if [[ -d "$INSTALL_ROOT" ]]; then
      BACKUP="${INSTALL_ROOT}.backup.$(date +%Y%m%d-%H%M%S)"
      mv "$INSTALL_ROOT" "$BACKUP"
    fi
    INSTALL_STARTED=1
    mkdir -p "$INSTALL_ROOT"
    ditto "$PACKAGE/DCP.app" "$INSTALL_ROOT/DCP.app"
    ditto "$PACKAGE/platform" "$INSTALL_ROOT/platform"
    if [[ -d "$SAVED/DCP-UserData" ]]; then ditto "$SAVED/DCP-UserData" "$INSTALL_ROOT/DCP-UserData"; fi
    DATA_DIR="$INSTALL_ROOT/DCP-UserData/macOS/data"
    STORAGE_DIR="$INSTALL_ROOT/DCP-UserData/macOS/storage"
    mkdir -p "$DATA_DIR" "$STORAGE_DIR"
    if [[ ! -f "$DATA_DIR/dcp.sqlite" && -d "$SAVED/legacy-data" ]]; then ditto "$SAVED/legacy-data" "$DATA_DIR"; fi
    if [[ ! -f "$STORAGE_DIR/keys/master.key" && -d "$SAVED/legacy-storage" ]]; then ditto "$SAVED/legacy-storage" "$STORAGE_DIR"; fi
    DB_SOURCE="$SAVED/DCP-UserData/macOS/data/dcp.sqlite"
    [[ -f "$DB_SOURCE" ]] || DB_SOURCE="$SAVED/legacy-data/dcp.sqlite"
    KEY_SOURCE="$SAVED/DCP-UserData/macOS/storage/keys/master.key"
    [[ -f "$KEY_SOURCE" ]] || KEY_SOURCE="$SAVED/legacy-storage/keys/master.key"
    if [[ -f "$DB_SOURCE" ]] && ! cmp -s "$DB_SOURCE" "$DATA_DIR/dcp.sqlite"; then echo "SQLite migration verification failed." >&2; exit 1; fi
    if [[ -f "$KEY_SOURCE" ]] && ! cmp -s "$KEY_SOURCE" "$STORAGE_DIR/keys/master.key"; then echo "Master key migration verification failed." >&2; exit 1; fi
    chmod -R u+rwX "$INSTALL_ROOT/DCP-UserData"
    chmod -R u+rwX "$INSTALL_ROOT"
    "$INSTALL_ROOT/platform/scripts/install-runtime-macos.sh"
    INSTALL_COMPLETE=1
    open "$INSTALL_ROOT/DCP.app"
    echo "DCP macOS installed at: $INSTALL_ROOT"
    ;;

  Linux)
    ASSET="DCP-Linux-x64.tar.gz"
    ARCHIVE="$WORK_ROOT/$ASSET"
    CHECKSUM="$WORK_ROOT/$ASSET.sha256"
    EXTRACT="$WORK_ROOT/extracted"
    [[ "$(uname -m)" == "x86_64" ]] || { echo "This Linux release currently supports x86_64 only." >&2; exit 1; }
    command -v tar >/dev/null 2>&1 || { echo "tar is required." >&2; exit 1; }
    command -v sha256sum >/dev/null 2>&1 || { echo "sha256sum is required." >&2; exit 1; }
    command -v apt-get >/dev/null 2>&1 || { echo "Ubuntu/Debian apt-get is required for DCP Linux prerequisites." >&2; exit 1; }

    echo "Finding the latest DCP Linux release..."
    curl -fsSL -H "Accept: application/vnd.github+json" "$API" -o "$WORK_ROOT/releases.json"
    python3 - "$WORK_ROOT/releases.json" "$ASSET" "$ASSET.sha256" "$WORK_ROOT/asset-urls.txt" <<'PY'
import json, sys
required = {"DCP-Windows-x64.zip", "DCP-macOS-universal.zip", "DCP-Linux-x64.tar.gz", "DCP-Linux-x64.tar.gz.sha256", "DCP-Windows-update.json"}
for release in json.load(open(sys.argv[1], encoding="utf-8")):
    if release.get("draft"):
        continue
    assets = {a["name"]: a["browser_download_url"] for a in release.get("assets", [])}
    if required.issubset(assets):
        with open(sys.argv[4], "w", encoding="utf-8") as f:
            f.write(assets[sys.argv[2]] + "\n" + assets[sys.argv[3]] + "\n")
        break
else:
    raise SystemExit("No complete synchronized DCP release containing Windows, macOS and Linux packages was found.")
PY
    mapfile -t URLS < "$WORK_ROOT/asset-urls.txt"
    mkdir -p "$EXTRACT" "$(dirname "$INSTALL_ROOT")"
    curl -fL --retry 3 "${URLS[0]}" -o "$ARCHIVE"
    curl -fL --retry 3 "${URLS[1]}" -o "$CHECKSUM"
    EXPECTED="$(awk '{print $1}' "$CHECKSUM" | head -n1)"
    ACTUAL="$(sha256sum "$ARCHIVE" | awk '{print $1}')"
    [[ "$EXPECTED" =~ ^[a-fA-F0-9]{64}$ && "$ACTUAL" == "$EXPECTED" ]] || { echo "Linux package SHA-256 verification failed." >&2; exit 1; }
    tar -xzf "$ARCHIVE" -C "$EXTRACT"
    PACKAGE="$EXTRACT"
    [[ -x "$PACKAGE/DCP" && -x "$PACKAGE/Run-DCP.sh" && -d "$PACKAGE/platform" && -d "$PACKAGE/DCP-UserData/Linux/data" && -d "$PACKAGE/DCP-UserData/Linux/storage" ]] || { echo "Invalid Linux package: launcher, platform or per-device user-data directories are missing." >&2; exit 1; }

    SAVED="$WORK_ROOT/user-state"
    mkdir -p "$SAVED"
    [[ ! -d "$INSTALL_ROOT/DCP-UserData" ]] || cp -a "$INSTALL_ROOT/DCP-UserData" "$SAVED/DCP-UserData"
    [[ ! -d "$INSTALL_ROOT/platform/data" ]] || cp -a "$INSTALL_ROOT/platform/data" "$SAVED/legacy-data"
    [[ ! -d "$INSTALL_ROOT/platform/storage" ]] || cp -a "$INSTALL_ROOT/platform/storage" "$SAVED/legacy-storage"

    if [[ -d "$INSTALL_ROOT" ]]; then
      BACKUP="${INSTALL_ROOT}.backup.$(date +%Y%m%d-%H%M%S)"
      mv "$INSTALL_ROOT" "$BACKUP"
    fi
    INSTALL_STARTED=1
    mkdir -p "$INSTALL_ROOT"
    cp -a "$PACKAGE/." "$INSTALL_ROOT/"
    [[ ! -d "$SAVED/DCP-UserData" ]] || cp -a "$SAVED/DCP-UserData" "$INSTALL_ROOT/"
    DATA_DIR="$INSTALL_ROOT/DCP-UserData/Linux/data"
    STORAGE_DIR="$INSTALL_ROOT/DCP-UserData/Linux/storage"
    mkdir -p "$DATA_DIR" "$STORAGE_DIR"
    if [[ ! -f "$DATA_DIR/dcp.sqlite" && -d "$SAVED/legacy-data" ]]; then cp -a "$SAVED/legacy-data/." "$DATA_DIR/"; fi
    if [[ ! -f "$STORAGE_DIR/keys/master.key" && -d "$SAVED/legacy-storage" ]]; then cp -a "$SAVED/legacy-storage/." "$STORAGE_DIR/"; fi
    DB_SOURCE="$SAVED/DCP-UserData/Linux/data/dcp.sqlite"
    [[ -f "$DB_SOURCE" ]] || DB_SOURCE="$SAVED/legacy-data/dcp.sqlite"
    KEY_SOURCE="$SAVED/DCP-UserData/Linux/storage/keys/master.key"
    [[ -f "$KEY_SOURCE" ]] || KEY_SOURCE="$SAVED/legacy-storage/keys/master.key"
    if [[ -f "$DB_SOURCE" ]] && ! cmp -s "$DB_SOURCE" "$DATA_DIR/dcp.sqlite"; then echo "SQLite migration verification failed." >&2; exit 1; fi
    if [[ -f "$KEY_SOURCE" ]] && ! cmp -s "$KEY_SOURCE" "$STORAGE_DIR/keys/master.key"; then echo "Master key migration verification failed." >&2; exit 1; fi
    chmod -R u+rwX "$INSTALL_ROOT/DCP-UserData"
    chmod 0700 "$INSTALL_ROOT/DCP-UserData/Linux" "$DATA_DIR" "$STORAGE_DIR"

    if [[ $EUID -eq 0 ]]; then SUDO=(); else command -v sudo >/dev/null 2>&1 || { echo "sudo is required to install Ubuntu runtime libraries." >&2; exit 1; }; SUDO=(sudo); fi
    "${SUDO[@]}" apt-get update
    "${SUDO[@]}" apt-get install -y libgtk-3-0t64 libwebkit2gtk-4.1-0 php-cli php-curl php-intl php-mbstring php-sqlite3 php-xml php-gd php-zip
    chmod +x "$INSTALL_ROOT/DCP" "$INSTALL_ROOT/Run-DCP.sh" "$INSTALL_ROOT/Install-DCP-Launcher.sh"
    "$INSTALL_ROOT/platform/scripts/install-runtime-linux.sh"
    php "$INSTALL_ROOT/platform/scripts/verify-runtime.php"
    "$INSTALL_ROOT/Install-DCP-Launcher.sh"
    INSTALL_COMPLETE=1
    if [[ -n "${DISPLAY:-}" || -n "${WAYLAND_DISPLAY:-}" ]]; then
      nohup "$INSTALL_ROOT/Run-DCP.sh" > "$INSTALL_ROOT/dcp-launch.log" 2>&1 &
      echo "DCP launched. Log: $INSTALL_ROOT/dcp-launch.log"
    else
      echo "DCP installed. No graphical session detected; launch DCP from the Applications menu when logged into Ubuntu."
    fi
    echo "DCP Linux installed at: $INSTALL_ROOT"
    ;;
  *)
    echo "Unsupported operating system: $(uname -s)" >&2
    exit 2
    ;;
esac
