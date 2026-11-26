#!/usr/bin/env bash
set -Eeuo pipefail
AGENT="$1"; LOG="$2"; STATE="$3"; PLATFORM_ROOT="$4"
[ -n "$AGENT" ] && [ -n "$LOG" ] && [ -n "$STATE" ] && [ -n "$PLATFORM_ROOT" ] || exit 2
mkdir -p "$(dirname "$LOG")" "$(dirname "$STATE")"; exec > >(tee -a "$LOG") 2>&1
state(){ printf '{"agent":"%s","status":"%s","stage":"%s","updated_at":"%s"}\n' "$AGENT" "$1" "$2" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$STATE"; }
fail(){ state failed "$1"; echo "FAILED: $1"; exit 1; }
case "$AGENT" in desktop-commander) PACKAGE="@wonderwhy-er/desktop-commander@latest";; deepseek-harness) PACKAGE="@deepseek-ai/dsh@latest";; *) fail "Unsupported agent id";; esac
find_system_node(){ NODE=""; for candidate in /usr/local/lib/nodejs/node-v24.22.3-*/bin/node; do [ -x "$candidate" ] && NODE="$candidate" && break; done; [ -n "$NODE" ] || NODE="$(command -v node || true)"; }
state running "Checking system Node.js prerequisite"; find_system_node
NODE_OK=0; if [ -n "$NODE" ] && [ -x "$NODE" ]; then V="$("$NODE" --version 2>/dev/null | sed 's/^v//')"; [ "$V" = "24.22.3" ] && NODE_OK=1; fi
if [ "$NODE_OK" -ne 1 ]; then state running "Installing system Node.js 24.22.3"; bash "$PLATFORM_ROOT/installers/prerequisites/node/install-unix.sh"; find_system_node; fi
[ -n "$NODE" ] || fail "System Node.js is unavailable after prerequisite installation"
NODE_HOME="$(dirname "$NODE")"; export PATH="$NODE_HOME:/usr/local/bin:$PATH"; NPM="$NODE_HOME/npm"
[ -x "$NPM" ] || fail "npm executable missing beside verified system Node.js"
state running "Installing $PACKAGE into the system npm package directory"
if [ "$(uname -s)" = "Darwin" ]; then
  ADMIN_CMD="export PATH=$NODE_HOME:/usr/local/bin:/opt/homebrew/bin:\$PATH && $NPM install --global --prefix /usr/local --no-audit --no-fund $PACKAGE"
  osascript -e "do shell script \"$ADMIN_CMD\" with administrator privileges"
elif command -v pkexec >/dev/null 2>&1; then pkexec /bin/bash -c "export PATH='$NODE_HOME:/usr/local/bin:/usr/bin:/bin'; exec '$NPM' install --global --prefix /usr/local --no-audit --no-fund '$PACKAGE'"
else sudo -n /bin/bash -c "export PATH='$NODE_HOME:/usr/local/bin:/usr/bin:/bin'; exec '$NPM' install --global --prefix /usr/local --no-audit --no-fund '$PACKAGE'" || fail "Administrator approval is required; install via a graphical sudo/pkexec prompt."
fi
ROOT="$("$NPM" root --global --prefix /usr/local)"
case "$AGENT" in desktop-commander) EXPECTED="$ROOT/@wonderwhy-er/desktop-commander";; deepseek-harness) EXPECTED="$ROOT/@deepseek-ai/dsh";; esac
[ -d "$EXPECTED" ] || fail "npm completed but package directory is missing: $EXPECTED"
state complete "Installed and verified $PACKAGE"; echo "SUCCESS: $AGENT installed at $EXPECTED"
