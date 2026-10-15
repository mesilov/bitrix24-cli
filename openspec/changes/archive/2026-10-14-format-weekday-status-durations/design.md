# Design

## Context

Current calculator emits numeric totals and raw calendar seconds. Renderer/TSV and live tests depend on those numbers. Server timestamps contain explicit offsets, not an IANA timezone; archived #121 fixture 6372 contains raw events, stable created and snapshots.

## Goals / Non-Goals

Apply agreed units/weekend filtering without changing API routes or completeness gates. No user/host timezone, holidays, office hours or server-IANA guesses. Keep raw elapsedBetweenEventsSeconds for diagnostic evidence.

## Decisions

- Pure WeekdayDuration helper uses fixed offset from confirmed created, already parsed by CalendarInstant. Internal calendar comparison of client asOf uses that offset; output date strings are untouched. This is the only available server calendar evidence, rather than inventing a regional timezone.
- Count partial first/last days, full weeks arithmetically and at most six residual dates, so long history intervals do not cause day-by-day unbounded loops.
- Accumulate exact weekday seconds per status before projecting seconds/hours/days. Intervals receive the same projection. Round seconds to int, hours/days to two decimals independently, null leaves for partial. No precision property or numeric strings.
- JSON numeric values do not promise trailing zeros; human uses readable units, plain adds hours/days columns and unit-labelled total comments.
- Replay archived raw data through the production calculator and ResultPresenter, not a Python conversion of old totals. This proves recomputation of retained evidence, not a fresh request to the deleted portal task. Update live test independent arithmetic for weekdays.

## Risks / Trade-offs

Breaking numeric totals shape → document unreleased format and update command consumer tests. Fixed API offset is not an IANA timezone → expose exact utcOffset, preserve dates and do not claim regional DST calendar inference. Rounded interval sums may differ from independently rounded aggregate → test exact accumulation and explain docs.
