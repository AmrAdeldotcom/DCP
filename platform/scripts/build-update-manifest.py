#!/usr/bin/env python3
"""Create the signed-schema v3 platform manifest for one DCP package."""
from __future__ import annotations
import argparse, hashlib, json, re
from pathlib import Path

KEY_ID = "dcp-alpha10-rsa3072-20261124"
REPOSITORY = "AmrAdeldotcom/DCP"
FULL_NAMES = {
    ("windows", "x64"): "DCP-Windows-x64.zip",
    ("linux", "x64"): "DCP-Linux-x64.tar.gz",
    ("macos", "x64"): "DCP-macOS-Intel.zip",
    ("macos", "arm64"): "DCP-macOS-AppleSilicon.zip",
}
DELTA_NAMES = {
    ("windows", "x64"): "DCP-Windows-x64-delta.zip",
    ("linux", "x64"): "DCP-Linux-x64-delta.tar.gz",
    ("macos", "x64"): "DCP-macOS-Intel-delta.zip",
    ("macos", "arm64"): "DCP-macOS-AppleSilicon-delta.zip",
}

def asset(path: Path, tag: str, name: str) -> dict[str, object]:
    if not path.is_file() or path.stat().st_size < 1:
        raise ValueError(f"Package asset is missing or empty: {path}")
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return {
        "name": name,
        "url": f"https://github.com/{REPOSITORY}/releases/download/{tag}/{name}",
        "size": path.stat().st_size,
        "sha256": digest.hexdigest(),
    }

def safe_update_path(path: str) -> None:
    if not path or path.startswith("/") or "\\" in path or ":" in path or any(ord(c) < 32 for c in path):
        raise ValueError(f"Unsafe update path: {path!r}")
    parts = path.split("/")
    if any(part in ("", ".", "..") for part in parts):
        raise ValueError(f"Unsafe update path traversal: {path!r}")
    lower = path.lower()
    if lower == "dcp-userdata" or lower.startswith("dcp-userdata/") or re.match(r"^platform/(data|storage)(/|$)", lower) or re.search(r"(^|/)\.env($|\.)", lower):
        raise ValueError(f"User data or environment secrets are not updateable: {path}")

def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--platform", choices=["windows", "linux", "macos"], required=True)
    ap.add_argument("--architecture", choices=["x64", "arm64"], required=True)
    ap.add_argument("--version-manifest", required=True)
    ap.add_argument("--release-tag", required=True)
    ap.add_argument("--commit", required=True)
    ap.add_argument("--full-package", required=True)
    ap.add_argument("--delta-package")
    ap.add_argument("--delta-index")
    ap.add_argument("--base-version")
    ap.add_argument("--base-tag")
    ap.add_argument("--base-revision", type=int)
    ap.add_argument("--output", required=True)
    args = ap.parse_args()
    key = (args.platform, args.architecture)
    if key not in FULL_NAMES:
        raise ValueError("Unsupported platform/architecture pair")
    version_data = json.loads(Path(args.version_manifest).read_text(encoding="utf-8"))
    version = str(version_data.get("version", ""))
    tag = args.release_tag
    revision = version_data.get("packageRevision")
    if not re.fullmatch(r"\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", version) or tag != "v" + version:
        raise ValueError("Release tag and version manifest do not match")
    if version_data.get("releaseTag") != tag or not isinstance(revision, int) or revision < 1:
        raise ValueError("Release tag or package revision is invalid")
    if not re.fullmatch(r"[a-fA-F0-9]{40}", args.commit):
        raise ValueError("Git commit SHA must contain exactly 40 hexadecimal characters")

    full_path = Path(args.full_package)
    full_name = FULL_NAMES[key]
    delta = None
    full_deleted: list[str] = []
    if args.delta_package or args.delta_index or args.base_version or args.base_tag or args.base_revision is not None:
        if not all([args.delta_package, args.delta_index, args.base_version, args.base_tag]) or args.base_revision is None:
            raise ValueError("Delta package, index and exact base version/tag/revision must be supplied together")
        if not re.fullmatch(r"\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?", args.base_version) or args.base_tag != "v" + args.base_version or args.base_revision < 0:
            raise ValueError("Delta base metadata is invalid")
        index = json.loads(Path(args.delta_index).read_text(encoding="utf-8"))
        if index.get("schema") != 1 or not isinstance(index.get("files"), list) or not isinstance(index.get("deleted"), list):
            raise ValueError("Delta index schema is invalid")
        seen = set()
        files = []
        for item in index["files"]:
            path = str(item.get("path", "")); safe_update_path(path)
            if path in seen: raise ValueError(f"Duplicate delta path: {path}")
            seen.add(path)
            size, digest = item.get("size"), str(item.get("sha256", ""))
            if not isinstance(size, int) or size < 0 or not re.fullmatch(r"[a-fA-F0-9]{64}", digest):
                raise ValueError(f"Invalid delta file metadata: {path}")
            files.append({"path": path, "size": size, "sha256": digest.lower()})
        deleted = []
        for path in index["deleted"]:
            path = str(path); safe_update_path(path)
            if path in seen: raise ValueError(f"Delta path is both replaced and deleted: {path}")
            seen.add(path); deleted.append(path)
        full_deleted = sorted(deleted)
        delta = {
            "base": {"version": args.base_version, "release_tag": args.base_tag, "package_revision": args.base_revision},
            "asset": asset(Path(args.delta_package), tag, DELTA_NAMES[key]),
            "files": files,
            "deleted": sorted(deleted),
        }

    full_asset = asset(full_path, tag, full_name)
    full_asset["deleted"] = full_deleted
    manifest = {
        "schema": 3,
        "version": version,
        "release_tag": tag,
        "package_revision": revision,
        "platform": args.platform,
        "architecture": args.architecture,
        "commit": args.commit.lower(),
        "key_id": KEY_ID,
        "full": full_asset,
        "delta": delta,
    }
    output = Path(args.output)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
    print(f"DCP update manifest created: {output} ({args.platform}/{args.architecture}, revision {revision}, delta={'yes' if delta else 'no'})")
    return 0

if __name__ == "__main__":
    try: raise SystemExit(main())
    except Exception as exc:
        print(f"DCP manifest build failed: {exc}", file=__import__("sys").stderr)
        raise SystemExit(1)
