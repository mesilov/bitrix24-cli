# Verification — add-standalone-binary-build

Date: 2026-10-11. Issue: [#14](https://github.com/mesilov/bitrix24-cli/issues/14).
PR: [#17](https://github.com/mesilov/bitrix24-cli/pull/17), target `dev`.
Base: `5d236a084f67b9de2ef99fa99ad3fc586582c20b`; remote `dev` was refreshed before completion and still matches.
Verified implementation SHA: `20625918e15240159214c385126d40d4847da72c`.

## Summary

| Dimension | Result |
| --- | --- |
| Completeness | 17/17 implementation tasks, 8 added requirements reviewed |
| Correctness | 8/8 requirements mapped; all 12 scenarios have implementation and acceptance coverage; publication scenarios checked by workflow inspection/negative runs, without publishing a Release |
| Coherence | Design decisions and repository code patterns reviewed; isolated staging, common builders and version policy preserved |

No critical issues, warnings or suggestions remain. Implementation verification is complete and the change is ready for spec sync/archive. Actual tag publication and live portal testing are outside this implementation's required checks, as stated in design; neither is claimed as performed.

## Actual native CI

[Run 38142687381](https://github.com/mesilov/bitrix24-cli/actions/runs/38142687381) completed **success** for the verified implementation SHA:

| Native target / gate | Job | Result |
| --- | --- | --- |
| Linux amd64, Ubuntu 24.04 builder + clean Debian 12 runtime | 114479605563 | Success |
| macOS arm64, macOS 15 | 114479605385 | Success |
| Windows amd64, Windows Server 2022 | 114479605597 | Success |
| Complete release archive set | 114480550570 | Success |

Each native target passed exact `dev-20625918e15240159214c385126d40d4847da72c` version, help/list with no ANSI, Cyrillic command arguments and usage exit 1, SDK autoload, required production extensions, BCMath/ICU, UTF-8, zlib round-trip, trusted HTTPS and rejection of an untrusted certificate (cURL errno 60). The harness copies only the executable and its separate probe into a clean temporary directory, whitelists OS environment and removes build tools/credentials from PATH. Linux runs in a pinned Python-only container with artifact/fixture mounts and no application checkout or PHP installation.

Linkage inspection passed: Linux has no dynamic interpreter or NEEDED library; macOS imports only system libraries; Windows imports only system/API-contract DLLs and no external PHP, VC runtime or compiler libraries. Every target also passed wrong-version/missing-executable rejection and checksum → unpack → clean PATH installation/version check. Windows temporary PHAR cleanup passed after each command.

Verified artifacts: `b24cli-linux-amd64` (11703527760), `b24cli-macos-arm64` (11702359583), `b24cli-windows-amd64` (11703656189) and `b24cli-release-assets` (11703965831). The common assets job checked the exact three archive names and SHA256SUMS against final bytes, Unix executable mode 0755, ZIP CRC, license notices, exclusion of probe/PHAR/vendor directories and rejection of corrupted archive bytes.

Existing GitHub license, PHP-CS-Fixer, PHPStan, Rector and worktree-isolation jobs also passed on this SHA. The PR remains subject to another complete CI run after the spec/archive-only finishing commit.

## Local checks

- `make check`, `make lint-all` and `make test-worktree` passed. The worktree test covers parallel build/install/startup, vendor/cache/config/image isolation, shutdown/restart and injected-failure cleanup. The user's dirty primary checkout was preserved.
- `make package-phar` executed successfully using the pinned PHP 8.5.11 / Composer 2.10.3 Docker builder. Diagnostic PHAR smoke passed full version/help/list/autoload/extensions/Unicode/HTTPS checks with an explicitly supplied external PHP runtime; this is separate from standalone proof above.
- Production installed package names matched the lock's production set exactly with `dev=false`. Application entry, BuildVersion, autoload, Composer manifest/lock, LICENSE and metadata are present; dev tools, vendor tests and local `.env` are excluded. Application `.env` and directory-symlink staging fixtures were rejected. Runtime notices include PHP/micro/C libraries; Linux also carries actual musl COPYRIGHT and GCC 13.2.0 GPLv3 / Runtime Library Exception texts.
- A separate staging PHAR reported exact release identity `Bitrix24 CLI v0.1.0`; invalid SHA/version fixtures failed before staging. Source files were not rewritten. Source and PHAR `list --raw --no-ansi` matched; Cyrillic unknown-command stderr/exit code matched.
- All Python helpers passed compilation, PHP packaging/probe/stub scripts passed syntax/style checks, and actionlint 1.7.12 passed all three packaging workflows. Unsupported Linux/Windows native targets were rejected on the local macOS arm64 host.
- Archive fixtures passed exact names, checksums, permissions and notices; corruption and missing target fixtures failed. These preliminary fixtures are distinct from the actual native archive job above.
- The actual `b24cli-release-assets` artifact was downloaded locally; archive checks passed again on its exact bytes. All three build-info/source-lock files matched the source/PHP/SPC/micro and physical lock/patch identities. Windows checkout uses CRLF, so its recorded file hashes match the CRLF representation of those same Git files. The downloaded macOS archive was extracted into another temporary directory and its native binary reported the exact full-SHA version with a clean PATH.
- A downloaded earlier genuine Linux binary also passed the strengthened Unicode/HTTPS harness in clean Debian 12 and the revised PATH installation checker locally. These local executions used amd64 emulation; current native CI provides the platform evidence.
- Exact-match Windows patches were checked against the pinned SPC/micro sources. Micro archive reserialization preserved the original SHA-1 in runtime-sources and recorded the patched hash; patch SHA-256 values are in build-info/cache keys. Token/base64-log redaction fixtures passed without retaining real credentials.

## Inputs and compatibility decisions

PHP: `8.5.11`; static-php-cli: `5b5861c366a0d94bc84002db7b3f46144b388fbb`; micro: `fb6d497b6f4cf138ee3851a30c905d64b7b19aed`. Build metadata records source SHA, Composer lock SHA-256, these pins, extensions, payload strategy/compression and compatibility patch hashes. Source lock records actual downloaded libraries and micro archive hashes. Build inputs are repeatable; byte-for-byte reproducibility is not asserted.

Windows native acceptance exposed an upstream nmake quote error, non-UTF-8 argv and corrupted direct micro PHAR reads. Exact source patches address quoting/argv; bootstrap copies embedded gzip PHAR through the raw micro stream into a unique writable TEMP file and uses the normal PHAR wrapper, then removes it. Linux/macOS retain in-place PHAR. These decisions are reflected in design and installation prerequisites. Minimum validated systems are Debian 12 / macOS 15 arm64 / Windows Server 2022 x64; older systems and signing/notarization need separate acceptance.

## Requirement and scenario mapping

| Added requirement | Implementation | Scenario evidence |
| --- | --- | --- |
| Standalone platform packages | `build/package.py:52`, native wrappers, `docker/package/Dockerfile` | Execute outside checkout: all three native smoke/linkage jobs |
| Production package contents | `build/package.py:31`, `build/package-phar.php:20`, config manifest and runtime license export | Inspect packaged application: exact locked production set, mandatory PHAR entries, staging exclusions, archive notice checks |
| Runtime compatibility | `build/tests/runtime-probe.php`, `build/tests/smoke.py:47`, Windows argv patch | Offline compatibility: source/PHAR comparison and native Unicode/exit checks; HTTPS trust: success + errno 60 on all targets |
| Repeatable build inputs and isolation | `build/runtime.py:134`, `build/config.json`, Make/wrappers, checkout-local runtime/dist | Clean rebuild: actual clean GitHub checkouts and pinned builders; isolation: existing worktree regression and preserved primary checkout |
| Package version identity | `build/package.py:17`, `src/BuildVersion.php`, generated staging build-info | Release version: local exact v0.1.0 PHAR and tag-input workflow review; development revision: full SHA checked on all native binaries, wrong-version rejection |
| CI artifact verification | `.github/workflows/package-static.yml`, `package-platforms.yml` | PR packaging: actual successful four-job run; upload follows acceptance; prior failed Windows runs skipped the dependent assets job; PR has read-only permissions and no publish job |
| Release assets and publication gate | `build/release-assets.py`, `build/tests/archive-check.py`, `.github/workflows/release.yml` | Verified release preparation: actual three-archive set/checksums; incomplete release rejection: missing target/corruption fixtures, observed failed-matrix dependency skip and inspected publish `needs: packages` |
| Installation and evidence documentation | `build/README.md`, `build/tests/install-check.py`, this report | Verified archive install: all three native install checks; readiness report: local/CI/static-release/live-portal facts separated |

All 12 scenario conditions were reviewed against code and the evidence above. Release publication behavior was checked statically (tag convention, exact version input, complete-matrix dependency, archive re-verification, draft-before-public publication) and with preparation/rejection fixtures. No actual publication run is represented as successful.

## Evidence boundaries

No merge, version tag, GitHub Release, deployment, live Bitrix24 portal request or signing/notarization was performed. PR/push/manual event routing and the version-tag publication dependency were inspected and actionlint-validated; actual execution in this session is the PR event. The current `dev` application is a Symfony Console skeleton; task commands from unmerged feature branches are not present and cannot be claimed as packaged portal/business-command acceptance.
