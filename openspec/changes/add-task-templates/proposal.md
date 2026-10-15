# Proposal

## Why

CLI не позволяет читать и сопровождать шаблоны типовых задач. Пользователь возобновил #72 и 16.10.2026 согласовал чтение по ID и управление шаблонами для milestone 0.3.0.

## What Changes

- Добавить `task:template:show`, `task:template:fields:list`, `task:template:add`, `task:template:update`, `task:template:delete`.
- Сохранить REST 1.0 имена полей шаблона, права подключения, явную API policy, JSON/plain и dry-run.
- Добавить JTBD PM-08: сопровождать шаблоны типовых задач, сохраняя различие между наличием CLI и live-приёмкой.
- Поиск списка шаблонов, создание задачи по шаблону, повторения, пользовательские поля и изменение чек-листов не входят в согласованную первую реализацию. Для списка и прямого создания задачи контракт не подтверждён.

## Capabilities

### New Capabilities

- `task-templates`: чтение и управление шаблонами задач по проверенным REST 1.0 контрактам.

### Modified Capabilities

Нет. Историческая граница MVP 0.1.0 остаётся неизменной.

## Impact

Каталог команд, новый application service/port и REST 1.0 adapter, ввод/вывод, scope manifest, CLI reference, JTBD, unit и portal integration tests. Новые зависимости не требуются.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/72 — согласованная первая реализация, milestone 0.3.0.
- Выделено из https://github.com/mesilov/bitrix24-cli/issues/61; результаты задач остаются отдельной возможностью.
