#!/usr/bin/env bash
# Uses committed HEAD, synthetic configuration and temporary worktrees only.
set -euo pipefail
unset MAKEFLAGS MFLAGS
root=$(cd "$(dirname "$0")/.." && pwd -P)
cd "$root"
for dependency in git make docker; do
    command -v "$dependency" >/dev/null || { echo "Missing dependency: $dependency" >&2; exit 1; }
done
docker info >/dev/null
docker compose version
git diff --quiet HEAD -- Makefile docker-compose.yaml scripts tests .gitignore || {
    echo 'Commit environment/test changes before testing: fresh worktrees use HEAD.' >&2
    exit 1
}
test -z "$(git ls-files --others --exclude-standard -- scripts tests)" || {
    echo 'Commit new scripts/tests before testing.' >&2
    exit 1
}
mkdir -p "$root/.worktree-test-results"
results=$(mktemp -d "$root/.worktree-test-results/run.XXXXXX")
scratch=$(mktemp -d "${TMPDIR:-/tmp}/bitrix24-cli-isolation.XXXXXX")
scratch=$(cd "$scratch" && pwd -P)
a="$scratch/first path/worktree"
b="$scratch/second path/worktree"
pids=()
step=setup
fail() { echo "FAIL [$step]: $*" >&2; exit 1; }
equal() { test "$1" = "$2" || fail "$3"; }
announce() { step=$1; printf '%s\n' "CHECK: $step"; }
compose() { local checkout=$1; shift; sh "$checkout/scripts/compose.sh" "$@"; }
container() { compose "$1" ps -q php-cli; }
cleanup() {
    local status=$? cleanup_status=0 checkout project cleaned=false
    trap - EXIT INT TERM
    set +e
    for pid in ${pids[@]+"${pids[@]}"}; do wait "$pid" 2>/dev/null || true; done
    for checkout in "$a" "$b"; do
        if test -f "$checkout/scripts/compose.sh"; then
            project=$(compose "$checkout" --project-name)
            if compose "$checkout" down --remove-orphans --rmi local >>"$results/cleanup.log" 2>&1; then
                if test -n "$(docker ps -aq --filter "label=com.docker.compose.project=$project")"; then
                    echo "Cleanup left containers for $project" >&2
                    cleanup_status=1
                    continue
                fi
                # Only checkout paths created by this test are removed forcibly.
                git worktree remove --force "$checkout" >>"$results/cleanup.log" 2>&1 || cleanup_status=1
            else
                echo "Cleanup failed; retained worktree: $checkout" >&2
                cleanup_status=1
            fi
        fi
    done
    if test "$cleanup_status" -eq 0; then rm -rf "$scratch"; cleaned=true; fi
    printf 'platform=%s\ncommit=%s\nlast_check=%s\nstatus=%s\ncleanup=%s\n' \
        "$(uname -s)" "$(git rev-parse HEAD)" "$step" "$status" "$cleaned" >"$results/result.txt"
    printf 'Test evidence: %s\n' "$results"
    if test "$status" -eq 0 && test "$cleanup_status" -eq 0; then
        echo 'PASS: concurrent worktree isolation and cleanup'
    elif test "$status" -eq 0; then status=1; fi
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

announce 'fresh worktrees with matching basename and spaces in their paths'
git worktree add --detach "$a" HEAD >"$results/git-a.log" 2>&1
git worktree add --detach "$b" HEAD >"$results/git-b.log" 2>&1
test -f "$a/.git" && test -f "$b/.git" || fail 'expected linked worktree .git files'
test ! -e "$a/vendor" && test ! -e "$b/vendor" || fail 'fresh worktree unexpectedly has dependencies'
test ! -e "$a/.env" && test ! -e "$b/.env" || fail 'local configuration was copied'
pa=$(compose "$a" --project-name)
pb=$(compose "$b" --project-name)
test "$pa" != "$pb" || fail 'project identity collision'
equal "$pa" "$(compose "$a" --project-name)" 'project identity is unstable'
ln -s "$a" "$scratch/alias"
equal "$pa" "$(compose "$scratch/alias" --project-name)" 'symlink changes physical identity'
equal "$pa" "$(COMPOSE_PROJECT_NAME=incorrect compose "$a" --project-name)" 'ambient project name overrides checkout identity'
make -s -C "$a" worktree-info >"$results/info-a.txt"
make -s -C "$b" worktree-info >"$results/info-b.txt"
git -C "$a" check-ignore -q .worktree/example
git -C "$a" check-ignore -q .worktree-test-results/example

announce 'distinct images and explicit checkout mounts'
ia=$(compose "$a" config --images)
ib=$(compose "$b" config --images)
test "$ia" != "$ib" || fail 'shared image tag'
printf '\nLABEL io.bitrix24-cli.isolation-test="A"\n' >>"$a/docker/php-cli/Dockerfile"
printf '\nLABEL io.bitrix24-cli.isolation-test="B"\n' >>"$b/docker/php-cli/Dockerfile"
printf 'checkout-A\n' >"$a/.isolation-source"
printf 'checkout-B\n' >"$b/.isolation-source"
printf 'WORKTREE_TEST=A\nCOMPOSE_PROJECT_NAME=incorrect\n' >"$a/.env"
printf 'WORKTREE_TEST=B\nCOMPOSE_PROJECT_NAME=incorrect\n' >"$b/.env"

announce 'parallel build, dependency installation, startup and checks'
(make -C "$a" docker-init docker-up cli check >"$results/runtime-a.log" 2>&1) &
pids+=("$!")
(make -C "$b" docker-init docker-up cli check >"$results/runtime-b.log" 2>&1) &
pids+=("$!")
status_a=0; status_b=0
wait "${pids[0]}" || status_a=$?
wait "${pids[1]}" || status_b=$?
pids=()
equal "$status_a" 0 "worktree A commands failed; see $results/runtime-a.log"
equal "$status_b" 0 "worktree B commands failed; see $results/runtime-b.log"
ca=$(container "$a"); cb=$(container "$b")
test -n "$ca" && test -n "$cb" && test "$ca" != "$cb" || fail 'distinct running containers missing'
equal "$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$ca")" "$pa" 'wrong A project label'
equal "$(docker inspect -f '{{index .Config.Labels "com.docker.compose.project"}}' "$cb")" "$pb" 'wrong B project label'
equal "$(docker inspect -f '{{.Config.Image}}' "$ca")" "$ia" 'wrong A image'
equal "$(docker inspect -f '{{.Config.Image}}' "$cb")" "$ib" 'wrong B image'
equal "$(docker inspect -f '{{index .Config.Labels "io.bitrix24-cli.isolation-test"}}' "$ca")" A 'A ran another checkout image'
equal "$(docker inspect -f '{{index .Config.Labels "io.bitrix24-cli.isolation-test"}}' "$cb")" B 'B ran another checkout image'
equal "$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Source}}{{end}}{{end}}' "$ca")" "$a" 'wrong A bind mount'
equal "$(docker inspect -f '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Source}}{{end}}{{end}}' "$cb")" "$b" 'wrong B bind mount'

announce 'checkout, dependency, configuration and UID/GID isolation'
for checkout in "$a" "$b"; do
    compose "$checkout" run --rm -T php-cli php -r \
        'foreach (["bcmath", "excimer", "intl", "pcntl", "Zend OPcache", "yaml", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: ".$extension."\n"); exit(1); } } echo "excimer=".phpversion("excimer")." yaml=".phpversion("yaml")."\n";' >>"$results/extensions.log" 2>&1
    compose "$checkout" run --rm -T php-cli php -r \
        'file_put_contents("vendor/.isolation-dependency", getcwd()); file_put_contents(".isolation-owner", posix_getuid().":".posix_getgid());' >>"$results/files.log" 2>&1
    equal "$(cat "$checkout/.isolation-owner")" "$(id -u):$(id -g)" 'wrong container UID/GID'
    owner=$(compose "$checkout" run --rm -T php-cli php -r 'echo fileowner(".isolation-owner").":".filegroup(".isolation-owner");' 2>>"$results/files.log")
    equal "$owner" "$(id -u):$(id -g)" 'wrong generated file owner'
done
compose "$a" run --rm -T php-cli php -r \
    'file_put_contents(".isolation-source", "changed-A\n"); file_put_contents("vendor/.isolation-dependency", "changed-A"); file_put_contents(".env", "WORKTREE_TEST=changed-A\n");' >>"$results/files.log" 2>&1
equal "$(cat "$b/.isolation-source")" checkout-B 'source write affected B'
equal "$(cat "$b/vendor/.isolation-dependency")" /var/www/html 'dependency write affected B'
equal "$(cat "$b/.env")" $'WORKTREE_TEST=B\nCOMPOSE_PROJECT_NAME=incorrect' 'configuration write affected B'
make -C "$a" composer-install composer-dumpautoload composer-validate cli ARGS=--version >>"$results/runtime-a.log" 2>&1
equal "$(cat "$b/vendor/.isolation-dependency")" /var/www/html 'A dependency install affected B'

announce 'shutdown and restart leave the other container running'
make -C "$a" docker-down >"$results/lifecycle.log" 2>&1
equal "$(docker inspect -f '{{.State.Running}}' "$cb")" true 'shutdown stopped B'
equal "$(container "$b")" "$cb" 'shutdown replaced B'
make -C "$b" cli >>"$results/runtime-b.log" 2>&1
make -C "$a" docker-up docker-restart >>"$results/lifecycle.log" 2>&1
equal "$(container "$b")" "$cb" 'restart replaced B'
equal "$(docker inspect -f '{{.State.Running}}' "$cb")" true 'restart stopped B'
equal "$(cat "$b/.isolation-source")" checkout-B 'lifecycle changed B files'
make -C "$b" cli check >>"$results/runtime-b.log" 2>&1

announce 'injected failure cleanup'
if test "${WORKTREE_TEST_FAIL_AFTER_START:-0}" = 1; then fail 'intentional failure to exercise cleanup'; fi
announce 'all acceptance checks completed'
