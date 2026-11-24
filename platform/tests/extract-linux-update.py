#!/usr/bin/env python3
from __future__ import annotations
import io, subprocess, sys, tarfile, tempfile
from pathlib import Path
ROOT = Path(__file__).resolve().parents[2]
EXTRACTOR = ROOT / 'platform/scripts/extract-update-tar.py'

def archive(path: Path, entries: list[tuple[str, bytes, str]]) -> None:
    with tarfile.open(path, 'w:gz') as tar:
        for name, data, kind in entries:
            info = tarfile.TarInfo(name)
            if kind == 'file':
                info.size = len(data); info.mode = 0o755
                tar.addfile(info, io.BytesIO(data))
            elif kind == 'symlink':
                info.type = tarfile.SYMTYPE; info.linkname = '/tmp/escape'
                tar.addfile(info)
            elif kind == 'dir':
                info.type = tarfile.DIRTYPE; info.mode = 0o755
                tar.addfile(info)

def run(path: Path, output: Path, full: bool = False, expected: bool = True) -> None:
    cmd = [sys.executable, str(EXTRACTOR), str(path), str(output)]
    if full: cmd.append('--full-package')
    result = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True)
    assert (result.returncode == 0) == expected, result.stdout + result.stderr

def main() -> int:
    with tempfile.TemporaryDirectory(prefix='dcp-linux-extract-test-') as temp:
        root = Path(temp); good = root/'good.tar.gz'; out = root/'out'
        archive(good, [('platform/app/index.php', b'hello', 'file'), ('./DCP-UserData/Linux/data/dcp.sqlite', b'secret', 'file')])
        run(good, out, full=True)
        assert (out/'platform/app/index.php').read_bytes() == b'hello'
        assert not (out/'DCP-UserData').exists()
        print('PASS: full archive extracts app files and skips all bundled user data')
        bad = root/'bad.tar.gz'; archive(bad, [('../escape.php', b'no', 'file')])
        run(bad, root/'bad-out', expected=False)
        print('PASS: path traversal is rejected')
        link = root/'link.tar.gz'; archive(link, [('platform/app/link', b'', 'symlink')])
        run(link, root/'link-out', expected=False)
        print('PASS: symlink entries are rejected')
        protected = root/'protected.tar.gz'; archive(protected, [('platform/storage/keys/master.key', b'key', 'file')])
        run(protected, root/'protected-out', expected=False)
        print('PASS: platform storage and key paths are rejected')
    print('ALL LINUX UPDATE ARCHIVE EXTRACTION TESTS PASSED')
    return 0

if __name__ == '__main__': raise SystemExit(main())
