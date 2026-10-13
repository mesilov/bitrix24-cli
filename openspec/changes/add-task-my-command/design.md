# Design

## Context

База реализации f7dc2a7. ListTaskCommand уже принимает responsible/status/due-before/max-scan; TaskInputMapper создаёт TaskQuery, ListTasksHandler запускает TaskListScanner. Scanner объединяет equality predicates и строгий due-before, ограничивает scan/output и сохраняет partial. Open states уже определены в DeadlineWindow::OPEN_STATUSES. task:my остаётся исходным post-MVP кандидатом для EMP-01.

## Goals / Non-Goals

Дать отдельное read-only рабочее представление с явным --user. Не вводить автоприоритизацию, новый user identity resolver, write API, календарные окна или отдельный механизм сканирования; не расширять права.

## Decisions

- Добавить MyTaskCommand с --user (required), --open, --due-before, --max-scan и общими форматами/profile/API-policy. --user через тот же positive-ID parser задаёт existing where.responsibleId; TaskQuery.role остаётся null, caller не подменяется и auto-current-user lookup не нужен.
- Переиспользовать ListTasksHandler/ListTasksRequest/TaskListScanner. Расширить TaskQuery дополнительным optional openOnly=false; scanner добавляет нужное status поле в predicate projection и проверяет существующие нормализованные open states с AND. Missing/unknown data => partial. Поведение старых constructors/queries при false сохраняется.
- Дополнительный REST3 get, REST1 fallback и вызов другой CLI-команды не требуются: существующая list pipeline уже выполняет выборку и поддерживает metadata/limits/output. Не копировать legacy start/returns из документации в новый payload.
- Зарегистрировать отдельный CommandCatalog entry; актуализировать exact command inventory и route/contract tests, не удаляя параллельно добавленные операции. Проверенный итоговый inventory: 42 task-команды и четыре users-команды.
- Отдельный tests/Integration/Task/MyTaskCommandTest.php с command:task:my/happy-path. SDK fixtures/readback/cleanup; unit проверяют invalid input до credentials, strict-rest3, AND, open states, boundary/null/unknown fields и budget. Integration проверяет выбранного исполнителя/фильтры и не использует другую CLI-команду.
- Во время реализации обновить docs/task-jtbd.md: ID EMP-01 сохранить, task:my переводить в ✅ Готово только по факту реализации и ссылаться на MyTaskCommandTest.php; границы полного EMP-01 остаются явными. README/help и CHANGELOG #118 обновляются в том же MR.

## Official documentation and verification boundary

14.10.2026 effective config из свежего checkout: codex mcp get bitrix24_dev --json показал enabled=true, Streamable HTTP https://mcp-dev.bitrix24.tech/mcp; выполнены реальные bitrix-search и bitrix-method-details по tasks.task.list. Официальный URL: https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-list.html. Подтверждены наличие list метода и документированные select/filter/order/pagination параметры. Это документация, не вызов портала. План сохраняет существующий transport/pagination codec; новый payload не проектируется. responsibleId и status/deadline фильтры сохраняют текущую модель TaskInputMapper/TaskQuery. Любое изменение API-контракта при реализации отдельно проверяется обязательным MCP.

## Risks / Trade-offs

- --user звучит как caller → help явно различает выбранного исполнителя и права подключения; обязательный ID соответствует согласованному кандидату.
- Новый open predicate меняет scanner → false-default и regression list/find tests обязательны.
- Неизвестное состояние даёт ложную полноту → strict predicate projection, partial/reason, без молчаливого совпадения.
- task:my похожа на task:list → реализовать тонкий интерфейс над существующим query pipeline; дополнительные backend операции не нужны.

## Migration Plan

Реализация в feature/118-add-task-my от свежей dev по запросу пользователя взять #118 в работу и довести до результата. Проверить target tests/lint/live happy-path, update JTBD/docs, доставить через MR в dev. После merge и закрытия #118 sync/archive delta specs.

## Implementation documentation refresh

14.10.2026: из worktree реализации effective config bitrix24_dev enabled=true; реальный bitrix-search → bitrix-method-details tasks.task.list подтвердил REST3 select/filter/order/pagination. Транспорт сохраняется. Для SDK fixture завершения проверены tasks.task.complete/taskId через тот же MCP; эта REST1 запись применяется только к созданным тестовым задачам, не входит в маршрут task:my. Источники: https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-list.html и https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-complete.html. Документация не является portal acceptance.

Поля REST3 сверены через bitrix-search → bitrix-article-details «Поля задачи в REST 3.0»: responsibleId integer, status string с шестью документированными значениями, deadline datetime|null. Источник: https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html. Ошибочный ввод task:my валидируется mapper до CommandRunner/profile selection; общий scanner проверяет тип исполнителя и валидность срока, сравнивает DateTimeImmutable (включая доли секунды). Для no-deadline fixture REST3 null не очистил автодедлайн, пустая строка REST3 отклонена. Классический SDK tasks.task.update с taskId/fields.DEADLINE="" и REST3 readback подтвердили null; это только test setup, не fallback команды. Контракт метода проверен MCP и дополнительно по https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html.

## Concurrent lifecycle integration

Before merge, origin/dev advanced to 8662ee7 (PR #66/#60). Conflicts resolved by retaining all seven lifecycle commands/routes, group-membership predicate/readback and lifecycle/JTBD/CHANGELOG changes alongside task:my. Registration delta now specifies **42 task + 4 users commands**; public command map has **55 commands**. The additional lifecycle integration scenario is preserved. Final acceptance is rerun on the combined scanner.
