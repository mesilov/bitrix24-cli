# Verification: limit-binary-build-triggers

Date: 2026-10-13. Base revision: `96ce5028072f90afe70104660b67e02c528d63de` (`dev`).

| Dimension | Result |
| --- | --- |
| Completeness | 4/4 implementation tasks; 1 modified requirement with 4 scenarios |
| Correctness | All 4 scenarios mapped to workflow configuration and existing verification gates |
| Coherence | Manual-only development caller; shared builders, release gate and source quality workflows preserved |

## Scenario evidence

- **Manually package a development revision:** `package-static.yml:4` retains `workflow_dispatch`; line 15 calls the shared platform workflow and line 17 passes the selected `github.sha`. Permissions remain `contents: read`. The unchanged shared workflow checks out this input, verifies all three native packages and uploads the complete artifact set. The caller has no publication job.
- **Package a pull request:** parsed event keys contain only `workflow_dispatch`; `pull_request` is absent. Command tests, schema checks and linters retain their push/PR triggers.
- **Skip automatic branch packaging:** `push` is absent from the development caller. All five source test/linter workflows are byte-identical to the base revision and retain `push` and `pull_request`.
- **Preserve release-tag packaging:** `release.yml` is byte-identical to the base revision. Its only trigger is a push with `tags: ['*.*.*']`; `validate-tag` precedes shared packaging, and publication depends on successful packaging and archive verification.

## Executed checks

- Ruby YAML parsing: all 9 workflow files parsed successfully.
- Parsed trigger/revision/permission checks: manual-only development packaging, reusable-only platform builder, tag-only release caller, selected source SHA and read-only manual permissions passed.
- Compared all 8 other workflow files with the base revision: unchanged. Five source test/linter workflows retain both event types.
- `python3 build/tests/source-check.py`: 12 tests passed.
- `python3 build/tests/publish-check.py`: 7 tests passed, using fake GitHub state without publication.
- `openspec validate limit-binary-build-triggers --strict`: passed after preserving and updating the existing `Package a pull request` scenario name in the delta.
- `git diff --check`: passed.
- Documentation review: both guides name the existing workflow, selected `dev` ref and `b24cli-release-assets` artifact correctly.

## GitHub trace and branch rules

- [Issue #77](https://github.com/mesilov/bitrix24-cli/issues/77) is open with milestone `0.2.0`, label `enhancement` and the correct change ID/path in `## OpenSpec`.
- `proposal.md` links back to issue #77.
- Live branch/ruleset reads on 2026-10-13: `dev` has no required status checks; the active `main` ruleset contains deletion/non-fast-forward protection and no required packaging checks. No branch-rule changes are required.

## Evidence limits and assessment

No critical issues, warnings or suggestions found in the configuration, completeness, scenario mapping and pattern checks. REST API-contract checks are not applicable: no application/API changes.

Native packaging, a new release and portal calls were not executed for this trigger-only change. Manual/release scenario evidence comes from the parsed callers, unchanged platform/publication gates and existing source/publication tests; it does not claim new binary acceptance. Remote source CI will be inspected after the review PR is opened.

The change is ready for review. Archive/spec sync and worktree cleanup follow confirmed merge to `dev` and issue closure under the maintainer workflow.
