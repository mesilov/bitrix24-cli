# Verification

## Verdict

Реализация присутствует, 19/21 задач завершены. Полная приёмка **не завершена**: task 4.4 требует denied-role live evidence, task 5.3 — итоговой verify со всеми завершёнными задачами. Change остаётся активным. Пользователь 14.10.2026 разрешил синхронизацию с dev и merge реализации; это не закрывает gaps приёмки и не подтверждает release. Состояние доставки и CI проверяется отдельно в PR #66.

## Completeness

| Область | Evidence | Результат |
| --- | --- | --- |
| Семь lifecycle команд, registration/DI/routes | CommandCatalog, семь Command-классов, ContainerRegistrationTest, TaskLifecycleTest | Реализовано; 37 task-команд и 5 connection-команд |
| Exact REST1 taskId payload и ACK | Rest1TaskLifecycleAdapter, parameterized cases TaskLifecycleTest | Реализовано; malformed/foreign ACK → outcomeUnknown, без retry |
| Rights/control/native result preflight | TaskLifecycleService, TaskStatus, gateway result.list | Реализовано; права action проверяются отдельно, создание результата не скрыто |
| Actual status, partial/unknown, preview, cancellation | TaskLifecycleTest, lifecycle-signal-worker.php, ResultPresenter | Реализовано; SIGINT subprocess подтверждает confirmed/unknown и отсутствие post-read после отмены |
| EMP-01/PM-02/AM-02 status lists | ListTaskCommandTest, локальные AND/predicate cases | Реализовано и проверено на userId=14/groupId=234 |
| Denied observer role | По одному case в каждом lifecycle CommandTest | Реализованные cases пропущены: B24CLI_TEST_RESTRICTED_WEBHOOK отсутствует; task 4.4 не закрыта |

## Correctness

- `make unit`: **377 tests, 2558 assertions**, pass. Проверяет семь route/payload contracts, offline help/invalid input/strict policy без credentials, права и semantic gates, result binding/shape, malformed ACK, lost response, server race без retry, actual/concurrent status, partial read, dry-run, human/plain/JSON, SIGINT и status validation.
- `make check`: pass (Composer validation, PHP syntax, platform requirements, command bootstrap).
- `make lint-all`: pass (allowed licenses, PHP CS Fixer, PHPStan, Rector).
- `openspec validate add-task-lifecycle --strict`: pass. `git diff --check`: pass.
- Отдельный live запуск `--filter testHappyPathChangesTaskState`: **7 tests, 76 assertions**, pass, без skips. Каждый case вызывает только свою CLI-команду через production provider; fixtures/readback/cleanup — SDK.
- Live matrix `--group lifecycle`: **24 tests, 266 assertions; 17 passed, 7 skipped**, 0 failures/errors. Пропущены только семь observer cases. Роль фактического подключения — пользователь 1; контроль проверялся с отдельным постановщиком 14. Это не доказательство прав пользователя 14 как действующего подключения.
- SDK readback подтвердил start=in_progress, pause=pending, defer=deferred, renew=pending, complete=completed без контроля; при creatorId=14/responsibleId=1 complete=supposedly_completed, approve=completed, disapprove=pending. CLI возвращает полученные состояния, не вычисляет их из имён действий.
- Native result: отсутствие результата блокирует complete (exit4); созданный SDK native result допускает complete и повторное complete после renew. Creator completion при needsControl=true может сразу дать completed — поведение отражено честно.
- autocompleteSubTasks=true: CLI complete завершил собственную подзадачу. allowsTimeTracking=true: start/pause сохранили исходную запись времени; серверный таймер может добавлять новую запись.
- Group filter: REST3 list возвращал groupId=null даже с group.id projection; get(group.id) подтверждал 234. Реализован заранее объявленный REST3 companion для подходящих по остальным predicates строк с неизвестным groupId. Lookup ≤ scanned ≤ max-scan; недоказанная группа → partial/exit3. Все group/assignee/status сочетания проверены.

## Coherence

Имена API сохранены: mutation taskId; REST3 get/access id; status, needsControl, requireResult, containsResults, autocompleteSubTasks; native result id/taskId/status. Task status enum не используется для result.status. Raw status patch, generic REST routes, task:my и auto --me не добавлены. Каталог и provider единые; API policy проверяет полный план до credentials; обращения к другим версиям при сбое нет.

Дизайн уточнён фактами портала: для контроля достаточно другого creatorId и текущего исполнителя при соответствующих правах; отдельный executor webhook также поддерживается. Дельта логического списка содержит сценарий проверки group membership через карточку. Эти уточнения сохраняют пользовательскую цель и не снимают gates.

## Evidence and remaining work

- Локальные JUnit: `var/cache/lifecycle-junit.xml`, `var/cache/lifecycle-happy-junit.xml`.
- Evidence журнала SDK fixtures: `var/cache/portal-evidence.jsonl`; результаты включают реальные taskStatus/confirmed/outcomeUnknown без credentials.
- Финальные records не содержат cleanupFailures. В промежуточном прогоне cleanup task 5724 не был подтверждён; отдельный REST3 `task:list --id 5724` после него подтвердил complete=true и пустой список. Повторного delete не выполнялось.
- Нужен webhook доступного задаче наблюдателя без права выбранного действия. После его предоставления выполнить observer matrix, закрыть 4.4, повторить verify и закрыть 5.3. Наличие ID 14 само по себе не даёт подключения от его имени.
- GitHub CI и native package jobs — отдельное evidence; локальные тесты не подтверждают их успех или выпуск бинарников.


## Повторная verify после синхронизации с dev — 14.10.2026

| Измерение | Результат |
| --- | --- |
| Completeness | 19/21 задач. 4.4 и 5.3 остаются незавершёнными; observer matrix требует restricted webhook |
| Correctness | Реализация всех требований присутствует. Contract, policy, output, cancellation и фильтры подтверждены unit suite; live denied-role evidence не проверено |
| Coherence | Сохранены 41 task-команда, четыре users-команды, шесть local-команд, русская справка, ролевая и календарная фильтрация, сортировка id DESC, result/tag/upload scope из dev |

Повторные проверки на результате merge:

- make unit: **756 tests / 12044 assertions**, pass. Добавлен regression case сочетания scalar/participant role, статуса и неизвестной группы для task:list/task:find; исключённые строки и дубли не создают лишних companion запросов.
- make check и make lint-all: pass. Исправлена сигнатура SDK fixture createTask с named fields после выделения LiveCommandTestCase в dev. Общий harness сохраняет roleFilter и lifecycle evidence.
- make cli-reference и проверка ссылок JTBD/карты: pass. Все семь lifecycle help переведены на русский; docs/task-jtbd.md отражает реализацию PM-05/EMP-05 и status-selection EMP-01/PM-02/AM-02, с границами приёмки.
- openspec validate add-task-lifecycle --strict и git diff --check: pass. Дельты runtime/MVP сохраняют существующие user/result/tag/upload/role сценарии dev.
- Повторная lifecycle matrix: **24 cases / 266 assertions, 17 passed, 7 skipped**, без failures/errors. Все skips — observer denied-role cases. Fixtures очищены.
- Прежняя ошибка native CI локализована: smoke harness ожидал прежние 30 команд (затем 34 в dev). Harness теперь проверяет 41 task-команду, наличие всех семи lifecycle и strict-rest3 dry-run denial до API, а справку — на русском. Успех native packaging определяется отдельным новым CI запуском, не локальной компиляцией Python.

### Requirement/scenario mapping

- task-lifecycle: TaskLifecycleService/Rest1TaskLifecycleAdapter/TaskStatus, TaskLifecycleTest, lifecycle-signal-worker, семь собственных CommandTest и status cases ListTaskCommandTest покрывают discovery, positive taskId, routes/ACK, action rights, review gates, required native result, actual/concurrent status, partial/unknown/cancel, preview, list AND/unknown group/scan и side effects. Control/result/live happy-path подтверждены; denied observer live evidence отсутствует.
- task-console-runtime: CommandCatalog/DI/ApiPolicyGuard и ContainerRegistrationTest/IntegrationCommandCoverageTest/TaskLifecycleTest, текущие TaskRoleFilterTest/TaskDeadlineFilterTest/LocalFileUploadTest/RussianHelpTest проверяют полный текущий каталог и route plans.
- task-mvp-scope: raw status остаётся запрещён FieldSchema; внутренний lifecycle result read не создаёт result и не заменяет отдельный task-results scope. Сохраняются существующие tags, Disk и participant admissions; историческая граница 0.1.0 не переписывается.
- task-workflows: ListTaskCommandTest и TaskLifecycleTest подтверждают EMP-01/PM-02/AM-02 фильтры своего/другого responsibleId, groupId, status и membership после переходов; видимая выборка и ограничение scan не объявляются полным проектом.

### Осталось

Два critical completeness gaps: 4.4 (ограниченное подключение наблюдателя) и 5.3 (итоговая verify после полной приёмки). Пользователь разрешил merge реализации с известными gaps. Change не готов к архивированию; checklist не помечается полностью выполненным. Issue #60 сохраняет активный путь change до завершения приёмки.
