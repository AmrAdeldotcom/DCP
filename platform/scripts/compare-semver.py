#!/usr/bin/env python3
"""Return 1 when base (version, revision) is older than target; otherwise 0."""
from __future__ import annotations
import re
import sys

def parse(value: str):
    match = re.fullmatch(r"(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?", value)
    if not match:
        raise ValueError(f"Invalid semantic version: {value}")
    core = tuple(map(int, match.group(1, 2, 3)))
    prerelease = match.group(4).split(".") if match.group(4) else None
    return core, prerelease

def compare(left: str, right: str) -> int:
    left_core, left_pre = parse(left)
    right_core, right_pre = parse(right)
    if left_core != right_core:
        return (left_core > right_core) - (left_core < right_core)
    if left_pre is None:
        return 0 if right_pre is None else 1
    if right_pre is None:
        return -1
    for a, b in zip(left_pre, right_pre):
        a_num, b_num = a.isdigit(), b.isdigit()
        if a_num and b_num:
            result = (int(a) > int(b)) - (int(a) < int(b))
        elif a_num:
            result = -1
        elif b_num:
            result = 1
        else:
            result = (a > b) - (a < b)
        if result:
            return result
    return (len(left_pre) > len(right_pre)) - (len(left_pre) < len(right_pre))

def main() -> int:
    if len(sys.argv) != 5:
        raise SystemExit("Usage: compare-semver.py BASE_VERSION TARGET_VERSION BASE_REVISION TARGET_REVISION")
    result = compare(sys.argv[1], sys.argv[2])
    older = result < 0 or (result == 0 and int(sys.argv[3]) < int(sys.argv[4]))
    print("1" if older else "0")
    return 0

if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exc:
        print(f"Semantic version comparison failed: {exc}", file=sys.stderr)
        raise SystemExit(2)
