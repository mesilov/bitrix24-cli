# Design

## Context

База 1741b9c. ListTaskCommand уже принимает responsible/status/due-before/max-scan; TaskInputMapper создаёт TaskQuery, ListTasksHandler запускает TaskListScanner. Scanner объединяет equality predicates и строгий due-before, ограничивает scan/output и сохраняет partial. Open states уже определены в DeadlineWindow::OPEN_STATUSES. task:my остаётся исходным post-MVP кандидатом для EMP-01.

## Goals / Non-Goals

Дать отдельное read-only рабочее представление с явным --user. Не вводить автоприоритизацию, новый user identity resolver, write API, календарные окна или отдельный механизм сканирования; не расширять права. План не является реализацией.

## Decisions

- Добавить MyTaskCommand с --user (required), --open, --due-before, --max-scan и общими форматами/profile/API-policy. --user через тот же positive-ID parser задаёт existing where.responsibleId; TaskQuery.role остаётся null, caller не подменяется и auto-current-user lookup не нужен.
- Переиспользовать ListTasksHandler/ListTasksRequest/TaskListScanner. Расширить TaskQuery дополнительным optional openOnly=false; scanner добавляет нужное status поле в predicate projection и проверяет существующие нормализованные open states с AND. Missing/unknown data => partial. Поведение старых constructors/queries при false сохраняется.
- Дополнительный REST3 get, REST1 fallback и вызов другой CLI-команды не требуются: существующая list pipeline уже выполняет выборку и поддерживает metadata/limits/output. Не копировать legacy start/returns из документации в новый payload.
- Зарегистрировать отдельный CommandCatalog entry; актуализировать exact command inventory и route/contract tests, не удаляя параллельно добавленные операции. Main spec count 35 относится к базе этого плана; перед apply пересчитать по актуальной dev.
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

Реализовать отдельной feature-веткой #118 от свежей dev после отдельного запроса apply. Проверить target tests/lint/live happy-path, update JTBD/docs, доставить через MR в dev. После merge и закрытия #118 sync/archive delta specs. Сейчас сохраняется только план.

## Синхронизация planning branch

Перед публикацией интегрирована dev f7dc2a7 (локализация help, #113). ListTasksHandler/TaskQuery/Scanner и задача task:my не изменились. При реализации использовать текущие русскоязычные help descriptors/группы и обновить docs/cli-command-map.md вместе с README и JTBD.
