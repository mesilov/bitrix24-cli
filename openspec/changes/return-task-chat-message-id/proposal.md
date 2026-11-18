# Proposal

## Why

`task:chat:send` подтверждает отправку, но возвращает `resourceId = null` и не сообщает ни ID сообщения, ни ID чата задачи. Агенту, который после комментария должен дать пользователю ссылку на конкретное сообщение, приходится вызывать `task:chat:list TASK_ID --all` и угадывать своё сообщение по автору и тексту. Это лишний вызов и эвристика на стороне каждого агента: при одинаковых текстах или параллельной записи легко выбрать чужое или более старое сообщение. `chats:send` уже возвращает `messageId` и делает readback, поэтому чат задачи выглядит непоследовательно.

REST-метод отправки ID не отдаёт: по официальной документации `tasks.task.chat.message.send` возвращает только `result: true`. Значит, сопоставление должен делать CLI — один раз, по строгим правилам и с честным исходом, а не каждый агент заново.

## What Changes

- `task:chat:send` до записи проверяет привязку чата к задаче (`tasks.task.get`, как `task:chat:update/delete`) и запоминает ID последнего сообщения чата (`im.dialog.get`). Если привязка не доказана, ничего не отправляется.
- После единственного вызова `tasks.task.chat.message.send` CLI читает сообщения новее запомненного ID (`im.dialog.messages.get`, `FIRST_ID`, с конечным budget) и ищет сообщение пользователя подключения с отправленным текстом.
- Ровно одно совпадение — `readback = verified`: ответ содержит `messageId` (он же `resourceId`), `chatId` и `dialogId` (`chatN`) — всё, из чего клиент собирает ссылку без дополнительного вызова. Ни одного или несколько совпадений либо ошибка чтения — `complete = false`, `reason = readback-unverified`, exit 3, статус и ID кандидатов в `data.readback`; отправка не повторяется, `confirmed = true` сохраняется.
- `task:chat:update` дополнительно возвращает `chatId` и `messageId` (ID изменённого сообщения уже возвращается в `resourceId`).
- `task:chat:send` требует scope `im` наравне с остальными командами `task:chat:*`; без него команда блокируется до записи.
- Готовая ссылка `messageUrl` не формируется: формат веб-ссылки на сообщение официальной REST-документацией не описан (см. design).

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `task-console-runtime`: новое требование об идентификации отправленного сообщения чата задачи; сценарий «Confirmed chat send without message ID» заменяется проверенным readback.
- `connection-scope-validation`: `task:chat:send` блокируется при отсутствии `im` вместе с `task:chat:list/update/delete`.

## Impact

Код: `TaskChatService` (send/update, маршруты и план), `TaskChatGateway`/`TaskChatAdapter` (без новых REST-методов — уже используемые `im.dialog.get` и `im.dialog.messages.get`), `CurrentUserGateway` (ID пользователя вебхука без API-вызова), справка `SendTaskChatMessageCommand`, `CommandCatalog` (маршруты `task:chat:send`). Тесты: `CommandContractsTest`, новые unit-сценарии readback, integration `SendTaskChatMessageCommandTest`. Документация: README, `docs/task-jtbd.md` при необходимости, CHANGELOG.

Официальные источники (MCP документации Битрикс24, 18.11.2026):
- [`tasks.task.chat.message.send`](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-chat-message-send.html) — REST 3.0, scope `tasks`, `fields.taskId` (integer), `fields.text` (string); ответ — `result: true` без ID сообщения.
- [`im.dialog.messages.get`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html) — scope `im`, `DIALOG_ID` (`chatXXX`), `FIRST_ID` (сообщения новее указанного ID), `LIMIT` ≤ 50; сообщение содержит `id`, `chat_id`, `author_id`, `text`.
- [`im.dialog.get`](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) — `last_message_id` (уже используется `task:chat:list`).
- Формат веб-ссылки `https://<portal>/online/?IM_TASK=chat<CHAT_ID>&IM_MESSAGE=<MESSAGE_ID>` поиском по документации не найден; граница проверки зафиксирована в design.

Снимок планирования: `dev` `8a4122d`.

## GitHub issues

- [#223 — task:chat:send: возвращать messageId и chatId отправленного сообщения](https://github.com/mesilov/bitrix24-cli/issues/223). Change покрывает возврат `messageId`/`chatId` через проверенный readback, исход без повторной отправки при неудаче сопоставления и ID изменённого сообщения в `task:chat:update`; `messageUrl` не формируется по причине, указанной выше.
- Milestone: `0.3.0`; label: `enhancement`.
