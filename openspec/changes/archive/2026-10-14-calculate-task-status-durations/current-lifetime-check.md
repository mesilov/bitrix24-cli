# Current lifetime and controlled exact totals — 14.10.2026

## Why the earlier totals were null

The production handler previously consumed only history and two current-state get readbacks. It did not receive the fixture's independently recorded actions, creation identity or known event set. Multiple NEW plus an old DELETE therefore prevented a creation boundary; the STATUS-only gate and missing navigation separately prevented completeness. Pausing for five seconds does not transmit the test's knowledge to the CLI.

## Production correction

- Declare REST3 tasks.task.list in the handler and CommandCatalog policy; read `filter=[["id","=",taskId]]`, select id/created, limit 2 before/after collecting history. REST3 get cannot select created.
- Validate one matching ID and unchanged strict timezone-aware created. Identity/read errors and creation changes give current-task-creation-unverified; cancellation propagates.
- Exclude only valid-dated events **strictly before** this proven creation instant. Preserve all lifecycle rows in historyEvidence and excluded rows with exclusionReason=before-current-task-created in excludedHistoryEvidence. Events at the boundary, invalid dates, current DELETE and multiple current NEW remain subject to partial rules.
- Begin exact lifetime occupancy at actual created rather than a possibly delayed NEW. creationBoundary records the source/verified creation independently of whether history distribution is complete.
- Accept numeric STATUS 2/3/4/5, verified by independent lifecycle readbacks and the official positive status subset. REAL_STATUS has priority; matching aliases keep both IDs without double charging. Unsupported negative meta codes and string aliases remain partial. A positive STATUS row that disagrees with an existing REAL_STATUS stream remains unverified.
- Preserve source/navigation/page/budget gates and the public history:list contract. A known current creation boundary does not prove no intermediate cycles were omitted.

Primary sources checked via mandatory MCP search/details and official web documentation:

- [REST3 list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html): exact id filter, select and pagination; created is a list field. MCP method-details also resolves its current documentation under /api-reference/rest-v3/tasks/tasks-task-list.html; its mixed REST1 start section is not sent by this implementation.
- [REST1 status semantics](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html): positive REAL_STATUS lifecycle codes and STATUS additional negative meta codes. The history-field interpretation is an inference corroborated by every action's independent REST3 status/statusChanged readback; only the observed 2/3/4/5 subset is admitted.
- [History method](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-history-list.html): ambiguous navigation remains independently documented in history-investigation-check.md. No new arbitrary-source guarantee was found or asserted.

## Fresh live verification

`testFiveSecondReviewCycleShowsStatusDurationResult`: **1 test, 83 assertions, pass**, elapsed 43.909 seconds. New fixture **6366** has stable REST3 created `2026-10-14T10:17:02+03:00`. Both old records, IDs 16558 NEW and 16676 DELETE, are excluded; current NEW=23996 remains. Six own actions produce IDs 23998/24000/24002/24004/24006/24008, matching the initial current NEW plus every action in the final history. Each action has before/after REST3 status readback and matching statusChanged/history timestamp. Only the owned fixture is deleted; portal-evidence.jsonl confirms no cleanup failures.

The test computes expected totals independently from created, recorded REST3 statusChanged and asOf, then invokes the **same production calculator** with explicitly controlled-fixture-actions coverage. It does not fabricate API total/next or inject fake transport responses. This controlled source is complete within the isolated actions executed by the test; it is not a claim about arbitrary portal history, concurrent outside actors or retention.

| Status | Exact controlled seconds |
| --- | ---: |
| pending | 12 |
| in_progress | 11 |
| supposedly_completed | 12 |
| completed | 6.236554 |
| deferred | 0 |
| declined | 0 |

Total **41.236554 seconds**, exactly created→asOf; asOf=`2026-10-14T07:17:43.236554+00:00` (=10:17:43.236554+03:00). Intervals are 6/6/6/6/5/6/6.236554 seconds. Pauses are at least five seconds; API/readback latency is additional.

Artifacts:

- current-lifetime-controlled-fixture.json: per-action snapshots/raw history, actual creation readbacks, known event IDs, pause measurements and cleanup proof.
- current-lifetime-controlled-result.json: **complete=true**, exact totals, coverage=controlled-fixture-actions, excluded history evidence.
- current-lifetime-cli-result.json: ordinary live CLI has **only history-navigation-unverified**, complete=false/exit3/null exact totals. Its stable current creation and supported status chain are now verified; the test's action journal is not passed into the CLI.

## Checks and remaining acceptance

Targeted scope/arithmetic/CLI tests: **18 tests / 188 assertions**. Full unit suite: **817 tests / 12859 assertions**, pass. Cases cover earlier NEW/DELETE/STATUS, delayed NEW, equal absolute created with different offset strings, source partial, identity errors, changing/malformed created, equal-boundary/current DELETE and negative metadata statuses. Production code and integration test follow repository style/static gates; final results are recorded in verification.md. Documentation/JTBD/help/routes updated together. Task 3.3 remains open: successful exact **ordinary CLI** acceptance needs independent source completeness; controlled sums alone do not close that criterion.
