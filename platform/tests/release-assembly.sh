#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/dcp-release-assembly-test.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT HUP INT TERM
mkdir -p "$WORK/current" "$WORK/out-full" "$WORK/empty-base" "$WORK/revision-base" "$WORK/revision-current" "$WORK/out-revision"

python3 - "$WORK" <<'PY'
import json,os,stat,sys,tarfile,zipfile
from pathlib import Path
work=Path(sys.argv[1])
specs=[
 ('windows','x64','DCP-Windows-x64.zip','zip'),
 ('linux','x64','DCP-Linux-x64.tar.gz','tar.gz'),
 ('macos','x64','DCP-macOS-Intel.zip','zip'),
 ('macos','arm64','DCP-macOS-AppleSilicon.zip','zip'),
]
def create_tree(root,platform,arch,revision):
 root.mkdir(parents=True)
 (root/'platform/app').mkdir(parents=True)
 (root/'platform/version.json').write_text(json.dumps({
  'version':'1.0.0-alpha.10','releaseTag':'v1.0.0-alpha.10','channel':'alpha',
  'build':'10','packageRevision':revision
 },indent=2)+'\n')
 (root/'platform/app/index.php').write_text('initial approved app' if revision==1 else 'revision two index')
 if revision>1: (root/'platform/app/revision-two.php').write_text('revision two file')
 data='Linux' if platform=='linux' else 'Windows' if platform=='windows' else 'macOS'
 (root/'DCP-UserData'/data/'data').mkdir(parents=True)
 if platform=='linux':
  (root/'DCP').write_text('linux launcher'); os.chmod(root/'DCP',0o755)
  (root/'Run-DCP.sh').write_text('#!/bin/sh\n'); os.chmod(root/'Run-DCP.sh',0o755)
 elif platform=='windows':
  (root/'DCP.exe').write_text('windows executable')
 else:
  binary=root/'DCP.app/Contents/MacOS/DCP'
  binary.parent.mkdir(parents=True)
  binary.write_text('macOS '+arch+' executable'); os.chmod(binary,0o755)
 db=root/'DCP-UserData'/data/'data/dcp.sqlite'
 db.write_text('PRIVATE DATABASE MUST NEVER ENTER UPDATE PACKAGES')
def archive(root,path,fmt):
 if fmt=='tar.gz':
  with tarfile.open(path,'w:gz') as tf:
   for item in sorted(root.rglob('*')): tf.add(item,arcname=item.relative_to(root).as_posix(),recursive=False)
 else:
  with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as z:
   for item in sorted(root.rglob('*')):
    if item.is_file():
     info=zipfile.ZipInfo(item.relative_to(root).as_posix())
     info.external_attr=(stat.S_IFREG|stat.S_IMODE(item.stat().st_mode))<<16
     z.writestr(info,item.read_bytes())
for platform,arch,name,fmt in specs:
 current=work/'current'/(platform+'-'+arch)
 create_tree(current,platform,arch,1)
 archive(current,work/'current'/name,fmt)
 base=work/'revision-base'/(platform+'-'+arch)
 revised=work/'revision-current'/(platform+'-'+arch)
 create_tree(base,platform,arch,1)
 create_tree(revised,platform,arch,2)
 archive(base,work/'revision-base'/name,fmt)
 archive(revised,work/'revision-current'/name,fmt)
PY

openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$WORK/private.pem" >/dev/null 2>&1
openssl pkey -in "$WORK/private.pem" -pubout -out "$WORK/public.pem" >/dev/null 2>&1
PRIVATE_B64="$(python3 - "$WORK/private.pem" <<'PY'
import base64,sys
print(base64.b64encode(open(sys.argv[1],'rb').read()).decode('ascii'))
PY
)"
export DCP_RELEASE_SIGNING_PRIVATE_KEY_B64="$PRIVATE_B64"
export DCP_UPDATE_PUBLIC_KEY_PATH="$WORK/public.pem"

bash "$ROOT/platform/scripts/assemble-signed-release.sh" \
  v1.0.0-alpha.10 aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
  "$WORK/current" "$WORK/empty-base" "$WORK/out-full" ""

python3 - "$WORK/out-full" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1])
names=[
 ('DCP-Windows-update-v3.json','DCP-Windows-x64.zip'),
 ('DCP-Linux-update-v3.json','DCP-Linux-x64.tar.gz'),
 ('DCP-macOS-Intel-update-v3.json','DCP-macOS-Intel.zip'),
 ('DCP-macOS-AppleSilicon-update-v3.json','DCP-macOS-AppleSilicon.zip'),
]
for manifest_name,full_name in names:
 m=json.loads((root/manifest_name).read_text())
 assert m['version']=='1.0.0-alpha.10' and m['package_revision']==1
 assert m['delta'] is None
 assert m['full']['size']==(root/full_name).stat().st_size
 assert m['full']['sha256']==hashlib.sha256((root/full_name).read_bytes()).hexdigest()
print('PASS: first approved release produces four signed Full packages with synchronized hashes and no legacy base dependency')
PY
for manifest in "$WORK/out-full"/DCP-*-update-v3.json; do
  openssl dgst -sha256 -verify "$WORK/public.pem" -signature "$manifest.sig" "$manifest" >/dev/null
done
echo 'PASS: all four detached manifest signatures verify'

bash "$ROOT/platform/scripts/assemble-signed-release.sh" \
  v1.0.0-alpha.10 aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa \
  "$WORK/revision-current" "$WORK/revision-base" "$WORK/out-revision" v1.0.0-alpha.10
python3 - "$WORK/out-revision" <<'PY'
import json,pathlib,sys
root=pathlib.Path(sys.argv[1])
for p in root.glob('DCP-*-update-v3.json'):
 m=json.loads(p.read_text())
 assert m['version']=='1.0.0-alpha.10' and m['package_revision']==2
 assert m['delta'] is not None
 assert m['delta']['base']=={'version':'1.0.0-alpha.10','release_tag':'v1.0.0-alpha.10','package_revision':1}
 assert any(x['path']=='platform/app/index.php' for x in m['delta']['files'])
 assert any(x['path']=='platform/app/revision-two.php' for x in m['delta']['files'])
print('PASS: subsequent packageRevision update selects the exact preceding approved package and creates Delta assets')
PY
echo 'ALL SIGNED RELEASE ASSEMBLY TESTS PASSED'
