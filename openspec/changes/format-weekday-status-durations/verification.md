# Verification — format-weekday-status-durations — 14.10.2026

## Evidence

- Unit: 825 tests / 13125 assertions, pass. WeekdayDuration cases cover weekend-only, Friday–Monday (64800 seconds), positive/negative server offset with UTC cutoff, microseconds and 52 full weeks. Calculator case proves exact accumulation before independent rounding, excludes weekend completed time and preserves all source date strings and offset.
- Command tests cover nested units, null leaves, absence of precision, integer seconds, human/plain columns and unchanged read-only route list/policy/error contracts.
- Full lint-all (licenses, CS Fixer, PHPStan, Rector) passes after fixing test-only match default and naming/style findings. CLI reference: 56 commands matches. OpenSpec all-strict: 26 passed/0 failed, diff check passes.
- Fresh live acceptance: exact two reopenings, concurrent-change partial and new pending: 3 tests / 69 assertions, pass. Tasks 6382/6384/6386 deleted, cleanup failures empty (live-cleanup.json). Independent oracle iterates days instead of using production full-week arithmetic; units derive from unrounded totals. weekday-live-exact-reopen.json retains action/statusChanged/history evidence.
- Long retained fixture 6372 replayed through ordinary command + real handler/adapters/calculator/presenter with frozen asOf and retained API responses. Live capture had flattened user.id into userId; test restores that original nesting. 55 transitions, 56 intervals, two excluded old NEW/DELETE, identical date strings/evidence and null error. Result: long-task-6372-weekday-result.json. This is offline replay of a deleted portal task, not a fresh network request. The fixture falls on Wednesday, so weekend exclusion does not change its unrounded seconds.

## Requirement mapping and assessment

| Dimension | Evidence |
| --- | --- |
| Completeness | All three requirements implemented; task completion updated after PR delivery |
| Correctness | Traceable units: JSON/human/plain/null/rounding tests; calendar: weekends/offsets/boundaries/full weeks and independent live arithmetic; source fields and timestamps preserved |
| Coherence | Pure helper, shared runtime/presenter; no additional API route or mutation, user timezone or holiday calendar |

No critical findings, warnings or skipped checks within the declared format/calendar scope. Server calendar is explicitly the fixed offset in confirmed created (or unique verified NEW), not an inferred IANA region. Hours/days JSON are numeric and may omit trailing zeros. Public numeric totals become unit objects as explicitly requested; this is documented as a breaking unreleased change. Merge, release and fresh long-task portal query are not claimed.
