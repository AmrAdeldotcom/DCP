#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 6 ]]; then
  echo "Usage: assemble-signed-release.sh TAG COMMIT CURRENT_ASSETS BASE_ASSETS OUTPUT_ASSETS BASE_TAG" >&2
  exit 2
fi
TAG="$1"
COMMIT="$2"
CURRENT_ASSETS="$(cd "$3" && pwd)"
BASE_ASSETS="$4"
OUTPUT_ASSETS="$5"
BASE_TAG="$6"
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
mkdir -p "$OUTPUT_ASSETS"
OUTPUT_ASSETS="$(cd "$OUTPUT_ASSETS" && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/dcp-release-assembly.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT HUP INT TERM

[[ "$TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]] || { echo "Invalid release tag: $TAG" >&2; exit 1; }
[[ "$COMMIT" =~ ^[a-fA-F0-9]{40}$ ]] || { echo "Invalid release commit SHA." >&2; exit 1; }
[[ -d "$CURRENT_ASSETS" ]] || { echo "Current release asset directory is missing." >&2; exit 1; }
if [[ -n "$BASE_TAG" && ! "$BASE_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[A-Za-z0-9.-]+)?$ ]]; then
  echo "Invalid Delta base release tag: $BASE_TAG" >&2; exit 1
fi
mkdir -p "$BASE_ASSETS"
cp "$CURRENT_ASSETS"/DCP-Windows-x64.zip "$OUTPUT_ASSETS/"
cp "$CURRENT_ASSETS"/DCP-Linux-x64.tar.gz "$OUTPUT_ASSETS/"
cp "$CURRENT_ASSETS"/DCP-macOS-Intel.zip "$OUTPUT_ASSETS/"
cp "$CURRENT_ASSETS"/DCP-macOS-AppleSilicon.zip "$OUTPUT_ASSETS/"
for checksum in "$CURRENT_ASSETS"/*.sha256 "$CURRENT_ASSETS"/*.sha256sum; do
  [[ -f "$checksum" ]] && cp "$checksum" "$OUTPUT_ASSETS/"
done

extract_package() {
  local archive="$1"
  local destination="$2"
  mkdir -p "$destination"
  case "$archive" in
    *.tar.gz|*.tgz) tar -xzf "$archive" -C "$destination" ;;
    *.zip) unzip -q "$archive" -d "$destination" ;;
    *) echo "Unsupported DCP package archive: $archive" >&2; return 1 ;;
  esac
}

read_version_field() {
  local file="$1"
  local field="$2"
  python3 - "$file" "$field" <<'PY'
import json,sys
data=json.load(open(sys.argv[1],encoding="utf-8"))
value=data.get(sys.argv[2])
if value is None: raise SystemExit(3)
print(value)
PY
}

REFERENCE_VERSION=""
REFERENCE_REVISION=""
REFERENCE_TAG=""
for spec in   "windows|x64|DCP-Windows-x64.zip|DCP-Windows-x64-delta.zip|DCP-Windows-update.json"   "linux|x64|DCP-Linux-x64.tar.gz|DCP-Linux-x64-delta.tar.gz|DCP-Linux-update.json"   "macos|x64|DCP-macOS-Intel.zip|DCP-macOS-Intel-delta.zip|DCP-macOS-Intel-update.json"   "macos|arm64|DCP-macOS-AppleSilicon.zip|DCP-macOS-AppleSilicon-delta.zip|DCP-macOS-AppleSilicon-update.json"; do
  IFS='|' read -r platform architecture full_name delta_name manifest_name <<< "$spec"
  current_archive="$CURRENT_ASSETS/$full_name"
  [[ -s "$current_archive" ]] || { echo "Required full package is missing: $full_name" >&2; exit 1; }
  current_root="$WORK/current-$platform-$architecture"
  extract_package "$current_archive" "$current_root"
  version_file="$current_root/platform/version.json"
  [[ -s "$version_file" ]] || { echo "Package lacks platform/version.json: $full_name" >&2; exit 1; }
  version="$(read_version_field "$version_file" version)"
  release_tag="$(read_version_field "$version_file" releaseTag)"
  revision="$(read_version_field "$version_file" packageRevision)"
  [[ "$version" == "${TAG#v}" && "$release_tag" == "$TAG" && "$revision" =~ ^[1-9][0-9]*$ ]] || {
    echo "Package version/releaseTag/packageRevision does not match $TAG: $full_name" >&2; exit 1;
  }
  if [[ -z "$REFERENCE_VERSION" ]]; then
    REFERENCE_VERSION="$version"; REFERENCE_REVISION="$revision"; REFERENCE_TAG="$release_tag"
  elif [[ "$version" != "$REFERENCE_VERSION" || "$revision" != "$REFERENCE_REVISION" || "$release_tag" != "$REFERENCE_TAG" ]]; then
    echo "Platform packages are not synchronized: $full_name" >&2; exit 1
  fi

  delta_args=()
  base_archive="$BASE_ASSETS/$full_name"
  if [[ -n "$BASE_TAG" && -s "$base_archive" ]]; then
    base_root="$WORK/base-$platform-$architecture"
    if extract_package "$base_archive" "$base_root"; then
      base_version_file="$base_root/platform/version.json"
      if [[ -s "$base_version_file" ]]; then
        base_version="$(read_version_field "$base_version_file" version)"
        base_revision="$(read_version_field "$base_version_file" packageRevision 2>/dev/null || printf '0')"
        base_release_tag="$(read_version_field "$base_version_file" releaseTag 2>/dev/null || printf '%s' "$BASE_TAG")"
        base_is_older="$(python3 "$ROOT/platform/scripts/compare-semver.py" "$base_version" "${TAG#v}" "$base_revision" "$revision")"
        if [[ ! "$base_is_older" =~ ^[01]$ ]]; then base_is_older=0; fi
        if (( base_is_older )) && [[ "$base_release_tag" == "$BASE_TAG" ]]; then
          delta_path="$OUTPUT_ASSETS/$delta_name"
          delta_index="$WORK/$platform-$architecture-delta-index.json"
          python3 "$ROOT/platform/scripts/build-delta-package.py" \
            --base-dir "$base_root" --target-dir "$current_root" \
            --output "$delta_path" --index "$delta_index" \
            --format "$([[ "$delta_name" == *.tar.gz ]] && echo tar.gz || echo zip)"
          delta_args=(--delta-package "$delta_path" --delta-index "$delta_index" \
            --base-version "$base_version" --base-tag "$BASE_TAG" --base-revision "$base_revision")
        fi
      fi
    fi
  fi

  manifest_path="$OUTPUT_ASSETS/$manifest_name"
  signature_path="$manifest_path.sig"
  python3 "$ROOT/platform/scripts/build-update-manifest.py" \
    --platform "$platform" --architecture "$architecture" \
    --version-manifest "$version_file" --release-tag "$TAG" --commit "$COMMIT" \
    --full-package "$current_archive" "${delta_args[@]}" --output "$manifest_path"
  bash "$ROOT/platform/scripts/sign-update-manifest.sh" "$manifest_path" "$signature_path"
  echo "Prepared signed release manifest: $manifest_name ($platform/$architecture)"
done

python3 - "$OUTPUT_ASSETS" "$TAG" "$COMMIT" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1]); tag=sys.argv[2]; commit=sys.argv[3]
names=[
 ("DCP-Windows-update.json","DCP-Windows-x64.zip","DCP-Windows-x64-delta.zip"),
 ("DCP-Linux-update.json","DCP-Linux-x64.tar.gz","DCP-Linux-x64-delta.tar.gz"),
 ("DCP-macOS-Intel-update.json","DCP-macOS-Intel.zip","DCP-macOS-Intel-delta.zip"),
 ("DCP-macOS-AppleSilicon-update.json","DCP-macOS-AppleSilicon.zip","DCP-macOS-AppleSilicon-delta.zip"),
]
reference=None
for manifest_name,full_name,delta_name in names:
 m=json.loads((root/manifest_name).read_text(encoding="utf-8"))
 sync=(m.get("version"),m.get("release_tag"),m.get("package_revision"),m.get("commit"),m.get("key_id"))
 if sync[1] != tag or sync[3] != commit: raise SystemExit(f"Manifest tag/commit mismatch: {manifest_name}")
 if reference is not None and sync != reference: raise SystemExit(f"Cross-platform manifest mismatch: {manifest_name}")
 reference=sync
 for asset_name in [full_name,delta_name]:
  asset=m.get("full") if asset_name==full_name else (m.get("delta") or {}).get("asset")
  if asset is None: continue
  path=root/asset_name
  if not path.is_file(): raise SystemExit(f"Manifest asset missing: {asset_name}")
  digest=hashlib.sha256(path.read_bytes()).hexdigest()
  if path.stat().st_size != asset.get("size") or digest != asset.get("sha256"):
   raise SystemExit(f"Manifest asset size/hash mismatch: {asset_name}")
 sig=root/(manifest_name+".sig")
 if not sig.is_file() or sig.stat().st_size < 1: raise SystemExit(f"Manifest signature missing: {sig.name}")
print("PASS: all four platform manifests are synchronized and all included asset hashes match")
PY

PUBLIC_KEY="${DCP_UPDATE_PUBLIC_KEY_PATH:-$ROOT/platform/update/release-public.pem}"
for manifest in "$OUTPUT_ASSETS"/DCP-*-update.json; do
  openssl dgst -sha256 -verify "$PUBLIC_KEY" \
    -signature "$manifest.sig" "$manifest" >/dev/null
done
echo "DCP signed cross-platform release assets assembled successfully for $TAG."
