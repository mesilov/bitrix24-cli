# Design

## Context

`ChatSupport::scan()` (общий обход для `task:chat:list` и проверки сообщения в `task:chat:update`/`task:chat:delete`) при `before === null` и `after === null` вызывает `TaskChatAdapter::messages()` без `LAST_ID`. Следующая страница берётся через `LAST_ID = min(id)` текущей страницы, то есть обход идёт только к более старым сообщениям.

## Проверка на портале

Шаг обязателен: баг воспроизводится на портале и зависит от формы ответа `im.dialog.messages.get`. Запросы выполнены 12.11.2026 прямым HTTP по webhook, без `b24cli` и SDK, на тестовом портале. Официальный MCP документации Битрикс24 в этой сессии недоступен; контракт методов взят из ссылок ниже и из design change `add-unified-chat-access` (сверка через MCP 30.10.2026).

Фикстура: задача `b24cli-202-unread-check` (ID 6102) в тестовой группе `B24CLI_TEST_GROUP_ID`, ответственный — второй пользователь тестовых fixtures. Второй пользователь добавлен в чат задачи (`im.chat.user.add`, только фикстура) и отправил 6 сообщений m1–m6 (`im.message.add`). После проверки задача удалена (`tasks.task.delete` → `{"task":true}`), чат отвечает `ACCESS_ERROR`.

| Сценарий | Метод, REST | Payload | Ответ (сокращённо) | Вывод |
| --- | --- | --- | --- | --- |
| Состояние чата | [im.dialog.get](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html), 1.0 | `{"DIALOG_ID":"chat10586"}` | `counter=6`, `unread_id=23932`, `last_message_id=23942`, `message_count=8` | 6 непрочитанных |
| Без курсора | [im.dialog.messages.get](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html), 1.0 | `{"DIALOG_ID":"chat10586","LIMIT":3}` | 5 строк: m3, m2, m1 и два более старых системных сообщения | Подтверждён: окно первого непрочитанного, m4–m6 нет; строк больше `LIMIT` |
| От последнего | im.dialog.messages.get, 1.0 | `{"DIALOG_ID":"chat10586","LIMIT":3,"LAST_ID":23943}` | m6, m5, m4 | Подтверждён: `LAST_ID = last_message_id + 1` даёт последние |
| Прочитанность | im.dialog.get, 1.0 | как выше, после всех чтений | `counter=6`, `unread_id=23932` | Чтение не меняет read state |
| CLI до исправления | `b24cli 0.2.0 task:chat:list 6102 --limit 3` | — | m1, m2, m3 | Баг виден в CLI |
| CLI после исправления | `bin/console task:chat:list 6102 --limit 3` / `--all` | — | m4, m5, m6 / все 8 сообщений; маршрут `tasks.task.get` → `im.dialog.get` → `im.dialog.messages.get` | Исправление подтверждено |

Тот же эффект ранее наблюдался на групповом чате с 38 непрочитанными (#180): без `LAST_ID` первая страница была самой старой.

## Decisions

### 1. Старт с last_message_id + 1

Без `--before`/`--after` `scan()` получает `last_message_id` через `im.dialog.get` для уже проверенного `chat.id` задачи и начинает с `LAST_ID = last_message_id + 1`. Адаптер проверяет, что `im.dialog.get` вернул тот же `id` чата и целый `last_message_id`; иначе — `message-binding-unverified` (exit 4). Пустой чат (`last_message_id = 0`) даёт пустой полный результат.

Альтернатива — большой константный `LAST_ID` (например `2147483647`). На портале работает, но не документирована; отклонена.

### 2. Явные курсоры не меняются

`--before` и `--after` уже задают позицию, поэтому `im.dialog.get` не вызывается.

### 3. Маршрут и scope

`im.dialog.get` (REST 1.0, scope `im`) добавлен в планы `task:chat:list`, `task:chat:update`, `task:chat:delete` и в `ScopeManifest`. Команды и раньше требовали `im`, поэтому набор прав не меняется. strict-rest3 отклоняет план до подключения, как и раньше.

## Risks / Trade-offs

- Один дополнительный read-запрос на запуск без курсора.
- Если `im.dialog.get` недоступен при доступном `im.dialog.messages.get`, команда завершится ошибкой вместо неполного ответа. Это предпочтительнее молчаливой потери сообщений.
- Изменение `ScopeManifest` пересекается с веткой #180 (`feature/180-unified-chat-access`), где добавлена та же строка; конфликт тривиален.
