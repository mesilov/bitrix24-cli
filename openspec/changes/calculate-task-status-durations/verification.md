# Verification — calculate-task-status-durations — 14.10.2026

## Outcome

Implementation is reviewable as a **draft**, not complete acceptance. Task 3.3 remains open: exact live calendar totals cannot be proved with the available portal history. No merge/closure/archive is warranted from partial acceptance. Primary dirty checkout was preserved; work ran in /private/tmp/bitrix24-cli-issue-121, existing PR #123 targets dev.

## Follow-up: history identity and exhaustive-source investigation

OpenSpec task 3.5 completed; **9/10 tasks**, with 3.3 still critical/open. Fresh origin/dev 464bdb9 was integrated in 79201db; CHANGELOG conflict was resolved preserving both #121 and #58. Runtime completeness and creation gates were not changed.

- Raw HTTP contains earlier NEW/DELETE; SDK DTO and adapter IDs preserve them. Independent legacy rows explicitly have TASK_ID=6324, matching the new current task. The operator denies DB restore/counter changes. Server association is established; allocation/migration/retention cause is not established.
- Large fixture recovered all sixty known TITLE changes in 64 rows, with start ignored and next/total absent. A separate committed live test on 6328/6330 passed **1 test, 22 assertions**; 63 rows, all sixty changes, task isolation, read-only CLI/null totals and successful owned cleanup.
- Retained wire/legacy receipt plus regression fixture prove that even supplying historyComplete=true cannot justify exact totals for this ambiguous multi-lifetime source. Unit regression and prior arithmetic/concurrency/error tests pass.
- Final local suite after dev integration: **814 tests, 12822 assertions**. License, CS Fixer, PHPStan and Rector gates pass. OpenSpec strict validation and diff check pass.
- Requirement coverage adds the earlier-lifetime investigation scenario. Completeness and correctness remain **not fully verified** for exact live totals; no archive readiness is claimed. Existing status mapping/observed-cycle evidence is preserved separately from lifetime evidence.
- Vendor questions and attachment references are prepared in bitrix24-history-support-reproducer.md; no external support message sent. CI for the published follow-up SHA is verified separately.

See history-investigation-check.md and history-investigation-fixture.json for raw structure, exact IDs/dates, official source ambiguity and the diagnostic-only legacy SDK request-ID obstruction.

## Completeness, correctness, coherence

| Dimension | Evidence and limit |
| --- | --- |
| Completeness | Request/handler/command, calculator, scanner evidence, presentation, registration, unit tests, own integration class and docs implemented; exact portal happy-path remains incomplete |
| Correctness | Fixed reopening totals pending=30/in_progress=50/completed=40/total=120; historical cutoff, no-op, duplicates, offsets/DST, equal-time unique/ambiguous chains, unknown boundaries, source gaps/conflicts, race and readback errors covered offline |
| Coherence | PreparedOperation/CommandRunner, existing history gateway/scanner, REST3 TaskGateway, injected Clock, shared policies/connections/formats; no nested CLI or production mutations |

## Requirement and scenario mapping

| Requirement / scenarios | Implementation / tests |
| --- | --- |
| Read-only and invalid input / strict policy | StatusDurationsTaskCommand + StatusDurationsTaskRequest + handler routes; StatusDurationsTaskCommandTest tests deferred input, missing profile, help, strict-rest3, read routes, first error and cancellation |
| Canonical evidence / different fields | StatusDurationCalculator uses REAL_STATUS; matching STATUS IDs are supplementary evidence only, unmatched STATUS remains partial; alias/stage test |
| Chronology / duplicates, equal times, conflicting sequence | Calculator sorts offset-bearing instants and requires a unique from/to chain in each timestamp group; no ID-order assumption; HistoryEvidenceTest and calculator duplicates/order tests |
| Repeated entries / two reopenings | Calculator half-open intervals; fixture with seven transitions, eight intervals and conservation of 120 seconds |
| Initial boundary / missing creation | Unique NEW plus full consistent canonical chain required; multiple NEW/DELETE/invalid dates remain unknown; first event is never used as creation |
| Single asOf / historical reopen | Injected clock captured in request before network; later events validate chain/tail, accrue no time; historical-cutoff test and creation-before-cutoff usage test |
| Tail / concurrent changes | REST3 get before/after scanner, select status/statusChanged/changed; independent before/after comparison and last-event/statusChanged consistency; race/readback-error cases |
| Incomplete history / hidden cycles and page failure | Scanner preserves all reasons, navigation gate and 10000-event budget; calculator keeps null totals/seconds and unverified elapsed observations; partial-source tests |
| Traceability / known timestamps, unknown occupancy | observedTransitions and historyEvidence retain fields, raw values, offsets, user/event IDs; intervals have evidenceIds, verified, elapsedBetweenEventsSeconds; JSON/human/TSV tests |
| Calendar vs effort | Absolute calendar seconds include completed, nights and weekends; no effort or working-calendar routes; conservation and DST tests |
| Independent documentation / portal cannot prove history | Separate integration command group and SDK fixture/readback/cleanup; README, cli-command-map, development and JTBD PM-02/AM-03 reflect partial boundary |
| Registration / root inventory and namespaces | 43 task commands (including concurrent task:my), four users commands; CommandCatalog, ContainerRegistrationTest and generated CLI map |

## Live evidence

- Official required MCP config verified; search/method-details history/get and article-details REST3 fields answered. Sources are in proposal/design/live-contract-check.md.
- SDK fixture two reopen cycles independently read back pending/in_progress/completed. Portal emits STATUS numeric strings 2/3/5; REAL_STATUS is absent. Other numeric codes remain unknown in production calculation; logical lifecycle strings are handled with TaskStatus.
- next/total are null. Old NEW/DELETE events accompany current fixture creation, so creation is ambiguous. Do not silently choose one creation boundary or upgrade consistent neighbours to completeness.
- Own partial test checks exit 3, null totals, navigation reason, read-only API calls, current completed readback and successful cleanup.
- Exact happy-path explicitly reports **incomplete**, without a successful CLI invocation. This is recorded separately and is not a pass/skip substitute for exact arithmetic.
- Combined new-command/history:list live run: 3 tests, 41 assertions, **1 incomplete**; cleanupFailures empty. Live behavior was checked on implementation e851886 before the subsequent dev integration; final integration only adds concurrent task:my/inventory/doc changes to this scope.

## Review findings

- **O1 — CRITICAL, open acceptance:** prove exact totals on a portal/source with verified full navigation, one creation boundary and canonical REAL_STATUS events (or independently established STATUS equivalence). Keep task 3.3 open and PR draft. Partial-result safety is verified; it does not satisfy this acceptance.
- **O2 — SUGGESTION, conservative evidence limits:** unverified numeric codes stay unknown; more than eight status events sharing a timestamp are conservatively ambiguous to bound ordering search. Neither boundary is replaced with ID chronology or precise totals.

## Checks

- Before final dev integration: make unit — 772 tests, 12306 assertions; later evidence-retention focused run — 16 tests, 152 assertions.
- make check: Composer strict validation, syntax, platform requirements and CLI startup passed.
- make lint-all: allowed licenses, PSR-12, PHPStan and Rector passed.
- CLI reference and compose-connection checks passed; OpenSpec strict validation and git diff --check passed.
- Final post-integration checks and CI are recorded below once complete. Offline success is not exact portal proof, merged delivery, or a release.

## Final post-dev-integration checks

- Source implementation head: `1d9a3f82dbe3646ab56073ec2fe0d22d90c8b0fb`; base dev: `2b24edd1a808150fb382812c52d22b235e25d940`.
- make unit: **799 tests, 12658 assertions**, pass.
- make lint-all: all four checks pass; make check: pass.
- make cli-reference-check: **56 public commands**, pass.
- OpenSpec strict validation/diff checks pass.
- Exact live requirement/scenario coverage is **not verified** for the reasons in O1. One critical incomplete acceptance blocks archive; one suggestion, no implementation warnings in checks performed.

- CI-pinned jsonschema 4.25.1 checks passed in a disposable venv: 146 shared fixtures, 3 configuration examples; doctor 2 examples and 11 negative cases.
- Remote CI is pending publication; read final PR checks against its exact head. This report does not assert remote CI passed.
- OpenSpec progress: **7/8**, task 3.3 exact live acceptance remains open.

- Concurrent remote plan commits fb97a06/b3f9b18 integrated without force-push: lifecycle integration scenario and planning receipts preserved; implementation tasks remain checked only against tests, 3.3 remains open. No runtime changes in this reconciliation.

## Follow-up — rejected review then successful approval

Requested live scenario passes: **1 test, 38 assertions**; SDK task 6306 traversed pending → in_progress → supposedly_completed → pending → in_progress → supposedly_completed → completed. Each step has a new STATUS event and REST3 readback. Cleanup passed. See review-cycle-history-check.md and review-cycle-history-fixture.json.

Code 4 mapping is now confirmed and included as supposedly_completed. Numeric review/rejection/approval unit fixture proves 120-second conservation; STATUS-only remains partial/null even with this mapping. Targeted arithmetic/CLI suite: 14 tests, 144 assertions, pass. All four lint-all checks pass. Task 3.4 done; 3.3 exact live acceptance remains open (**8/9**).

- Final follow-up full unit suite: **800 tests, 12665 assertions**, pass. Live review-cycle run: **1 test, 38 assertions**, pass; all lint-all and OpenSpec strict/diff checks pass. New commit CI is checked separately after push; prior head 6ecbaa4 had 13/13 successful checks.
