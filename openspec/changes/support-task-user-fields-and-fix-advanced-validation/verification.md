# Verification — issue #58

Verified on 2026-10-14 using `openspec-verify-change`. Change: `support-task-user-fields-and-fix-advanced-validation`.

## Completeness, correctness and coherence

| Dimension | Result |
|---|---|
| Completeness | All 9/9 tracked tasks completed; PR #127 is OPEN to dev and its initial head/CI snapshot is saved in delivery.json. Ten added/modified requirements are mapped below. |
| Correctness | Local unit/contract and four live CLI cases cover the declared behavior; strict OpenSpec validation passes. |
| Coherence | Single REST1 custom create, exact native JSON, bounded scalar/list input, explicit schema read and versions, documented partial semantics and existing tag/observer behavior match the accepted revised design. |

## Requirement and scenario evidence

- `Explicit custom value input`: CustomTaskFields, InputSourceReader::uniqueObject and TaskInputMapper; TaskUserFieldsTest invalid JSON/duplicate escaped keys/system fields/scalar lists/null/object rejection, inline/file/stdin paths and type preservation.
- `Bounded custom schema preflight`: scoped adapter fields() and CustomTaskFields::validate; unknown/type-mismatch tests prove no writes; live unknown rejection has only one schema read. Missing mandatory/multiple metadata is not inferred; required-field validation remains server-side.
- `Single request custom task creation`: AddTaskHandler and LegacyTaskFields; payload tests prove one REST1 add with native ordinary/custom fields, native JSON alias rejection and profile/default precedence. Fresh CLI creation readback matches required string, numeric and multiple values; missing required fields fail and exact-title lookup finds no task.
- `Declared updates and strict policy`: UpdateTaskHandler, explicit catalog/guard; custom-only has no empty v3 patch, mixed update is v3 then custom v1, optional tags follow. Unit/portal checks prove strict denial before requests and dry-run schema reads without writes.
- `Recoverable composed outcomes`: existing ExecutionLedger, custom ACK validation and handlers; fixture tests cover denial, malformed/mismatched ACK, known task IDs, partial, outcomeUnknown, skipped tags, cancellation/130 and no dependent writes after rejected create. Custom+tags+profile observer success and failures are verified by unit tests.
- `Explicit patch and bounded advanced input`: FieldSchema local add/update validation and existing convenience field restrictions; tests cover names/types/booleans/enums/lists/ISO dates/read-only status and relations. Fresh ordinary advanced create contains only one v3 add; old editable gate is absent.
- `Planned task tag routes`: existing tag tests retain their native payloads and strict/partial semantics; custom creation's declared legacy exception is explicit in the delta.
- `Creation and observer assignment have explicit outcomes`: existing cancellation/observer regressions plus custom creation composition checks preserve known IDs and stop companions.
- `Milestone custom field admission` and `API admission and future lifecycle acceptance remain explicit`: new gateway/catalog permits only explicit custom schema/add/update; system UF fields and arbitrary legacy fields/raw status stay blocked.

All 29 scenarios were inspected against these paths and tests. No implementation gaps, critical findings or specification divergences were found. Delivery status remains separate from code/portal verification.

## Checks

- Final `make test-unit` after latest dev merge: **796 tests, 12,545 assertions, PASS**.
- Fresh feature CLI portal tests: **4 tests, 63 assertions, no skips, PASS**. Tests are now grouped in existing AddTaskCommandTest/UpdateTaskCommandTest under the repository's one-class-per-command convention; the receipt preserves names recorded during execution before that grouping-only move.
- `make check`: PASS (Composer validation, PHP syntax, platform requirements and CLI startup); final merge recheck is recorded in the delivery receipt.
- `make lint-all`: PASS, including license checker, CS Fixer, PHPStan and Rector, after merge and final integration-test placement.
- `openspec validate support-task-user-fields-and-fix-advanced-validation --strict`: PASS; `git diff --check`: PASS.
- Mandatory fresh origin/dev merge completed; changelog conflict resolved with both #58 and #118 entries. PR diff retains substantive #58 Unreleased entries.
- Issue #58 is assigned to mesilov, label bug, milestone 0.2.0, OPEN; proposal and issue contain the change path and reciprocal issue link.

## Portal boundary and cleanup

Receipt: `research/2026-10-14-cli-acceptance.json`; prior SDK/API research: `research/2026-10-14-sdk-portal-probe.md/json`.

Portal: SDK playground. The available string/numeric/multiple field codes were discovered from its schema; **UF_QUERY and the reporter's portal were not available**. Restricted-role live acceptance was not run; denial/cancellation/unknown cases are fixture tests. This does not claim universal portal or all custom-type support.

Owned task IDs **6296, 6298, 6300, 6302** were all deleted with no cleanup failures. Independent REST1 task list filtered by those IDs returned **zero tasks**. The rejected required-field create also left no task under its exact title. No field definitions or unrelated tasks were changed.

## Delivery

Requested scope is a reviewed implementation PR to dev, not merge or release. The active change is retained until merged completion; then archive/sync/issue-path update/worktree cleanup follow maintainer instructions. CI state must be read from the published head, not inferred from local or portal checks.

PR: https://github.com/mesilov/bitrix24-cli/pull/127. Initial head d5c0a48c7e1e1a5d2289360317821d51da7e4ef8; terminal CI must be verified against the final published head. The PR is not merged and no release is published.

## Review follow-up — 2026-10-14

The automated review of PR #127 identified missing JTBD documentation. `docs/task-jtbd.md` now records the new public options in PM-01/AM-01/AM-04, command/test links, source SHA and bounded live evidence. Existing 22 scenario IDs and business coverage limits are preserved. Documentation paths were checked locally; no runtime code changed in this follow-up.
