# Verification

Снимок: ветка `feature/223-task-chat-send-message-id` от `dev` `8a4122d`, 18.11.2026.

## Контракт API

Проверено через официальный MCP документации Битрикс24 (`https://mcp-dev.bitrix24.tech/mcp`, `bitrix-search` → `bitrix-method-details`):

- `tasks.task.chat.message.send` — REST 3.0, `fields.taskId` (integer), `fields.text` (string), ответ `result: true` без ID. Payload в коде не изменился (`CommandContractsTest`).
- `im.dialog.messages.get` — `DIALOG_ID`, `FIRST_ID` (новее указанного ID), `LIMIT` ≤ 50; поля `id`, `chat_id`, `author_id`, `text`.
- `im.dialog.get` — `last_message_id`.
- Формат веб-ссылки на сообщение в документации не найден — `messageUrl` не формируется (design, решение 5).

## Автоматические проверки

| Проверка | Результат |
|---|---|
| `make unit` | OK, 1384 теста |
| `make lint-all` (licenses, php-cs-fixer, phpstan, rector) | OK |
| `openspec validate return-task-chat-message-id --strict` | valid |

Unit-сценарии (`TaskChatSendReadbackTest`, `ScopeValidationCommandTest`, `CommandContractsTest`): единственное совпадение с отсечением старого своего и нового чужого сообщения с тем же текстом; многострочный текст с `\r\n`; `ambiguous`, `missing`, `failed`, `incomplete` (budget 200) — код 3 и ровно одна отправка; непроверенная привязка — код 4 без записи; dry-run — только `tasks.task.get` и план из трёх шагов; блокировка без `im` до первого REST-вызова; `task:chat:update` с `chatId`/`messageId`.

## Тестовый портал

`SendTaskChatMessageCommandTest` и `UpdateTaskChatMessageCommandTest` на SDK playground (вебхук с полными правами, `.env.local` основного checkout):

- happy path: `readback = verified`, `messageId`/`resourceId` совпадают с ID сообщения, найденного SDK-чтением чата, `chatId` — с чатом из карточки задачи, `dialogId = chat<chatId>` — OK;
- многострочный текст найден readback — OK (сервер сохраняет текст так, что нормализованное сравнение совпадает);
- `task:chat:update` — OK;
- `testSecondUserWithoutTaskRoleCannotAccessOwnedFixture` — пропущен: второй вебхук принадлежит администратору, вебхука без прав администратора нет.

## Границы

- Не проверены на портале: исходы `ambiguous`/`missing`/`failed` (смоделированы в unit), текст с BB-кодами и упоминаниями, вебхук без `im`.
- Readback сравнивает текст после нормализации переводов строк и пробелов по краям; если сервер преобразует текст иначе, результат — `missing` с кодом 3, а не неверный ID.
