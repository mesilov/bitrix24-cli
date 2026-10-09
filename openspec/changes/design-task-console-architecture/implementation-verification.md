# Implementation verification — 2026-10-09

Issue: [#10](https://github.com/mesilov/bitrix24-cli/issues/10). PR: [#11 → dev](https://github.com/mesilov/bitrix24-cli/pull/11). Worktree: `/private/tmp/bitrix24-cli-issue-10`, branch `feature/10-research-task-command-surface`. Previous [verification](verification.md) records the documentation-only stage; this artifact records the authorized implementation.

## Scorecard

| Dimension | Evidence and boundary |
| --- | --- |
| Completeness | 24/27 tasks completed locally. Portal tasks 5.2–5.4 remain open. 30 Command classes, 28 handlers/typed requests; 29 post-MVP operations remain excluded |
| Correctness | All 12 runtime requirements have implementation/local contract evidence below. Concrete portal schema/permissions/navigation not verified: credentials are not supplied |
| Coherence | Standalone Console + DI, explicit registration/ports, lazy SDK provider, explicit API versions and no fallback. FrameworkBundle/Kernel are absent. Helper names consolidated as documented in design |

## Requirement evidence

| Requirement | Implementation | Verification |
| --- | --- | --- |
| Exact MVP registration | CommandCatalog, RegisterTaskCommandsPass, config/services.php, ContainerCommandLoader | CommandContractsTest inventory/30 route cases; ContainerRegistrationTest duplicate/mismatch/alias rejection |
| Deferred connection | EnvConnectionResolver, B24ClientProvider | Inventory/help/completion spy; ConnectionAndSdkTest lazy creation and env precedence; both launcher subprocesses with missing env path |
| Application error boundary | B24Application, Failure, ResultPresenter | CommandBehaviorTest parser failures, ambiguity, global flags, JSON/quiet/silent and no calls |
| Explicit patch/advanced input | TaskInputMapper, FieldSchema, UpdateTaskHandler | Sparse/empty description, stdin, advanced create/metadata/dry-run, rejected raw status and mode conflicts |
| Complete API policy plan | ApiPolicyGuard, handler routes | Every REST1/IM command rejected in strict mode before calls; explicit SDK Core v1/v3 URLs verified through MockHttpClient |
| Task chat binding | ChatSupport, TaskChatAdapter | Wrong task association/MESSAGE_ID gate4 before mutation; send boolean ACK without invented message ID |
| Child resource binding | TimeSupport, ChecklistTree/Support, REST1 adapters | Foreign entry/root, cycles, nested items, SORT_INDEX/Y-N normalization; exact positional time query codec |
| Prepared mutation preview | PreparedOperation, CommandRunner | Advanced/child dry-run performs only explicit reads; noninteractive delete gate; real PTY decline/acceptance |
| Execution accounting | OperationResult, ExecutionLedger | Per-file confirmed/unknown/skipped ledger; lost SDK write response is unknown with exactly one request |
| Cancellation | RuntimeState, CommandRunner, SDK transport, Docker pcntl | Real subprocess SIGINT: exit130, confirmed first attachment retained and later steps stopped; actual local HTTP timeout <2s with timeout0.1 |
| Offline versus portal evidence | phpunit.xml.dist, separate suites, CI workflow | 132 offline tests / 614 assertions passed. 7 live tests explicitly skipped, 0 portal assertions |
| Root environment webhook | EnvConnectionResolver, provider, Compose read-only overlay, Make CLI_RUN | Synthetic external env bind read-only tested in Docker; process override precedence verified without exposing credentials |

## Local commands and results

- `make docker-build`: PHP 8.4.26, Alpine 3.23, bcmath/intl/pcntl; build successful.
- `make check`: Composer strict validation, platform requirements, all PHP syntax and CLI startup successful.
- `make test`: **132 tests, 614 assertions**, no warnings/failures.
- `make test-integration`: **7 skipped tests, 0 assertions**, missing webhook; not a portal pass.
- `make lint-all`: dependency licenses, PSR-12, PHPStan level5 and Rector dry-run successful; tests/config/both launcher are in scope.
- `openspec validate design-task-console-architecture --strict` and `openspec validate --specs --strict`: active architecture and 5 canonical specs valid.
- `git diff --check`: passed.
- Real Docker read-only external env mount and process precedence were checked with synthetic URLs only. No real credential file was inspected or committed.

Locked runtime: b24phpsdk 3.7.0, Symfony Console/DependencyInjection 8.1.8, PHPUnit12.5.38. Ordinary SDK integration Factory uses webhook; explicit `getServiceBuilder(true)` uses ApplicationBridge OAuth and refresh persistence. This CLI implements the user-selected webhook path only.

## Portal suite and remaining work

`tests/Integration/TaskCommandsTest.php` exercises card/schema/search/sparse updates; chat send/read/update/delete/wrong-task binding; time CRUD and omitted text; roots/nested checklist items/title/complete/renew/delete with unchanged task status; participants omission/clear and history completeness. Each test creates owned tasks and removes those tracked IDs in tearDown; failed cleanup reports IDs. No bulk deletion/upload is performed.

Optional cases need an existing Disk file (`B24CLI_TEST_DISK_FILE_ID`) and a non-admin restricted webhook (`B24CLI_TEST_RESTRICTED_WEBHOOK`). File attachment is verified by ACK only: REST3 get leaves fileIds null. A successful get is not evidence of attachment membership. Default assign-to-current-user verifies payload/readback, not a distinct-user reassignment.

O1 — **Open portal acceptance, critical for archive:** supply BITRIX24_WEBHOOK in root .env, run `B24CLI_ENV_FILE=<absolute-root-env> make test-integration` in this worktree, record observed schema/API/role/results and fix runtime discrepancies. Complete tasks 5.2–5.4 only after that evidence. Restricted chat/time role cases and full history navigation need portal-specific evidence beyond the basic optional denied task-edit case.

O2 — **Verification limits:** no live portal, second-user reassignment, independent attachment readback, restricted chat/time/checklist permissions or concrete history completeness were proven. Local fixtures and SDK source inspection do not close these boundaries. CI results are reported separately after publication; local checks do not prove remote CI.

No archive or merge is performed while these portal acceptance tasks are open. This is a reviewable implementation with successful local acceptance and prepared live tests.

## Final interruption regression

A transport failure concurrent with cancellation now returns exit130 and outcomeUnknown=true for a sent write. MockHttpClient verifies one attempted request without retry; this supplements the real subprocess between-step SIGINT case. Full offline suite and lint-all rerun: 132 tests / 614 assertions passed.

At commit 7133a76 all six PR workflows passed, including Linux Worktree isolation. The local worktree acceptance retry also passed with cleanup=true; the first run failed during Composer extraction before CLI startup and cleaned up successfully. Remote CI for subsequent commits must be checked separately.
