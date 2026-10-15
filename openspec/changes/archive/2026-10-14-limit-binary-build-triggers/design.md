# Design

## Context

See `proposal.md` for motivation. `package-static.yml` calls `package-platforms.yml` on PRs to `dev`/`main`, pushes to `dev` and manual dispatch. `release.yml` calls the same builder after validating a version tag against `main` history. The source test and linter workflows are independent of packaging.

## Goals / Non-Goals

**Goals:** avoid starting the native platform builders during routine development while retaining explicit development builds and the complete release pipeline.

**Non-Goals:** change builders, runtime caches, artifact retention, billing settings, application behavior or source quality checks.

## Decisions

1. Keep only `workflow_dispatch` in `package-static.yml`. Use `github.sha` as the exact selected revision. Branch/path filters and a PR label would still permit automatic builds; the user explicitly chose manual development builds.
2. Leave `package-platforms.yml` and `release.yml` unchanged. Both manual and release runs continue to use the same platform verification and archive generation. Manual packaging keeps read-only permissions and cannot publish a release.
3. Document Actions → Package standalone binaries → Run workflow → branch selection, plus `gh workflow run package-static.yml --ref dev`. The workflow already exists on the default branch, satisfying GitHub's manual-dispatch availability requirement.
4. Verify the trigger policy by parsing workflow YAML and comparing the release/platform/quality workflows with the base revision. Run existing source/publication contract checks without compiling binaries. No new persistent test suite or dependencies are needed for this small configuration change.

## Risks / Trade-offs

- Packaging regressions may be discovered later → manually dispatch a build for packaging-sensitive changes before release; release publication still requires all platform checks.
- A required packaging check could block PR merging after its trigger is removed → the live `dev` branch has no required checks; the active `main` ruleset contains only deletion/non-fast-forward protection, checked on 2026-10-13. No branch-rule changes are needed.
- The selected branch must contain the manual workflow → keep the existing filename and explain ref selection in the documentation.

## Migration Plan

Deliver the workflow, docs and delta specification through a PR to `dev`. The normal release promotion to `main` carries the same policy. A rollback restores the previous event block in `package-static.yml`; release assets need no migration.

## Sources

- [Manual workflow dispatch](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow)
- [Required checks and workflow filters](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/trigger-a-workflow)
- [GitHub Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions): standard runners in this public repository have free compute; this change reduces unnecessary builds and generated artifacts.
