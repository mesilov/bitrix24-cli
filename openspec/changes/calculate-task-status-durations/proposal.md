# Proposal

## Why

`task:history:list` показывает переходы, но не рассчитывает календарное время в статусах. Для PM-02 и AM-03 нужны интервалы и суммы с учётом повторного открытия; неполная история не должна превращаться в ложный точный отчёт.

## What Changes

- Добавить read-only `task:status:durations TASK_ID [--as-of ISO8601]` с интервалами каждого входа в статус, суммами и evidence IDs.
- Учитывать все циклы завершения/повторного открытия и время в завершённом статусе; не смешивать календарную длительность с трудозатратами.
- Ввести явные неизвестные границы и `null` для неподтверждённых итогов, `complete=false`/exit 3 для неполной или противоречивой истории.
- Подтвердить live-форму `REAL_STATUS`/`STATUS` и navigation; использовать имеющийся history gateway/scanner без ослабления его completeness gate.
- Дополнить каталог, русскую справку, отдельные unit/integration проверки и трассировку PM-02/AM-03 в документации.

## Capabilities

### New Capabilities
- `task-status-durations`: расчёт календарных интервалов и сумм по статусам, повторные входы, границы достоверности истории и asOf.

### Modified Capabilities
- `task-console-runtime`: добавить команду к текущему явному task inventory, сохранив регистрацию остальных namespaces, deferred connection и integration ownership.

## Impact

Новая команда/handler/request и чистый калькулятор в Application; существующие `HistoryScanner`, `TaskHistoryGateway`, REST1 adapter, runtime catalog/container, ResultPresenter и human renderer. REST1 history + заранее объявленное REST3 чтение карточки для подтверждения состояния. Новых зависимостей и мутаций нет; strict-rest3 запрещает весь смешанный план. `task:history:list` не меняет публичный контракт.

Вне объёма: рабочие календари, SLA, трудозатраты, массовый проектный отчёт, автоматическая отправка, реализация task:report/brief.

## GitHub issues

- [#121](https://github.com/mesilov/bitrix24-cli/issues/121), milestone **0.2.0**: расчёт времени в статусах с повторными открытиями и неполной историей.

## Sources and verification boundary

- [tasks.task.history.list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-history-list.html): taskId, filter.FIELD, order.createdDate; события createdDate/field/value.from/value.to/user/id.
- Источник реализации: `dev` SHA `5ed034980e58ed950c3c733f3f01ec5ca08dd990`, проверка 14.10.2026; `src/Application/Task/HistoryScanner.php`, REST1 adapter и ListTaskHistoryCommandTest.
- Официальный MCP search/details ответил; конфигурация fresh worktree включает required bitrix24_dev. Статусные события/навигация на реальном портале не проверялись; текущий integration test доказывает TITLE event и документированную partial boundary, не полный статусный цикл. Это план, runtime не изменён.
