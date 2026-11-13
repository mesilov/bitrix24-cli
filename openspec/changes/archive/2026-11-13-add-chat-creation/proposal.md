# Proposal

## Why

Агент уже умеет писать в любой доступный групповой чат через `chats:send` (#180), но не может сам завести чат под рассылку. Сценарий из #185: «Сделать канал вместе со своей группой, куда писать новости про ИИ-темы, релевантные компании». Сейчас пользователь создаёт такой чат вручную в интерфейсе, а агент получает только готовый адресат. Создание чата — неидемпотентная запись: повторный запуск агента без проверки плодит одноимённые чаты, поэтому правила защиты от дубля и подтверждения результата должен держать CLI, а не каждый агент заново.

## What Changes

- Добавить JTBD **CHAT-06 — Завести чат для рассылки команде** в `chat-access` и `docs/task-jtbd.md`.
- Добавить команду записи `chats:add --title TITLE [--description TEXT] [--type CHAT|OPEN] [--user-id ID]... [--allow-duplicate-title] [--dry-run]`: создаёт групповой чат через `im.chat.add` (REST 1.0, scope `im`) от имени пользователя подключения; он становится владельцем.
- До записи искать доступные групповые чаты с тем же точным названием (`im.search.chat.list`); при совпадении или неполном поиске ничего не создавать (`chat-exists`, `search-incomplete`), пока не передан `--allow-duplicate-title`.
- `im.chat.add` вызывается один раз; потерянный ответ — `outcomeUnknown` без повтора. После создания CLI читает чат (`im.dialog.get`) и его участников (`im.dialog.users.list`) и сообщает, подтверждены ли название и запрошенные участники; адресат `chatN` сразу пригоден для `chats:send`.
- Объявить маршрут `im.chat.add` в `CommandCatalog` и scope manifest; `strict-rest3` отклоняет команду до подключения.

Создание «каналов» Битрикс24 не входит в изменение: официальная документация `im.chat.add` (проверено MCP 13.11.2026) допускает только `TYPE` = `CHAT` (закрытый) и `OPEN` (открытый чат); тип канала REST не создаёт. Отправка в уже существующий канал по `chatN` возможна через `chats:send`, если у пользователя есть право писать. Добавление участников в существующий чат, аватар, цвет и привязка `ENTITY_TYPE` — вне объёма.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `chat-access`: namespace `chats:*` дополняется `chats:add`; новые требования CHAT-06, защита от дубля по названию и явный исход создания.

## Impact

Новые классы: команда `chats:add`, запрос, сервис создания, порт и адаптер `im.chat.add`; изменяются `ChatInputMapper`, `ResultPresenter` (профиль `chat-created`), `CommandCatalog`, `ScopeManifest`, `config/services.php`. Документация: `docs/chats.md`, `docs/cli-command-map.md`, `docs/connection-scopes.md`, `docs/task-jtbd.md`, README, CHANGELOG. Тесты: unit-сценарии команды и live integration-тест `CreateChatCommandTest` (создаёт чат на тестовом портале; удалить чат REST не позволяет, поэтому тест создаёт один закрытый чат с уникальным названием только из пользователя подключения).

Официальные источники (MCP документации Битрикс24, 13.11.2026): [`im.chat.add`](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html) — `USERS` (array), `TYPE` (`OPEN`|`CHAT`, по умолчанию `CHAT`), `TITLE`, `DESCRIPTION` (string), результат — integer ID чата; [`im.message.add`](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-add.html) — `DIALOG_ID` в формате `chatXXX`; [`im.dialog.get`](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) и [поля чата](https://apidocs.bitrix24.ru/api-reference/chat-bots/chats/fields.html) — `id`, `name`, `description`, `owner`, `type` (значения типа в документации не перечислены, поэтому CLI их возвращает, но не сверяет); `imbot.v2.Chat.add` — только от имени бота, не используется.

Снимок планирования: `dev` `ff9481e`.

## GitHub issues

- [#185 — Добавить отправку сообщения из агента в произвольный чат](https://github.com/mesilov/bitrix24-cli/issues/185). Отправка в произвольный доступный чат уже поставлена `chats:send` (#180); этот change покрывает оставшуюся часть issue — создание чата (закрытого или открытого) с участниками для последующей рассылки — и фиксирует, что каналы REST не создаются.
- Milestone: `0.3.0`; label: `enhancement`.
- Связанные: [#180](https://github.com/mesilov/bitrix24-cli/issues/180) (`chats:*`, `chats:send`).
