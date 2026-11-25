#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 3 || $# -gt 5 ]]; then
  echo "Usage: clone-pinned-source.sh REPOSITORY REF DESTINATION [MAX_ATTEMPTS] [blob:none]" >&2
  exit 2
fi
REPOSITORY="$1"
REF="$2"
DESTINATION="$3"
MAX_ATTEMPTS="${4:-5}"
FILTER="${5:-}"

if [[ -n "$FILTER" && "$FILTER" != "blob:none" ]]; then
  echo "Only the blob:none partial-clone filter is supported." >&2
  exit 2
fi
if [[ ! "$REPOSITORY" =~ ^https://github\.com/[^[:space:]]+$ && ! "$REPOSITORY" =~ ^file://[^[:space:]]+$ ]]; then
  echo "Only HTTPS GitHub repositories or file:// test fixtures are allowed." >&2
  exit 2
fi
if [[ ! "$REF" =~ ^[A-Za-z0-9._/-]+$ ]]; then
  echo "Invalid pinned source ref." >&2
  exit 2
fi
if [[ ! "$MAX_ATTEMPTS" =~ ^[1-8]$ ]]; then
  echo "MAX_ATTEMPTS must be an integer from 1 to 8." >&2
  exit 2
fi
if [[ -e "$DESTINATION" ]]; then
  echo "Refusing to overwrite an existing source destination: $DESTINATION" >&2
  exit 1
fi
mkdir -p "$(dirname "$DESTINATION")"

clone_args=(--depth 1 --branch "$REF")
if [[ "$FILTER" == "blob:none" ]]; then clone_args+=(--filter=blob:none); fi

for ((attempt=1; attempt<=MAX_ATTEMPTS; attempt++)); do
  echo "Fetching $REPOSITORY at $REF (attempt $attempt/$MAX_ATTEMPTS)"
  if git -c http.lowSpeedLimit=1000 -c http.lowSpeedTime=60 \
      clone "${clone_args[@]}" "$REPOSITORY" "$DESTINATION"; then
    git -C "$DESTINATION" rev-parse --verify HEAD >/dev/null
    echo "Pinned source checkout ready: $DESTINATION"
    exit 0
  fi
  rm -rf -- "$DESTINATION"
  if (( attempt < MAX_ATTEMPTS )); then
    delay=$((attempt * 5))
    echo "Source fetch failed; retrying in ${delay}s." >&2
    sleep "$delay"
  fi
done

echo "Unable to fetch pinned source after $MAX_ATTEMPTS attempts: $REPOSITORY@$REF" >&2
exit 1
