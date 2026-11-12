# Design

## Context

`task:time:add`/`task:time:update` используют явно допущенные REST 1.0 `task.elapseditem.add`/`update` ([требование task-mvp-scope](../../specs/task-mvp-scope/spec.md)). `Rest1TimeEntryAdapter` отправляет в `ARFIELDS` только `SECONDS` и `COMMENT_TEXT`. Чтение (`task:time:list`) уже возвращает `CREATED_DATE` как `createdDate`.

## Проверка документации (MCP Битрикс24, 13.11.2026)

Источник: официальный MCP `https://mcp-dev.bitrix24.tech/mcp`, вызовы `bitrix-search` → `bitrix-method-details`. Сервер подключён к сессии через Streamable HTTP напрямую (JSON-RPC `initialize`/`tools/call`), без секретов.

- [task.elapseditem.add](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-add.html): `TASKID` (integer, обязательный), `ARFIELDS` (object, обязательный). В `ARFIELDS` документированы только `SECONDS` (integer), `COMMENT_TEXT` (string), `USER_ID` (integer).
- [task.elapseditem.update](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-update.html): `TASKID`, `ITEMID`, `ARFIELDS` с теми же тремя полями; успешный ответ — `null`.
- [task.elapseditem.get](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-get.html) и [getlist](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-get-list.html): запись содержит `CREATED_DATE`, `DATE_START`, `DATE_STOP` (ISO 8601 со смещением); фильтр getlist поддерживает `CREATED_DATE` с префиксами `<`, `<=`, `>`, `>=`.
- [task.elapseditem.getmanifest](https://apidocs.bitrix24.ru/api-reference/tasks/elapsed-item/task-elapsed-item-get-manifest.html) возвращает описание методов; формат не предназначен для автоматической обработки.

Граница: страницы add/update не указывают, что `CREATED_DATE` доступен для записи. SDK `b24phpsdk` (`Elapseditem::add`) тоже не принимает дату. Поэтому запись поля подтверждена отдельно проверкой на портале (ниже).

## Проверка на портале

Сырые запросы по webhook без `b24cli` и SDK. Тестовый портал из `.env.local` основного checkout, тестовая группа `B24CLI_TEST_GROUP_ID=234`. Домен и webhook не сохраняются. Дата проверки — 13.11.2026.

| # | Метод (REST 1.0) | Payload (без секретов) | Ответ | Вывод |
| --- | --- | --- | --- | --- |
| 0 | `task.elapseditem.getmanifest` | `{}` | `REST: writable elapseditem data fields` и `allowedKeys` для add/update: `USER_ID`, `COMMENT_TEXT`, `SECONDS`, `SOURCE`, `CREATED_DATE`, `DATE_START`, `DATE_STOP` | `CREATED_DATE` записываемый для add и update: подтверждено |
| 1 | `tasks.task.add` | `TITLE` (одноразовая), `RESPONSIBLE_ID=CREATED_BY=1`, `GROUP_ID=234`, `ALLOW_TIME_TRACKING=Y` | задача `6144` | фикстура создана |
| 2 | `task.elapseditem.add` | `SECONDS=600`, `CREATED_DATE=2026-10-22T10:00:00+03:00` | id `110`; readback `CREATED_DATE=2026-10-22T10:00:00+03:00`, `DATE_START`/`DATE_STOP` = момент вызова | дата в прошлом: подтверждено |
| 3 | `task.elapseditem.add` | `SECONDS=600`, `CREATED_DATE=2026-11-20T12:00:00Z` | id `112`; readback `2026-11-20T15:00:00+03:00` | дата в будущем и смещение `Z`: подтверждено, портал переводит в свой пояс |
| 4 | `task.elapseditem.add` | `SECONDS=300` без даты | id `114`; `CREATED_DATE` = момент вызова | прежнее поведение без флага: подтверждено |
| 5 | `task.elapseditem.add` | `SECONDS=60`, `CREATED_DATE=not-a-date` | id `116`; `CREATED_DATE` = момент вызова, ошибки нет | портал молча игнорирует неверную дату: нужна клиентская проверка |
| 6 | `task.elapseditem.update` | `ITEMID=114`, только `CREATED_DATE` | `ERROR_CORE`, `TASKS_ERROR_ASSERT_EXCEPTION` | update без `SECONDS` невозможен: `--seconds` остаётся обязательным |
| 7 | `task.elapseditem.update` | `ITEMID=114`, `SECONDS=1200`, `CREATED_DATE=2026-10-21T18:00:00+03:00` | `null`; readback `SECONDS=1200`, `CREATED_DATE=2026-10-21T18:00:00+03:00` | исправление даты через update: подтверждено |
| 8 | `task.elapseditem.update` | `ITEMID=110`, только `SECONDS=660` | `null`; `CREATED_DATE` остался `2026-10-22T10:00:00+03:00` | update без даты сохраняет прежнюю дату: подтверждено |
| 9 | `task.elapseditem.getlist` | фильтр `>=CREATED_DATE=2026-10-21T00:00:00+03:00`, `<CREATED_DATE=2026-10-23T00:00:00+03:00` | записи `110`, `114` | заданная дата участвует в фильтре по дню: подтверждено |
| 10 | `task.elapseditem.delete` ×4, `tasks.task.delete` | записи `110`, `112`, `114`, `116`; задача `6144` | `null` ×4, `{"task": true}`; `tasks.task.get` → `[]` | cleanup выполнен |

До этого те же сценарии 0–8 выполнялись на портале сохранённого профиля `b24cli` (не тестовая группа) в одноразовой задаче с тем же результатом. Задача и записи там удалены (`task.elapseditem.delete` ×5, `task:delete --force`). Это отступление от правила тестовой группы зафиксировано; повторная проверка выше выполнена по правилам.

Права (`B24CLI_TEST_RESTRICTED_WEBHOOK`) для даты отдельно не проверялись: флаг не меняет маршрут и проверку прав, существующие тесты second-user сохраняются.

## Goals / Non-Goals

**Goals:**

- Внести запись времени заданной датой при добавлении.
- Исправить дату существующей записи через update.
- Отклонять неверную дату до запросов.

**Non-Goals:**

- `DATE_START`/`DATE_STOP`. Это интервал работы таймера; issue требует дату записи, а отчёты и фильтр getlist используют `CREATED_DATE`.
- Относительные даты (`yesterday` и т. п.) и дата без времени. Относительные даты развиваются отдельно ([#141](https://github.com/mesilov/bitrix24-cli/issues/141)); дата без времени требует выбора часового пояса.
- Изменение update без `--seconds`: портал такой вызов отклоняет.

## Decisions

### Имя флага `--created-date`

Флаг однозначно соответствует полю API `CREATED_DATE` и уже существующему ключу вывода `createdDate` в `task:time:list`. Короткое `--date` не говорит, какое из трёх полей даты записи меняется (`CREATED_DATE`, `DATE_START`, `DATE_STOP`). Альтернатива `--date` отклонена из-за этой неоднозначности.

### Формат и проверка

Используется существующая `FieldSchema::date`: ISO 8601 `YYYY-MM-DDTHH:MM:SS[.fff]` со смещением `Z` или `±HH:MM` и проверка реальной даты. Это тот же формат, что у `--deadline` и `task:deadline:set --at`. Значение передаётся в API без преобразования; портал сам переводит его в свой пояс (проверка №3). Неверный формат → usage error, exit 2, ноль вызовов API. Без проверки портал записал бы текущий момент (проверка №5), и пользователь бы этого не заметил.

### Ограничения диапазона

CLI не ограничивает дату прошлым или будущим. Портал принимает обе (№2, №3), а issue прямо просит «наперёд или в прошлое».

### Вывод

План `--dry-run` и `changedFields` включают `createdDate`, когда флаг задан. Без флага вывод не меняется. Отдельное чтение после записи не добавляется: проверенная дата сохраняется порталом без изменений (№2, №7), а прежние команды тоже не читают запись после записи.

### Порт и адаптер

`TimeEntryGateway::add/update` получают необязательный аргумент `?string $createdDate = null`. Адаптер добавляет `CREATED_DATE` в `ARFIELDS`, только когда он задан. Payload без флага совпадает с прежним, что фиксируют существующие контрактные тесты.

## Risks / Trade-offs

- Поле записи подтверждено манифестом и порталом, но не страницами add/update документации. → Ссылки на источники и ответы портала сохранены выше; интеграционные тесты читают `createdDate` после add/update.
- Манифест помечен как нестабильный формат. → Он используется только как дополнительное доказательство при планировании, а не в runtime.
