# Verification: unify-group-id-option

Дата: 11.10.2026. Baseline: dev b53178e. Scope: issue #27, включая согласованный пользователем фильтр группы для task:find.

## Scorecard

| Dimension | Result |
| --- | --- |
| Completeness | 8/8 tasks; 2 modified и 2 added requirements реализованы |
| Correctness | 4/4 requirements, 15/15 scenarios проверены кодом и unit/subprocess coverage |
| Coherence | Существующий mapper, REST3 adapter, TaskQuery/TaskListScanner и completion; новых dependencies нет |

Critical: 0. Warnings: 0. Suggestions: 0. Skipped applicable checks: 0. Ready for archive.

## Requirements and scenarios

- Convenient and explicit task field updates: AddTaskCommand/UpdateTaskCommand и TaskInputMapper сохраняют sparse fields. GroupIdOptionTest::testExplicitAndOmittedGroupIdKeepSparseRest3Payloads проверяет add/update routes, API version, groupId и отсутствие поля. Прежние basic creation, несколько изменений и empty description покрывают CommandContractsTest, CommandBehaviorTest и соответствующие integration test definitions; локально выполнялись unit tests, не live integration.
- Explicit filtering and completeness of lists: TaskInputMapper передаёт groupId в TaskQuery.where, TaskListScanner выбирает predicate fields и фильтрует локально. GroupIdOptionTest::testGroupFilterStaysLocalAcrossPagesAndStructuredInput проверяет 51 задачу/две страницы, отсутствие server group filter, structured where/where-file и отсутствие неявного фильтра. ScanningAndBindingTest сохраняет coverage scan cap/page errors/empty result и разделение id server filter от predicates.
- Consistent group identifier option: GroupIdOptionTest проверяет invalid/missing/absent ID, inline advanced/fields-file input, конфликт field flags/JSON, отсутствие старой option definition, реальный help и динамический _complete для всех четырёх команд. 36 subprocess вызовов (4 команды × 3 порядка старого/обоих флагов × fake transport worker/bin/b24cli/bin/console) подтверждают usage-error/exit 2 до API и чтения отсутствующего env file.
- Group-scoped title search: FindTaskCommand и TaskInputMapper добавляют --group-id в существующие local predicates. GroupIdOptionTest::testFindCombinesTitleAndGroupWhilePreservingLimitsAndPartialResults проверяет 54 задачи/две страницы, Unicode title и пересечение группы, отсутствие group filter, id/title output, limit/all, полный пустой результат и scan cap/exit 3. Прежний title search сохраняет coverage ScanningAndBindingTest.

## Commands executed

- Targeted: make unit TEST_ARGS='--filter GroupIdOptionTest' — PASS, 49 tests / 507 assertions.
- make check — PASS: strict Composer validation, PHP syntax, locked platform requirements, CLI startup.
- make unit — PASS, 242 tests / 1664 assertions, PHPUnit 12.5.38 / PHP 8.4.26.
- make lint-all — PASS: allowed licenses, PHP-CS-Fixer (0/172 files), PHPStan (no errors), Rector (no changes).
- openspec validate --all --strict — PASS, 10 items / 0 failed. Existing informational note: define-task-jtbd contains an ADDED requirement already present in cli-experience; its archive is outside #27.
- git diff --check — PASS.

Первичная установка Composer прервалась на codeload timeout PHPStan. Изолированный vendor восстановлен из локального worktree с идентичным SHA-256 composer.lock fca97d6ebd4b108c3869709b0a6745d7a657447b5bf011a5a8b233d9946b8ee7. Повторный make composer-install завершился успешно: nothing to install/update/remove, autoload regenerated. Composer lock/dependencies не изменены.

## Surface and traceability

- --group-id зарегистрирован в task:add/update/list/find; src не содержит project option declarations/mapper keys.
- README migration note предупреждает о breaking change, объясняет groupId и локальную фильтрацию. Ранее опубликованные preview binaries отдельно помечены как собранные до миграции.
- Активные cli-candidates Markdown/JSON и define-task-jtbd delta обновлены. Архивы, portal evidence, Docker --project-name/COMPOSE_PROJECT_NAME не изменены.
- Issue #27 содержит обратную ссылку на unify-group-id-option; proposal содержит полный URL #27. Milestone 0.1.0 и существующие labels enhancement/documentation подтверждены через GitHub.
- #19 и его локальные proposal/design/spec/tasks уже используют groupId/--group-id. Profile defaults и connection storage ещё не реализованы; explicit-over-profile runtime matrix принадлежит #19. В #27 подтверждены явные basic/advanced groupId и отсутствие неявных defaults; profile defaults не добавлялись.

## Evidence boundary

API payloads/filtering проверены контролируемым transport fixture. Live portal запросы и создание реальных задач для #27 не выполнялись. Публикация, CI и merge подтверждаются отдельно в GitHub PR; локальные результаты не обозначают merge или обновление старых binary artifacts.

## Archive verification

- cli-experience main spec: 2 modified + 2 added requirements synced; Purpose, unrelated requirement blocks and retained scenarios preserved.
- openspec validate --specs --strict — 6 passed / 0 failed.
- После переноса в openspec/changes/archive/2026-10-11-unify-group-id-option/: openspec validate --all --strict — 9 passed / 0 failed; git diff --check — PASS.
- Архивный путь обновлён в issue #27, acceptance checklist отмечен по проверенным результатам. Доставка в main остаётся отдельным шагом.
