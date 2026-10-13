# Proposal

## Why

Для EMP-01 нужна отдельная команда рабочего представления выбранного исполнителя. Базовый список уже умеет responsible/role/deadline фильтры; task:my должна дать согласованный короткий интерфейс, переиспользуя эту реализацию.

## What Changes

- Добавить `task:my --user ID [--open --due-before ISO8601 --max-scan N]` для 0.2.0; --user обязателен, без неявного определения другого пользователя или расширения прав подключения.
- Добавить personal-view фильтр открытых состояний поверх текущего TaskQuery/TaskListScanner; остальные defaults, выходные форматы, API policy и признаки неполноты сохранить.
- Зарегистрировать отдельный Command и integration class, обновить EMP-01, README/help/CHANGELOG при реализации.

## Capabilities

### New Capabilities

- `task-personal-view`: выбор доступных задач заданного исполнителя, open/deadline predicates и честная полнота результата.

### Modified Capabilities

- `task-console-runtime`: явная регистрация task:my наряду с текущими 41 task-командой (42 с task:my), сохранение users/local namespaces и правил per-command integration tests.

## Impact

Реализация затрагивает TaskQuery, TaskListScanner, mapper/command, CommandCatalog, registration/unit/integration tests и docs. База реализации `f7dc2a7`; каталог после интеграции lifecycle и добавления task:my содержит 42 task-команды и четыре users-команды.

## GitHub issues

- [#118](https://github.com/mesilov/bitrix24-cli/issues/118) — отдельная task:my и приёмка EMP-01, milestone 0.2.0, enhancement.
- Продуктовый источник: `openspec/specs/task-workflows/spec.md` (EMP-01) и candidate `openspec/changes/define-task-jtbd/cli-candidates.json`; выборка уже существует в task:list, новая команда не обещает автоматическую приоритизацию.
