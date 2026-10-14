# Tasks

## 1. Calculation and format

- [x] 1.1 Implement weekday/calendar and independent unit rounding; test Friday–Monday, weekend-only, offsets, microseconds, long range and partial nulls.
- [x] 1.2 Update calculator, human/plain outputs and command tests, preserving timestamp/evidence/read-only contracts.
- [x] 1.3 Update help, CLI map, duration documentation and CHANGELOG; verify generated reference matches.

## 2. Evidence and verification

- [x] 2.1 Update independent live integration expectations and run exact/partial/pending acceptance with owned cleanup.
- [x] 2.2 Replay retained task 6372 raw history through production calculation/presentation; retain JSON and assert 55 transitions, 56 intervals, old-event exclusions and agreed totals.
- [x] 2.3 Run full unit, lint-all and OpenSpec strict/diff checks; record requirement mapping and open PR to dev.

## Workflow follow-up

- Archive/sync after a confirmed authorized merge and closure, and preserve evidence before removing this worktree.
