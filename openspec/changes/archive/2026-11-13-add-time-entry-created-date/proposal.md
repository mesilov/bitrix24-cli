# Proposal

## Why

`task:time:add` и `task:time:update` принимают только `--seconds` и `--text`. Портал датирует запись днём выполнения команды, поэтому работу за прошлый день нельзя внести задним числом, а ошибочную дату нельзя исправить. Агент из issue вносил время 20.10 за 22.10, и запись попала бы на неверный день отчёта.

## What Changes

- `task:time:add` получает необязательный флаг `--created-date`. Значение — ISO 8601 с явным смещением часового пояса, как у `--deadline`. Флаг передаётся в REST 1.0 `task.elapseditem.add` как `ARFIELDS.CREATED_DATE`. Без флага payload не меняется, и портал ставит текущий момент.
- `task:time:update` получает тот же необязательный флаг `--created-date`. Он передаётся в `task.elapseditem.update` как `ARFIELDS.CREATED_DATE`. Без флага портал сохраняет прежнюю дату записи. `--seconds` для update остаётся обязательным: портал отклоняет update без `SECONDS`.
- Значение проверяется до любых запросов: дата без смещения, невозможная дата или иной формат дают usage error (exit 2). Портал молча заменяет нераспознанную дату текущим моментом, поэтому клиентская проверка обязательна.
- Дата в прошлом и в будущем допускается: портал принимает обе.
- План `--dry-run` и `changedFields` результата показывают `createdDate`. Это имя уже используется в выводе `task:time:list` для `CREATED_DATE`.
- `DATE_START`/`DATE_STOP` (интервал работы таймера) не меняются и в этом change не открываются.
- Обновляются справка обеих команд, README, `docs/task-jtbd.md` (EMP-06) и `CHANGELOG.md`.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `task-mvp-scope`: новое требование о дате записи времени для `task:time:add`/`task:time:update` в рамках уже допущенных маршрутов REST 1.0 `task.elapseditem.*`.

## Impact

- Код: `AddTaskTimeEntryCommand`, `UpdateTaskTimeEntryCommand` (флаг и справка), `TaskUsage` (второй пример), `TaskInputMapper` (проверка через `FieldSchema::date`), `AddTaskTimeEntryRequest`/`UpdateTaskTimeEntryRequest`, `TimeEntryService` (план и результат), `TimeEntryGateway`/`Rest1TimeEntryAdapter` (`CREATED_DATE` в `ARFIELDS`).
- API: прежние маршруты REST 1.0 `task.elapseditem.add`/`update`. Новое поле записи — `CREATED_DATE`. Новых методов, scopes и маршрутов нет; `strict-rest3` по-прежнему отклоняет команды до подключения.
- Тесты: контрактные unit-тесты payload, проверка неверных дат без вызова API, интеграционные тесты add/update с чтением `createdDate`.
- Документация: справка, README, JTBD EMP-06, `CHANGELOG.md`; генерируемая карта команд сверяется без изменений.

## GitHub issues

- [#179](https://github.com/mesilov/bitrix24-cli/issues/179) — `task:time:add` и `task:time:update` не имеют опции даты: невозможна запись наперёд или в прошлое. Change покрывает задание даты записи при добавлении и её исправление при update. Milestone: 0.3.0; label: enhancement.
