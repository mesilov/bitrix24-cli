# Proposal

## Why

`task:chat:list` без `--before`/`--after` теряет новые сообщения чата задачи, если у пользователя подключения в этом чате есть непрочитанные. Первый запрос `im.dialog.messages.get` уходит без `LAST_ID`, а портал в этом случае возвращает окно вокруг первого непрочитанного сообщения, а не последние сообщения. Дальше обход идёт к более старым сообщениям, поэтому сообщения новее окна не попадают в результат, а `meta.complete` остаётся `true`.

Та же функция `ChatSupport::scan()` проверяет принадлежность сообщения в `task:chat:update` и `task:chat:delete`, поэтому они могут не найти новое сообщение и ошибочно вернуть `message-binding-unverified`.

Воспроизведено на тестовом портале 12.11.2026 (см. design.md, «Проверка на портале»): `task:chat:list --limit 3` вернул первые три непрочитанных сообщения вместо трёх последних.

## What Changes

- Без `--before`/`--after` обход чата задачи начинается с последнего сообщения: `LAST_ID = last_message_id + 1` из `im.dialog.get` (REST 1.0, scope `im`).
- Маршрут `im.dialog.get` объявляется в планах `task:chat:list`, `task:chat:update`, `task:chat:delete` и в scope manifest. strict-rest3 по-прежнему отклоняет эти команды до подключения.
- `--before`/`--after` сохраняют прежнее поведение и не вызывают `im.dialog.get`.
- Форматы вывода, `meta`, бюджет `--max-messages` и проверка связи задача → чат не меняются.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `task-console-runtime`: добавлено требование о стартовой позиции обхода чата задачи.

## Impact

`src/Application/Task/ChatSupport.php`, `TaskChatService`, порт `TaskChatGateway` и `TaskChatAdapter`, `CommandCatalog::ROUTES`, `ScopeManifest`, тестовые fixtures. Один дополнительный read-запрос `im.dialog.get` на запуск без курсора. Состояние прочитанности не меняется.

## GitHub issues

- [#202](https://github.com/mesilov/bitrix24-cli/issues/202) — `task:chat:list` теряет новые сообщения при непрочитанных; исправление стартовой позиции обхода и регрессионный тест. Milestone 0.3.0, label bug. Найдено при работе над [#180](https://github.com/mesilov/bitrix24-cli/issues/180).
