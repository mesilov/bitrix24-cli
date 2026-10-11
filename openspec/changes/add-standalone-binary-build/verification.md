# Verification — add-standalone-binary-build

Date: 2026-10-11. Issue: [#14](https://github.com/mesilov/bitrix24-cli/issues/14).
PR: [#17](https://github.com/mesilov/bitrix24-cli/pull/17), target `dev`.
Verified core source/publication SHA: `d47b30f0cbedfaf3b63181214660540a113fb9d7`.
Integrated bases: `main` `998644aad5de8ced858a70a45b557947367cc98a`,
`dev` `09c598e1eb085a125d689b1d35c1fe700d9b410a`. Both are ancestors of HEAD.

## Review follow-up status

| Dimension | Result |
| --- | --- |
| Completeness | 20/22 tasks complete; 8 modified requirements reviewed; native source identity/acceptance and final archive tasks await CI |
| Correctness | All 8 requirements and 15 scenarios mapped below; native task acceptance is pending |
| Coherence | Common staging/source policy, checkout isolation, pinned toolchain and existing launcher/DI/task contracts preserved |

Native verification is in progress; archive readiness is not yet claimed.
A subsequent Unix runtime review found missing pcntl for the merged SIGINT contract.
The target-specific pcntl build and controlled task interruption fixture are implemented
and require a fresh PHAR/native acceptance run before completing this report.

## Actual native CI

[Run 38146040105](https://github.com/mesilov/bitrix24-cli/actions/runs/38146040105)
builds the current implementation from the exact source SHA above. Native
Linux amd64, macOS arm64 and Windows amd64 jobs and the final archives gate must
all finish successfully before task completion and archive. Current quality,
unit and worktree CI checks passed; native compilation is still in progress.

The updated harness copies only `b24cli` and its separate diagnostic probe into
a clean temporary directory. PATH and environment exclude build tools and portal
credentials. It checks exact `b24cli dev-<full SHA>` version, help/list, registration
and help for all 30 task commands, JSON usage exit 2, UTF-8 arguments in the real
`task:add --dry-run` plan, and `task:show` connection-unavailable exit 1 with no API
calls. It also checks SDK autoload, required extensions, BCMath/ICU, Unicode,
trusted HTTPS, rejection of an untrusted CA (errno 60), and temporary PHAR cleanup.
Linux executes in the pinned Python-only Debian 12 image without PHP or checkout.

Each target then checks linkage, wrong-version/missing-executable rejection and
checksum → unpack → executable permissions → clean PATH installation. The common
assets job checks all three final archive names, SHA256SUMS, ZIP CRC, notices,
Unix mode 0755, excluded diagnostic files and rejection of corrupted archive bytes.
The next matrix must additionally pass real Unix SIGINT/confirmed-step retention
through production application handlers in the separate offline diagnostic probe.

## Local checks completed

- `make check` passed: strict Composer validation, PHP syntax, startup and registration.
- `make lint-all` passed: allowed licenses, PHP-CS-Fixer (170 files), PHPStan and Rector.
- `make unit` passed: **193 tests / 1157 assertions** for Console, DI, SDK controlled HTTP, timeout and SIGINT. No live portal requests.
- `make test-worktree` passed with fresh concurrent checkouts, matching basenames/spaces, separate image/vendor/cache/config, UID/GID checks, shutdown/restart and injected-failure cleanup. The unrelated dirty primary checkout was preserved.
- `make package-phar` built locked production dependencies using PHP 8.5.11 / Composer 2.10.3 and reported `b24cli dev-d47b30f0cbedfaf3b63181214660540a113fb9d7`. PHAR acceptance passed all updated task/Unicode/TLS checks outside the checkout using an explicitly supplied PHP runtime; this diagnostic result is separate from standalone proof.
- `B24CLI_RELEASE_VERSION=v0.1.0 make package-phar` reported exact `b24cli v0.1.0`. This local identity check created neither a Git tag nor a GitHub Release. Wrong-version and missing-PHAR rejection passed.
- Production PHAR inspection includes `bin/b24cli`, compatible `bin/console`, `config/services.php`, source, production autoload/lock/manifest, BuildVersion, metadata and licenses. Installed package names match production lock exactly with dev=false; dev tools, environment files, vendor tests and build caches are excluded.
- **12 source regression tests** use isolated real Git histories: tagless feature commit, older detached commit, dirty tracked/staged/untracked files, allowed ignored build outputs, rejected ignored application inputs, invalid/mismatched SHA, lightweight and annotated main tags, rejected feature/non-version tags. Python entry points for all 4 package targets and the Linux Docker wrapper reject dirty/mismatched identity before staging or Docker submission.
- **7 publication regression tests**, including create/upload/edit interruption subcases, verify one-time draft creation, repairing partial uploads, complete upload before publication, public-release preservation, fail-closed lookup errors, missing asset rejection and archive-verification gating. The GitHub CLI is modeled with recorded commands/state; no real Release is created, edited or uploaded.
- Python helpers compile; actionlint 1.7.12 passes all workflows; `git diff --check` passes. `openspec validate --all --strict` passed all 10 current changes/specs before sync.

## Production and developer source policy

Production CI is triggered by a concrete version tag. `validate-tag` checks out
that tag with full history, resolves an annotated/lightweight tag to the exact
commit and requires `git merge-base --is-ancestor <sha> origin/main` before any
platform build. Older main commits may be rebuilt; a feature-only tag fails.
All builders receive that resolved SHA and the exact tag name; publishing is
dependent on the complete verified matrix and archive set.

Developer Make commands work at any clean checked-out commit on any branch,
including detached HEAD, without a tag/main requirement. They record `dev-<full
SHA>`. Native builders validate HEAD equality before staging; Linux Make/CI
validates before submitting the Docker context. Ignored runtime/dist/vendor state
is allowed, while ignored application/resource inputs are rejected. A supplied
SHA cannot identify another commit's payload.

Publication uses `gh release view --json isDraft`, which can find a draft by its
pending tag ([official GitHub CLI implementation](https://github.com/cli/cli/blob/trunk/pkg/cmd/release/shared/fetch.go)).
Only the explicit release-not-found result permits draft creation; other lookup
errors stop. All verified assets are uploaded with clobber before changing draft
state. Reruns resume drafts left by creation, partial upload or failed publication;
already public releases remain unchanged. Release concurrency serializes the tag.

## Requirement and scenario mapping

| Modified requirement | Implementation | Scenario evidence |
| --- | --- | --- |
| Standalone platform packages | `build/package.py`, native wrappers, `docker/package/Dockerfile` | Execute outside checkout: native clean-PATH smoke and linkage; current matrix pending |
| Production package contents | `build/config.json`, `build/package-phar.php`, staging and license export | Inspect application: mandatory entry/config, exact production lock, exclusions and archive notices |
| Runtime compatibility | `build/tests/smoke.py`, `runtime-probe.php`, Windows patches | Offline commands: 30 command help/DI, UTF-8 real task plan, usage/connection JSON and unit contracts; HTTPS: trusted + rejected CA |
| Repeatable build inputs and isolation | `build/runtime.py`, config, Make/wrappers | Clean rebuild: exact checkout/pins; isolated staging; actual concurrent worktree regression |
| Package version identity | `build/source.py`, `package.py`, `src/BuildVersion.php` | Release version: exact local v0.1.0; development revision: full SHA; reject mismatched source: real Git fixtures and pre-staging CLI tests |
| CI artifact verification | `package-static.yml`, `package-platforms.yml` | PR build/upload follows smoke; current native run pending; PR workflow has read-only permissions and no publish step |
| Release assets and publication gate | `release.yml`, `publish-release.py`, `release-assets.py`, `archive-check.py` | Verified assets and incomplete-set gate; reject tag outside main via real Git fixtures; resume draft via 7 modeled interruption/error checks |
| Installation and evidence documentation | `build/README.md`, `install-check.py`, this report | Native checksum/unpack/PATH check; readiness records local, CI, modeled publication and live-provider boundaries separately |

All 15 scenario conditions have implementation/test coverage. The actual native
acceptance gates must finish before declaring this report complete. Tag-trigger
routing and publication dependency are actionlint/static validated; main/tag
provenance is exercised using real Git fixtures and draft retry logic is modeled.
Actual GitHub release publication is not represented as successful.

## Inputs and compatibility

PHP `8.5.11`; static-php-cli `5b5861c366a0d94bc84002db7b3f46144b388fbb`;
micro `fb6d497b6f4cf138ee3851a30c905d64b7b19aed`. Unix checkout Composer lock
SHA-256 `fca97d6ebd4b108c3869709b0a6745d7a657447b5bf011a5a8b233d9946b8ee7`.
Metadata records source, lock, toolchain pins, extensions, payload strategy/gzip
compression and patch hashes; source lock records actual downloaded archives.
Repeatable inputs do not assert byte-for-byte reproducibility.

Windows uses documented exact-source compatibility patches for nmake quoting,
UTF-8 argv and temporary embedded PHAR reading/cleanup. Writable TEMP is required.
Linux/macOS retain in-place PHAR. Runtime/license and OS dependency policies are
unchanged. Minimum validation: Debian 12 / macOS 15 arm64 / Windows Server 2022 x64;
older systems and signing/notarization need separate acceptance.

## Historical evidence and boundaries

Initial implementation and archive-only CI passed
[run 38143343522](https://github.com/mesilov/bitrix24-cli/actions/runs/38143343522)
at `c9e79d79fc74d94736eb787ea7c5b35f086fa6cf`, before dev merged task commands.
That evidence explains the original 17 completed tasks; it does not replace the
current native acceptance for the updated task runtime. Earlier packaging/licensing
and corruption investigations remain in Git history.

No PR merge, real version tag, GitHub Release, deployment, signing/notarization
or live Bitrix24 portal request was performed in this follow-up. Branch main/dev
integration is distinct from merging PR #17. Release/main routing has offline
and static coverage; the actual CI event used for native acceptance is pull_request.
