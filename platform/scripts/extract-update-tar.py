#!/usr/bin/env python3
"""Extract DCP update tarballs without links, traversal, special files or user-data writes."""
from __future__ import annotations
import argparse, os, re, shutil, tarfile
from pathlib import Path, PurePosixPath

MAX_FILES = 200_000
MAX_TOTAL_BYTES = 2 * 1024 * 1024 * 1024
MAX_FILE_BYTES = 512 * 1024 * 1024

def safe_path(raw: str) -> PurePosixPath:
    if not raw or "\\" in raw or "\x00" in raw or ":" in raw or any(ord(c) < 32 or ord(c) == 127 for c in raw):
        raise ValueError(f"Unsafe archive path: {raw!r}")
    path = PurePosixPath(raw)
    if path.is_absolute() or any(part in ("", ".", "..") for part in path.parts):
        raise ValueError(f"Archive path traversal rejected: {raw!r}")
    if path.parts and ((len(path.parts) >= 2 and path.parts[0].lower() == "platform" and path.parts[1].lower() in {"data", "storage"}) or
                       any(part.lower() == ".env" or (part.lower().startswith(".env.") and part.lower() != ".env.example") for part in path.parts)):
        raise ValueError(f"Archive attempts to include protected DCP data: {raw!r}")
    return path

def extract(archive_path: Path, destination: Path, full_package: bool) -> int:
    destination.mkdir(mode=0o700, parents=True, exist_ok=True)
    root = destination.resolve()
    seen: set[str] = set()
    count = 0
    total = 0
    with tarfile.open(archive_path, "r:gz") as archive:
        for member in archive:
            raw = member.name.rstrip("/")
            path = safe_path(raw)
            rel = path.as_posix()
            if rel == ".":
                if member.isdir(): continue
                raise ValueError("Archive root may only be a directory entry")
            if path.parts and path.parts[0].lower() == "dcp-userdata":
                if full_package: continue
                raise ValueError(f"Delta archive attempts to include DCP user data: {rel}")
            if rel in seen:
                raise ValueError(f"Duplicate archive entry: {rel}")
            seen.add(rel)
            target = root.joinpath(*path.parts)
            if not target.resolve().is_relative_to(root):
                raise ValueError(f"Archive path escapes extraction root: {rel}")
            if member.isdir():
                target.mkdir(mode=0o755, parents=True, exist_ok=True)
                continue
            if not member.isfile() or member.issym() or member.islnk() or member.isdev() or member.isfifo():
                raise ValueError(f"Archive contains unsupported link or special entry: {rel}")
            if member.size < 0 or member.size > MAX_FILE_BYTES:
                raise ValueError(f"Archive entry exceeds size limit: {rel}")
            count += 1
            total += member.size
            if count > MAX_FILES or total > MAX_TOTAL_BYTES:
                raise ValueError("Archive exceeds DCP extraction limits")
            target.parent.mkdir(mode=0o755, parents=True, exist_ok=True)
            if target.exists() or target.is_symlink():
                raise ValueError(f"Archive path collides with an existing staged entry: {rel}")
            source = archive.extractfile(member)
            if source is None:
                raise ValueError(f"Could not read archive entry: {rel}")
            with source, target.open("xb") as output:
                shutil.copyfileobj(source, output, length=1024 * 1024)
            target.chmod(member.mode & 0o755 if member.mode & 0o111 else 0o644)
    return count

def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("archive")
    parser.add_argument("destination")
    parser.add_argument("--full-package", action="store_true")
    args = parser.parse_args()
    count = extract(Path(args.archive), Path(args.destination), args.full_package)
    print(f"DCP update archive safely extracted: {count} files")
    return 0

if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"DCP update extraction failed: {error}", file=__import__("sys").stderr)
        raise SystemExit(1)
