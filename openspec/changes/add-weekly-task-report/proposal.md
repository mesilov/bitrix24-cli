# Proposal

## Why

При подведении итогов недели руководителю нужен один отчёт с выполненной работой, очередью приёмки и текущей нагрузкой проекта. AM-03 описывает общую сводку, но не определяет недельный период и четыре показателя; `task:report` отсутствует в runtime.

## What Changes

- Добавить JTBD **PM-09 — Недельный отчёт** и его запись в `docs/task-jtbd.md` со статусом «План».
- Спроектировать read-only `task:report --group-id ID [--responsible ID] [--week this-week|last-week|YYYY-MM-DD] [--timezone IANA] [--max-scan N]`: по умолчанию весь выбранный проект за прошлую календарную неделю; исполнитель только сужает проект.
- Вывести четыре показателя: список закрытых за неделю, текущий список ожидающих контроля, количество созданных за неделю, текущее количество задач в работе.
- Различать период создания/закрытия и текущие наблюдаемые статусы. Повторно открытые задачи не включать в закрытые; события закрытия и историческое состояние на конец недели не восстанавливать.
- Сохранять область доступных задач, полноту каждой секции, неизвестные точные количества и partial/exit 3; использовать явно объявленный REST1 list без записей и fallback.
- Подготовить постановку для milestone **0.3.0**. Этот этап изменяет требования и документацию; runtime-команда будет реализована отдельно.

## Capabilities

### New Capabilities

- `task-weekly-report`: период, область проекта, четыре показателя, bounded scan, форматы и явные ограничения недельного отчёта.

### Modified Capabilities

- `task-workflows`: добавить PM-09 с измеримым признаком успеха и необязательным сужением до исполнителя.
- `task-mvp-scope`: явно допустить недельный отчёт в развитии 0.3.0 после исторического MVP, сохранив остальные исключения.

## Impact

Будущая реализация затронет `Console/TaskInputMapper`, новый `ReportTasksCommand`, request/handler, порт чтения отчёта и REST1 adapter, calendar resolver, presenter, `CommandCatalog`, API policy/scope manifest и тесты. Обнаружены существующие `DeadlineWindowResolver`, `Clock`, `Rest1TaskListAdapter`, `DeadlineTaskGateway`, `TaskListScanner`, `TaskStatus`; дизайн определяет границы их повторного использования.

Текущий снимок `dev`: `0caf9db62fce3f91852aafc77fd9b25cb3f9bde2`, 49 task-команд / 63 публичных. Планируемая команда увеличит каталог до 50 / 64; сейчас каталог не изменяется. Нужны только чтения `tasks.task.list` REST1 и scope `task`. Смежный [#141](https://github.com/mesilov/bitrix24-cli/issues/141) расширяет общие фильтры дат, но не определяет этот отчёт; зависимость от его реализации не вводится.

## GitHub issues

- [#168 — Недельный отчёт по проекту с четырьмя показателями (PM-09)](https://github.com/mesilov/bitrix24-cli/issues/168). Покрывает PM-09, четыре показателя, проект по умолчанию и optional responsible, период/полноту и read-only API-контракт.
- Milestone: `0.3.0`; label: `enhancement`. GitHub API возвращает `repository.issueTypes=null`; тип issue недоступен для этого репозитория.
- Обратная ссылка проверена в разделе `## OpenSpec` issue: `add-weekly-task-report`, путь `openspec/changes/add-weekly-task-report/`. Текст постановки сохранён также в [issue-draft.md](issue-draft.md).

## Источники

Сверка официального MCP `bitrix24_dev` выполнена 16.10.2026: search → method details (`tasks.task.list`, `tasks.task.field.list`). Нативные поля и статусы REST1 уточнены по [документации list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html) и [полям задачи](https://apidocs.bitrix24.ru/api-reference/tasks/fields.html). Это проверка документации, а не live-приёмка. Полный контракт и ограничения — в `design.md`.
