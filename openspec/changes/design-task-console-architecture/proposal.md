# Proposal

## Why

Согласованный MVP содержит 30 команд задач, на старте change `bin/console` запускал только пустой Symfony Application. Для реализации нужен единый способ регистрации команд, передачи ввода в обработчики и обращения к явно выбранным версиям Bitrix24 API, чтобы команды сохраняли согласованные UX и границы MVP.

## What Changes

- Спроектировать и реализовать standalone приложение на Symfony Console и Symfony DependencyInjection: bootstrap, явную регистрацию 30 команд, ленивое создание подключения и общий вывод/ошибки.
- Разделить Console-команды, прикладные запросы/обработчики и API-порты с адаптерами. `task:update`, `task:assign` и `task:deadline:set` используют один обработчик изменения полей с разными ограничениями ввода.
- Описать маршрут каждой команды, проверки task/chat/message/checklist/time IDs, dry-run, подтверждение удаления, пагинацию, API policy и неопределённый результат записи.
- Реализовать карту команд, standalone bootstrap/DI, API adapters/handlers, offline и отдельные live integration tests. Пользователь разрешил apply 2026-10-09. Live приёмка требует предоставленного webhook и фиксируется отдельно.
- Зафиксировать отсутствие `task:subtask:add` в регистрации MVP. Это исключение уже отражено в `define-task-jtbd` и `task-mvp-scope`; изменение не возвращает 29 исключённых операций через aliases или скрытые шаги.

## Capabilities

### New Capabilities

- `task-console-runtime`: сборка и запуск команд задач через Console/DI, границы ввода и выполнения, явные API-маршруты и проверяемая регистрация MVP.

### Modified Capabilities

Нет. `cli-experience`, `task-mvp-scope` и `task-workflows` остаются входными контрактами; пользовательское исключение отдельной подзадачи оформлено в существующем `define-task-jtbd`.

## Impact

Точки реализации: launcher `b24cli`, `bin/console`, PHP service definitions, классы под существующим namespace `Bitrix24\CLI\` в `src/Console`, `src/Application`, `src/Infrastructure`, тесты команд и bootstrap. Добавляются `symfony/dependency-injection:^8.0`, `symfony/dotenv:^8.0` и PHPUnit 12; FrameworkBundle, Kernel, bundles и полный Symfony framework не требуются. Закреплённый в lock Console — 8.1.8, SDK — 3.7.0; версии и ограничения проверяются повторно при apply.

Подключение использует согласованный входящий webhook из root .env/process environment; Docker поддерживает read-only bind внешнего env-файла. OAuth/хранилище нескольких профилей остаётся отдельной областью, auth-команды не добавляются. Проверки API на тестовом портале потребуются при реализации; документированный маршрут и согласованный scope не являются runtime acceptance.

## GitHub issues

- [#10 — исследование CLI/MCP и поверхности блока задач](https://github.com/mesilov/bitrix24-cli/issues/10): архитектура реализации согласованных команд после исследования и уточнения MVP. Change: `design-task-console-architecture`, путь `openspec/changes/design-task-console-architecture/`.

## Related planning

- [Текущий UX](../../specs/cli-experience/spec.md), [граница MVP](../../specs/task-mvp-scope/spec.md), [продуктовые JTBD](../../specs/task-workflows/spec.md).
- [Каталог API и команд](../define-task-jtbd/cli-candidates.json), [матрица JTBD](../define-task-jtbd/jtbd-coverage.md), [решения по scope](../define-task-jtbd/design.md).

## Implementation authorization 2026-10-09

Пользователь разрешил реализацию согласованной пачки команд и интеграционных тестов. Credentials будут в root .env; используется входящий webhook по BITRIX24_WEBHOOK и отложенное создание ServiceBuilder. Уточнения SDK audit применены перед кодом; runtime evidence фиксируется отдельно.
