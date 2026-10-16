# Verification: add-task-templates

Проверено 16.10.2026. Issue: https://github.com/mesilov/bitrix24-cli/issues/72. База реализации: `536131d9dbeb4a5c9e189f20e604e8c26c812e03` (dev). Согласованы пять команд чтения/управления шаблонами.

## Results

| Измерение | Результат |
| --- | --- |
| Completeness | Реализованы 5 команд и 5 требований; 5 отдельных portal integration classes. Все 6/6 задач завершены; опубликован MR #160 в dev. |
| Correctness | Все сценарии сопоставлены тестам ниже; 861 unit tests / 14 191 assertions прошли. |
| Coherence | Port/application service/REST1 adapter и CommandRunner соответствуют design; нативные имена и типы согласованы с issue и документацией MCP. |

## Requirement and scenario mapping

| Требование и сценарии | Реализация | Проверка |
| --- | --- | --- |
| Read template and field metadata: accessible, unavailable, metadata | Rest1TaskTemplateAdapter::get/fields; TaskTemplateService | TaskTemplatesTest::testShowAndMetadataKeepNativeFieldsAndSerializedValues; testUnavailableOrMismatchedTemplatesNeverMutate; testInvalidMetadataDoesNotBecomeAnEmptySuccessfulSchema |
| Create/update: create, sparse update, invalid native fields, files/stdin | TaskTemplateInputMapper; Rest1TaskTemplateAdapter::add/update | testCreateAndSparseUpdateUseNativePayloadAndSdkScalarArrays; testInlineFileAndStdinHaveIdenticalPayloads; testInvalidInputFailsBeforeAnyApiCall |
| Ambiguous deletion: partial result, automation force | TaskTemplateService::prepare; CommandRunner | testDeleteFalseReportsPartialRegardlessOfObservedVisibility; testNegativeAndMalformedMutationRepliesAreNeverRetried; testInvalidInputFailsBeforeAnyApiCall |
| Shared policy/output/dry-run: offline add/update, read-only delete, policy/scope, machine output | CommandCatalog; ScopeManifest; ResultPresenter; TaskTemplateCommand | testDryRunsAndStrictPolicyDeclareOnlyAdmittedRoutes; ScopeValidationCommandTest::testMissingTaskScopeBlocksEveryTemplateCommandBeforeBusinessApi; testPlainAndHumanEscapeValuesWithoutChangingNativeKeys; testPlainMetadataMutationAndPlanUseDocumentedColumns; testDeleteVisibilityIsAlsoReportedInPlainAndHumanOutput |
| PM-08 JTBD: inspect coverage | docs/task-jtbd.md; docs/task-usage.md; README; generated map | IntegrationCommandCoverageTest; cli-reference.php --check; ссылки на пять integration classes проверены |

Отдельно testActualSdkPreservesScalarResponsesForAllTemplateMutations проходит через настоящий SDK и MockHttpClient: integer/boolean results нормализуются SDK в одноэлементные массивы. Fixture-транспорт не заменяет эту проверку.

## Executed checks

- PHP 8.4.26, Composer install из composer.lock (96 packages); platform requirements и composer validate --strict прошли.
- Полный unit suite: 861 tests, 14 191 assertions; без failures/skips.
- PHP CS Fixer: 0 файлов требуют исправления; PHPStan: no errors; Rector: no changes.
- Composer License Checker: all dependencies allowed.
- openspec validate --all --strict: 25 passed, 0 failed. Информационные замечания существующего define-task-jtbd не относятся к этому change.
- CLI reference: соответствует 62 публичным командам. JSON/plain/human и оба help routes проверены unit suite.
- Compose connection forwarding: 4 fixtures, readonly overlays — passed.
- git diff --check — passed.
- Portal integration group task-templates: 5 tests skipped, 0 assertions; webhook не задан. Это не live-приёмка.

Из-за исчерпанного Docker address pool проверки запущены в существующем PHP 8.4 образе через docker run --network none с изолированным mount worktree. Чужие сети/контейнеры не изменялись.

## Limits

Не проверены live-портал, полная матрица прав, native binaries и публикация Release. Delete=false возвращает exit 3/confirmed=false даже при последующем get=false; успешное удаление не заявляется. Записываемые поля ограничены явным набором в design/help; list/instantiate/расписания/custom fields/чек-листы не входят в согласованную поставку.

Локальные проверки не заменяют GitHub CI. CI и проверка замечаний MR фиксируются после публикации.

## Delivery

- MR: https://github.com/mesilov/bitrix24-cli/pull/160, base dev, OPEN.
- Первый implementation commit: `3866850c9f3357d7c4c8e8b9473d69fd40bedf83`.
- Непосредственно перед MR выполнены git fetch origin и git merge --no-edit origin/dev (already up to date); changelog проверен относительно свежей origin/dev.
- Issue #72 обновлено согласованным объёмом и обратной ссылкой на change; milestone 0.3.0 и label enhancement проверены через GitHub.
- Локальные ссылки README/JTBD/CLI map/руководства проверены: 0 отсутствующих файлов.
- CI и review должны проверяться на последнем head после публикации этого отчёта; runtime не менялся после локальных проверок.

Критических расхождений реализации, требований и design не обнаружено. Все применимые локальные проверки выполнены. Live-приёмка и native binaries остаются явно не проверены; их отсутствие не представлено как успешный результат. Merge и архивирование ещё не выполнены.

## Review correction

Замечание https://github.com/mesilov/bitrix24-cli/pull/160#discussion_r4287792305 подтверждено: human/plain скрывали visibleAfter. Добавлен столбец только для delete; true/false сохраняются, неудавшееся чтение показывает unknown (JSON — null). Add/update сохранили четыре столбца. Новый regression test проверяет оба режима и три результата чтения. После исправления полный unit suite: 861 tests / 14 191 assertions; PHP CS Fixer, PHPStan, Rector, CLI reference и strict OpenSpec прошли.
