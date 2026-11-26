# Proposal

## Why

Skill переводит запросы пользователя в public CLI, но после #95 CLI покрывает только поиск по текущим календарным окнам. Запросы «создай задачу со сроком завтра в 18:00», «перенеси дедлайн на пятницу в 17:00», «задачи на следующей неделе», «за прошлый месяц», «ближайшие 7 дней» и «фактически завершённые на этой неделе» сейчас либо отклоняются (`--at tomorrow` → usage-error), либо требуют от skill собственных расчётов дат, что запрещено контрактом skill ↔ CLI.

## What Changes

- Назначение относительного срока: task:add и task:update получают `--deadline-day TOKEN --deadline-time HH:MM`, task:deadline:set — `--day TOKEN --time HH:MM`; все три принимают `--timezone IANA`. Токены дня: today, tomorrow, day-after-tomorrow, monday…sunday (ближайший такой день строго после сегодня), next-week-monday…next-week-sunday (день следующей календарной недели) и точная дата YYYY-MM-DD. Время обязательно; скрытого часа по умолчанию нет. Существующие `--deadline`/`--at` остаются строго ISO8601 и взаимоисключающи с новыми флагами.
- Результат — точный ISO8601 с offset выбранного timezone; dry-run и ответ показывают токен, время, timezone, now и итоговое значение. Несуществующее (DST gap) или неоднозначное (DST overlap) локальное время и срок не позже now отклоняются usage-error до API.
- Дополнительные календарные окна поиска: last-week, next-week, last-month, next-month, last-quarter, next-quarter. Скользящие окна в календарных днях: next-7-days = [00:00 сегодня, 00:00 через 7 дней), last-7-days = [00:00 шесть дней назад, 00:00 завтра). Окна доступны всем временным флагам.
- Новый `--closed-window` для task:list/task:find: фактическая дата завершения (REST1 `CLOSED_DATE`) в окне, независимо от текущего статуса; задачи с пустой датой завершения не совпадают. «Сейчас закрыта» задаётся дополнительным `--status completed`.
- Сохраняются: REST1 route и strict-rest3 policy-denied/exit4 для временных выборок, AND-комбинации, бюджет обхода, partial/exit3, точный ISO-ввод, dry-run без записи, confirmed/partial/outcomeUnknown и отсутствие автоматического повтора записи.
- Обновить help/completion, README/руководства, JTBD, CHANGELOG и отдельный bitrix24-cli-skill.

## Capabilities

### New Capabilities

- `task-deadline-assignment`: преобразование относительного дня и времени в точный срок задачи для task:add, task:update и task:deadline:set.

### Modified Capabilities

- `task-deadline-windows`: новые календарные и скользящие окна, окно фактического завершения `--closed-window`, расширенная валидация и mapping skill.

## Impact

DeadlineWindowResolver/DateWindow, TaskInputMapper, AddTaskCommand/UpdateTaskCommand/SetTaskDeadlineCommand, ListTaskCommand/FindTaskCommand, TaskListRoute/TaskListScanner, Rest1TaskListAdapter (поле closed ↔ CLOSED_DATE уже сопоставлено), completion, README/docs/task-usage.md, docs/task-jtbd.md, CHANGELOG, unit/command/native тесты. Отдельный репозиторий bitrix24-cli-skill получает только public CLI contract после поставки. Новых зависимостей нет.

## GitHub issues

- [#141](https://github.com/mesilov/bitrix24-cli/issues/141), milestone 0.3.0, label enhancement: назначение относительных сроков, дополнительные периоды поиска, фильтр фактического завершения. Change покрывает все три сценария issue; поиск по дате создания уже поставлен (`--created-window`, #95) и расширяется только новыми окнами.
- Связи: [#95](https://github.com/mesilov/bitrix24-cli/issues/95) — завершённые календарные окна (остаётся завершённым); [bitrix24-cli-skill #4](https://github.com/mesilov/bitrix24-cli-skill/issues/4) — исходный запрос относительных дат.

## Agreed decisions 2026-11-13

Согласованы с пользователем до реализации: «ближайшие 7 дней» — календарные дни, включая сегодня; относительный срок — отдельными флагами, `--deadline`/`--at` только ISO8601; токен дня недели — ближайший такой день строго после сегодня; `--closed-window` — только по CLOSED_DATE, без требования текущего статуса.

## Evidence boundary

2026-11-13 MCP `bitrix24_dev` проверен из worktree (`codex mcp get bitrix24_dev --json`) и реальными запросами initialize/tools/list/bitrix-search/details. Подтверждено: REST3 поле `deadline` — `datetime | null` в ISO 8601, `closed` — `datetime | null` ([Поля задачи в REST 3.0](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html)); классическое поле `closedDate` — ISO 8601, `null` для незавершённой задачи ([Поля задачи](https://apidocs.bitrix24.ru/api-reference/tasks/fields.html)). `bitrix-method-details tasks.task.list` возвращает только REST3-страницу, поэтому список фильтров классического метода дополнительно прочитан на официальной странице [tasks.task.list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html): фильтр CLOSED_DATE, CREATED_DATE, CHANGED_DATE, DEADLINE; на [REST3 tasks.task.list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html) фильтр допускает только `id` (ошибка Filterable). Документация не описывает, сбрасывается ли CLOSED_DATE при renew и выставляется ли при переходе в «ожидает контроля»; это проверяется live (см. design.md). Live-проверки портала в постановке не выполнялись.
