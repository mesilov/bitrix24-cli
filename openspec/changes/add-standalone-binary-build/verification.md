# Verification — 2026-10-11

Issue: https://github.com/mesilov/bitrix24-cli/issues/14
PR: https://github.com/mesilov/bitrix24-cli/pull/17
Base: `5d236a084f67b9de2ef99fa99ad3fc586582c20b` (`dev`).
Current native acceptance run: https://github.com/mesilov/bitrix24-cli/actions/runs/38135985985
Source for this run: `34dfe43ac969c7033ef7014bf6ea5346039cc26b`.

## Local checks completed

- `make check`, license checker, PHP-CS-Fixer, PHPStan and Rector passed. The initial Rector finding in BuildVersion was fixed and the dry run repeated successfully.
- `make test-worktree` passed against committed implementation: parallel build/install/startup, isolated vendor/cache/config/images, shutdown/restart and injected-failure cleanup. Primary dirty checkout status remained unchanged.
- `make package-phar` succeeded from implementation HEAD using the pinned Docker builder. PHAR smoke passed with `dev-34dfe43ac969c7033ef7014bf6ea5346039cc26b`.
- PHAR smoke copied only application/probe into a temporary directory, used a clean PATH and environment, and passed exact version, help/list, usage exit 1, SDK autoload, production extensions, BCMath, ICU normalization, UTF-8, trusted HTTPS and rejected certificate (cURL errno 60).
- Production PHAR inspection found entry point, BuildVersion, autoload, Composer manifest/lock, LICENSE and build metadata. No dev executables, vendor tests or `.env` remained. Production installed-package names matched `composer.lock` exactly with `dev=false`.
- Application staging `.env` and directory symlink fixtures were rejected. Vendor-shipped `.env` samples are excluded rather than read or packaged.
- A separate staging PHAR reported `Bitrix24 CLI v0.1.0`. Source files were not rewritten. Invalid SHA/version, missing executable and wrong version fixtures failed acceptance.
- Source and PHAR `list --raw --no-ansi` outputs matched exactly; an unknown Cyrillic command preserved exit 1 and the same stderr.
- All new Python files compiled; PHP build/probe scripts passed syntax and style checks. actionlint 1.7.12 passed the three packaging workflows.
- Three-platform archive *fixtures* passed exact archive names, checksums, Unix executable permissions and notice checks. A corrupted archive and a missing platform executable failed verification. These fixtures are not evidence of native binaries.

## Native platform and CI checks

Pending the actual platform jobs and final artifact inspection. No task requiring native acceptance is marked complete yet.

First run `38135599370` exposed an old Debian Python extraction API and an outdated macOS setup-php action. Both were corrected. Windows was cancelled by the next source push. This run did not upload verified platform artifacts or publish a Release.

## Requirement mapping and final assessment

Pending final native acceptance and scenario review. Packaging is implemented in `build/`, `docker/package/` and the reusable platform/PR/release workflows. The current `dev` application is a Symfony Console skeleton; business task commands from other open feature branches are not included.

## Evidence boundaries

- Local PHAR acceptance used an external PHP runtime deliberately. It does not establish standalone binary compatibility.
- Actual native CI and archive acceptance will be recorded from completed jobs/artifacts.
- Version-tag trigger and the publish dependency gate are subject to static/dry-run validation; no actual tag or GitHub Release is created by this implementation session.
- No merge, production deployment, live Bitrix24 portal request or signing/notarization was performed.
