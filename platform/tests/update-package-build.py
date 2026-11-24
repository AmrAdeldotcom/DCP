#!/usr/bin/env python3
from __future__ import annotations
import hashlib, json, subprocess, sys, tempfile, tarfile, zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DELTA = ROOT / 'platform/scripts/build-delta-package.py'
MANIFEST = ROOT / 'platform/scripts/build-update-manifest.py'

def run(*args: str) -> None:
    subprocess.run([sys.executable, *args], check=True, cwd=ROOT)

def main() -> int:
    with tempfile.TemporaryDirectory(prefix='dcp-update-package-test-') as temp:
        work = Path(temp); base = work / 'base'; target = work / 'target'
        (base / 'platform/app').mkdir(parents=True); (target / 'platform/app').mkdir(parents=True)
        (base / 'platform/app/keep.php').write_text('same')
        (target / 'platform/app/keep.php').write_text('same')
        (base / 'platform/app/old.php').write_text('remove me')
        (target / 'platform/app/Database.php').write_text('new database class')
        (base / 'DCP-UserData/Linux/data').mkdir(parents=True)
        (target / 'DCP-UserData/Linux/data').mkdir(parents=True)
        (base / 'DCP-UserData/Linux/data/dcp.sqlite').write_bytes(b'private-old-db')
        (target / 'DCP-UserData/Linux/data/dcp.sqlite').write_bytes(b'private-new-db')
        (base / 'platform/storage/keys').mkdir(parents=True)
        (target / 'platform/storage/keys').mkdir(parents=True)
        (base / 'platform/storage/keys/master.key').write_bytes(b'old-key')
        (target / 'platform/storage/keys/master.key').write_bytes(b'new-key')
        (target / 'Run-DCP.sh').write_text('#!/bin/sh\n')
        (target / 'platform/cloud').mkdir(parents=True)
        (target / 'platform/cloud/.env.example').write_text('API_KEY=replace-me\n')
        (base / 'Run-DCP.sh').write_text('#!/bin/sh\necho old\n')
        (target / 'Run-DCP.sh').chmod(0o755)
        (target / 'payload').mkdir()
        (target / 'payload/legacy-delta-file').write_text('must not be packaged twice')
        (target / 'DCP-Windows-update.json').write_text('{"legacy":true}')

        for fmt, suffix in [('tar.gz', 'delta.tar.gz'), ('zip', 'delta.zip')]:
            archive, index = work / suffix, work / (suffix + '.json')
            run(str(DELTA), '--base-dir', str(base), '--target-dir', str(target), '--output', str(archive), '--index', str(index), '--format', fmt)
            data = json.loads(index.read_text())
            paths = {item['path'] for item in data['files']}
            assert paths == {'platform/app/Database.php', 'Run-DCP.sh', 'platform/cloud/.env.example'}, paths
            assert data['deleted'] == ['platform/app/old.php'], data['deleted']
            if fmt == 'tar.gz':
                with tarfile.open(archive, 'r:gz') as package:
                    names = set(package.getnames())
            else:
                with zipfile.ZipFile(archive) as package:
                    names = set(package.namelist())
            assert names == paths, names
            assert not any('DCP-UserData' in name or 'master.key' in name or '.sqlite' in name or name.startswith('payload/') or name == 'DCP-Windows-update.json' for name in names)
            print(f'PASS: {fmt} delta contains only changed app files and explicit deletions; user data excluded')

        full = work / 'DCP-Linux-x64.tar.gz'; full.write_bytes(b'full-package-fixture')
        version = work / 'version.json'
        version.write_text(json.dumps({'version':'1.0.0-alpha.10','releaseTag':'v1.0.0-alpha.10','packageRevision':1}))
        index = work / 'delta.tar.gz.json'
        manifest = work / 'DCP-Linux-update.json'
        run(str(MANIFEST), '--platform','linux','--architecture','x64','--version-manifest',str(version),
            '--release-tag','v1.0.0-alpha.10','--commit','a'*40,'--full-package',str(full),
            '--delta-package',str(work/'delta.tar.gz'),'--delta-index',str(index),
            '--base-version','1.0.0-alpha.9','--base-tag','v1.0.0-alpha.9','--base-revision','0','--output',str(manifest))
        parsed = json.loads(manifest.read_text())
        assert parsed['schema'] == 3 and parsed['platform'] == 'linux' and parsed['architecture'] == 'x64'
        assert parsed['delta']['base']['package_revision'] == 0
        assert parsed['full']['sha256'] == hashlib.sha256(full.read_bytes()).hexdigest()
        assert parsed['full']['deleted'] == ['platform/app/old.php']
        assert parsed['delta']['asset']['name'] == 'DCP-Linux-x64-delta.tar.gz'
        print('PASS: schema 3 manifest binds platform, exact base revision, full fallback and asset hashes')
        secret = target / 'platform/cloud/.env.local'
        secret.write_text('API_KEY=must-not-ship\n')
        rejected = subprocess.run([sys.executable, str(DELTA), '--base-dir', str(base), '--target-dir', str(target),
            '--output', str(work/'bad-delta.zip'), '--index', str(work/'bad-delta.json'), '--format', 'zip'],
            cwd=ROOT, capture_output=True, text=True)
        assert rejected.returncode != 0 and 'environment secret path' in rejected.stderr, rejected.stdout + rejected.stderr
        secret.unlink()
        print('PASS: delta builder rejects .env.local while retaining the safe .env.example template')
    print('ALL UPDATE PACKAGE BUILD TESTS PASSED')
    return 0

if __name__ == '__main__': raise SystemExit(main())
