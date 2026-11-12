# Verification

## 1.1 Сверка с официальным MCP документации Битрикс24

Дата: 13.11.2026. Endpoint: `https://mcp-dev.bitrix24.tech/mcp` (сервер `b24-dev-mcp` 0.2.0). В клиенте этой сессии сервер не подключён как инструмент, поэтому запросы выполнены напрямую по Streamable HTTP (`initialize` → `tools/call`); сервер не требует авторизации, секреты не передавались.

| Запрос | Инструмент | Результат |
| --- | --- | --- |
| `im.message.add отправка сообщения в чат` | `bitrix-search` | `im.message.add`, `imbot.*` (DEPRECATED/бот), `imopenlines.crm.message.add` |
| `im.message.add` | `bitrix-method-details` | scope `im`; `DIALOG_ID` (`chatXXX`, `sgXXX`, ID пользователя), `MESSAGE`; результат — integer ID. Уже используется `chats:send` |
| `im.chat.add создание чата`, `создание канала чат TYPE CHANNEL` | `bitrix-search` | `im.chat.add`, `imbot.v2.Chat.add`, `imbot.chat.add` (DEPRECATED); метода создания канала нет |
| `im.chat.add` | `bitrix-method-details` | scope `im`; `USERS` (array), `TYPE` (`OPEN` — открытый, `CHAT` — закрытый, по умолчанию `CHAT`), `TITLE`, `DESCRIPTION`, `COLOR`, `MESSAGE`, `AVATAR`, `ENTITY_TYPE`, `ENTITY_ID`, `COPILOT_MAIN_ROLE`; результат — integer ID чата. [Источник](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html) |
| `imbot.v2.Chat.add` | `bitrix-method-details` | только от имени бота (`botId`, scope `imbot`), `type`: `chat`/`open`; не используется |
| `каналы в мессенджере REST`, `новостной канал мессенджер` | `bitrix-search` | статей и методов создания канала нет; `im.recent.list` имеет только фильтр `ONLY_CHANNEL` для чтения |
| `im.dialog.get` | `bitrix-method-details` | scope `im`; `DIALOG_ID`; `ACCESS_ERROR` при отсутствии доступа. [Источник](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) |
| Поля чата | `bitrix-article-details` | `id`, `name`, `description`, `owner`, `type` (значения не перечислены), `dialog_id`. [Источник](https://apidocs.bitrix24.ru/api-reference/chat-bots/chats/fields.html) |

Выводы: CLI-опции `--title`, `--description`, `--type`, `--user-id` однозначно отображаются на `TITLE`, `DESCRIPTION`, `TYPE`, `USERS`; значения `--type` совпадают со значениями API (`CHAT`, `OPEN`), синонимы отклоняются. Каналы REST не создаёт. `type` ответа `im.dialog.get` не сверяется.

## 1.2 Трассировка

- Issue [#185](https://github.com/mesilov/bitrix24-cli/issues/185): milestone `0.3.0`, label `enhancement`; в описание добавлен раздел `## OpenSpec` с `add-chat-creation` и путём `openspec/changes/add-chat-creation/`.
- `proposal.md` → `## GitHub issues` ссылается на #185 и описывает покрытую часть.

## 5.1 Автоматические проверки

Ветка `feature/185-send-message-to-chat` от `dev` `ff9481e`, Docker `php-cli` (PHP 8.4.27):

| Проверка | Результат |
| --- | --- |
| `make unit` | OK — 1201 тест, 19582 проверки (новый `ChatsAddCommandTest`: 9 тестов) |
| `make lint`, `make lint-cs-fixer`, `make lint-phpstan`, `make lint-rector` | без ошибок и исправлений |
| `make cli-reference-check` | карта CLI соответствует 72 публичным командам |
| `openspec validate add-chat-creation --strict` | valid |

## 5.2 Тестовый портал

Портал `bitrix24-php-sdk-playground.bitrix24.ru`, webhook из `.env.local` основного checkout через `B24CLI_ENV_FILE`.

- `make integration TEST_ARGS='--group command:chats:add'` — OK (1 тест, 23 проверки). Последовательность команд из `var/cache/portal-evidence.jsonl`:
  1. `chats:add --dry-run` → exit 0, только `im.search.chat.list`; `im.chat.add` не вызывался.
  2. `chats:add --title … --description … --user-id <пользователь подключения>` → exit 0, `im.search.chat.list` → `im.chat.add` (один раз) → `im.dialog.get` → `im.dialog.users.list`; `readback = verified`, `outcomeUnknown = false`. SDK-чтение `im.dialog.get` подтвердило ID, название, описание и владельца; SDK `im.message.add` в созданный `chatN` вернул ID сообщения.
  3. Повторный `chats:add` с тем же названием → exit 4 `chat-exists`, кандидат — созданный `chatN`, `im.chat.add` не вызывался: поиск `im.search.chat.list` находит только что созданный чат сразу.
- `make integration TEST_ARGS='--group chats'` — OK (13 тестов, 3 пропуска: звонки требуют `B24CLI_TEST_CALL_CHAT`/`B24CLI_TEST_CALL_ID`, не связано с изменением).
- Ручной smoke `make cli ARGS="chats:add --title 'b24cli smoke ИИ-новости' --type OPEN --user-id 1 --dry-run"` — human-таблица плана из трёх шагов, запись не выполнялась.

Границы: на портале остался один закрытый тестовый чат `b24cli-chats-add <hex>` на каждый запуск integration-теста (REST не удаляет чаты). Открытый чат (`OPEN`), чат с другими сотрудниками, сценарии `members-missing`/`title-mismatch`/`outcomeUnknown` и `chat-create-denied` на портале не проверялись — только unit-тестами с фиктивным транспортом. Поведение `im.chat.add` с несуществующим ID в `USERS` не проверено. Сквозная приёмка «создать чат с реальной группой и публиковать новости» не проводилась.
