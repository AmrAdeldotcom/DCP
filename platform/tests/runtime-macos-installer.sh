#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TMP="$(mktemp -d "${TMPDIR:-/tmp}/dcp-macos-runtime-test.XXXXXX")"
trap 'rm -rf "$TMP"' EXIT

PASS=0
fail() { echo "FAIL: $1" >&2; exit 1; }
pass() { echo "PASS: $1"; PASS=$((PASS + 1)); }

make_fixture() {
  local name="$1"
  local root="$TMP/$name"
  mkdir -p "$root/platform/scripts" "$root/platform/dcp-env/runtime/php/macos-x64/bin" "$root/platform/dcp-env/runtime/php/macos-x64/conf.d" "$root/platform/dcp-env/runtime/php/macos-arm64/bin" "$root/bin"
  cp "$ROOT/platform/scripts/install-runtime-macos.sh" "$root/platform/scripts/install-runtime-macos.sh"
  cp "$ROOT/platform/scripts/verify-runtime.php" "$root/platform/scripts/verify-runtime.php"
  chmod +x "$root/platform/scripts/install-runtime-macos.sh"
  printf '%s\n' '#!/bin/sh' 'echo DCP_TEST_SYSTEM_PHP' 'exit 0' > "$root/bin/php"
  chmod +x "$root/bin/php"
  printf '%s\n' '#!/bin/sh' 'echo x86_64' > "$root/bin/uname"
  chmod +x "$root/bin/uname"
  printf '%s\n' '#!/bin/sh' 'echo DCP_TEST_MANAGED_PHP' 'exit 0' > "$root/platform/dcp-env/runtime/php/macos-x64/bin/php"
  chmod +x "$root/platform/dcp-env/runtime/php/macos-x64/bin/php"
  shasum -a 256 "$root/platform/dcp-env/runtime/php/macos-x64/bin/php" | awk '{print $1}' > "$root/platform/dcp-env/runtime/php/macos-x64/php.sha256"
  printf 'memory_limit=128M\n' > "$root/platform/dcp-env/runtime/php/macos-x64/php.ini"
  printf '%s\n' '#!/bin/sh' 'echo DCP_TEST_ARM_PHP' 'exit 0' > "$root/platform/dcp-env/runtime/php/macos-arm64/bin/php"
  chmod +x "$root/platform/dcp-env/runtime/php/macos-arm64/bin/php"
  printf '%s\n' "$root"
}

FIXTURE="$(make_fixture system)"
SYSTEM_OUTPUT="$(DCP_PHP_SYSTEM_CANDIDATES="$FIXTURE/bin/php" PATH="$FIXTURE/bin:/usr/bin:/bin:/usr/sbin:/sbin" "$FIXTURE/platform/scripts/install-runtime-macos.sh" 2>&1)" || fail "system PHP candidate should be accepted"
case "$SYSTEM_OUTPUT" in *DCP_TEST_SYSTEM_PHP*) pass "compatible system PHP is preferred";; *) fail "system PHP was not selected";; esac
case "$SYSTEM_OUTPUT" in *DCP_TEST_MANAGED_PHP*) fail "managed PHP ran despite a healthy system PHP";; esac

FIXTURE="$(make_fixture managed)"
rm -f "$FIXTURE/bin/php"
MANAGED_OUTPUT="$(DCP_PHP_SYSTEM_CANDIDATES="" PATH="$FIXTURE/bin:/usr/bin:/bin:/usr/sbin:/sbin" "$FIXTURE/platform/scripts/install-runtime-macos.sh" 2>&1)" || fail "managed PHP fallback should be accepted"
case "$MANAGED_OUTPUT" in *DCP_TEST_MANAGED_PHP*) pass "managed PHP is selected when system PHP is absent";; *) fail "managed PHP fallback was not selected";; esac

FIXTURE="$(make_fixture incompatible-system)"
printf '%s\n' '#!/bin/sh' 'echo DCP_TEST_INCOMPATIBLE_SYSTEM_PHP' 'exit 1' > "$FIXTURE/bin/php"
chmod +x "$FIXTURE/bin/php"
INCOMPATIBLE_OUTPUT="$(DCP_PHP_SYSTEM_CANDIDATES="$FIXTURE/bin/php" PATH="$FIXTURE/bin:/usr/bin:/bin:/usr/sbin:/sbin" "$FIXTURE/platform/scripts/install-runtime-macos.sh" 2>&1)" || fail "managed PHP should follow an incompatible system PHP"
case "$INCOMPATIBLE_OUTPUT" in *DCP_TEST_MANAGED_PHP*) pass "managed PHP is used after an incompatible system PHP";; *) fail "incompatible system PHP blocked the managed fallback";; esac

FIXTURE="$(make_fixture corrupt)"
rm -f "$FIXTURE/bin/php"
printf '%s\n' '#!/bin/sh' 'echo DCP_TEST_CORRUPTED_PHP' 'exit 0' > "$FIXTURE/platform/dcp-env/runtime/php/macos-x64/bin/php"
chmod +x "$FIXTURE/platform/dcp-env/runtime/php/macos-x64/bin/php"
if DCP_PHP_SYSTEM_CANDIDATES="" PATH="$FIXTURE/bin:/usr/bin:/bin:/usr/sbin:/sbin" "$FIXTURE/platform/scripts/install-runtime-macos.sh" >/dev/null 2>&1; then
  fail "managed PHP with a mismatched SHA-256 must be rejected"
fi
pass "managed PHP checksum mismatch is rejected"

FIXTURE="$(make_fixture no-checksum)"
rm -f "$FIXTURE/bin/php" "$FIXTURE/platform/dcp-env/runtime/php/macos-x64/php.sha256"
if DCP_PHP_SYSTEM_CANDIDATES="" PATH="$FIXTURE/bin:/usr/bin:/bin:/usr/sbin:/sbin" "$FIXTURE/platform/scripts/install-runtime-macos.sh" >/dev/null 2>&1; then
  fail "managed PHP without a checksum must be rejected"
fi
pass "managed PHP without a checksum is rejected"

FIXTURE="$(make_fixture unsupported)"
rm -f "$FIXTURE/bin/php" "$FIXTURE/platform/dcp-env/runtime/php/macos-x64/bin/php" "$FIXTURE/platform/dcp-env/runtime/php/macos-arm64/bin/php"
printf '%s\n' '#!/bin/sh' 'echo i386' > "$FIXTURE/bin/uname"
chmod +x "$FIXTURE/bin/uname"
if DCP_PHP_SYSTEM_CANDIDATES="" PATH="$FIXTURE/bin:/usr/bin:/bin:/usr/sbin:/sbin" "$FIXTURE/platform/scripts/install-runtime-macos.sh" >/dev/null 2>&1; then
  fail "unsupported architecture must be rejected"
fi
pass "unsupported architecture is rejected"

echo "ALL MACOS RUNTIME INSTALLER TESTS PASSED ($PASS)"
