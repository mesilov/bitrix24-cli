# Proposal

## Why

Skill должен переводить запросы «дедлайн сегодня/завтра/на этой неделе/в этом месяце/квартале» и «должны были закрыть вчера/позавчера» в устойчивые аргументы public CLI. Сейчас task:list имеет только локальный due-before, task:find не имеет временных фильтров, а REST3 не принимает серверный deadline filter.

## What Changes

- Добавить единые `--deadline-window` и `--overdue` для task:list/task:find, с календарной глубиной день → неделя → месяц → квартал.
- Canonical windows: today, tomorrow, yesterday, day-before-yesterday, this-week, this-month, this-quarter. Timezone: `--timezone IANA`, default локальный timezone CLI с явной metadata.
- Вычислять полуоткрытые календарные интервалы и один now на запрос; skill передаёт мнемонику, CLI рассчитывает даты.
- Для новых временных выборок использовать явный REST1 tasks.task.list маршрут с серверными >=DEADLINE/<DEADLINE; strict-rest3 запрещает этот маршрут, без скрытого fallback.
- Различать окно deadline, просрочку и фактическую дату закрытия. Последняя вне MVP.
- Сохранить AND-комбинации фильтров, лимиты, бюджет, partial и существующие форматы вывода; обновить help, README, CHANGELOG и отдельный skill.

## Capabilities

### New Capabilities

- `task-deadline-windows`: календарные окна дедлайна, timezone, просрочка и public CLI mapping для skill.

### Modified Capabilities

- `cli-experience`: явный REST1-маршрут временных выборок при сохранении текущего REST3 поведения остальных списков и поиска.

## Impact

TaskInputMapper, TaskQuery, TaskListScanner, ListTaskCommand/FindTaskCommand, gateway/adapters, CommandCatalog/API policy; тесты unit и portal integration. Отдельный репозиторий bitrix24-cli-skill получает только документированный public CLI contract после реализации. Реализация выполняется по явному запросу пользователя 2026-10-13; выбран REST1 и рекомендованная грамматика.

## GitHub issues

- [#95](https://github.com/mesilov/bitrix24-cli/issues/95), milestone 0.2.0, label enhancement: user stories, варианты мнемоник, API-маршрут и приёмка.

## Evidence boundary

2026-10-13 проверены официальные документы через MCP search/details и полные страницы методов. На текущем портале выполнены 4 read-only запроса: базовый REST3 успешен; REST3 deadline range отклонён (HTTP400, отсутствие Filterable); REST1 тот же range успешен (HTTP200, 5 задач, все внутри диапазона); противоречивый REST1 range дал 0. См. research.md и api-evidence.json. Это не проверка будущего CLI и не универсальная гарантия всех порталов.
