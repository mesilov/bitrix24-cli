# Verification: add-task-my-command (#118)

Date: 2026-10-14 (Asia/Bishkek). Implementation base: f7dc2a7.

| Dimension | Result |
| --- | --- |
| Completeness | 6/6 tasks complete; 6 requirements / 15 scenarios covered |
| Correctness | All 6 requirements and 15 scenarios mapped to command/query/scanner and tests |
| Coherence | Thin MyTaskCommand → ListTasksRequest/ListTasksHandler → shared scanner; no CLI chaining or new transport |

## Requirement and scenario mapping

- Explicit executor selection: TaskInputMapper::myTasks sets where.responsibleId; MyTaskCommand validates before profile selection. Unit invalid user/date/budget cases include missing environment file/profile. Live tests verify a different accessible executor; caller remains user 1.
- Open predicate: optional TaskQuery.openOnly=false; scanner selects/requires status and uses DeadlineWindow::OPEN_STATUSES. Unit covers pending/in_progress/supposedly_completed/deferred versus completed/declined, missing/unknown status, and omitted open. Live covers pending versus completed.
- Deadline/conjunction: shared scanner compares DateTimeImmutable instants strictly, including offsets/fractions. Unit tests cover before/equal/after, null and user/open/deadline AND. Live fixtures confirm boundary with an equivalent timezone, null deadline and conjunction.
- Bounded completeness: pagination maxScan, output limit 50 and id DESC stay in shared scanner; missing/malformed predicate data or exhausted budget returns exit 3/complete=false/reason. Unit verifies scan 51/output 50 and scan 1/partial; live verifies scan 1/partial.
- Output/discovery/regression: CommandCatalog declares one REST3 read route. Unit verifies strict-rest3, human/JSON/TSV, help/list/completion without credentials, and existing task:list/find/role contracts. No task:my write/fallback route exists.
- Exact registration: 35 task + 4 users commands, 6 local commands, 3 visible builtins (48 public total); catalog/AsCommand uniqueness, routes and per-command integration ownership are checked.
- docs/task-jtbd.md keeps EMP-01 and marks task:my implemented, links its integration file and preserves prioritization/end-to-end/permission limits. README/help/command map/CHANGELOG are updated.

## Local Docker evidence

- PHP 8.4.26 / PHPUnit 12.5.38.
- Final `make unit`: **771 tests, 11219 assertions, no failures/skips**.
- Target scanner/role/deadline/contracts/coverage/help regression subset: **184 tests, 2093 assertions**.
- `make check`: strict Composer metadata/platform, PHP syntax and CLI startup passed.
- `make lint-all`: license checker, PHP CS Fixer, PHPStan, Rector dry-run passed.
- `make cli-reference-check`: matches **48 public commands**.
- `openspec validate add-task-my-command --strict`: passed; `git diff --check`: passed.
- Additional global `openspec validate --all --strict`: 21 pass / 4 fail on existing long requirement warnings in cli-experience, connection-configuration, task-console-runtime, task-mvp-scope. These warnings are tracked by issue #114 / PR #117; unrelated specifications were not rewritten for #118.

## Live portal evidence

`B24CLI_ENV_FILE=<existing root .env.local> make integration TEST_ARGS='--group command:task:my'`: **4 tests, 55 assertions, no failures/skips**. SDK fixtures/readback/cleanup; each case executes only its own task:my CLI command. Final owned IDs: 6224, 6226, 6228, 6230, 6232, 6234, 6246, 6248, 6252, 6254. All cleanup failure lists are empty. CLI call accounting records REST3 reads only; expected scan-budget case is exit 3. This proves these operations for the configured portal/caller, not arbitrary permission combinations or full EMP-01 prioritization.

`portal-evidence.jsonl` retains **all four attempts** (16 receipts):

1. Fixture without explicit deadline inherited a portal deadline and failed the expected exclusion (4 tests, 44 assertions, 1 failure). All fixtures cleaned.
2. REST3 deadline=null did not clear that deadline; explicit SDK readback failed (4 tests, 39 assertions, 1 failure). All fixtures cleaned.
3. REST3 deadline="" was rejected (4 tests, 38 assertions, 1 error). All fixtures cleaned.
4. Final fixture clears DEADLINE="" through classical SDK tasks.task.update and verifies null with REST3 get. All four tests passed; all fixtures cleaned. The classical write is fixture setup only, never a task:my fallback.

## Documentation and evidence limits

Official MCP was queried from the fresh implementation checkout: effective config enabled=true; bitrix-search followed by exact method/article details verified REST3 tasks.task.list and field names/types (responsibleId integer, status string enum, deadline datetime|null). SDK fixture methods were also checked. Source links are recorded in design.md.

Native binaries/release packaging were not required or run: Docker source checks and portal acceptance do not prove a published 0.2.0 artifact. CI and merge status are reported separately when available. No applicable change-specific verification was skipped. Change verification: 0 critical issues, 0 warnings, 0 suggestions; all change-specific checks passed. Ready for archive after confirmed delivery to dev.

## Fresh dev integration

Before PR, fetched and merged origin/dev at 5ed0349 (Russian-help archive #113); merge was clean. Runtime files were unchanged by this sync. Repeated affected help/my/coverage tests: 37 tests, 4439 assertions; CLI map check 48 commands and strict change validation passed. Current diff contains #118 CHANGELOG entry, EMP-01 docs and only scoped implementation/planning/evidence. Existing global OpenSpec warnings remain separately tracked by #114.

## Final dev refresh before merge

Fetched/merged origin/dev at 48e010c (PR #117 / issue #114), without conflicts or runtime changes. Updated only the registration delta statement to retain normalized wording and the separate Command registration validation requirement. After this integration global `openspec validate --all --strict` passes **25/25, 0 warnings/failures**. The earlier 21/4 result above is historical evidence and is superseded by this dated pass. Runtime code still matches the 771-unit/4-live tested version and the initial clean code review; CI is repeated on the new head.
