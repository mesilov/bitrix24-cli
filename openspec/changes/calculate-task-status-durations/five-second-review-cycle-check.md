# Five-second lifecycle test — 14.10.2026

`StatusDurationsTaskCommandTest::testFiveSecondReviewCycleShowsStatusDurationResult` runs a controlled SDK fixture with six lifecycle actions and a five-second pause before each. The final completed state is also held for five seconds before `task:status:durations` fixes its default asOf. Each pause is measured with hrtime; the test asserts elapsed monotonic time >=5 seconds. API/readback/history latency adds to event-to-event elapsed time.

Run:

```sh
B24CLI_ENV_FILE=/path/to/.env.local make integration TEST_ARGS='--filter testFiveSecondReviewCycleShowsStatusDurationResult'
```

Live result: **1 test, 52 assertions, pass**, elapsed 41.343 seconds. Fixture task **6332**, creator=14, responsible/acting user=1, needsControl=true. Primary actor can perform the review actions; this is not a denied-role matrix. Owned cleanup passed, confirmed by portal-evidence.jsonl. Required official MCP search/details verified REST1 taskId contracts for history/start/complete/disapprove/approve; API contracts were not changed by this test. Sources remain the official method pages recorded in the existing review-cycle/history investigation receipts.

| Action | State after action | Server statusChanged (+03:00) | New history event | Raw values |
| --- | --- | --- | ---: | --- |
| create | pending | NEW 09:46:23 | 23896 | null→null |
| start | in_progress | 09:46:29 | 23898 | 2→3 |
| complete | supposedly_completed | 09:46:35 | 23900 | 3→4 |
| disapprove | pending | 09:46:40 | 23902 | 4→2 |
| start | in_progress | 09:46:46 | 23904 | 2→3 |
| complete | supposedly_completed | 09:46:52 | 23906 | 3→4 |
| approve | completed | 09:46:57 | 23908 | 4→5 |

Pause measurements: 5.000081460, 5.000571711, 5.000224627, 5.000248461, 5.002566336, 5.002630045 seconds; final completed pause 5.000105002 seconds. The helper preserves the existing faster test and its separate receipt.

## Actual method result

Full command JSON is `five-second-status-durations-result.json`. Per-step state/history and monotonic pauses are in `five-second-review-cycle-fixture.json`. Both are sanitized; credentials and user names are absent. `asOf=2026-10-14T06:47:03.110346+00:00` equals 09:47:03.110346+03:00.

| Interval status | elapsedBetweenEventsSeconds | seconds | verified |
| --- | ---: | --- | --- |
| initial pending | null | null | false |
| in_progress | 6 | null | false |
| supposedly_completed | 5 | null | false |
| pending after rejection | 6 | null | false |
| in_progress | 6 | null | false |
| supposedly_completed | 5 | null | false |
| completed until asOf | 6.110346 | null | false |

`complete=false`, `historyComplete=false`, exit3, error.code=partial-result. All six totals are null. Reasons are history-navigation-unverified, deleted-task-history, status-equivalence-unverified, ambiguous-creation-boundary. Baseline still contains NEW/DELETE from 23 February plus the current NEW; those records are retained in evidence. Two final REST3 snapshots agree on completed/statusChanged/changed, but do not prove the missing global source contract. The new test demonstrates the real result without altering production completeness or calling observed gaps exact occupancy durations. Exact acceptance task 3.3 remains open; progress is **10/11 tasks**.
