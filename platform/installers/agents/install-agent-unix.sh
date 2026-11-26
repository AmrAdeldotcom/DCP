#!/usr/bin/env bash
set -Eeuo pipefail
AGENT="$1"; LOG="$2"; STATE="$3"; PLATFORM_ROOT="$4"
[ -n "$AGENT" ] && [ -n "$LOG" ] && [ -n "$STATE" ] && [ -n "$PLATFORM_ROOT" ] || exit 2
mkdir -p "$(dirname "$LOG")" "$(dirname "$STATE")"; exec > >(tee -a "$LOG") 2>&1
state(){ printf '{"agent":"%s","status":"%s","stage":"%s","updated_at":"%s"}\n' "$AGENT" "$1" "$2" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$STATE"; }
fail(){ state failed "$1"; echo "FAILED: $1"; exit 1; }
case "$AGENT" in desktop-commander) PACKAGE="@wonderwhy-er/desktop-commander@latest";; deepseek-harness) PACKAGE="@deepseek-ai/dsh@latest";; *) fail "Unsupported agent id";; esac
state running "Checking Node.js prerequisite"
NODE="$HOME/.local/share/dcp/runtimes/node-v24.22.3/bin/node"
if [ ! -x "$NODE" ]; then NODE="$(command -v node || true)"; fi
NODE_OK=0
if [ -n "$NODE" ] && [ -x "$NODE" ]; then
 V="$("$NODE" --version 2>/dev/null | sed 's/^v//')"
 if [ "$V" = "24.22.3" ]; then NODE_OK=1; fi
fi
if [ "$NODE_OK" -ne 1 ]; then state running "Installing verified Node.js 24.22.3"; bash "$PLATFORM_ROOT/installers/prerequisites/node/install-unix.sh"; NODE="$HOME/.local/share/dcp/runtimes/node-v24.22.3/bin/node"; fi
NODE_HOME="$(dirname "$NODE")"; export PATH="$NODE_HOME:$HOME/.local/bin:$PATH"; NPM="$NODE_HOME/npm"
[ -x "$NPM" ] || fail "npm executable missing beside verified Node.js"
state running "Installing $PACKAGE"; "$NPM" install --global --no-audit --no-fund "$PACKAGE"
ROOT="$("$NPM" root --global)"
case "$AGENT" in desktop-commander) EXPECTED="$ROOT/@wonderwhy-er/desktop-commander";; deepseek-harness) EXPECTED="$ROOT/@deepseek-ai/dsh";; esac
[ -d "$EXPECTED" ] || fail "npm completed but package directory is missing: $EXPECTED"
state complete "Installed and verified $PACKAGE"; echo "SUCCESS: $AGENT installed at $EXPECTED"
