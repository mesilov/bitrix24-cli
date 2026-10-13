# Verification: improve-task-cli-dx

Дата: 2026-10-13. Issue [#12](https://github.com/mesilov/bitrix24-cli/issues/12), milestone 0.2.0; implementation PR [#94](https://github.com/mesilov/bitrix24-cli/pull/94) → dev.

Runtime commit: `ab95cbf`; published integration commit: `5350b6dbbff93239b8358991cb0116e444b6a7cf`. Planning base `0aeb25b` refreshed by merge of current dev `79a4f9f9b480aa4139de87b5250c25eb3885a1d0`. The intervening installer delivery changed docs/CI/binary-distribution only; `git diff ab95cbf 5350b6d -- src config tests composer.json composer.lock` is empty. Primary dirty checkout was preserved.

## Summary

| Dimension | Result |
| --- | --- |
| Completeness | 16/16 tracked tasks; 6 MODIFIED requirements, 42 scenarios; no skipped applicable checks |
| Correctness | All 6 requirements mapped to code and offline checks; new scenarios plus retained baseline regression scenarios covered |
| Coherence | Existing result/query/scanner contracts retained; pure renderer, shared mapper, native Symfony help/parser integration; no new dependencies or API routes |

## Requirement and scenario coverage

| Requirement | Scenarios | Implementation | Offline evidence |
| --- | ---: | --- | --- |
| Discoverable help without remote operations | 3 | TaskHelpCommand, TaskHelpDescriptor, TaskUsage | UsageDiagnosticsTest checks every task command's direct/explicit help, ordinary examples before options/advanced details, native help formats, list/version and zero API/credentials; existing role/group/order completion checks retained |
| Human output and composable structured output | 6 | ResultPresenter, TaskHumanRenderer | TaskPresentationTest checks semantic card, available names/ID fallback, additional nested fields, identical call ledger/select across human/plain/JSON, narrow Unicode and wide tables, literal markup/control handling; CommandBehaviorTest retains mutation/envelope/TSV contracts |
| Explicit filtering and completeness of lists | 11 | Existing TaskListScanner; new summary/hints in renderer/presenter | TaskPresentationTest complete limited/empty and all current partial reasons/verbosity; CombinedTaskSearchTest first-page/next-page/role-read boundaries; RecentTaskScanTest id DESC, explicit order, scan cap and duplicate-check diagnostics in all modes |
| Actionable errors and stable exit status | 5 | UsageDiagnostics, B24Application, FieldSchema, TaskUsage | UsageDiagnosticsTest unknown/missing/extra/conflicting input, safe suggestions, secret-bearing argv, no API, NO_COLOR/TERM=dumb/quiet/silent; existing CommandBehaviorTest policy gates and runtime error/binding/cancellation tests retain exit semantics. Existing gated policy behavior is checked; this change does not add excluded lifecycle commands |
| Bounded task search by title | 13 | FindTaskCommand and shared TaskInputMapper; unchanged TaskQuery/TaskListScanner | CombinedTaskSearchTest 0/1/many, predicates on page 2, repeatable server IDs, where-file stdin/role/AND/conflicts, explicit order/tie-breaker, fixed id/title JSON/TSV, cap/missing data and role policy; ScanningAndBindingTest and RecentTaskScanTest retain literal Unicode/empty/bounded search checks |
| Role filters compose with existing query criteria | 4 | Shared query validation; unchanged role identity/scanner/routes | CombinedTaskSearchTest role+where/ordinary criteria, contradictory responsible, candidate-only participant reads and strict-rest3 before API; TaskRoleFilterTest retains list predicates, selected profile/identity, cancellation and partial membership behavior |

All 21 original CLI scenarios are retained verbatim in five MODIFIED blocks. All four role-query scenarios remain, with the agreed expansion of Role and title search. Canonical specs are not overwritten before archive.

## Local validation

- `make test`: **626 tests, 5078 assertions**, passed in Docker/PHP 8.4.26.
- `make check`: Composer strict validation, PHP syntax, platform requirements and CLI startup passed.
- `make lint-all`: allowed licenses, PHP-CS-Fixer, PHPStan and Rector passed.
- `openspec validate improve-task-cli-dx --strict`: valid.
- `git diff --check`: passed.
- Bitrix24 MCP effective config and real search/details confirmed; official REST3 references and limits are recorded in design.md. API field names stay id/title/responsibleId/groupId/status/deadline. Without temporal flags, server filter stays id; explicit existing participant-role REST1 reads remain gated by policy.

Additional Unicode table regression caught visual-width overflow and repeated interpretation of markup during Symfony cell wrapping. Cells now wrap by grapheme/display width before Table rendering; literal markup and every wide character are retained and terminal control bytes are escaped.

## Reproduced behavior on offline fixtures

- `task:show 123`: ordered card with available responsible/group names and IDs, status label/raw value, deadline/description; same one API call and requested projections.
- `task:find --titel договор`: concrete --titel/--title advice and valid example; usage-error/exit2, zero API calls and no argument values echoed.
- `task:find --title договор --group-id 7 --responsible 42 --json`: succeeds, only id/title items, id DESC and complete metadata.
- `task:list --limit 1`: human shows returned=1/matched=3/scanned=3, complete=true and limitApplied=true; JSON keeps its envelope and TSV stdout keeps its columns with summary in stderr.

## Findings and delivery boundary

No critical issues, warnings or suggestions found in the applicable local completeness/correctness/coherence checks. The earlier installer integration left runtime code unchanged; the final integration of dev at 674ccc0 preserves PR #98 temporal filters and adds shared conflict validation for combined find; machine shapes, native verbosity, bounded scanning and current API gates are preserved.

Remote CI status is a separate check on the published SHA. Live portal acceptance remains [#42](https://github.com/mesilov/bitrix24-cli/issues/42); native release binaries and live platform terminals were not tested for this DX change. Local fixtures and documentation checks do not establish portal permissions or universal platform/provider support.

Implementation is verified locally and submitted for review. Archive/sync and worktree removal follow confirmed merge into dev and closure of #12; the change remains active while PR #94 is open.

## Final pre-merge integration and live smoke

2026-10-13: current dev `674ccc0` includes PR #98. The two overlapping query/help files were merged preserving both changes; temporal flags reject where/where-file/due-before in both commands. `make test` passed with **667 tests, 5433 assertions**; `make check`, all four `make lint-all` checks, strict change validation and diff whitespace checks passed. CHANGELOG records #12 in Unreleased.

Read-only public CLI calls on the configured test portal showed tasks 5286 and 5284, task 5286 card with responsible.name, combined title/responsible/status/due-before yielding one result, conflicting status yielding zero complete results, and a scan cap yielding partial/exit3. No tasks were changed. This smoke covers those reads only; it does not complete #42 or prove native release acceptance.
