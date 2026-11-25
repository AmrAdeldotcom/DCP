#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/dcp-clone-retry-test.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT HUP INT TERM
REAL_GIT="$(command -v git)"
mkdir -p "$WORK/source" "$WORK/fake-bin"
git -C "$WORK/source" init -q -b main
git -C "$WORK/source" config user.name "DCP Test"
git -C "$WORK/source" config user.email "dcp-test@example.invalid"
printf 'pinned fixture\n' > "$WORK/source/fixture.txt"
git -C "$WORK/source" add fixture.txt
git -C "$WORK/source" commit -qm "fixture source"
cat > "$WORK/fake-bin/git" <<'FAKE_GIT'
#!/usr/bin/env bash
set -euo pipefail
is_clone=0
for arg in "$@"; do [[ "$arg" == clone ]] && is_clone=1; done
if (( is_clone )); then
  count=0
  [[ -f "$GIT_RETRY_COUNTER" ]] && count="$(cat "$GIT_RETRY_COUNTER")"
  count=$((count + 1))
  printf '%s' "$count" > "$GIT_RETRY_COUNTER"
  if (( count < 3 )); then
    echo "simulated transient DNS lookup failure" >&2
    exit 128
  fi
fi
exec "$GIT_REAL" "$@"
FAKE_GIT
chmod 0755 "$WORK/fake-bin/git"
PATH="$WORK/fake-bin:$PATH" GIT_REAL="$REAL_GIT" GIT_RETRY_COUNTER="$WORK/attempts" \
  bash "$ROOT/platform/scripts/clone-pinned-source.sh" \
  "file://$WORK/source" main "$WORK/clone" 4
test "$(cat "$WORK/attempts")" = 3
test "$(cat "$WORK/clone/fixture.txt")" = "pinned fixture"
echo "PASS: transient source fetch failures are retried and the requested ref is checked out"
