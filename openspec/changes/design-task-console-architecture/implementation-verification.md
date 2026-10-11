# Implementation verification — 2026-10-11

Issue: [#10](https://github.com/mesilov/bitrix24-cli/issues/10). PR: [#11 → dev](https://github.com/mesilov/bitrix24-cli/pull/11). Worktree: `/private/tmp/bitrix24-cli-issue-10`, branch `feature/10-research-task-command-surface`.

## Scorecard

| Dimension | Evidence and boundary |
| --- | --- |
| Completeness | 29/32 tasks complete; 5.2–5.4 remain partially verified. All 30 MVP commands executed on the portal. 30 Command classes / 28 handlers; 29 post-MVP operations excluded |
| Correctness | 13 runtime requirements and the revised scope admission have implementation/offline coverage. Live suite: 7 passed, 1 restricted-role case skipped, 142 assertions. Filled participant enrichment now succeeds through explicitly selected companion reads; native REST3 auditors remains broken on this portal |
| Coherence | Standalone Symfony Console + DI, explicit API versions/ports, deferred SDK provider. Conditional enrichment routes are checked before credentials. PHPStan, Rector, code style and licenses passed; no error-triggered API fallback/retry |

## Changes verified

- `TaskParticipantEnricher` handles selected auditors/accomplices only. `ShowTaskHandler` reads the other fields through REST3, excluding those relations, then `Rest1ParticipantAdapter::ids` reads only ID/selected role IDs through REST1. Returned task ID and each member ID are validated. Legacy card data/auditorsData never overwrite or enrich the REST3 card.
- `Rest1UserAdapter` filters user.get by unique selected IDs, selects only required fields, chunks <=50 and caps lookup at 10000 IDs. Name is assembled from NAME/SECOND_NAME/LAST_NAME. Role order is retained; overlap shares one lookup. Missing users/scope-hidden fields/read failures preserve the card/known IDs with null, complete=false and partial3. Interruption remains130, without further calls.
- Normal task:show remains one REST3 read. ID-only and empty participant roles skip user.get. Strict-rest3 rejects enriched plans before API/credential reads; unsupported projections fail local validation. task:list retains native REST3 projections.
- `TaskParticipantEnrichmentTest` adds 33 cases: role order/dedup/minimal select, empty/ID-only/default routes, strict/local gates, missing/denied/transport/interrupted responses, wrong-task/malformed member IDs, foreign/duplicate/malformed user rows, chunk/budget bounds and actual SDK HTTP response codec.
- Earlier method-specific ACK, sparse participant replacement, env precedence, task/child binding, cleanup and signal protections retain their test coverage. Participant writes still affect only the two selected role sets; this read admission does not authorize generic REST1 CRUD.

## Local checks

- `make test`: **193 tests / 792 assertions**, no failures or warnings.
- `make check`: Composer strict/platform requirements, PHP syntax and shared bootstrap/startup passed.
- `make lint-all`: licenses, PHP-CS-Fixer, PHPStan level5 and Rector dry-run passed. Initial Rector naming/type suggestions were applied without lowering rules.
- `openspec validate --all --strict` and `git diff --check` passed.
- Locked environment: PHP8.4.26 / Alpine3.23, SDK3.7.0, Console/DI8.1.8, PHPUnit12.5.38. Incoming webhook is loaded from the same external read-only .env.local mount; credentials are absent from argv/evidence.

## Portal evidence

Current source: [sanitized execution record](portal-enrichment-evidence-2026-10-11.json). Prior run: [2026-10-10 evidence](portal-evidence-2026-10-10.json). Raw JUnit and selected trace remain ignored under var/cache; committed evidence contains no webhook URL/token, request parameters, user names or chat text.

Final run: **8 tests / 142 assertions / 0 failures / 0 errors / 1 skipped**. All 30 CLI names appear in the current record. Identity: webhook user1 as fixture creator/responsible, own message/time-entry author; admin status is not asserted.

`testFilledParticipantsAreEnrichedThroughExplicitUserLookup` verifies filled auditors and accomplices, shared profile lookup, name readback, ID-only projection and empty-role behavior. Filled/root path uses exactly tasks.task.get@3.0 → tasks.task.get@1.0 → user.get@1.0 and complete=true. ID-only/empty use two task reads. Targeted rerun on final code adds observer email readback and absence from unselected roles: 1 test / 23 assertions passed. Name/email share the same user.get; email values are excluded from evidence. The full-suite result above precedes these extra email assertions; production code did not change between runs. A prior owned diagnostic task confirmed user scope already present and user.get succeeds while native REST3 auditors.id still returns INTERNAL_SERVER_ERROR.

Current owned tasks: **5360, 5362, 5364, 5366, 5368, 5370, 5372, 5374, 5376**. Owned Disk file: **1374**. All deleted with no cleanup failures. Diagnostic task5356 and targeted-test tasks5358/5378 were also deleted.

## Remaining verification

O1 — **Critical for archive: task5.2 is partially verified.** Allowed-role card/schema/list/search/chat cases pass, but denied-role chat behavior needs B24CLI_TEST_RESTRICTED_WEBHOOK and specific portal cases.

O2 — **Critical for archive: task5.3 is partially verified.** Own time/checklist operations and wrong-task gates pass; denied-role time/checklist cases remain unverified without a second webhook.

O3 — **Critical for archive: task5.4 is partially verified.** Participant omission/clear, nonempty history and offline strict gates pass. Denied-role participants and history navigation/fullness remain unproven. Provide restricted fixtures before checking off the task.

O4 — **Native API limitation retained, CLI enrichment verified.** Native REST3 filled auditors projections still fail despite user scope. task:show now reads those selected roles through the user-authorized composition; task:list native projections retain the limitation. No silent fallback is used.

O5 — **Bounded evidence.** History/elapsedTime completeness remains partial, attachment is ACK-only because REST3 get returns fileIds=null, and assignment uses the current user rather than proving transfer to a second user. Multi-request enrichment is not an atomic task/user snapshot.

No new spec/design or code-pattern divergence was found. Three incomplete portal acceptance tasks prevent archive. Publication/CI is tracked for the exact pushed head on PR11, separately from these local/live checks. No merge is performed.
