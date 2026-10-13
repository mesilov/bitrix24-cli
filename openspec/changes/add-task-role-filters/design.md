# Design

## Context

См. `proposal.md` для мотивации и границ изменения. Исследование выполнено 2026-10-13 на свежей `origin/dev` (`45895884c221fb363eb284a4d77d0641eddbf58e`).

Текущие `ListTaskCommand`/`FindTaskCommand` передают `TaskQuery` в общий `TaskListScanner`. Только id отправляется как серверный REST3 filter; groupId/responsibleId/status/deadline и буквальный Unicode title проверяются локально. Scanner обходит страницы в max-scan, дедуплицирует IDs, сохраняет partial и применяет output limit после проверки. `task:find` возвращает только id/title и сейчас имеет только title/group-id и флаги лимитов.

`Rest1ParticipantAdapter::ids(taskId, roles)` уже читает подтверждённые participant IDs через `tasks.task.get` REST1 с taskId и select ID/ACCOMPLICES/AUDITORS, проверяя ID возвращённой задачи и список положительных IDs. `task:show` использует его как заранее объявленное чтение. `CommandCatalog::ROUTES` и `ApiPolicyGuard` запрещают неописанные маршруты и проверяют strict-rest3 до prepare. List/find пока объявляют только REST3 tasks.task.list.

`ConnectionManager` выбирает webhook из profile/env-file/process env; `FileCredentialStore::hostname` валидирует полную форму HTTPS `/rest/<user-id>/<token>/`. Единственная поддержанная аутентификация — входящий вебхук. Получать ID из произвольных profile defaults нельзя.

Незавершённый `improve-task-cli-dx` (#12) содержит старые `--project` примеры. Этот change работает с текущим `--group-id` и не реализует/переписывает чужой план. #60 lifecycle и #63 users не являются зависимостями ролевого фильтра.

## Goals / Non-Goals

**Goals:** переиспользовать существующий bounded scanner и проверенное чтение участников; сохранить явные API policies и единый выбранный источник идентичности. Спецификация `task-role-filtering` определяет пользовательский контракт.

**Non-Goals:** новая схема credential storage, OAuth, user.current/user.get для определения текущего пользователя, запросы ролей по другим пользователям и массовое обогащение списка данными пользователей. REST3 ошибки явного participant select остаются отдельной portal acceptance границей #42.

## Decisions

### 1. Один флаг и четыре роли с точными соответствиями

В обеих командах добавить `--role` как optional VALUE_REQUIRED с completion values `responsible`, `accomplice`, `creator`, `auditor`. `TaskInputMapper` валидирует его до credentials; отдельное typed значение/enum хранится в `TaskQuery`. Не помещать роль в scalar `where`: membership имеет другую форму и роль должна сочетаться с существующим advanced where через AND.

| Значение CLI | Группа Битрикс24 | Данные для проверки |
| --- | --- | --- |
| responsible | делаю | REST3 responsibleId, integer |
| accomplice | помогаю | REST1 ACCOMPLICES, IDs; REST3 имя relation — accomplices |
| creator | поручил | REST3 creatorId, integer |
| auditor | наблюдаю | REST1 AUDITORS, IDs; REST3 имя relation — auditors |

Это значения CLI, не переименование API fields. Задача с несколькими ролями проходит каждую соответствующую отдельную выборку; creator не подразумевает responsibleId != current user. Статус не добавляется автоматически.

Альтернатива: четыре отдельных флага или русские значения. Один перечислимый флаг делает комбинацию с title/group-id/where явной и не вводит четыре взаимных конфликта. Несколько ролей/OR потребовали бы отдельного пользовательского контракта и не включаются.

### 2. ID владельца вебхука без сетевого запроса

Добавить небольшой application port текущей идентичности с connection adapter: прочитать webhook только через выбранный `ConnectionResolver`, повторно использовать действующую проверку URL и извлечь положительный user-id из пути с проверкой переполнения. Не дублировать selection precedence. Не сохранять raw URL/token в TaskQuery, meta или exceptions. Роль активирует получение identity после policy guard; отсутствие роли не читает webhook ради identity.

`meta.roleFilter` содержит только `role` и нормализованный `userId`. Profile defaults остаются только defaults создания задачи.

Альтернатива: REST1 user.current. Он требует дополнительного user scope и non-v3 маршрута даже для scalar роли. У поддержанного входящего вебхука user ID уже содержится в проверяемом URL; user.current и pending users namespace здесь не нужны. При будущем OAuth эта реализация port должна быть заменена отдельно, без догадок по token.

### 3. Ролевой predicate в общем scanner

В `TaskListScanner` получать identity один раз на вызов. Для responsible/creator добавить нужное scalar поле в select и локально сравнить нормализованный positive ID; повреждённое или отсутствующее поле означает incomplete. Не отправлять эти predicates в REST3 filter.

Для accomplice/auditor сначала проверить title и текущие scalar predicates. Только для уникальной задачи, прошедшей их, вызвать существующий `ParticipantGateway::ids` с её id и выбранной ролью. Membership проверяется по точному positive integer user ID. Scanner не добавляет REST3 participant projections и не вызывает user.get. Состав ролей не добавляется к обычным output rows; task:find по-прежнему возвращает id/title.

При companion Failure (кроме interruption) остановить обход, сохранить подтверждённые совпадения и вернуть partial/exit 3 с `role-read-error`; чужая TASK_ID/неверная форма из существующего adapter — такая же непроверенная роль. Пустой подтверждённый список — обычное несовпадение. Ctrl-C продолжает давать 130 без следующих запросов. Output limit остаётся после проверки; REST3 scanned count сохраняет существующую семантику, companion reads отражаются в API accounting и ограничены числом уникальных проверенных кандидатов, не более max-scan.

Альтернатива: REST3 accomplices.id/auditors.id. Проверенный в #42 портал возвращал INTERNAL_SERVER_ERROR для заполненного auditors, поэтому нельзя делать работоспособность ролевого фильтра зависимой от этого projection. Альтернатива: отдельный REST1 server scan ролей с subsequent REST3 hydration. Он меняет бюджет, сортировку и pagination accounting; для этого изменения выбран существующий прямой task-bound adapter и один scanner.

### 4. Полный план маршрутов до выполнения

`ListTasksHandler::routes(request)` и `FindTasksHandler::routes(request)` вместе с closures команд должны учитывать выбранную роль. Для accomplice/auditor добавить только REST1 tasks.task.get read в разрешённые пары `CommandCatalog`. Scalar роли остаются с REST3 tasks.task.list. Guard выполняется до определения identity/credentials и любых сетевых шагов, включая пустой результат. Переход после API error на другую версию отсутствует.

Существующее различие неподдержанных server predicates и заранее согласованных REST1 companion reads остаётся явным в help/docs. `--select auditors` отдельно сохраняет действующий контракт native projections, этот флаг не подменяется вследствие role filter.

### 5. Приёмка и документация

Unit cases проверяют четыре роли в обеих командах, отрицательные и пересекающиеся membership, selected connection, ввод, AND, pagination/limit/budget/partial/cancellation, полный route plan и отсутствие лишних network reads. Для credentials-free checks использовать spy resolver и FakeApiTransport.

Portal tests расширяют только существующие ListTaskCommandTest/FindTaskCommandTest. Fixture setup/readback/cleanup идёт через SDK, каждый класс выполняет только свою CLI-команду. Создать собственные подходящие/неподходящие tasks с уникальным title и выбирать ID/title так, чтобы не зависеть от постороннего наполнения портала. Чужой responsible/creator negative case требует доступного второго пользователя; при его отсутствии это явный skip с отдельным evidence gap. Запуск общей integration suite не нужен для правки планирования.

README/help показывают текущий интерфейс первой сборки и четыре коротких примера; `docs/development.md` описывает composition, overhead и API policy. Не добавлять миграционную историю до 1.0.0.

## Risks / Trade-offs

- [Participant роли добавляют до одного read на подходящую задачу] → title/group/scalar predicates и дедупликация выполняются раньше; max-scan конечен, ошибки останавливают обход. Пользователь может ограничить группу или IDs; стоимость отражается в docs.
- [Список и участники читаются в разные моменты] → результат отражает подтверждённые чтения, не атомарный snapshot; не обещать согласованность при параллельном изменении ролей.
- [Повреждённый/слишком большой user-id URL] → positive integer validation без overflow и без публикации token; не брать ID из defaults.
- [API permission/filled participant ограничения портала] → строгий preflight policy и отдельная live evidence; unit/skip не объявлять portal acceptance, error-triggered retry отсутствует.
- [Параллельные changes изменят scanner/handlers] → перед apply/PR обновить origin/dev fast-forward/merge без перезаписи чужой работы и повторно сверить текущие команды; #12/#60/#63 не реализуются попутно.

## Migration Plan

После реализации и проверок отправить feature-ветку в PR с target dev. Хранилище подключения и структура существующих данных не меняются. При необходимости отмена выполняется revert через PR в dev; прежние сохранённые профили не требуют преобразования. Архивирование выполняется только после завершения реализации/verify, с доставкой specs и обновлением пути issue.

## Sources and verification boundary

Проверено по официальным источникам 2026-10-13:

- [Задачи Битрикс24](https://helpdesk.bitrix24.ru/open/17962166/) и [режим Список](https://helpdesk.bitrix24.ru/open/17987682/) — четыре группы пользователя.
- [REST3 tasks.task.list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html) — единственный поддержанный filter field id; select, order и pagination.
- [Поля REST3 задач](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html) — creatorId/responsibleId integer, accomplices/auditors arrays of user objects; relation имена не заменяются REST1 keys.
- [Классический tasks.task.get](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get.html) и [поля задач](https://apidocs.bitrix24.ru/api-reference/tasks/fields.html) — taskId/select и participant IDs; существующий adapter запрашивает ID/ACCOMPLICES/AUDITORS, response содержит task.id/accomplices/auditors.
- [Обзор REST3](https://apidocs.bitrix24.ru/api-reference/rest-v3.html) — `/rest/api/<user_id>/<webhook_token>/` и версии маршрутов; действующая connection validation принимает входящий `/rest/<user_id>/<token>/`.
- [user.current](https://apidocs.bitrix24.ru/api-reference/user/user-current.html) — user/user_brief/user_basic scopes; этот маршрут не включается.

В фазе планирования live вызовы и runtime tests не выполнялись. Результаты реализации и приёмки добавляются в `verification.md` отдельно; предыдущие receipt/ограничения #42 сохраняются.

Перед apply ветка обновлена до `origin/dev` `96ce502`. Effective config проверен через `codex mcp get bitrix24_dev --json` из worktree; реальное подключение подтверждено search → method-details tasks.task.list/tasks.task.get и article-details «Поля задачи в REST 3.0». MCP подтвердил creatorId/responsibleId integer и различие participant REST3 objects / классических ACCOMPLICES/AUDITORS. Ответы method-details объединяют одноимённые версии, поэтому контракт версии сверялся также с URL статей, перечисленными выше. MCP не выполняет portal вызовы; секреты ему не передавались.
