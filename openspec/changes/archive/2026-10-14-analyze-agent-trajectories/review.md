# Review MR #107 / skill MR #7 against #96

Дата: 2026-10-13. Reviewed starting heads: CLI `6eb42b8`, skill `da8f86e`.
Scope: согласованный local-card MVP; «мокай пока отправку» остаётся обязательной
границей. Issue #96 open, milestone 0.2.0; review/fixes не означают merge/release.

## Scorecard after fixes

| Измерение | Результат |
| --- | --- |
| Completeness | 18/18 tasks, включая две review-задачи; 15 ADDED/MODIFIED requirements найдены в CLI/skill/config. Full-history adapters/scheduler/HTTP deployment не добавлены. |
| Correctness | Runtime checks и regression cases прошли; 27 capability scenarios и 8 config scenarios сопоставлены с реализацией/CLI tests либо skill instruction review. Native agent UI decision behavior не проверен в реальных Codex/Claude/OpenCode запусках. |
| Coherence | Shared config lock/journal, actual public command/help, separate offer/processing dates, snapshot/idempotency, mock != delivered и CLI-only skill соблюдены. Native Windows/macOS standalone doctor не проверялся. |

## Findings and resolution

Все шесть findings подтверждены исходниками/исполняемыми regression cases и исправлены.

| ID | Приоритет | До исправления | Исправление и evidence |
| --- | --- | --- | --- |
| O1 | P2 | Fixed TLD list пропускал bare portal domains `.kz`/`.by`, другие TLD/IDN и IP; вход сохранялся/попадал в preview. | `ProblemTrajectoryInput::text`: hostname recognition без TLD allowlist и IPv4/IPv6 через FILTER_VALIDATE_IP, включая port/key:value. `testSensitiveInputNeverEntersStorageOrOutput` + `testSensitiveAnalysisDoesNotChangeSnapshotOrProcessingDate`: отклонение без эхо/записи/сдвига Processing. |
| O2 | P2 | Исполняемый skill example переиспользовал `gap-episode` для разных reports: conflicts/retry вместо отдельного эпизода. | Явный `<UNIQUE_EPISODE_KEY>` placeholder, который агент заменяет до вызова, сохраняет для retries и меняет для нового эпизода. Public CLI smoke: два разных эпизода дают разные IDs; retry первого возвращает его прежний ID. CLI docs отдельно поясняют уникальный batchId для нового анализа. |
| O3 | P2 | Publication терял skillVersion; кандидат имел только CLI versions, хотя #96/spec требуют версии CLI/скилла. | `ProblemTrajectoryStore::publication` включает известные skill versions и explicit unknown. `testPublicationContainsCliAndKnownOrUnknownSkillVersions` и documentation smoke. |
| O4 | P2 | `reportedCliVersion` повреждённой сохранённой карточки не проверялся и мог вывести приватный token через list. | Stored version проходит тот же identifier/privacy guard. `testDamagedReportedVersionIsCountedButNeverExposedOrDeleted`: corrupt count/exit 3, без содержимого в stdout/stderr и без удаления файла. |
| O5 | P2 | Retry прежней группы после частичной перегруппировки snapshot возвращал success/duplicate с уже superseded анализом. | Active snapshot проверяется и для immutable group retry. `testRetryOfPartiallySupersededSnapshotCannotReturnAnActiveOldGroup`: usage error, текущие refs/Processing сохранены. |
| O6 | P2 | Допустимые 20×4096-byte reproduction и acceptance lists давали publication >128 KiB: analysis сохранялся, затем list помечал его corrupt. | Лимит чтения body согласован с MAX_BATCH_BYTES; внутренний document bound остаётся. `testMaximumValidGroupRemainsReadableAndCanBeSubmitted`: valid analysis >128 KiB → list → consent/mock submit проходят. |

Первое расширение targeted suite воспроизвело **14 failures**; отдельный size
regression — ещё одну. После исправлений full suite содержит 17 дополнительных
unit cases относительно starting head; исходная проверка не считалась доказательством
этих новых случаев. Только синтетические fixture values использовались в тестах.

## Verification

- PHP 8.4.26 Docker: **734 tests / 6020 assertions**, full `make unit` pass.
- `make check lint-all`: Composer strict/platform, syntax/startup, allowed
  licenses, PhpCsFixer, PHPStan level 5 и Rector pass.
- Config schema: 146 shared fixtures, 3 published examples; doctor schema:
  2 published examples, 11 negative structural cases.
- Skill quick_validate и package Markdown links pass.
- Actual CLI documentation smoke с network=none: 7 stages, включая distinct
  episode keys/retry isolation, safe publication versions и mock/reconcile.
- OpenSpec strict validation pass; issue ↔ proposal и MR pair проверены.

Сопоставление исходных 15 requirements с реализацией сохранено в verification.md.
Новые scenarios уточняют уже существующие privacy/snapshot/bounded input/versions
requirements; owner split, telemetry defaults и consent не меняются.

## Remaining verification limits

Critical findings после исправлений не обнаружены в выполненных проверках;
неисправленных warnings/suggestions в проверенном source scope нет. Native agent
UI и новые native Windows/macOS binaries не проверены. Реальный HTTP
handoff/moderation/issue publication остаётся #15/#32, а полные истории — следующим
этапом. Механическая проверка текста не гарантирует семантическое обезличивание.
Remote CI относится к опубликованному SHA и фиксируется отдельно в MR/issue;
этот локальный отчёт не утверждает его результат, merge или readiness для archive.
