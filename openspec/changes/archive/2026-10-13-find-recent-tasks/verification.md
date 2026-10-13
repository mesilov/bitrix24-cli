# Verification: find-recent-tasks

Date: 2026-10-13. Initial base: `origin/dev` at `96ce502`; refreshed and merged `origin/dev` at `de98df7` before MR. The only conflict was CHANGELOG.md; #64 and #78 entries were both preserved. The incoming change affects maintainer instructions/changelog/OpenSpec only; runtime/tests were unchanged. Post-merge checks: all canonical specs validation, whitespace/conflict-marker checks and the required #64 changelog diff.

| Dimension | Result |
| --- | --- |
| Completeness | 4/4 tasks, both modified requirements implemented |
| Correctness | Default DESC, explicit order, local predicates, scan/output limits and all partial formats covered |
| Coherence | Shared mapper/REST3 route retained; help, README, CHANGELOG and integration expectations match |

## Requirement and scenario evidence

- `Explicit filtering and completeness of lists`: `TaskInputMapper::query` sends DESC by default and preserves explicit order/id tie-breaker; `TaskListScanner::scan` retains page order, scope, count/limit/partial semantics and adds effective order/hint. `GroupIdOptionTest` and `ScanningAndBindingTest` preserve local predicates and id-only filter, page-error/missing-data/no-progress handling.
- `Bounded task search by title`: common --order parser and `FindTaskCommand` expose validated sorting; scanner still uses literal `mb_stripos`, id/title output, trim/empty validation and bounded pages. Existing CommandBehavior/ScanningAndBinding/GroupIdOption cases cover literal Unicode, invalid input, group AND title, zero/one/many results and incomplete scans.
- Fresh group task among 10051 records: `RecentTaskScanTest::testNewestGroupTaskIsReachableBeyondTenThousandTasks` sorts the full fixture using API request order and applies pagination. With maxScan=10 the newest match is returned; explicit ASC returns an empty partial. Both list and find are tested; filter remains empty, groupId is selected locally.
- Chosen/default order across three pages and limitApplied: `testPaginationAndOutputLimitsKeepTheChosenOrder`; scanning continues after output limit, ordered prefix is preserved.
- Repeatable custom order/id tie-breaker: `testCustomSortAndIdTieBreakerArePreserved`; unsupported field/direction: `testInvalidSortFailsBeforeApi` (exit2, zero API calls).
- Incomplete empty/nonempty duplicate checks in JSON/plain/human, --all budget independence, structured order/hint and stderr/stdout separation: `testBudgetDiagnosticsForEmptyAndNonemptyResultsInEveryFormat`.
- Full empty success and no budget hint: `testCompleteEmptyResultIsSuccessful`; credential-free help/completion: `testHelpAndCompletionDescribeSortWithoutApi`.
- Real-portal find integration expectation now requires second/newer task first and meta.order DESC. This assertion was updated and linted; portal execution is not claimed.

## Executed checks

- Focused new regression suite: 14 tests, 354 assertions, passed.
- Full offline suite after Rector naming adjustment: **380 tests, 2458 assertions**, passed on PHP 8.4.26.
- `make check`: Composer strict validation, platform requirements, PHP syntax and CLI startup passed.
- `make lint-all`: allowed licenses, PHP CS Fixer (0 changes), PHPStan (no errors), Rector dry run (0 changes) passed. Rechecked after integration expectation update.
- `openspec validate find-recent-tasks --strict` and `git diff --check`: passed.
- Docker environment: checkout image built with repository Dockerfile; Compose network creation failed because the host default address pool is exhausted. Existing networks were preserved. Checks used the same Make targets with RUN overridden to an ephemeral `docker run --rm --network bridge --user 501:20 --volume /private/tmp/bitrix24-cli-issue-64:/var/www/html --workdir /var/www/html <checkout-image>` runner. Locked dependencies installed in this worktree only.

## Assessment and evidence boundaries

No critical issues, warnings or suggestions in the implementation/spec checks; no applicable local verification checks skipped. API contracts were checked through the required official documentation MCP and the current REST3 method page (see design.md). Runtime group/title filtering remains local.

Live portal acceptance, native standalone builds and remote CI are separate evidence and are not implied by offline checks. The active #12 planning artifacts still describe their earlier ASC default; that future implementation must reconcile with the canonical #64 contract when rebasing, as recorded in design.md.

## Merge recheck — 2026-10-13

Integrated fresh origin/dev `ed54555` before authorized merge of PR #84. README and CHANGELOG conflicts were resolved by preserving #64 search documentation and all incoming tag/result/connection changes. Shared mapper/presenter auto-merges retain DESC/order/partial behavior. Full offline suite: **436 tests, 3143 assertions**, passed; make check and all four make lint-all gates passed. Canonical specs: 10 passed (one existing long-requirement warning in task-results), archive #64 unchanged and synchronized. Local ignored files are only reproducible vendor dependencies and var caches; no uncommitted user work was present in this worktree before merge.
