#!/bin/sh
# Install only public GitHub release assets; no API tokens or CI fallback.
set -eu
LC_ALL=C
export LC_ALL
fail() { printf 'b24cli installer: %s\n' "$*" >&2; exit 1; }
usage() {
    cat <<'HELP'
Install b24cli for macOS arm64 or Linux amd64, without sudo.
Usage: sh install.sh [--version TAG] [--install-dir PATH] [--no-modify-path]
Default: latest stable public release, $HOME/.local/bin.
Requires: cat, curl, tar, awk, sed, grep, mktemp, cp, mv, chmod, mkdir, rm, ls, uname,
          and sha256sum (Linux) or shasum (macOS).
Notices and shell activation: $HOME/.local/share/b24cli/{package,env}.
Source that env file in your current shell after installation.
HELP
}
version=
install_dir=
modify_path=1
while [ "$#" -gt 0 ]; do
    case "$1" in
        --help|-h) usage; exit 0 ;;
        --version|--install-dir)
            [ "$#" -ge 2 ] && [ -n "$2" ] || fail "$1 requires a value"
            case "$1" in --version) version=$2 ;; *) install_dir=$2 ;; esac
            shift 2 ;;
        --no-modify-path) modify_path=0; shift ;;
        *) fail "unknown option: $1 (use --help)" ;;
    esac
done
valid_tag() {
    case "$1" in ''|*[!A-Za-z0-9._+-]*|[!A-Za-z0-9]*) return 1 ;; esac
    [ "${#1}" -le 128 ]
}
[ -z "$version" ] || valid_tag "$version" || fail 'invalid release tag'
[ -n "${HOME:-}" ] || fail 'HOME is required'
# Colon/newline paths cannot be represented as a single PATH entry.
valid_path() {
    case "$1" in /*) ;; *) fail 'paths must be absolute' ;; esac
    case "$1" in *:*) fail 'paths must not contain a colon' ;; esac
    case "$1" in *'
'*) fail 'paths must not contain a newline' ;; esac
}
install_dir=${install_dir:-$HOME/.local/bin}
share_dir=$HOME/.local/share/b24cli
valid_path "$install_dir"
valid_path "$share_dir"
for utility in uname cat; do
    command -v "$utility" >/dev/null 2>&1 || fail "required utility missing: $utility"
done
case "$(uname -s)/$(uname -m)" in
    Darwin/arm64) target=macos-arm64; checksum_tool=shasum ;;
    Linux/x86_64|Linux/amd64) target=linux-amd64; checksum_tool=sha256sum ;;
    *) fail "unsupported target: $(uname -s)/$(uname -m); supported: macOS arm64, Linux amd64" ;;
esac
for utility in curl tar awk sed grep mktemp cp mv chmod mkdir rm ls "$checksum_tool"; do
    command -v "$utility" >/dev/null 2>&1 || fail "required utility missing: $utility"
done
release_base=https://github.com/mesilov/bitrix24-cli/releases
if [ -z "$version" ]; then
    resolved=$(curl -q --fail --silent --show-error --location --proto '=https' --proto-redir '=https' \
        --retry 3 --retry-connrefused --retry-max-time 120 \
        --connect-timeout 15 --max-time 120 --output /dev/null --write-out '%{url_effective}' "$release_base/latest") \
        || fail 'cannot resolve latest stable public Release; choose an available --version tag'
    case "$resolved" in "$release_base/tag/"*) version=${resolved#"$release_base/tag/"} ;; *) fail 'latest did not resolve to a public release tag' ;; esac
    valid_tag "$version" || fail 'latest returned an invalid release tag'
fi
archive=b24cli-$target.tar.gz
work=$(mktemp -d "${TMPDIR:-/tmp}/b24cli-install.XXXXXXXX") || fail 'cannot create temporary directory'
binary_stage=
package_stage=
env_stage=
cleanup() {
    rm -rf "$work"
    [ -z "$binary_stage" ] || rm -f "$binary_stage"
    [ -z "$package_stage" ] || rm -rf "$package_stage"
    [ -z "$env_stage" ] || rm -f "$env_stage"
}
trap cleanup 0
trap 'exit 130' INT
trap 'exit 143' TERM HUP
fetch() {
    curl -q --fail --silent --show-error --location --proto '=https' --proto-redir '=https' \
        --retry 3 --retry-connrefused --retry-max-time 120 \
        --connect-timeout 15 --max-time 300 --output "$work/$1" "$release_base/download/$version/$1" \
        || fail "cannot download public Release $version asset $1"
}
fetch "$archive"
fetch SHA256SUMS
# Reject missing, malformed and duplicate records for our exact asset.
expected=$(awk -v name="$archive" '
    $NF == name { count++; if (NF != 2 || length($1) != 64 || $1 ~ /[^0-9a-fA-F]/) bad=1; hash=$1 }
    END { if (count != 1 || bad) exit 1; print tolower(hash) }
' "$work/SHA256SUMS") || fail "missing, malformed or duplicate checksum for $archive"
if [ "$checksum_tool" = shasum ]; then
    actual=$(shasum -a 256 "$work/$archive")
else
    actual=$(sha256sum "$work/$archive")
fi
actual=${actual%% *}
[ "$actual" = "$expected" ] || fail "SHA-256 mismatch for $archive"
# Validate names and member types before extraction (no links or traversal).
tar -tzf "$work/$archive" > "$work/members" || fail 'invalid archive'
awk '
    /(^\/|(^|\/)\.\.(\/|$)|[^A-Za-z0-9_.+\/-])/ { exit 1 }
    { name=$0; sub(/^\.\//,"",name); if (seen[name]++) exit 1;
      if (name ~ /^(b24cli|build-info.json|runtime-sources.json|compression-info.json|THIRD-PARTY-NOTICES.md|BITRIX24-CLI-LICENSE.txt|YIIPRESS-LICENSE.txt)$/) next;
      if (name ~ /^(runtime-licenses|compression-licenses)\//) next;
      exit 1 }
' "$work/members" || fail 'unsafe or unexpected archive member'
tar -tvzf "$work/$archive" > "$work/types" || fail 'cannot inspect archive'
awk 'substr($0,1,1) != "-" && substr($0,1,1) != "d" { exit 1 }' "$work/types" || fail 'archive links or special files are unsupported'
mkdir "$work/package"
tar -xzf "$work/$archive" -C "$work/package" || fail 'cannot unpack archive'
for file in b24cli build-info.json runtime-sources.json compression-info.json THIRD-PARTY-NOTICES.md BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt; do
    [ -s "$work/package/$file" ] || fail "archive missing required file: $file"
done
for directory in runtime-licenses compression-licenses; do
    [ -d "$work/package/$directory" ] || fail "archive missing licenses: $directory"
    [ -n "$(ls -A "$work/package/$directory")" ] || fail "archive has empty licenses: $directory"
done
chmod 755 "$work/package/b24cli"
reported=$("$work/package/b24cli" --version --no-ansi) || fail 'candidate --version failed; see https://github.com/mesilov/bitrix24-cli/issues/23 for macOS launch diagnostics'
[ "$reported" = "b24cli $version" ] || fail "candidate version mismatch: expected b24cli $version; received $reported"
# Stage both destinations on their own filesystem before committing the binary.
mkdir -p "$install_dir" "$share_dir"
[ ! -d "$install_dir/b24cli" ] || fail 'binary destination is a directory'
binary_stage=$(mktemp "$install_dir/.b24cli.XXXXXXXX")
cp "$work/package/b24cli" "$binary_stage"
chmod 755 "$binary_stage"
staged_version=$("$binary_stage" --version --no-ansi) || fail 'staged binary cannot execute in install directory'
[ "$staged_version" = "b24cli $version" ] || fail 'staged binary version mismatch'
package_stage=$(mktemp -d "$share_dir/.package.XXXXXXXX")
for file in build-info.json runtime-sources.json compression-info.json THIRD-PARTY-NOTICES.md BITRIX24-CLI-LICENSE.txt YIIPRESS-LICENSE.txt runtime-licenses compression-licenses; do
    cp -R "$work/package/$file" "$package_stage/"
done
# Single-quote shell strings, including literal quotes in user directories.
quote() { printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"; }
quoted_dir=$(quote "$install_dir")
quoted_env=$(quote "$share_dir/env")
cat > "$work/env" <<ENV
# b24cli shell activation (safe to source repeatedly).
case ":\${PATH:-}:" in
    *:$quoted_dir:*) ;;
    *) export PATH=$quoted_dir\${PATH:+:\$PATH} ;;
esac
ENV
if [ "$modify_path" -eq 1 ]; then
    zsh_dir=${ZDOTDIR:-$HOME}
    valid_path "$zsh_dir"
    mkdir -p "$zsh_dir"
    login_profile=$HOME/.profile
    for profile in "$HOME/.bash_profile" "$HOME/.bash_login"; do
        if [ -f "$profile" ]; then login_profile=$profile; break; fi
    done
    source_line="[ ! -f $quoted_env ] || . $quoted_env # b24cli installer"
    for profile in "$zsh_dir/.zshrc" "$HOME/.bashrc" "$login_profile"; do
        if ! grep -Fqx "$source_line" "$profile" 2>/dev/null; then
            printf '\n%s\n' "$source_line" >> "$profile" || fail "cannot update shell profile: $profile"
        fi
    done
fi
env_stage=$(mktemp "$share_dir/.env.XXXXXXXX")
cp "$work/env" "$env_stage"
chmod 644 "$env_stage"
# Only installer-owned package/env entries are replaced; config and data are untouched.
old_package=
if [ -e "$share_dir/package" ]; then
    old_package=$(mktemp -d "$share_dir/.previous.XXXXXXXX")
    mv "$share_dir/package" "$old_package/package"
fi
if ! mv "$package_stage" "$share_dir/package"; then
    [ -z "$old_package" ] || mv "$old_package/package" "$share_dir/package"
    fail 'cannot replace license notices'
fi
package_stage=
if ! mv -f "$binary_stage" "$install_dir/b24cli"; then
    rm -rf "$share_dir/package"
    [ -z "$old_package" ] || mv "$old_package/package" "$share_dir/package"
    fail 'cannot replace installed binary'
fi
binary_stage=
mv -f "$env_stage" "$share_dir/env"
env_stage=
[ -z "$old_package" ] || rm -rf "$old_package"
printf 'Installed b24cli %s at %s/b24cli\nSHA-256: %s\nNotices: %s/package\n' "$version" "$install_dir" "$expected" "$share_dir"
printf 'Activate in your current shell:\n  . %s\n  b24cli --version\n' "$quoted_env"
if [ "$modify_path" -eq 1 ]; then
    printf 'New zsh/bash sessions load the env file from their startup profiles.\n'
else
    printf 'Shell profiles were left unchanged (--no-modify-path).\n'
fi
