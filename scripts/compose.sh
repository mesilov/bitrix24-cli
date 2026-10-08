#!/bin/sh
# Always address the environment of this script's checkout, including linked worktrees.
set -eu

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd -P)
# Direct wrapper calls use the same file ownership defaults as Make.
LOCAL_UID=${LOCAL_UID:-$(id -u)}
LOCAL_GID=${LOCAL_GID:-$(id -g)}
export LOCAL_UID LOCAL_GID
if command -v sha256sum >/dev/null 2>&1; then
    digest=$(printf '%s' "$root" | sha256sum)
elif command -v shasum >/dev/null 2>&1; then
    digest=$(printf '%s' "$root" | shasum -a 256)
else
    printf '%s\n' 'Compose identity requires sha256sum or shasum.' >&2
    exit 1
fi
digest=${digest%% *}
project="bitrix24-cli-$digest"

case "${1:-}" in
    --project-name) printf '%s\n' "$project" ;;
    --root) printf '%s\n' "$root" ;;
    --info) printf 'project=%s\ncheckout=%s\n' "$project" "$root" ;;
    *) exec docker compose --project-name "$project" \
        --project-directory "$root" --file "$root/docker-compose.yaml" "$@" ;;
esac
