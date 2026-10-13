# RC verification: add-ci-linters — 2026-10-12

Baseline: `dev d145b226fd048423c2ce2af93b28efd733d4ccec`, issue [#4](https://github.com/mesilov/bitrix24-cli/issues/4). Reviewed proposal/design/spec/tasks and [original evidence](verification.md).

| Dimension | Result |
| --- | --- |
| Completeness | 10/10 retained tasks; 5 requirements implemented. Fork execution remains excluded from required RC acceptance and open in #41/0.2.0. |
| Correctness | Four separate push/PR workflows; same locked Docker/Make tools; nonzero errors, check/dry-run modes and CLI analysis paths confirmed. Local make check/unit/lint-all passed. |
| Coherence | Minimal contents:read, no portal credentials or registry login; checkout-specific Compose, UID/GID, existing tool configurations preserved. |

Requirement/scenario mapping:

- Automatic quality checks → `.github/workflows/{license-check,php-cs-fixer,phpstan,rector}.yml`, push/pull_request and internal CI runs in original verification. Fork configuration is present; actual external fork is **not verified**, tracked in #41.
- Reproducible local commands → Makefile lint targets, composer.lock and Docker Compose wrapper; fresh worktree docker-init and all four linters passed.
- Failures remain visible → direct Make/workflow exit propagation, original eight negative probes and actual failed preparation run; no continue-on-error. Negative mutation probes were not repeated during docs-only maintenance.
- Checks do not rewrite source files → PHP-CS-Fixer check and Rector --dry-run; no source diff after checks.
- CLI analysis scope → `.php-cs-fixer.php`, phpstan.neon.dist and rector.php include src/bin/console; current successful analysis of populated codebase.

Local combined run: **366 tests / 2104 assertions**, Composer strict/platform checks, PHP syntax/startup, allowed licenses, PHP-CS-Fixer, PHPStan and Rector passed. No new live fork/portal test was run. No critical issue in retained RC scope; external fork runtime evidence remains a stated limit. Archive preserves the original records.
