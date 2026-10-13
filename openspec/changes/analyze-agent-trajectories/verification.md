# Verification: analyze-agent-trajectories

Дата: 2026-10-13. Scope: согласованный MVP #96 с уточнением пользователя «мокай пока отправку». CLI и skill package изменены в отдельных worktree; пользовательские primary checkouts сохранены. Проверка advisory, без merge/archive/release.

## Completeness / Correctness / Coherence

| Измерение | Результат |
| --- | --- |
| Completeness | 16/16 tasks выполнены по согласованному mock MVP. Новая capability имеет 11 requirements/23 scenarios; config delta — 4 requirements/8 scenarios. |
| Correctness | Все 15 ADDED/MODIFIED requirements сопоставлены с кодом/skill instructions ниже. CLI runtime и state cases покрыты offline tests; skill instruction flow дополнительно проверен public CLI smoke. Native UI поведения Codex/Claude/OpenCode отдельно не проверялись. |
| Coherence | Explicit command catalog, DI/Clock/feedback port, общий config lock, protected atomic publish/journal, стандартный envelope и skill-only public CLI. Config schema/runtime/fixtures согласованы; полная история/scheduler отсутствуют. |

## Requirement mapping

| Requirement | Реализация и evidence |
| --- | --- |
| Capability gap trigger | Skill SKILL.md/references/problem-trajectories.md: version/help gate, normal multi-call допустим, unsupported operation останавливается. Policy reviewed; public report example прошёл CLI smoke. |
| Public problem registration command | ProblemTrajectoryCommand/DoctorCommandRunner, ProblemTrajectoryInput::report; tests record/idempotency/private values/containers/size/depth. |
| Protected configuration adjacent storage | ProblemTrajectoryStore, ConfigDirectoryResolver, FileProtection; tests paths with Unicode/spaces, symlinks/permissions, concurrent profile+cards and interrupted journal. OS resolver tests сохранены. |
| Collection toggle and bounded retention | TelemetrySettings/store record+terminal cleanup; tests false, 100 pending, lowered limit, corrupt card, mock retention, explicit dismiss. Orphan analysis/private metadata очищаются после ухода последних active cards. |
| Public telemetry and card lifecycle access | status/list/configure/record-offer/record-analysis/recover actions; read-only tests, schema contract, actual CLI help/inventory и network=none smoke. |
| Seven day offer at skill invocation | CLI computes analysisDue using later offered/processed timestamp, UTC 604800 seconds; exact boundary test. Skill explicitly prompts once and records offer, no daemon. Native agent UI not verified. |
| Snapshot based processing state | Explicit cardIds, immutable batch/group fingerprint, journal and idempotent retry; tests invalid batch/date preservation, new pending outside snapshot, interrupted recovery. |
| Grouped and evidence limited candidates | Batch classifications/decisions/versions/counts and generated synthetic publication preview; 1/2/3/20/50 tests, unchecked/superseded rejection. Skill uses known evidence/public CLI; unavailable dedup stays unchecked. |
| Sanitized reviewable publication text | Input allowlist/bounds/known-secret guards across prose and technical metadata; saved preview, explicit consent. Raw values not echoed; absolute anonymization of arbitrary prose is not claimed. |
| Consent based feedback batch handoff | Explicit submit --confirm-publication --mock-feedback; production gateway unavailable. Fake/mock tests and network=none smoke confirm no actual send. |
| Receipt and unknown outcome reconciliation | reserve-before-call, durable per-group metadata, same key, monotonic confirmed receipt, private access secret separate; partial/lost reply/restart/reconcile tests. Mock receipt distinct from delivery/issue. |
| Root problem trajectory telemetry | TelemetrySettings + root schema object; effective true/100/null/null, omission and explicit false cases. |
| Strict telemetry values | ProfileRepository object/type check, UTC calendar roundtrip, schema FormatChecker; shared positive/negative/literal/overflow/date/unknown fixtures. |
| Telemetry persistence preserves profiles | ProfileRepository transaction shares .lock with add/use/remove; initial config without credentials, concurrency worker with profile mutation. |
| Published configuration schema | docs/config.schema.json/configuration.md and shared fixtures; Python FormatChecker and PHP actual repository tests. Existing role/defaults cases retained. |

## Checks and evidence boundaries

- Full PHP unit suite: **676 tests / 5124 assertions** in PHP 8.4.26 Docker. Doctor-specific tests include privacy/depth, technical metadata secrets, storage limits, UTC boundaries, concurrent profile writes, journal recovery and lost replies.
- `make check`: Composer strict/platform checks, syntax and actual CLI startup passed.
- `make lint-all`: allowed licenses, PSR12, PHPStan level 5, Rector dry run passed after applying reported type/style corrections.
- `config-schema-check.py`: 146 shared fixtures, 3 JSON documentation examples, 8 explicitly documented runtime-only constraints.
- `problem-trajectory-contract-check.py`: 2 published input examples and 11 negative structural cases.
- Skill creator quick_validate passes; all package Markdown references resolve. Installation policy and base 0.1.0 command map retained, doctor is capability-gated by installed help.
- Skill and CLI documentation examples exercised via actual public CLI with Docker network=none and an isolated temporary config: status without writes → report file → offer/suppression → snapshot preview → endpoint-unavailable → explicit mock → deterministic reconcile. No user config/portal/history used.
- Local Docker initially could not allocate another network (all address pools subnetted). Checks used the ordinary project image with a temporary Compose network_mode=bridge override; no unrelated networks or containers were deleted. This override is not committed or needed by clean native CI.

## Findings / limits

- No critical implementation/spec mismatch found in the checked MVP; 6.3 covers this report, strict validation and issue/change trace checks.
- Native Codex/Claude/OpenCode UI executions and native Windows/macOS standalone doctor acceptance are **not verified** by these source/offline checks. Shared OS path/ACL code is reused, but this does not constitute new platform-binary proof.
- Live feedback/HTTP schema/moderation/issue publication is **not applicable to the current agreed mock scope**; it remains #15/#32 follow-up. Fake receipts and CLI source checks do not prove external delivery.
- Reports are minimized/inspected; regex validation is not a guarantee of removal of arbitrary personal meaning. Record and mock never send network; future real handoff must retain consent/moderation.
- Remote CI, review/merge and release publication are separate states. This report does not claim them.

Assessment: no critical issues in local MVP verification; source/contract/CLI smoke checks passed. Platform binaries and native agent UI remain bounded limitations. Open review before integration; do not archive until verified delivery under maintainer rules.
