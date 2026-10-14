# Verification and closure — issue #42

Date: 2026-10-14. Base: dev `0f6c006`. Change: `complete-task-portal-acceptance`, milestone 0.2.0. Source IDs: architecture 5.2–5.4 and JTBD 2.3.

## Delivery scope and user decision

User explicitly stopped further portal checks, authorized merge into dev and requested closing #42. The delivered scope is additional per-command acceptance tests, second-user fixtures, sanitized observations and documentation. All ten original compound acceptance tasks are preserved unchecked and cancelled by that decision. Closing and archiving is not full acceptance proof. No runtime code or production routes changed. No new portal runs after the user's stop request.

## Executed portal evidence

Two pre-stop runs completed with cleanup: 4 tests / 29 assertions (1 passed, 3 failed); 10 tests / 87 assertions (6 passed, 1 failed, 1 error, 2 skipped). Combined: 14 executions / 116 assertions, 7 passed, 4 failed, 1 error, 2 skipped. The failed records remain in [portal-evidence-2026-10-14.json](portal-evidence-2026-10-14.json) and are not counted as acceptance. All recorded task/Disk fixture cleanup failure lists are empty.

| Area | Observed result | Limit |
| --- | --- | --- |
| Identity/scopes | Owner 1, second user 16; both expose task/tasks, im, disk and user | Second connection is not restricted |
| Rights preflight | Second user's SDK access on owner fixture includes admin=true | Denied-role matrix unverified; first three denial assumptions failed because operations were allowed |
| Reassignment | Responsible 16 confirmed by owner and independent second-webhook REST3 card | Confirmed on disposable task, no universal permission proof |
| Card/schema | title metadata equal across connections; administrator read succeeds | Non-admin denial unverified |
| Bounded list/title search | max-scan=1 sees latest fixture and returns exit 3 / scan-budget-exhausted | No full unbounded scan proof |
| History | Both known title transitions returned; complete=false, history-navigation-unverified, exit 3 | Exact lifetime completeness unverified |
| Native auditors | Populated auditors causes INTERNAL_SERVER_ERROR; selected task:show uses exactly REST3 get then REST1 get and returns second observer | Native task:list case not re-run; prior limitation retained, no fallback/retry added |
| Attachment | Attach ACK succeeds, selected fileIds is null | Independent membership not verified; first draft test raised TypeError on null, now explicitly records/skips unsupported relation |
| Time navigation | Three owned entries; nPageSize=2 returned only first two with complete=true/exit 0 | Observed runtime completeness defect; regression assertion deliberately remains failing on this portal |
| Time/checklist/participants | Additional outsider tests prepared, admin preflight guarded | Unexecuted matrix and existing happy-path not counted as new acceptance |

## Correctness and coherence

One command per integration class; fixtures/readback use SDK helpers, not another CLI command. BITRIX24_SECOND_USER_WEBHOOK is test-only; optional B24CLI_TEST_RESTRICTED_WEBHOOK selects a distinct non-admin identity. The administrator preflight causes explicit skip and records observed rights. Original records from 11.10.2026 remain unchanged. API field names and version boundaries retain existing contracts. Evidence records API calls/effects, acting IDs, expected statuses, targets and cleanup failures; no webhook values or message/profile personal data are included.

The delivered tests were revised after the user stopped runs; revised skip/null handling and unexecuted outsider cases are not live-verified. Offline strict-rest3 tests already exist in CommandBehaviorTest and related contract tests; they were not re-run for this issue after the user's stop request, and no new zero-call proof is claimed.

## Official documentation evidence

Effective bitrix24_dev configuration read from this checkout; real MCP search then exact method details succeeded on 2026-10-14. Sources: [REST3 task get](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html), [scope](https://apidocs.bitrix24.ru/api-reference/common/system/scope.html), [elapseditem navigation](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-get-list.html), [flat checklist list](https://apidocs.bitrix24.ru/api-reference/tasks/checklist-item/task-checklist-item-get-list.html), [history](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-history-list.html), [attach](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-file-attach.html), [message update](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-update.html), [message delete](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-delete.html). Documentation is not portal proof.

## Local/source validation

Strict OpenSpec validation passed before archival; skip_specs=true, no delta specs or main-spec synchronization. Source static checks and remote delivery receipts are appended below when complete. This is an archive by user decision with 10 incomplete/cancelled tasks, not implementation-complete.

- `make lint-phpstan lint-cs-fixer lint-rector`: passed (PHPStan 329 files / no errors, CS Fixer zero changes, Rector no changes).
- `git diff --check`: passed; saved evidence secret scan: zero matches against both supplied webhook values/tokens.
- `openspec validate complete-task-portal-acceptance --strict`: valid.
- `openspec archive complete-task-portal-acceptance --yes --skip-specs`: archived with explicit warning 0/10 compound tasks, by the user closure instruction. No deltas to synchronize.
