# Design

## Context

См. proposal.md. `TaskInputMapper::query` задаёт `id ASC`, `TaskListScanner` дополнительно пересортировывает find-совпадения по id ASC после обхода. `Rest3TaskAdapter` передаёт order и pagination в REST3, а поля группы/заголовка проверяются локально. Лимит вывода применяется после окончания ограниченного обхода.

## Goals / Non-Goals

**Goals:** получить свежие задачи первыми при конечном бюджете, согласовать сортировку обхода и вывода, объяснить предел проверки дублей.

**Non-Goals:** менять REST маршрут, фильтровать groupId/title на сервере, прекращать обход по --limit, гарантировать полноту при concurrent writes или расширять title matching.

## Decisions

1. Default — `id DESC` для list/find. ID даёт однозначный порядок и доступ к недавним задачам без дополнительной проекции created. Увеличение бюджета без смены порядка оставило бы проблему таймаутов.
2. Find получает существующий повторяемый --order списка с общим mapper/allowlist. Явные order сохраняются; отсутствующий id дополняется ASC, как у списка. Scanner сохраняет серверный порядок, убирая отдельный usort для find.
3. Группа, title, responsible/status/deadline остаются локальными. MCP method summary подтверждает форму filter/order/pagination, но не обещает filterable groupId; подробная актуальная документация прямо ограничивает filter полем id. Не вводим недокументированный запрос и legacy fallback.
4. `meta.order` содержит фактическую сортировку; `meta.hint` при scan-budget-exhausted объясняет увеличение --max-scan и предел --all. Presenter включает reason/scanned/order/hint в partial diagnostic и JSON error.message. Data/TSV columns и error.code сохраняются.
5. Regression fixture >10000 записей реально применяет request order и pagination, а не возвращает готовую первую страницу; это доказывает достижимость свежей задачи при небольшом бюджете. Небольшие multi-page fixtures проверяют порядок результата, custom order, локальную группу и literal Unicode search.

## API evidence — 2026-10-13

- Effective config проверен `codex mcp get bitrix24_dev --json`; enabled endpoint `https://mcp-dev.bitrix24.tech/mcp`.
- Официальный MCP: `bitrix-search` → `bitrix-method-details` для `tasks.task.list` и `tasks.task.field.get`. Подтверждены REST3 filter clauses, order object ASC/DESC, pagination offset/limit, metadata filterable/sortable.
- [tasks.task.list REST 3.0](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html): серверный filter только id; order поддерживает id/title/created/groupId и другие перечисленные поля. URL `/rest-v3/tasks/tasks-task-list.html`, возвращённый MCP summary, устарел (404); используем действующую страницу выше.
- Документация и offline fixtures не подтверждают приёмку на реальном портале. Живой портал не требуется для изменения документированного порядка; результаты проверок фиксируются отдельно.

## Risks / Trade-offs

- Группа вне первых maxScan задач → честный partial, совет увеличить бюджет; default DESC даёт приоритет свежим данным, не обещает полноты.
- Offset pagination при concurrent create/delete может смещать страницы → существующая дедупликация/no-progress guards сохраняются; snapshot/keyset pagination вне задачи.
- Явный custom order может сканировать старые задачи первыми → meta.order и help показывают фактический выбор пользователя.
- Активный change `improve-task-cli-dx` (#12) содержит исходный id ASC и отсутствие find --order → его реализация должна согласовать эти planning артефакты с канонической спецификацией #64 при обновлении базы. Задачи #12 в этом change не реализуются.

## Migration Plan

Изменение поставляется через MR в dev. README описывает текущий контракт и примеры `--order id:asc`; CHANGELOG отмечает исправление #64. Rollback — revert изменения; defaults и docs возвращаются вместе.
