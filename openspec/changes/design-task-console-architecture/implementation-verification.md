# Implementation verification — 2026-10-11

Issue: [#10](https://github.com/mesilov/bitrix24-cli/issues/10). PR: [#11 → dev](https://github.com/mesilov/bitrix24-cli/pull/11). Worktree: `/private/tmp/bitrix24-cli-issue-10`, branch `feature/10-research-task-command-surface`.

## Scorecard

| Dimension | Evidence and boundary |
| --- | --- |
| Completeness | 35/38 tasks complete; 5.2–5.4 remain partially verified. All 30 MVP commands executed on the portal. 30 Command classes / 23 request-reference DTO / 11 handlers + 4 services; 29 post-MVP operations excluded |
| Correctness | 14 runtime requirements and the revised scope admission have implementation/offline coverage. Live suite: 7 passed, 1 restricted-role case skipped, 145 assertions. Filled participant enrichment now succeeds through explicitly selected companion reads; native REST3 auditors remains broken on this portal |
| Coherence | Standalone Symfony Console + DI, explicit API versions/ports, deferred SDK provider. Conditional enrichment routes are checked before credentials. PHPStan, Rector, code style and licenses passed; no error-triggered API fallback/retry |

## Changes verified

- `TaskParticipantEnricher` handles selected auditors/accomplices only. `ShowTaskHandler` reads the other fields through REST3, excluding those relations, then `Rest1ParticipantAdapter::ids` reads only ID/selected role IDs through REST1. Returned task ID and each member ID are validated. Legacy card data/auditorsData never overwrite or enrich the REST3 card.
- `Rest1UserAdapter` filters user.get by unique selected IDs, selects only required fields, chunks <=50 and caps lookup at 10000 IDs. Name is assembled from NAME/SECOND_NAME/LAST_NAME. Role order is retained; overlap shares one lookup. Missing users/scope-hidden fields/read failures preserve the card/known IDs with null, complete=false and partial3. Interruption remains130, without further calls.
- Normal task:show remains one REST3 read. ID-only and empty participant roles skip user.get. Strict-rest3 rejects enriched plans before API/credential reads; unsupported projections fail local validation. task:list retains native REST3 projections.
- `TaskParticipantEnrichmentTest` adds 33 cases: role order/dedup/minimal select, empty/ID-only/default routes, strict/local gates, missing/denied/transport/interrupted responses, wrong-task/malformed member IDs, foreign/duplicate/malformed user rows, chunk/budget bounds and actual SDK HTTP response codec.
- Earlier method-specific ACK, sparse participant replacement, env precedence, task/child binding, cleanup and signal protections retain their test coverage. Participant writes still affect only the two selected role sets; this read admission does not authorize generic REST1 CRUD.

## Local checks

- `make unit`: **191 tests / 942 assertions**, no failures or warnings.
- `make check`: Composer strict/platform requirements, PHP syntax and shared bootstrap/startup passed.
- `make lint-all`: licenses, PHP-CS-Fixer, PHPStan level5 and Rector dry-run passed. Initial Rector naming/type suggestions were applied without lowering rules.
- `openspec validate --all --strict` and `git diff --check` passed.
- Locked environment: PHP8.4.26 / Alpine3.23, SDK3.7.0, Console/DI8.1.8, PHPUnit12.5.38. Incoming webhook is loaded from the same external read-only .env.local mount; credentials are absent from argv/evidence.

## Portal evidence

Current source: [sanitized refactor execution record](portal-refactor-evidence-2026-10-11.json). Earlier enrichment: [2026-10-11 evidence](portal-enrichment-evidence-2026-10-11.json). Prior run: [2026-10-10 evidence](portal-evidence-2026-10-10.json). Raw JUnit and selected trace remain ignored under var/cache; committed evidence contains no webhook URL/token, request parameters, user names or chat text.

Final run: **8 tests / 145 assertions / 0 failures / 0 errors / 1 skipped**. All 30 CLI names appear in the current record. Identity: webhook user1 as fixture creator/responsible, own message/time-entry author; admin status is not asserted.

`testFilledParticipantsAreEnrichedThroughExplicitUserLookup` verifies filled auditors/accomplices, shared profile lookup, selected email, ID-only projection and empty-role behavior. Names/email remain excluded from evidence. The full suite includes the final email assertions. Earlier enrichment/diagnostic runs retain their original counts and IDs in their own records.

Current owned tasks: **5380, 5382, 5384, 5386, 5388, 5390, 5392, 5394, 5396**. Owned Disk files: **1380**. All deleted with zero cleanup failures. The restricted-role test is skipped without a second webhook; teardown still cleans up its own primary-webhook fixture.

## Architecture simplification and MR review

- 17 handlers were grouped into TaskMetadataService, TaskChatService, TimeEntryService and ChecklistItemService with explicit routes/prepare methods. All 34 moved methods preserve their route/preflight/execute logic against base commit 7d07ab1 after name/reference substitutions and whitespace normalization. The remaining 11 handlers keep distinct scenarios; no API adapter/transport/provider behavior changed.
- Seven simple request classes were replaced by TaskReference/ChecklistItemReference, leaving 23 DTO/reference classes. Shared mapper methods retain positive-ID validation. Thirty Command classes and seven Gateway interfaces remain.
- CommandCatalog supplies ContainerCommandLoader directly, without console.command tags, reflection or custom compiler pass. Unit tests verify all 30 loaded classes, matching AsCommand, unique classes/no aliases and zero credential/API access. CommandRunner derives name/effect centrally.
- [Naming thread](https://github.com/mesilov/bitrix24-cli/pull/11#discussion_r4240924676): tests/Unit, Tests\Unit namespace, PHPUnit suite unit-tests. Local subprocess/loopback checks are preserved and do not call the external portal.
- [Make/CI thread](https://github.com/mesilov/bitrix24-cli/pull/11#discussion_r4241086872): make unit / make integration run only unit-tests / integration-tests respectively; CI runs make unit. Old test/test-integration and requested make-unit/make-integration are compatible aliases. Live integration uses the actual provider and selected env.
- Final local run: 191 tests / 942 assertions, no failures/errors/warnings; make check, licenses, PHP-CS-Fixer, PHPStan level5 and Rector passed. The old PHPStan path and new test variable naming were corrected without lowering rules. Three old compiler-pass cases were replaced by one full-catalog test; existing command/runtime/SDK tests were preserved (193 → 191 tests, 792 → 942 assertions).
- Full live run: 8 tests / 145 assertions, 7 passed + 1 restricted-role skip, 0 failures/errors; all 30 command names appear in the new sanitized record. OpenSpec strict: all 8 items passed; git diff --check passed.


## Remaining verification

O1 — **Critical for archive: task5.2 is partially verified.** Allowed-role card/schema/list/search/chat cases pass, but denied-role chat behavior needs B24CLI_TEST_RESTRICTED_WEBHOOK and specific portal cases.

O2 — **Critical for archive: task5.3 is partially verified.** Own time/checklist operations and wrong-task gates pass; denied-role time/checklist cases remain unverified without a second webhook.

O3 — **Critical for archive: task5.4 is partially verified.** Participant omission/clear, nonempty history and offline strict gates pass. Denied-role participants and history navigation/fullness remain unproven. Provide restricted fixtures before checking off the task.

O4 — **Native API limitation retained, CLI enrichment verified.** Native REST3 filled auditors projections still fail despite user scope. task:show now reads those selected roles through the user-authorized composition; task:list native projections retain the limitation. No silent fallback is used.

O5 — **Bounded evidence.** History/elapsedTime completeness remains partial, attachment is ACK-only because REST3 get returns fileIds=null, and assignment uses the current user rather than proving transfer to a second user. Multi-request enrichment is not an atomic task/user snapshot.

No new spec/design or code-pattern divergence was found. Three incomplete portal acceptance tasks prevent archive. Publication/CI is tracked for the exact pushed head on PR11, separately from these local/live checks. No merge is performed.
