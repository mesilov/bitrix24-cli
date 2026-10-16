# Verification: fix-task-handover-project-scope

## Result

| Dimension | Evidence |
|---|---|
| Completeness | 4/4 tasks; 1 ADDED + 2 MODIFIED requirements implemented |
| Correctness | 3/3 requirements mapped; all 11 delta scenarios covered by unit/integration/live assertions |
| Coherence | Implementation follows design: normal scope guard, native bounded lifecycle, precise pre-assignment snapshot, separate component results |

Critical: 0. Warning: 0. Suggestion: 0. No applicable verification check skipped. Ready for archive after confirmed source MR merge.

## Requirement mapping

- **Фактический scope состава проекта**: `src/Application/Connection/ScopeManifest.php`, mapping sonet_group.user.get → sonet_group. `ScopeValidationCommandTest::testHandoverUsesActualPortalProjectScopeAndNeverSubstitutesSonet` tests actual grant acceptance and missing-grant denial before business calls. Existing `testTtlAndManifestRevisionTriggerRefreshButAppVersionAloneDoesNot` proves revision invalidation. Live CLI scope validation is fresh, including third-party manager mode. No fixture resolver replaces the CLI connection.
- **Проверка каждой операции и сохранение карточек**: `TaskHandoverService` checks exact pre-assignment status/project/source, confirms recipient/project after ACK, admits only original status or known reset to pending, then uses effective per-item target. Other unexpected status remains a conflict, even when it equals the requested target. `TaskHandoverSelectionStatusTest` covers implicit and explicit restoration for all four source states, a different target, unexpected state, dry-run restoration and partial readback. Live source filters and excluded/nonmatching sentinels verify preservation independently under two identities. Dry-run writes are absent; selection IDs match owned fixtures.
- **Управляемый целевой статус**: existing HandoverStatusPlan and TaskLifecycleService implement bounded native actions with fresh rights/control/result gates. Existing and added tests cover five targets, result/control/approve denial, cancellation, no-progress, unknown outcomes and final verification. Live verifies all five goals, complete → approve, missing/present native result, no-control rejection, and creator completion returning a different status as partial. Unit checks cover start → pause/disapprove paths and invalid arguments; the live portal reset often removes the need for those intermediate actions.

## Validation

- `make unit`: 893 tests, 16881 assertions; targeted handover: 31 tests, 2557 assertions; targeted scope: 18 tests, 193 assertions.
- `make check`, `make lint-all`: PASS (license, CS Fixer, PHPStan, Rector).
- `make cli-reference-check`: 63 public commands.
- `openspec validate --all --strict`: 27 passed, 0 failed before archive.
- Final live: 18/18 custom assertions, 4/4 repository integration tests (119 assertions), no skips; cleanup complete. See [live-acceptance.md](live-acceptance.md) and [live-receipt.json](live-receipt.json). Scope-only failures are recorded separately and were followed by a full repeat.

The test portal identities prove these allowed operations, not a restricted non-admin policy. Network loss, real concurrent mutations and subtask side effects remain unit/contract boundaries. Release/standalone binaries were not exercised. These limits are explicit in the receipt and do not substitute for the verified behavior.
