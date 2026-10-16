# Proposal

## Why

EMP-09 должен покрывать передачу только отложенных задач Юлии другому участнику проекта с исключением 92888 и сохранением либо явным изменением статуса. Сейчас `task:handover` выбирает все открытые задачи и проверяет только назначение и проект.

## What Changes

- `--source-status` ограничивает исходный открытый статус; повторяемый `--exclude-id` исключает задачи по положительному ID.
- `--target-status pending|in_progress|deferred|supposedly_completed|completed` задаёт целевой статус через штатные lifecycle-действия. Без параметра ожидается исходный статус каждой задачи.
- Снимок полностью собирается до записи. Перед назначением проверяется точный исходный статус; после выполнения — ответственный, проект и ожидаемый статус.
- Результат отдельно сохраняет подтверждение назначения, перехода и итоговой проверки; частичная ошибка/потеря ответа останавливает пакет без повторов.
- Обновляются EMP-09, PM-04, JTBD, документация и проверки.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `task-project-handover`: параметры выборки, переходы и подтверждение результата.
- `task-workflows`: EMP-09 и PM-04 с выборочной передачей и явным целевым статусом.

## Impact

TaskInputMapper, HandoverTasksRequest, TaskHandoverService, существующий TaskLifecycleService, CommandCatalog routes, JSON/plain/human, документация, unit и opt-in integration tests. REST3 list/get/update/access/result.list + REST1 проверка участников и lifecycle. Зависимости не добавляются. `declined` отклоняется до API: документированного штатного действия для этой цели нет. Завершение и контроль включены по явному уточнению пользователя.

## GitHub issues

- [#165](https://github.com/mesilov/bitrix24-cli/issues/165) — весь объём этого change; milestone `0.3.0`.
