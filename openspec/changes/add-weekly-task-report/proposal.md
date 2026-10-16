# Proposal

## Why

При подведении итогов недели руководителю нужен один отчёт с выполненной работой, очередью приёмки и текущей нагрузкой проекта. AM-03 описывает общую сводку, но не определяет недельный период и четыре показателя. До этого change `task:report` отсутствовала в runtime.

## What Changes

- Добавить JTBD **PM-09 — Недельный отчёт** и его запись в `docs/task-jtbd.md` с раздельными статусами реализации и live-приёмки.
- Реализовать read-only `task:report --group-id ID [--responsible ID] [--week this-week|last-week|YYYY-MM-DD] [--timezone IANA] [--max-scan N]`: по умолчанию весь выбранный проект за прошлую календарную неделю; исполнитель только сужает проект.
- Вывести четыре показателя: список закрытых за неделю, текущий список ожидающих контроля, количество созданных за неделю, текущее количество задач в работе.
- Различать период создания/закрытия и текущие наблюдаемые статусы. Повторно открытые задачи не включать в закрытые; события закрытия и историческое состояние на конец недели не восстанавливать.
- Сохранять область доступных задач, полноту каждой секции, неизвестные точные количества и partial/exit 3; использовать явно объявленный REST1 list без записей и fallback.
- Поставить функцию в milestone **0.3.0**. Постановка влита отдельно в MR #169; реализация ведётся отдельной веткой и MR по тому же change.

## Capabilities

### New Capabilities

- `task-weekly-report`: период, область проекта, четыре показателя, bounded scan, форматы и явные ограничения недельного отчёта.

### Modified Capabilities

- `task-workflows`: добавить PM-09 с измеримым признаком успеха и необязательным сужением до исполнителя.
- `task-console-runtime`: обновить точный каталог с 49 до 50 task-команд при реализации task:report.
- `task-mvp-scope`: явно допустить недельный отчёт в развитии 0.3.0 после исторического MVP, сохранив остальные исключения.

## Impact

Реализация затрагивает `Console/TaskInputMapper`, новый `ReportTasksCommand`, request/handler, порт чтения отчёта и REST1 adapter, calendar resolver, presenter, `CommandCatalog`, API policy/scope manifest и тесты. Обнаружены существующие `DeadlineWindowResolver`, `Clock`, `Rest1TaskListAdapter`, `DeadlineTaskGateway`, `TaskListScanner`, `TaskStatus`; дизайн определяет границы их повторного использования.

Исходный снимок планирования `dev`: `0caf9db62fce3f91852aafc77fd9b25cb3f9bde2`, 49 task-команд / 63 публичных. Реализация синхронизирована с `dev` после MR #169 (`c6295051a61d48b8821a00d6766c4585bf8ccb08`) и увеличивает каталог до 50 / 64. Нужны только чтения `tasks.task.list` REST1 и scope `task`. Смежный [#141](https://github.com/mesilov/bitrix24-cli/issues/141) расширяет общие фильтры дат, но не определяет этот отчёт; зависимость от его реализации не вводится.

## GitHub issues

- [#168 — Недельный отчёт по проекту с четырьмя показателями (PM-09)](https://github.com/mesilov/bitrix24-cli/issues/168). Покрывает PM-09, четыре показателя, проект по умолчанию и optional responsible, период/полноту и read-only API-контракт.
- Постановка: [MR #169](https://github.com/mesilov/bitrix24-cli/pull/169), влит в dev (`c629505`). Реализация: [MR #173](https://github.com/mesilov/bitrix24-cli/pull/173), отдельная ветка `feature/168-weekly-task-report`.
- Milestone: `0.3.0`; label: `enhancement`. GitHub API возвращает `repository.issueTypes=null`; тип issue недоступен для этого репозитория.
- Обратная ссылка проверена в разделе `## OpenSpec` issue: `add-weekly-task-report`, путь `openspec/changes/add-weekly-task-report/`. Текст постановки сохранён также в [issue-draft.md](issue-draft.md).

## Источники

Сверка официального MCP `bitrix24_dev` выполнена 16.10.2026: search → method details (`tasks.task.list`, `tasks.task.field.list`). Нативные поля и статусы REST1 уточнены по [документации list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html) и [полям задачи](https://apidocs.bitrix24.ru/api-reference/tasks/fields.html). Это проверка документации, а не live-приёмка. Полный контракт и ограничения — в `design.md`.
