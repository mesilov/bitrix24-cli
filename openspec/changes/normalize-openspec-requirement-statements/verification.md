# Verification: normalize-openspec-requirement-statements

Дата: 2026-10-14 (Asia/Bishkek). Issue: [#114](https://github.com/mesilov/bitrix24-cli/issues/114).
Исходный dev: `f21e0632c9fab5933b632bd649a214d5b7df49f6`. OpenSpec CLI: `1.14.1`.
Повторная проверка выполнена после fresh fetch и merge dev `1741b9c5104c47a211904a47185aa442229c12d6`; четыре затронутых canonical specs в этой базе не изменились.

## Scorecard

| Dimension | Result |
| --- | --- |
| Completeness | 5/5 tasks complete; MR #117 opened in dev. All 5 MODIFIED and 11 ADDED blocks applied to canonical specs. |
| Correctness | 16/16 delta requirement blocks match main specs; all original normative clauses mapped below. All 235 old scenarios in the four capabilities preserved. Existing implementation/test references reviewed; runtime suite delegated to native MR CI. |
| Coherence | Original requirement names, Purpose and untouched blocks retained. No runtime, dependency, JSON-schema, JTBD or historical-archive edits. |

## Strict validation

Baseline `openspec validate --specs --strict`: 12 passed / 4 failed, five warnings for statements longer than 500 characters. After normalization: 16 passed / 0 failed, no warnings. `openspec validate normalize-openspec-requirement-statements --strict` passes. `git diff --check` passes.

| Capability / existing requirement | Before | After |
| --- | ---: | ---: |
| cli-experience / Explicit file and structured input | 514 | 213 |
| task-console-runtime / Exact MVP command registration | 566 | 410 |
| task-console-runtime / Policy covers the complete route plan | 613 | 292 |
| task-mvp-scope / API admission and future lifecycle acceptance remain explicit | 734 | 359 |
| connection-configuration / Published configuration schema | 521 | 216 |

All 11 extracted statements are also at most 500 characters. `evidence.json` records lengths and preservation counts.

## Contract mapping

Every row maps the complete original statement into retained and extracted requirements. Extraction does not add an API route, input mode or configuration field.

| Original requirement | Retained clauses | Extracted clauses and target requirements |
| --- | --- | --- |
| Explicit file and structured input | Literal text / JSON `--fields` / JSON file `--fields-file`; text/JSON PATH or `-`; validate format/accessibility before writing. | Local binary PATH only and task-file-upload restrictions → **Binary attachment input restrictions**. Advanced mode cannot mix edit flags → **Advanced field input is a separate mode**. No implicit stdin read or two consumers; terminal rejection already present in original scenario → **Explicit stdin ownership**. |
| Exact MVP command registration | 34 task = 30 original + 4 result; four users, separate connection/help/completion; single CommandCatalog, no tags/compiler pass/scanning; excluded operations cannot return via aliases. | Unit checks for unique names/classes, matching AsCommand and excluded task operations → **Command registration validation**; alias check reflects the retained existing prohibition. |
| Policy covers the complete route plan | Check all possible routes including preflight by method/API version and fields; strict-rest3 rejects every non-v3 plan before first request; equal method names never imply version interchangeability or fallback. | Selected REST3, IM companion, scoped REST1 tasks, users, opt-in observer after task:add, selected task:show enrichment and task:list/find role reads → **Default policy selected route admission**. REST1 disk.folder.uploadFile only in local task:file:attach → **Local attachment upload route admission**. |
| API admission and future lifecycle acceptance remain explicit | 59 = 30 MVP + 0 pending + 29 later; rights checked for candidates; lifecycle remains later irrespective of writable status; catalog presence does not prove other exceptions, semantics or runtime readiness. | 4 time / 8 checklist-root-item / 2 participants-history and selected task:show companion reads → **Initial scoped REST1 task admission**. Milestone 0.2.0 REST1 tasks.task.update only with TAGS in task:add/update → **Milestone tag write admission**. Milestone 0.2.0 REST1 disk.folder.uploadFile only explicit local task:file:attach → **Milestone local Disk upload admission**. |
| Published configuration schema | docs/configuration.md and docs/config.schema.json; Draft 2020-12/version 1; document runtime/filesystem-dependent checks. | Version 1 objects, required metadata, aliases, safe credential references, API-named task defaults, local default.users selectors/opt-in boolean, root telemetry, strict types → **Configuration schema structural coverage**. Shared valid/invalid structural fixtures and schema/runtime agreement → **Shared configuration structure fixtures**. |

## Preservation check

Compared `git show <baseline>:openspec/specs/<capability>/spec.md` with working files using a temporary standard-library Python helper:

- Parse requirement blocks and reject duplicate requirement names.
- Compare the complete title/Purpose prefix byte-for-byte.
- Require all original requirement names; compare untouched bodies and original scenario sections verbatim, ignoring only blank separator lines outside blocks.
- Compare every MODIFIED/ADDED delta block with the matching canonical block; require exactly the planned added names and statements at most 500 characters.
- Confirm 67 original requirements and 235 original scenarios across all four files; 11 added requirements and no removed requirement. No ADDED/MODIFIED operation headers leak into canonical specs.

| Capability | Original requirements preserved | Original scenarios preserved | Supporting requirements added |
| --- | ---: | ---: | ---: |
| cli-experience | 16 | 80 | 3 |
| task-console-runtime | 21 | 71 | 3 |
| task-mvp-scope | 7 | 21 | 3 |
| connection-configuration | 23 | 63 | 2 |
| Total | 67 | 235 | 11 |

## Existing implementation and coverage references

- Input requirements: `src/Console/InputSourceReader.php`, `src/Console/TaskInputMapper.php`; `tests/Unit/CommandBehaviorTest.php` (invalid/mixed JSON, sparse patch, stdin conflict), `tests/Unit/LocalFileUploadTest.php` (local source validation and strict mixed-plan gate).
- Registration: `src/Bootstrap/CommandCatalog.php`; `tests/Unit/ContainerRegistrationTest.php` and `tests/Unit/CommandContractsTest.php` (explicit catalog, AsCommand, namespace inventory and aliases).
- Route admission: `src/Console/ApiPolicyGuard.php`, `src/Bootstrap/CommandCatalog.php`; `tests/Unit/CommandBehaviorTest.php`, `tests/Unit/TaskTagsTest.php` and `tests/Unit/LocalFileUploadTest.php` (pre-request strict rejection, narrow TAGS/Disk payloads). The release catalogue totals remain existing specification obligations, not a new release-acceptance claim.
- Configuration: `docs/config.schema.json`, `docs/configuration.md`, `tests/Fixtures/configuration/schema-cases.json`, `tests/Unit/ConfigSchemaContractTest.php`, `build/tests/config-schema-check.py`; `.github/workflows/command-tests.yml` runs both validators in native CI.

This is a documentation normalization. Local PHP/runtime tests and live portal acceptance were not rerun. Existing tests are references for unchanged behavior; native MR CI is tracked on the final head SHA separately. Official Bitrix24 documentation MCP search/details succeeded; sources and the documentation-only boundary are in `design.md`.

## Delivery and assessment

No semantic-loss or design-coherence findings in the checks above. Fresh fetch/merge and repeated validation are complete. [MR #117](https://github.com/mesilov/bitrix24-cli/pull/117) is open into dev. Issue #114 remains open with milestone 0.2.0 and documentation category; proposal and issue contain both trace directions. Native CI results are tracked on the final head SHA in the MR. The change remains active for review; archive and cleanup follow a later confirmed merge.
