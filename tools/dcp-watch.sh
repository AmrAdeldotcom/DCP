#!/bin/bash
# DCP live engineering monitor. Run: bash tools/dcp-watch.sh [refresh-seconds]
# Stop with Ctrl+C.
set -u
ROOT="/Volumes/Data/ChatGPT/DCP"
INTERVAL="${1:-3}"
if ! [[ "$INTERVAL" =~ ^[0-9]+$ ]] || [ "$INTERVAL" -lt 1 ]; then
  echo "Usage: $0 [refresh-seconds]" >&2; exit 2
fi
if [ ! -d "$ROOT/.git" ]; then echo "DCP repository not found: $ROOT" >&2; exit 2; fi
cd "$ROOT" || exit 2
while true; do
  clear 2>/dev/null || true
  printf 'DCP LIVE MONITOR | %s | refresh %ss | Ctrl+C to exit\n' "$(date '+%Y-%m-%d %H:%M:%S %Z')" "$INTERVAL"
  printf '%s\n' '======================================================================'
  printf '\n[ GIT / ACTIVE BRANCH ]\n'
  git branch --show-current
  git log -1 --format='HEAD: %h | %cs | %s'
  printf '\n[ UNCOMMITTED CHANGES ]\n'
  git status --short | head -35
  printf '\n[ WORK QUEUE — LAST 22 LINES ]\n'
  tail -22 WORK-QUEUE.md 2>/dev/null || true
  printf '\n[ LATEST HANDOFF ]\n'
  HANDOFF="$(ls -t PROJECT-MEMORY/handoffs/DC*-2026-11-24.md 2>/dev/null | head -1)"
  if [ -n "$HANDOFF" ]; then echo "$HANDOFF"; tail -16 "$HANDOFF"; else echo 'No dated handoff found.'; fi
  printf '\n[ PROJECT MEMORY STATUS ]\n'
  if command -v python3 >/dev/null 2>&1; then
    python3 - <<'PY'
import json
try:
 d=json.load(open('PROJECT-MEMORY/CURRENT-STATE.json'))
 print('Updated:',d.get('updatedAt','unknown'))
 print('Session:',d.get('session','unknown'))
 print('Active task:',d.get('activeTask','unknown'),'/',d.get('activeTaskStatus','unknown'))
 print('Next:',d.get('nextTask','unknown'))
 print('HEAD recorded:',d.get('headCommit','unknown'))
except Exception as e: print('CURRENT-STATE read error:',e)
PY
  fi
  printf '\n[ ACTIVE BUILD / TEST / UPDATE PROCESSES ]\n'
  ps -Ao pid,etime,stat,command | grep -E 'dotnet build|dotnet run|php platform/tests|pwsh .*update|update-linux.php|update-macos.php|build-linux|build-macos|build-delta-package|build-update-manifest|gh run|git push|ssh .*ubuntu|ssh .*windows' | grep -v 'grep -E' | grep -v 'dcp-watch.sh' | head -28 || true
  printf '\n[ RECENT LOGS ]\n'
  find PROJECT-MEMORY/runtime DCP-UserData -type f \( -name '*.log' -o -name '*status*.json' \) -mmin -180 -print 2>/dev/null | head -12
  printf '\n[ NOTE ]\n'
  echo 'Local repository/process view only; this cannot prove a task is alive or reconnect ChatGPT.'
  sleep "$INTERVAL"
done
