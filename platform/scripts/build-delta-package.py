#!/usr/bin/env python3
"""Build a base-aware DCP file delta from two complete, already-built packages."""
from __future__ import annotations
import argparse, hashlib, json, os, shutil, stat, tarfile, tempfile, zipfile
from pathlib import Path

SENSITIVE_NAMES = {"master.key"}

def is_excluded(rel: str) -> bool:
    parts = rel.split("/")
    lower = [part.lower() for part in parts]
    if lower[0] == "dcp-userdata": return True
    if lower[0] == "payload" or lower[0].endswith("-update.json") or lower[0].endswith("-update.json.sig") or lower[0] == "dcp-update-manifest.json": return True
    if len(lower) >= 2 and lower[0] == "platform" and lower[1] in {"data", "storage"}: return True
    if any(part == ".git" or part == "node_modules" for part in lower): return True
    if any(part == ".env" or part.startswith(".env.") for part in lower):
        raise ValueError(f"Package tree contains an environment secret path: {rel}")
    if Path(rel).name.lower() in SENSITIVE_NAMES or rel.lower().endswith((".sqlite", ".sqlite3", ".db")):
        raise ValueError(f"Package tree contains private user data: {rel}")
    return False

def collect(root: Path) -> dict[str, dict[str, object]]:
    if not root.is_dir(): raise ValueError(f"Package root is not a directory: {root}")
    found: dict[str, dict[str, object]] = {}
    for current, dirs, files in os.walk(root, followlinks=False):
        current_path = Path(current)
        rel_dir = current_path.relative_to(root).as_posix()
        if rel_dir == ".": rel_dir = ""
        kept_dirs = []
        for name in sorted(dirs):
            path = current_path / name
            rel = f"{rel_dir}/{name}".lstrip("/")
            if path.is_symlink(): raise ValueError(f"Symlink in package tree is not supported: {rel}")
            if not is_excluded(rel): kept_dirs.append(name)
        dirs[:] = kept_dirs
        for name in sorted(files):
            path = current_path / name
            rel = f"{rel_dir}/{name}".lstrip("/")
            if is_excluded(rel): continue
            if path.is_symlink() or not path.is_file():
                raise ValueError(f"Non-regular package entry is not supported: {rel}")
            hasher = hashlib.sha256()
            with path.open("rb") as stream:
                for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                    hasher.update(chunk)
            digest = hasher.hexdigest()
            info = path.stat()
            found[rel] = {"path": rel, "size": info.st_size, "sha256": digest, "mode": stat.S_IMODE(info.st_mode)}
    return found

def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base-dir", required=True)
    ap.add_argument("--target-dir", required=True)
    ap.add_argument("--output", required=True)
    ap.add_argument("--index", required=True)
    ap.add_argument("--format", choices=["tar.gz", "zip"], required=True)
    args = ap.parse_args()
    base_root, target_root = Path(args.base_dir).resolve(), Path(args.target_dir).resolve()
    base, target = collect(base_root), collect(target_root)
    changed = []
    for rel, item in sorted(target.items()):
        old = base.get(rel)
        if old is None or any(old[k] != item[k] for k in ("size", "sha256", "mode")):
            changed.append(item)
    deleted = sorted(set(base) - set(target))
    output, index_path = Path(args.output), Path(args.index)
    output.parent.mkdir(parents=True, exist_ok=True)
    index_path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="dcp-delta-") as temp:
        stage = Path(temp) / "payload"
        stage.mkdir()
        for entry in changed:
            rel = str(entry["path"])
            source, destination = target_root / rel, stage / rel
            destination.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(source, destination)
        if args.format == "tar.gz":
            with tarfile.open(output, "w:gz", format=tarfile.PAX_FORMAT) as archive:
                for entry in changed:
                    rel = str(entry["path"])
                    archive.add(stage / rel, arcname=rel, recursive=False)
        else:
            with zipfile.ZipFile(output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
                for entry in changed:
                    rel = str(entry["path"])
                    archive.write(stage / rel, arcname=rel)
    index = {
        "schema": 1,
        "files": [{k: item[k] for k in ("path", "size", "sha256")} for item in changed],
        "deleted": deleted,
    }
    index_path.write_text(json.dumps(index, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Delta package created: {output} ({len(changed)} files, {len(deleted)} deletions, {output.stat().st_size} bytes)")
    return 0

if __name__ == "__main__":
    try: raise SystemExit(main())
    except Exception as exc:
        print(f"DCP delta build failed: {exc}", file=__import__("sys").stderr)
        raise SystemExit(1)
