#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
target="${1:-linux}"
case "$target" in
    linux) stage=linux-artifacts; output=dist/linux-amd64; platform=linux/amd64 ;;
    phar) stage=phar-artifacts; output=dist/phar; platform="" ;;
    *) echo 'Expected linux or phar target' >&2; exit 2 ;;
esac
sha="${B24CLI_SOURCE_SHA:-$(git rev-parse HEAD)}"
args=(buildx build --file docker/package/Dockerfile --target "$stage"
    --build-arg "B24CLI_SOURCE_SHA=$sha"
    --build-arg "B24CLI_RELEASE_VERSION=${B24CLI_RELEASE_VERSION:-}"
    --output "type=local,dest=$output")
if [ -n "$platform" ]; then args+=(--platform "$platform"); fi
if [ -n "${GITHUB_TOKEN:-}" ]; then args+=(--secret id=github_token,env=GITHUB_TOKEN); fi
exec docker "${args[@]}" .
