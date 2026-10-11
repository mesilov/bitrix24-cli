# Implementation verification — 2026-10-11

Issue: [#10](https://github.com/mesilov/bitrix24-cli/issues/10). PR: [#11 → dev](https://github.com/mesilov/bitrix24-cli/pull/11). Worktree: `/private/tmp/bitrix24-cli-issue-10`, branch `feature/10-research-task-command-surface`.

## Scorecard

| Dimension | Evidence and boundary |
| --- | --- |
| Completeness | 42/42 retained 0.1.0 tasks complete; unverified parts of legacy 5.2–5.4 transferred to #42/0.2.0, not executed by this decision. All 30 MVP commands have a dedicated integration class and own live happy-path method with result assertions. 30 Command classes / 23 request-reference DTO / 11 handlers + 4 services; 29 post-MVP operations excluded |
| Correctness | 15 runtime requirements and the revised scope admission have implementation/offline coverage. Live suite: 41 passed, 1 restricted-role case skipped, 395 assertions across 30 per-command classes. Filled participant enrichment succeeds through explicitly selected companion reads; native REST3 auditors remains broken on this portal. Time/history retain their precisely asserted completeness boundaries |
| Coherence | Standalone Symfony Console + DI, explicit API versions/ports, deferred SDK provider. Conditional enrichment routes are checked before credentials. PHPStan, Rector, code style and licenses passed; no error-triggered API fallback/retry |

## Changes verified

- `TaskParticipantEnricher` handles selected auditors/accomplices only. `ShowTaskHandler` reads the other fields through REST3, excluding those relations, then `Rest1ParticipantAdapter::ids` reads only ID/selected role IDs through REST1. Returned task ID and each member ID are validated. Legacy card data/auditorsData never overwrite or enrich the REST3 card.
- `Rest1UserAdapter` filters user.get by unique selected IDs, selects only required fields, chunks <=50 and caps lookup at 10000 IDs. Name is assembled from NAME/SECOND_NAME/LAST_NAME. Role order is retained; overlap shares one lookup. Missing users/scope-hidden fields/read failures preserve the card/known IDs with null, complete=false and partial3. Interruption remains130, without further calls.
- Normal task:show remains one REST3 read. ID-only and empty participant roles skip user.get. Strict-rest3 rejects enriched plans before API/credential reads; unsupported projections fail local validation. task:list retains native REST3 projections.
- `TaskParticipantEnrichmentTest` adds 33 cases: role order/dedup/minimal select, empty/ID-only/default routes, strict/local gates, missing/denied/transport/interrupted responses, wrong-task/malformed member IDs, foreign/duplicate/malformed user rows, chunk/budget bounds and actual SDK HTTP response codec.
- Earlier method-specific ACK, sparse participant replacement, env precedence, task/child binding, cleanup and signal protections retain their test coverage. Participant writes still affect only the two selected role sets; this read admission does not authorize generic REST1 CRUD.

## Local checks

- `make unit`: **193 tests / 1157 assertions**, no failures or warnings.
- `make check`: Composer strict/platform requirements, PHP syntax and shared bootstrap/startup passed.
- `make lint-all`: licenses, PHP-CS-Fixer, PHPStan level5 and Rector dry-run passed. Initial Rector naming/type suggestions were applied without lowering rules.
- `openspec validate --all --strict` and `git diff --check` passed.
- Locked environment: PHP8.4.26 / Alpine3.23, SDK3.7.0, Console/DI8.1.8, PHPUnit12.5.38. Incoming webhook is loaded from the same external read-only .env.local mount; credentials are absent from argv/evidence.

## Portal evidence

Current source: [sanitized per-command execution record](portal-per-command-evidence-2026-10-11.json). Previous grouped workflows: [happy-path evidence](portal-happy-path-evidence-2026-10-11.json). Earlier records: [refactor](portal-refactor-evidence-2026-10-11.json), [enrichment](portal-enrichment-evidence-2026-10-11.json), [2026-10-10](portal-evidence-2026-10-10.json). Their original counts and fixture IDs are preserved. Raw logs/trace remain ignored; committed evidence excludes webhook URL/token, request parameters, user names, emails and chat text.

Final full run: **42 tests / 395 assertions / 41 passed / 1 restricted-role skip / 0 failures / 0 errors**. All 30 dedicated happy-path methods executed successfully within the precisely recorded API completeness boundaries. Acting identity: webhook user1 as fixture creator/responsible and own message/time-entry author; admin status is not asserted.

Current owned fixtures: **42 tasks and 1 Disk file**, all deleted with zero cleanup failures. Independent task:list readback confirms absence of all **89 task IDs** from split-suite runs/probes. Restricted-role skip occurs before fixture creation without a second webhook. SDK fixture and cleanup calls are recorded only as fixture evidence, not as additional CLI coverage.

## Per-command integration verification

- Each of the 30 CommandCatalog commands has its own `tests/Integration/Task/<CommandClass>Test.php`, class Group `command:<name>` and dedicated method Group `happy-path`. Twelve additional methods preserve participant/email, omitted/empty fields, nested checklist, wrong-task gates and the restricted-role case. [Command map](command-map.md#integration-happy-path-coverage) links every command to its own method and concrete assertions.
- Shared `LiveTaskCommandTestCase` creates disposable fixtures, reads back results and cleans up through SDK transport/adapters. Each case gets its own task(s); methods have no ordering dependency or shared mutable fixture. `invoke` rejects another CLI command before Console execution; SDK setup never counts as CLI coverage. Created CLI task IDs are registered before assertions for reliable cleanup.
- Unit guard checks exactly one command group per class, matching CommandCatalog/CommandTest naming, no duplicate/unknown classes and a dedicated happy-path method. An offline unit case proves cross-command invocation is rejected before application/provider access. Mutation probe removed the task:find happy-path tag: unit verification failed with the expected missing-method message, then the exact original file bytes were restored.
- `make integration TEST_ARGS='--group command:task:update'`: only three UpdateTaskCommandTest cases ran, 2 passed + 1 restricted-role skip, 18 assertions. `--group happy-path` selects the 30 own baseline methods; empty TEST_ARGS keeps the full suite. Existing Make aliases forward the same filters.
- The first split-suite run had 40 passed + 1 failure + 1 role skip. The deletion test incorrectly expected message ID absence, then empty text. Portal readback keeps the ID with a localized deletion placeholder. The final assertion checks confirmed deletion ACK and absence of the original text, preserving the verified contract without depending on placeholder language. Every failed/probe run cleaned up its own fixtures; no production behavior changed.
- Time/history accept only their exact documented partial reasons with exit3, complete=false and partial-result. All other baseline methods require exit0/error=null and meaningful expected data. Attachment remains confirmed ACK only; reassignment uses the acting user. Skips do not count as live success.

Final checks: 193 unit tests / 1157 assertions; 42 live tests / 395 assertions; make check, licenses, PHP-CS-Fixer, PHPStan level5 and Rector passed. All 8 strict OpenSpec items and diff-check passed. Tasks 8.1–8.4 complete; tasks 5.2–5.4 retain their existing evidence limits.

## Architecture simplification and MR review

- 17 handlers were grouped into TaskMetadataService, TaskChatService, TimeEntryService and ChecklistItemService with explicit routes/prepare methods. All 34 moved methods preserve their route/preflight/execute logic against base commit 7d07ab1 after name/reference substitutions and whitespace normalization. The remaining 11 handlers keep distinct scenarios; no API adapter/transport/provider behavior changed.
- Seven simple request classes were replaced by TaskReference/ChecklistItemReference, leaving 23 DTO/reference classes. Shared mapper methods retain positive-ID validation. Thirty Command classes and seven Gateway interfaces remain.
- CommandCatalog supplies ContainerCommandLoader directly, without console.command tags, reflection or custom compiler pass. Unit tests verify all 30 loaded classes, matching AsCommand, unique classes/no aliases and zero credential/API access. CommandRunner derives name/effect centrally.
- [Naming thread](https://github.com/mesilov/bitrix24-cli/pull/11#discussion_r4240924676): tests/Unit, Tests\Unit namespace, PHPUnit suite unit-tests. Local subprocess/loopback checks are preserved and do not call the external portal.
- [Make/CI thread](https://github.com/mesilov/bitrix24-cli/pull/11#discussion_r4241086872): make unit / make integration run only unit-tests / integration-tests respectively; CI runs make unit. Old test/test-integration and requested make-unit/make-integration are compatible aliases. Live integration uses the actual provider and selected env.
- Final local run: 191 tests / 942 assertions, no failures/errors/warnings; make check, licenses, PHP-CS-Fixer, PHPStan level5 and Rector passed. The old PHPStan path and new test variable naming were corrected without lowering rules. Three old compiler-pass cases were replaced by one full-catalog test; existing command/runtime/SDK tests were preserved (193 → 191 tests, 792 → 942 assertions).
- Full live run: 8 tests / 145 assertions, 7 passed + 1 restricted-role skip, 0 failures/errors; all 30 command names appear in the new sanitized record. OpenSpec strict: all 8 items passed; git diff --check passed.


## Remaining verification

O1 — **Deferred to #42/0.2.0: legacy task5.2 is partially verified.** Allowed-role card/schema/list/search/chat cases pass, but denied-role chat behavior needs B24CLI_TEST_RESTRICTED_WEBHOOK and specific portal cases.

O2 — **Deferred to #42/0.2.0: legacy task5.3 is partially verified.** Own time/checklist operations and wrong-task gates pass; denied-role time/checklist cases remain unverified without a second webhook.

O3 — **Deferred to #42/0.2.0: legacy task5.4 is partially verified.** Participant omission/clear, nonempty history and offline strict gates pass. Denied-role participants and history navigation/fullness remain unproven. Complete the future #42 tasks using restricted fixtures; no deferred case is checked off by the scope transfer.

O4 — **Native API limitation retained, CLI enrichment verified.** Native REST3 filled auditors projections still fail despite user scope. task:show now reads those selected roles through the user-authorized composition; task:list native projections retain the limitation. No silent fallback is used.

O5 — **Bounded evidence.** History/elapsedTime completeness remains partial, attachment is ACK-only because REST3 get returns fileIds=null, and assignment uses the current user rather than proving transfer to a second user. Multi-request enrichment is not an atomic task/user snapshot.

No new spec/design or code-pattern divergence was found. The three remaining portal acceptance groups are excluded from mandatory RC 0.1.0 acceptance by the user decision of 12.10.2026 and remain unverified in #42/0.2.0. Publication/CI is tracked for the exact pushed head on PR11, separately from these local/live checks. No merge is performed.

## Решение о приёмке RC 0.1.0 — 12.10.2026

По явному решению пользователя непроверенные части portal tasks 5.2–5.4 и связанного `define-task-jtbd` task 2.3 перенесены в milestone **0.2.0**, [#42](https://github.com/mesilov/bitrix24-cli/issues/42), change `complete-task-portal-acceptance` (`openspec/changes/complete-task-portal-acceptance/`). Они исключены из обязательной приёмки RC 0.1.0; перенос не является новым runtime proof. Уже выполненные 30 собственных happy-path и исходные records сохраняются.

Границы RC: denied-role matrix не подтверждена; history/elapsedTime completeness частична; file attachment ACK без независимого membership; reassignment только на текущего пользователя; native REST3 filled auditors limitation сохраняется для task:list, task:show использует заранее выбранную композицию чтений без error-triggered fallback/retry. Binding/policy/partial/outcomeUnknown/cancellation и 29 post-MVP исключений сохраняются. #19/#23/native packaging остаются критериями RC. [Полный список ограничений](../../../docs/rc-0.1.0.md).

Исходные JSON/live counts и ограничения не изменены. Архивирование в этом изменении не выполняется.
