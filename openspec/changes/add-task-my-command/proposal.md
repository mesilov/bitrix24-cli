# Proposal

## Why

Для EMP-01 нужна отдельная команда рабочего представления выбранного исполнителя. Базовый список уже умеет responsible/role/deadline фильтры; task:my должна дать согласованный короткий интерфейс, переиспользуя эту реализацию.

## What Changes

- Запланировать `task:my --user ID [--open --due-before ISO8601 --max-scan N]` для 0.2.0; --user обязателен, без неявного определения другого пользователя или расширения прав подключения.
- Добавить personal-view фильтр открытых состояний поверх текущего TaskQuery/TaskListScanner; остальные defaults, выходные форматы, API policy и признаки неполноты сохранить.
- Зарегистрировать отдельный Command и integration class, обновить EMP-01, README/help/CHANGELOG при реализации.

## Capabilities

### New Capabilities

- `task-personal-view`: выбор доступных задач заданного исполнителя, open/deadline predicates и честная полнота результата.

### Modified Capabilities

- `task-console-runtime`: явная регистрация task:my наряду с текущими 34 task-командами (35 на базе этого плана), сохранение users/local namespaces и правил per-command integration tests.

## Impact

Будущая реализация затронет TaskQuery, TaskListScanner, mapper/command, CommandCatalog, registration/unit/integration tests и docs. Сейчас изменяются только OpenSpec-артефакты и запись CHANGELOG о планировании: runtime не реализуется. Текущая база `1741b9c5104c47a211904a47185aa442229c12d6`; перед реализацией сверить актуальный inventory, сохранив параллельно добавленные команды.

## GitHub issues

- [#118](https://github.com/mesilov/bitrix24-cli/issues/118) — отдельная task:my и приёмка EMP-01, milestone 0.2.0, enhancement.
- Продуктовый источник: `openspec/specs/task-workflows/spec.md` (EMP-01) и candidate `openspec/changes/define-task-jtbd/cli-candidates.json`; выборка уже существует в task:list, новая команда не обещает автоматическую приоритизацию.
