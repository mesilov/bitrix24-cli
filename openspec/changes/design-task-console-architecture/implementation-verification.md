# Implementation verification — 2026-10-10

Issue: [#10](https://github.com/mesilov/bitrix24-cli/issues/10). PR: [#11 → dev](https://github.com/mesilov/bitrix24-cli/pull/11). Worktree: `/private/tmp/bitrix24-cli-issue-10`, branch `feature/10-research-task-command-surface`.

## Scorecard

| Dimension | Evidence and boundary |
| --- | --- |
| Completeness | 28/31 tasks complete; 5.2–5.4 remain partially verified. All 30 MVP commands executed on the portal; 28 returned success in at least one case, history/time context returned the specified partial result. 30 Command classes / 28 handlers; 29 post-MVP operations excluded |
| Correctness | 13 runtime requirements have implementation/offline coverage. Live suite: 7 passed, 1 restricted-role case skipped, 131 assertions. Filled REST3 auditor projection is a confirmed API limitation, covered as safe error handling rather than successful retrieval |
| Coherence | Standalone Symfony Console + DI, explicit API versions/ports, deferred SDK provider; no FrameworkBundle/Kernel or runtime API fallback. PHPStan, Rector, code style and licenses passed |

## Changes verified

- `Rest1TimeEntryAdapter` update/delete and `Rest1ChecklistAdapter` update accept the documented `result:null` as SDK `[null]`. Other false/empty/wrong response shapes are rejected. REST3/IM/complete/renew/delete boolean acknowledgements retain their own contract.
- `Rest1ParticipantAdapter` verifies the returned REST1 `task.id` against TASK_ID. Participants preflight reads id/title only; omission and explicit clearing preserve the other role set. Filled role readback is an explicit test-only REST1 get, not a production fallback.
- `FieldSchema` expands root accomplices/auditors select into id/name projections and preserves explicit nested select. Actual filled auditors projection still fails on this portal with INTERNAL_SERVER_ERROR; CLI reports api-error/details.apiErrorCode, makes one REST3 request and does not retry/fall back.
- Root `.env.local` is supported by default, otherwise `.env`; B24CLI_ENV_FILE selects an explicit file and process credentials take priority. CLI and integration settings use the same file selection. Credentials remain in the external read-only mount and are omitted from evidence.
- File test creates a small owned Disk file when no existing fixture ID is supplied. It deletes only its created file; supplied fixtures remain intact. Failed cleanup reports owned task/file IDs.

## Local checks

- `make test`: **160 tests / 676 assertions**, no failures or warnings; includes actual SDK HTTP response codec, 30 command routes/output contracts, negative acknowledgements, ID binding, policy, timeout/SIGINT and env precedence.
- `make check`: Composer strict/platform requirements, all PHP syntax and both shared application bootstrap/startup checks passed.
- `make lint-all`: licenses, PHP-CS-Fixer, PHPStan level5 and Rector dry-run passed. The initial PHPStan complaint about a non-exhaustive test helper match was fixed without lowering rules.
- `openspec validate --all --strict`: 5 canonical specs and 3 active changes passed; `git diff --check` passed.
- Locked environment: PHP8.4.26 / Alpine3.23, SDK3.7.0, Console/DI8.1.8, PHPUnit12.5.38. SDK integration Factory defaults to incoming webhook; OAuth ApplicationBridge remains outside this change.

## Portal evidence

Source: [sanitized execution record](portal-evidence-2026-10-10.json), generated from `tests/Integration/TaskCommandsTest.php` and its JUnit result. Raw generated files remain ignored under `var/cache/` and contain only selected safe command metadata and owned fixture IDs, without URL/token/parameters or chat text.

Final run: **8 tests / 131 assertions / 0 failures / 0 errors / 1 skipped**. Identity: webhook user1 as fixture creator/responsible, message author and time-entry author; admin status was not asserted. REST3 task operations and send, REST1 IM companion and admitted legacy task routes were observed separately.

All 30 CLI names appear in the execution record. Card/title/description/deadline/schema/access/list/search, own chat send/read/update/delete, time CRUD, checklist roots/nested items/title/state/delete, participants and file attach were exercised. Changes were read back where supported. Wrong-task message/entry and cross-root parent requests returned gate4 with read-only API calls. Complete/renew did not change task status. History and time context returned partial3 as specified.

Owned task IDs: **5338, 5340, 5342, 5344, 5346, 5348, 5350, 5352, 5354**. Owned Disk file: **1368**. All were cleaned up; no cleanup failures. Earlier diagnostic task5302 confirmed method-specific ACK shapes; task5316/5320 confirmed the server projection limitation, and all diagnostic fixtures including file1356 were deleted.

## Remaining verification

O1 — **Critical for archive: tasks5.2/5.3/5.4 have unverified denied-role cases.** B24CLI_TEST_RESTRICTED_WEBHOOK is absent; chat/time/checklist/participant permission checks under another role were not proven. Add the restricted fixture and role-specific cases before marking those tasks complete. Strict-rest3 zero-call gates remain covered offline, not claimed as portal role proof.

O2 — **Confirmed portal API limitation.** Filled `auditors`, `auditors.id`, `auditors.name` and their combination return INTERNAL_SERVER_ERROR. Safe error handling passes; successful REST3 retrieval of that relation is not verified. Recheck after a portal/API fix; do not silently substitute REST1 in task:show.

O3 — **Bounded result evidence.** History navigation/fullness and elapsedTime aggregate fullness remain unproven, so partial is preserved. File attachment is ACK-only because REST3 get returns fileIds=null. Assignment uses current user rather than proving reassignment to a second user. These limits are not hidden by passing tests.

No new spec/design divergence or code-pattern issue was found in the reviewed fixes. Three incomplete portal acceptance tasks prevent archive. Publication/CI is recorded on PR11 for the exact pushed commit; local and live checks are separate from CI. No merge is performed.
