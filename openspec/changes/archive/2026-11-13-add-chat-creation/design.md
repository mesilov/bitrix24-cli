# Design

## Context

`chats:*` (#180) уже разрешает адресат, ищет чаты по названию и отправляет сообщения с подтверждением по ID. Не хватает создания чата: сценарий #185 — завести чат с группой коллег и писать туда новости. Создание неидемпотентно и необратимо через REST (метода удаления чата нет), поэтому команда должна быть осторожнее отправки сообщения.

## Goals / Non-Goals

**Goals:** одна команда `chats:add`, создающая закрытый или открытый групповой чат от имени пользователя подключения; защита от дубля по точному названию; dry-run; один вызов записи без повтора; проверка созданного чата и участников; адресат `chatN`, сразу пригодный для `chats:send`.

**Non-Goals:** каналы Битрикс24 (REST их не создаёт), чаты от имени бота (`imbot.v2.Chat.add`), добавление и удаление участников существующего чата, аватар, цвет, первое сообщение (`MESSAGE`), привязка `ENTITY_TYPE`/`ENTITY_ID`, `COPILOT_MAIN_ROLE`.

## Decisions

### Интерфейс

`chats:add --title TITLE [--description TEXT] [--type CHAT|OPEN] [--user-id ID]... [--allow-duplicate-title] [--max-scan N] [--dry-run]`.

- `--title` обязателен: автоматическое название `im.chat.add` («#COLOR# чат №N») нельзя найти `chats:find` и проверить на дубль. Пробелы по краям обрезаются; после обрезки не короче 2 символов (как `--name` в `chats:find`) и не длиннее 255.
- `--type` принимает ровно значения API `TYPE`: `CHAT` (по умолчанию, закрытый) и `OPEN` (открытый). Синонимы (`closed`, `channel`, нижний регистр) отклоняются `usage-error`, чтобы не вводить собственное переименование полей API.
- `--user-id` повторяется или перечисляется через запятую, как в `users:presence`; ID дедуплицируются, до 500. Пользователь подключения добавляется порталом автоматически как владелец; если его ID передан явно, он остаётся в `USERS` — это допустимо по документации.
- `--description` необязателен и передаётся в `DESCRIPTION` только если задан.
- Первое сообщение не передаётся через `MESSAGE`: рассылка идёт через `chats:send`, у которого есть подтверждение по ID. Так у создания и отправки разные, независимо проверяемые исходы.

Альтернатива — флаг `--type closed|open`: отклонена правилом проекта о точных именах и значениях API.

### Защита от дубля

Перед записью CLI просматривает источник групповых чатов `im.search.chat.list` тем же кодом, что `chats:find` (локальное сравнение названия, отсев совпадений по участнику и личных диалогов, бюджет `--max-scan`, по умолчанию 10000). Проекты и задачи не просматриваются: создаётся обычный групповой чат, а одноимённый проект или задача дублем не являются; это также не требует scope `sonet_group` и `task`.

- Есть точное совпадение → `chat-exists` (exit 4), `error.details.candidates` с `chatN`; ничего не создаётся.
- Источник неполон (`scan-budget-exhausted`, `pagination-no-progress`, `page-error`) → `search-incomplete` (exit 4).
- `--allow-duplicate-title` пропускает проверку и маршрут поиска: агент явно решает создать второй одноимённый чат. `--max-scan` вместе с ним — `usage-error`.

Точное совпадение — как в `chats:find`: название после обрезки пробелов с учётом регистра. Чаты, которые пользователь не видит, проверка не находит; это граница, а не гарантия уникальности на портале.

### Запись и неизвестный исход

`im.chat.add` вызывается один раз с `TITLE`, `TYPE`, `DESCRIPTION` (если задан) и `USERS` (если задан). Ответ без положительного integer ID или потерянный ответ транспорта → `Failure` с `outcomeUnknown = true`; сообщение подсказывает `chats:find --name TITLE` перед повтором. `ACCESS_ERROR`/`ACCESS_DENIED` при создании → `chat-create-denied` (exit 1).

### Проверка результата

После создания:

1. `im.dialog.get` для `chatN`: `id` = N и `name` = TITLE → название подтверждено. `type` возвращается как есть: значения поля в документации не перечислены, сверять их не с чем.
2. `im.dialog.users.list` (существующий адаптер участников, без ботов): каждый запрошенный ID должен быть среди участников; `missingUserIds` — недостающие.

`data.operation.readback`: `verified` — оба шага прошли; `title-mismatch`, `members-missing`, `failed` (ошибка чтения, код в `readback.error`) → exit 3, но `chatId` и `target` сохраняются: чат создан, повторять создание нельзя. Прерывание (exit 130) пробрасывается.

### Маршруты и политика

`routes`: `im.search.chat.list` (read, если нет `--allow-duplicate-title`), `im.chat.add` (write), `im.dialog.get` (read), `im.dialog.users.list` (read). Все REST 1.0, scope `im`; `strict-rest3` отклоняет команду до подключения, как остальные `chats:*`. В `CommandCatalog::ROUTES` объявляется полный набор, в `ScopeManifest` добавляется `im.chat.add` → `im`.

### Вывод

Профиль `chat-created`. JSON `data`: `chat` (`target`, `chatId`, `title`, `type`, `description`, `userIds`), `operation` (`action: create`, `target`, `chatId`, `confirmed`, `readback`), `readback` (`status`, `dialog`: `id`, `name`, `type`, `owner`; `members`: `requested`, `present`, `missing`, `complete`), `duplicateCheck` (`status`: `none` | `skipped`, `scanned`). TSV при `--plain`: `target`, `chatId`, `title`, `type`, `confirmed`, `readback`. Dry-run — стандартный профиль `plan` с шагами `im.chat.add` (`title`, `type`, `description`, `userIds`), `im.dialog.get`, `im.dialog.users.list`; проверка дубля выполняется и в dry-run.

### Код

- `Application/Chat/Request/CreateChatRequest` — нормализованный ввод.
- `Application/Chat/Port/ChatCreateGateway` + `Infrastructure/Bitrix24/Rest1ChatCreateAdapter` — `im.chat.add`.
- `Application/Chat/ChatCreateService` — маршруты, проверка дубля, план, запись, проверка результата.
- `ChatFindService` получает публичный метод просмотра только источника групповых чатов; `chats:find` использует его же, поведение не меняется.
- `ChatInputMapper::create`, `Console/Command/Chats/CreateChatCommand` (`chats:add`).

## Risks / Trade-offs

- Удалить созданный чат через REST нельзя, поэтому live integration-тест создаёт по одному закрытому чату с уникальным названием `b24cli-chats-add <hex>` только из пользователя подключения; на тестовом портале такие чаты накапливаются. Альтернатива — не делать live-тест — оставила бы контракт `im.chat.add` без проверки на портале.
- Проверка дубля видит только доступные пользователю чаты и тратит запросы на поиск (до `--max-scan / 50` страниц). Компромисс в пользу безопасности повторного запуска агента; `--allow-duplicate-title` снимает затраты.
- `im.chat.add` с несуществующим ID в `USERS`: поведение не документировано. CLI не валидирует пользователей заранее (это потребовало бы scope `user`), а обнаруживает расхождение в проверке участников (`members-missing`, exit 3).
- Значение `type` в ответе `im.dialog.get` не сверяется: документация его не перечисляет.

## Migration Plan

Новая команда; существующие команды и форматы не меняются. Откат — удаление команды и маршрута.

## Open Questions

Нет.
