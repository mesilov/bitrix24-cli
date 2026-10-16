# Design

## Context

База `dev`: `6eb4673e16ca21fcd242c3f9b1b987dc82716752`, 16.10.2026. Существующий TaskListScanner умеет ограниченный обход с локальными фильтрами исполнителя/проекта/открытых состояний и companion get для неизвестного groupId. TaskGateway выполняет REST3 get/update; IncomingWebhookCurrentUserAdapter получает ID текущего пользователя выбранного вебхука без запроса user.current. Task:assign меняет одну задачу. Новая команда выполняет этот рабочий процесс как один вызов.

## Goals / Non-Goals

**Goals:** передать все доступные незавершённые задачи выбранного проекта от одного участника другому; сохранить явный проверенный результат каждого назначения и не выдавать неполную выборку за весь набор.

**Non-Goals:** новые зависимости, атомарная транзакция всего проекта, расширение прав, автоматические сообщения, изменение сроков/статусов, проверка чтения коллегой каждого вложения и автоматический возврат после отпуска.

## Decisions

1. CLI: обязательные положительные `--group-id` и `--responsible`; необязательный положительный `--from` (по умолчанию ID выбранного вебхука), `--max-scan` (10000) и `--dry-run`. Все параметры проверяются до credentials/API. Источник и получатель должны отличаться. Для руководителя явный --from не означает impersonation: API работает от подключения и применяет его реальные права.
2. Новый ProjectMemberGateway читает `sonet_group.user.get` REST1 с `ID` (положительный integer). Принимаются активные роли A/E/K и USER_ID как positive int или decimal string из официального ответа. Неверная форма, неизвестная роль, неоднозначные ID и неполная навигация дают отказ до записи. Участники не добавляются автоматически. Наличие обоих ID проверяется до сканирования; перед каждым назначением повторно проверяется членство, чтобы не использовать устаревший состав.
3. TaskQuery использует where responsibleId/groupId, openOnly=true, limit=null, maxScan. Открытые состояния: pending/in_progress/supposedly_completed/deferred. При complete=false или лимите записи не начинаются; ошибка содержит причину обхода. Нулевая выборка — успешный no-op. Снимок списка получается до изменений, поэтому смена ответственного не сдвигает пагинацию.
4. PreparedOperation.plan содержит реальные ID и четыре шага на задачу: проверка участников, GET актуальной карточки, UPDATE только responsibleId, GET результата. Подготовка dry-run выполняет только проверочные чтения состава и списка, без записи. Неполная подготовка не получает complete=true. Для записи используется тот же зафиксированный набор; перед каждой записью проверяются project, source и открытое состояние из card с select id/responsible.id/group.id/status. После записи проверяются ID, новый ответственный и проект.
5. Отчёт task-handover содержит items taskId/title/fromUserId/responsibleId/status/reason и метаданные groupId/fromUserId/responsibleId/selected/confirmed/complete/selectionComplete/outcomeUnknown. Статусы: confirmed, failed (отказ до подтверждённой записи), unverified (ACK есть, readback не подтвердил итог), unknown (потерян ответ записи), skipped. Первая ошибка останавливает обработку, последующие ID skipped. Exit 3 для неполного исполнения, 130 для отмены; до начала исполнения ошибки ввода/прав/policy/выборки сохраняют стандартный Failure. Подтверждённые записи не откатываются и не повторяются. Для неизвестного исхода пользователь проверяет task:show.
6. JSON/plain/human вывод строится через общий ResultPresenter с отдельным профилем и понятной сводкой. Вызовы и policy проходят CommandRunner; sonet_group.user.get добавляется в явные ROUTES и ScopeManifest с sonet. Strict-rest3 запрещает составной план до API/credentials. Offline --help/list/completion не читают credentials.
7. EMP-09 добавляется в task-workflows; PM-04 расширяется сценарием руководителя без нового PM-ID. docs/task-jtbd.md содержит 24 сценария: 8 PM / 7 AM / 9 EMP, таблицы команды/интеграционного теста и ограничения. Количество task-команд сверяется с CommandCatalog; runtime spec и package smoke обновляются до 49 (43 базовые + 5 template + handover).

## Documentation verification

Официальный обязательный MCP отвечает из worktree; effective config bitrix24_dev включён, Streamable HTTP https://mcp-dev.bitrix24.tech/mcp. Поиск, затем details выполнены 16.10.2026:

- [sonet_group.user.get](https://apidocs.bitrix24.ru/api-reference/sonet-group/members/sonet-group-user-get.html): REST1, scope sonet, ID integer, result list USER_ID/ROLE, активные A/E/K. Примеры возвращают USER_ID строкой; недокументированный пагинируемый ответ не считается полным.
- [tasks.task.update REST3](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-update.html): id, fields; пример responsibleId integer. Переиспользуется существующий TaskGateway, имена fields не переименовываются.
- [tasks.task.get REST3](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html): id, select с вложенными путями; используется group.id, существующая нормализация responsibleId.
- [tasks.task.field.list](https://apidocs.bitrix24.ru/api-reference/tasks/rest-v3/tasks-task-field-list.html): metadata не вводит нового writable API-контракта; полный live-schema в этой работе не проверен.

Ответы MCP get/update смешивают id/taskId в краткой сводке. Текущий проверенный REST3 adapter использует id; его контракт не меняется. Членство в проекте не доказывает право изменять все задачи: отказ update сохраняется в отчёте. Portal-вызовы в рамках этой задачи не разрешены как массовая передача реальных задач; интеграционный тест использует только собственные fixtures и требует отдельного opt-in на изолированную группу.

## Risks / Trade-offs

- Изменение задач после снимка → preflight перед каждой записью; атомарный compare-and-set API не обещается.
- Потеря доступа после назначения → ACK сохраняется как unverified при неуспешном readback; дальнейшие записи прекращаются.
- Долгая выборка/истечение timeout → ограниченный budget, zero writes при неполном scan, отмена сохраняет уже выполненный итог.
- Неполные права подключения → явная область tasks-visible-to-connection, отсутствие гарантии недоступных задач.

## Migration Plan

Реализация, main specs, docs и changelog поставляются одним MR в dev. После зелёного CI текущего SHA и review merge проверить issue, архивировать change отдельным MR в dev, обновить путь issue и удалить только worktree этой задачи. Rollback кода не откатывает уже сделанные назначения: они видны в отчёте и требуют отдельного решения.
