#!/usr/bin/env bash
# Packaging orchestration follows yiipress/engine; see THIRD-PARTY-NOTICES.md.
set -euo pipefail
cd "$(dirname "$0")/.."
exec python3 build/package.py macos-arm64 "$@"
