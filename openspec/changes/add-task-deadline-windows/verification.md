# Verification: add-task-deadline-windows

Date: 2026-10-13. Scope: implementation in feature/95-task-deadline-windows, issue #95; companion skill worktree feature/95-deadline-windows.

## Summary

| Dimension | Result |
| --- | --- |
| Completeness | 11 tracked tasks implemented and checked; proposal/spec/design/tasks present; strict validation passes |
| Correctness | 7 added deadline requirements and 3 modified cli-experience requirements mapped to implementation/tests |
| Coherence | Explicit REST1 deadline route, common resolver, existing bounded scan/public output contract and CLI-only skill |

## Requirement and scenario evidence

| Requirement | Implementation | Checks |
| --- | --- | --- |
| Canonical calendar windows | DeadlineWindowResolver, both Console commands/TaskInputMapper | DeadlineWindowTest::windows (all 7 tokens), help/completion checks |
| Deterministic time resolution | Clock, resolver, immutable DeadlineWindow passed through TaskQuery | Frozen dates, different timezones, 23/25-hour DST days, leap February, year/Q1/Q2/Q3/Q4, Sunday week; same filter across pages |
| Overdue deadline semantics | DeadlineWindow bounds + TaskListScanner lifecycle predicate | Numeric statuses 2–7 normalized; pending/in_progress/supposedly_completed/deferred included, completed/declined excluded; null/empty deadline and deadline=now excluded |
| Server date filtering/API policy | Rest1TaskListAdapter, handlers/CommandCatalog/ApiPolicyGuard | >=DEADLINE/<DEADLINE/GROUP_ID payloads; strict-rest3 exit4/no calls/no identity reads; existing REST3 control regression |
| Filter combinations/invalid input | Mapper, scanner | AND group/role/status/title across both commands and 4 roles, ID scope, Unicode literal query; repeated/invalid flags, timezone and where/due-before conflicts before API |
| Public skill translation | Companion skill references/deadline-windows.md and SKILL.md | All user stories mapped to public CLI flags; installed-help capability check; no date math/direct API bypass; quick_validate.py and relative links pass |
| Temporal field validation | Rest1TaskListAdapter::validate/normalize, README | Unavailable select/order exit2 before API; creatorId projection, REAL_STATUS sort, normalized numeric IDs/statuses |
| Explicit filtering/completeness of lists | Shared TaskListScanner with explicit temporal gateway | 101-row pagination, limit/all/max-scan, duplicates/no progress, page failure, malformed/missing predicates; complete/empty and partial/exit3 |
| Bounded title search | FindTasksHandler/TaskListScanner | Existing RecentTaskScanTest/ScanningAndBindingTest regression plus temporal literal title/status/role tests; id/title remains a list in all formats |
| Group-scoped title search | GROUP_ID on temporal route, local groupId on non-temporal REST3 | Unit server payload/AND exclusion tests and live empty-project selection; non-temporal GroupIdOptionTest unchanged |

## Verification commands

- `make unit`: 626 tests, 4636 assertions, passed on the final implementation; a subsequent test-only invalid-sort case also passed in targeted suite.
- `make unit TEST_ARGS='--filter "DeadlineWindowTest|TaskDeadlineFilterTest"'`: 41 tests, 341 assertions, passed after that case.
- `make lint-all`: dependency licenses, PHP-CS-Fixer, PHPStan, Rector; passed after applying repository formatting/type rules.
- `make check`: Composer strict validation/platform requirements, PHP syntax and CLI startup passed.
- `openspec validate add-task-deadline-windows --strict`: passed.
- `B24CLI_ENV_FILE=<existing credential file> make integration TEST_ARGS='--group deadline-readonly'`: 2 tests, 225 assertions, passed; only read-only tests run, no remote fixtures created/changed/deleted.
- Companion skill `quick_validate.py`, relative-link validation and canonical-token mapping checks passed.

## Live evidence and limits

Sanitized current CLI receipt: [live-cli-evidence.json](live-cli-evidence.json). Earlier direct API probe remains separately preserved in api-evidence.json; it is not used as implementation proof.

Current portal: list this-month/this-quarter returned 5 tasks, find returned 1 task using a title from an existing read-only control sample; quarter+overdue returned 5/1. Every returned date/status checked; task:find readback confirmed deadline/status through SDK helpers. All 7 calendar flags executed in both commands. Unbounded overdue with max-scan=100 correctly returned partial/exit3; the tests do not claim a complete historical/project inventory.

Positive project fixture absent: read-only `>GROUP_ID:0` returned no tasks. Live project selection verified an empty result and metadata; positive GROUP_ID membership is verified by unit payload/AND checks, not by a nonempty portal result. Completed/declined/deferred/control states are covered by all-six-state unit fixtures; live state availability is limited to existing tasks. No mutating portal checks were performed.

No critical implementation issues identified. Native standalone artifacts, all-portal compatibility and execution of the skill inside all AI clients are not verified by this run. Native GitHub CI/merge/release remain separate evidence, to be recorded from the created PR; this report does not claim merge or a released binary.

## Follow-up

Keep the change active while PR is under review. After verified merge into dev and issue closure, archive/sync the change and preserve that result in dev before removing its worktree, following bitrix24-cli-maintainer.
