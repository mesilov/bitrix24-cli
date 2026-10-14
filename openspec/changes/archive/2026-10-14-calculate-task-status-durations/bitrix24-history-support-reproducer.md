# Ready-to-send Bitrix24 history reproduction

Status: prepared locally; not sent. Supply the portal identity privately through the authenticated support channel, never a webhook URL/token.

## Subject

New task history contains NEW/DELETE from an earlier lifetime with the same TASK_ID; exhaustive response contract is unclear

## Reproduction

On 14 October 2026, a new task returned ID 6324. REST3 tasks.task.list with filter `[["id","=",6324]]`, select `id,created,status` returned created `2026-10-14T09:30:42+03:00`.

REST1 tasks.task.history.list with `{"taskId":6324,"order":{"createdDate":"ASC"}}` already returned:

```json
[
  {"createdDate":"2026-02-23T07:49:54+03:00","field":"NEW"},
  {"createdDate":"2026-02-23T07:50:15+03:00","field":"DELETE"},
  {"createdDate":"2026-10-14T09:30:43+03:00","field":"NEW"}
]
```

These rows are in the raw HTTP body before SDK response transformation. Legacy task.logitem.list positional `[6324]` independently returns TASK_ID=6324 on the February and October rows. A paired new task has different history IDs; mutations do not leak between the two task histories. The operator confirms no database restore/reinitialization or manual task ID counter changes. Only disposable test tasks were used and deleted after capture.

In a separate task 6308, sixty uniquely named TITLE updates plus a start transition produce 64 rows in a single response, with every known TITLE value present. Omitting start or setting start=0/50/10000/-1 yields the same rows. The raw result is `{"result":{"list":[...]},"time":{...}}`, with no next/total. FIELD filters and date ordering work. The paired fixture's history is unchanged. The fixture and its pair are deleted.

## Questions requiring server-side confirmation

1. Why are the February NEW/DELETE associated with today's numeric TASK_ID when no operator restore/counter change occurred? Please inspect allocation/history retention or migration for this portal. Do both history APIs scope only by numeric TASK_ID, without an incarnation discriminator?
2. Does tasks.task.history.list guarantee **all** visible change records in one response for any history size? Is start intentionally unsupported/ignored? Is there an internal cap, retention, permissions filtering or another way to obtain a count/exhaustion marker? The parameter table and generic fifty-row client examples differ.
3. How should a client select only the lifetime of the currently existing task? Is REST3 list.created the authoritative boundary? NEW can be logged one second later; please confirm timestamp precision, timezone and concurrency semantics.
4. For history FIELD=STATUS, are values 2/3/4/5 persisted lifecycle states matching REST3 pending/in_progress/supposedly_completed/completed, or can they represent user-specific metadata? When is REAL_STATUS emitted and how should duplicates be handled?

## Attachments

- `history-investigation-fixture.json`: sanitized raw-response structure, exact event IDs/date/value pairs, 64-row probe and legacy TASK_ID confirmation.
- `review-cycle-history-fixture.json`: rejected review followed by successful approval with per-action REST3 state readbacks and incremental history.
- `history-investigation-check.md`: evidence boundaries, SDK diagnostic query-key issue and cleanup.

The client currently refuses exact duration totals where scope/exhaustion is unproven. Confirmation should specify API version and supported guarantees, so a fix can be regression-tested without guessing that a short page or stable tail means complete history.
