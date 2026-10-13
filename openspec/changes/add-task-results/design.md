# Design

## Context

См. proposal.md. В dev команды разделены на Console → immutable Request → Application service/port → Bitrix24 adapter. CommandCatalog и DI заданы явно; CommandRunner реализует policy, preview, confirmation и вывод. TaskListScanner уже использует конечный обход с partial metadata. Результаты ещё отсутствуют в каталоге, исходные PM-05/AM-05/EMP-05 включают более широкий lifecycle и не считаются полностью выполненными.

## Goals / Non-Goals

Goals: самостоятельный TaskResultGateway и REST3 adapter, тонкие команды, общий runtime без новых зависимостей. Non-goals: result from-message, запись fileIds (документация запрещает это поле), автоматический lifecycle/приёмка, реализация шаблонов.

## Decisions

1. `task:result:update/delete TASK_ID --result ID` проверяют binding серверным filter `[[taskId,TASK_ID],[id,ID]]`; прямую mutation только по ID отвергаем, поскольку CLI обещает контекст TASK_ID. Отсутствие результата возвращает код 4, access errors остаются ошибками API.
2. List выбирает все документированные поля результата, order id ASC, страницы до 50, output limit 50 и scan budget 10000. Продвижение по offset, дедупликация по ID; cap, повтор страницы и ошибки после первой страницы дают partial. Чужие/повреждённые строки не допускаются к успешному выводу.
3. Add/update валидируют result.item, включая id/taskId; delete требует boolean result=true. Сомнительное подтверждение после mutation помечается outcomeUnknown=true. Общий transport не выполняет повтор записи.
4. Добавляем новые PM-07/AM-07/EMP-08, сохраняем существующие продуктовые цели PM-05/AM-05/EMP-05 и исходную MVP-матрицу как исторический контекст. В JTBD coverage добавляем явно обозначенный раздел 0.2.0 и machine-readable extension.

## API evidence — 2026-10-13

Effective `codex mcp get bitrix24_dev --json` подтверждает включённый Streamable HTTP https://mcp-dev.bitrix24.tech/mcp. Выполнены реальные bitrix-search `tasks.task.result.add`, `task.result` и bitrix-method-details для всех четырёх методов.

Официальный MCP даёт краткие карточки; list смешивает часть REST1 полей с REST3. Полный первоисточник уточняет `filter`, `items`, `authorId`, string `status=open|closed`, `fileIds`, `rights`, `messageId` (не commentId/createdBy/formattedText/files). Сверены примеры/таблицы в официальном репозитории:

- [add](https://github.com/bitrix24/b24restdocs/blob/main/api-reference/tasks/result/tasks-task-result-add.md): fields.taskId integer, fields.text string; result.item.
- [list REST3](https://github.com/bitrix24/b24restdocs/blob/main/api-reference/tasks/result/tasks-task-result-list-rest-v3.md): обязательный filter taskId, select/order/pagination, result.items.
- [update](https://github.com/bitrix24/b24restdocs/blob/main/api-reference/tasks/result/tasks-task-result-update.md): id integer, fields.text string; result.item.
- [delete](https://github.com/bitrix24/b24restdocs/blob/main/api-reference/tasks/result/tasks-task-result-delete.md): id integer; result.result boolean.

Прямые URL из MCP частично недоступны/404, поэтому полный контракт подтверждён через официальный GitHub. Это документационное свидетельство, а не portal acceptance.

## Risks / Trade-offs

- Offset-список может меняться во время обхода → стабильный order, дедупликация, конечный budget; snapshot isolation не обещается.
- Между preflight и mutation права/данные могут измениться → сервер остаётся авторитетом, ошибку не скрываем.
- Live webhook для нового worktree может отсутствовать → собственные integration cases с явным skip; отчёт отделяет локальные тесты от портальных.

## Migration Plan

Новые команды доставляются PR в dev; release и портал проверяются отдельно. Шаблоны остаются открытым отложенным issue/change.
