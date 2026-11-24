#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/dcp-alpha9-linux-delta.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT

BASE_ARCHIVE="$WORK/DCP-Linux-x64-alpha9.tar.gz"
BASE_DIR="$WORK/base"
TARGET_DIR="$WORK/target"
DELTA_ARCHIVE="$WORK/DCP-Linux-x64-alpha9-to-alpha10-delta.tar.gz"
INDEX="$WORK/index.json"

mkdir -p "$BASE_DIR" "$TARGET_DIR"
curl --connect-timeout 10 --max-time 180 --retry 2 --retry-delay 2 -fsSL \
  'https://github.com/AmrAdeldotcom/DCP/releases/download/v1.0.0-alpha.9/DCP-Linux-x64.tar.gz' \
  -o "$BASE_ARCHIVE"
tar -xzf "$BASE_ARCHIVE" -C "$BASE_DIR"

python3 - "$BASE_DIR/platform/version.json" <<'PY'
import json, sys
version = json.load(open(sys.argv[1], encoding="utf-8"))
assert version.get("version") == "1.0.0-alpha.9", version
assert int(version.get("packageRevision", 0)) == 0, version
print("Verified published Alpha 9 Linux base package.")
PY

cp -a "$BASE_DIR/." "$TARGET_DIR/"
mkdir -p "$TARGET_DIR/platform/app/Core"
cp "$ROOT/platform/app/Core/Database.php" "$TARGET_DIR/platform/app/Core/Database.php"
cp "$ROOT/platform/version.json" "$TARGET_DIR/platform/version.json"

python3 "$ROOT/platform/scripts/build-delta-package.py" \
  --base-dir "$BASE_DIR" \
  --target-dir "$TARGET_DIR" \
  --output "$DELTA_ARCHIVE" \
  --index "$INDEX" \
  --format tar.gz

python3 - "$INDEX" "$DELTA_ARCHIVE" <<'PY'
import json, pathlib, sys, tarfile
index_path, archive_path = map(pathlib.Path, sys.argv[1:])
index = json.loads(index_path.read_text(encoding="utf-8"))
paths = [entry["path"] for entry in index.get("files", [])]
assert "platform/app/Core/Database.php" in paths, paths
assert "platform/version.json" in paths, paths
assert not any(path.startswith(("DCP-UserData/", "platform/storage/")) for path in paths), paths
with tarfile.open(archive_path, "r:gz") as archive:
    members = {name.removeprefix("./") for name in archive.getnames()}
assert "platform/app/Core/Database.php" in members, sorted(members)
print(f"PASS: real Alpha 9 Linux package produces a {archive_path.stat().st_size}-byte repair Delta.")
print("PASS: Database.php and version metadata are included; user data is excluded.")
PY
