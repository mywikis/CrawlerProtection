#!/usr/bin/env bash
# Check that every message key in i18n/en.json has a corresponding
# documentation entry in i18n/qqq.json.  Exits with code 1 when any
# keys are missing so that CI can enforce the MediaWiki "MUST" requirement.

set -euo pipefail

EXTENSION_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

python3 - "$EXTENSION_ROOT/i18n/en.json" "$EXTENSION_ROOT/i18n/qqq.json" << 'PYTHON'
import json, sys

en_path, qqq_path = sys.argv[1], sys.argv[2]

with open(en_path, encoding='utf-8') as f:
    en_keys = {k for k in json.load(f) if k != '@metadata'}

with open(qqq_path, encoding='utf-8') as f:
    qqq_keys = {k for k in json.load(f) if k != '@metadata'}

missing = en_keys - qqq_keys
if missing:
    print("ERROR: Keys present in en.json but missing from qqq.json:")
    for key in sorted(missing):
        print(f"  - {key}")
    sys.exit(1)

print(f"OK: All {len(en_keys)} message key(s) from en.json are documented in qqq.json.")
PYTHON
