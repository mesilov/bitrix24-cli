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


## Completion verification — 2026-10-14

Fresh development source: `4221b746ee2a42ea4ebbfd08e2f48d26f73c6142`. Original implementation head: `265d4836be55de85c885f5b57360730b3cf4a57a`.

- PR [#79](https://github.com/mesilov/bitrix24-cli/pull/79) is merged into `dev`, merge commit `19808ad7e491ffc8148a41e4501b1303e9cc8139`; ancestry in fresh `origin/dev` confirmed. Issue #77 was closed as completed on 2026-10-14.
- GitHub terminal evidence for the original implementation head: 13/13 check runs successful, 11/11 workflow runs successful (6 pull_request, 5 push). No `Package standalone binaries` runs exist for that head. These are source CI receipts, not native binary acceptance.
- Re-parsed all 10 current workflows, accounting for YAML 1.1 parsers representing `on` as a boolean key. Manual caller has only workflow_dispatch, read-only permissions and a selected source SHA; platform builder has only workflow_call; release caller has only version-tag push, main-history validation and package/archive gates before publication. All five source test/linter workflows retain push and pull_request.
- Manual and release callers are unchanged since implementation head. Shared builder changes since that head add shell-installer checks on Linux/macOS; existing gates remain. This archive delivery changes no workflow or runtime file.
- Re-ran source-check (12 tests) and publish-check (7 tests): all passed, using local fixtures without compilation or release publication. Strict OpenSpec validation before sync: 26/26 items passed.
- Live rules: `dev` has no applicable rules; `main` has only deletion/non-fast-forward protection. Neither requires packaging checks.
- Compared the delta with the main `binary-distribution` requirement, synchronized its complete block and four scenarios, preserving all other requirements and Purpose. Post-sync strict spec validation: 18/18 passed; an exact block comparison confirms no pending delta.
- No critical issues, warnings or suggestions in the applicable configuration/spec/design checks. Native builds, release publication and portal/API checks are outside this trigger-only change.

Archive path: `openspec/changes/archive/2026-10-14-limit-binary-build-triggers/`. Follow-up delivery must merge the archive and synced main spec through a PR to dev before removing the task worktree.
