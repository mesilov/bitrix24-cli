# Proposal

## Why

Согласованный MVP содержит 30 команд задач, на старте change `bin/console` запускал только пустой Symfony Application. Для реализации нужен единый способ регистрации команд, передачи ввода в обработчики и обращения к явно выбранным версиям Bitrix24 API, чтобы команды сохраняли согласованные UX и границы MVP.

## What Changes

- Спроектировать и реализовать standalone приложение на Symfony Console и Symfony DependencyInjection: bootstrap, явную регистрацию 30 команд, ленивое создание подключения и общий вывод/ошибки.
- Разделить Console-команды, прикладные запросы/обработчики и API-порты с адаптерами. `task:update`, `task:assign` и `task:deadline:set` используют один обработчик изменения полей с разными ограничениями ввода.
- Описать маршрут каждой команды, проверки task/chat/message/checklist/time IDs, dry-run, подтверждение удаления, пагинацию, API policy и неопределённый результат записи.
- Уточнение пользователя 2026-10-11: для выбранных auditors/accomplices в task:show читать карточку через REST3 без этих relations, отдельно получать ID через REST1 tasks.task.get и обогащать их через user.get. Маршруты объявляются заранее; это разрешённая композиция чтений, без переключения после ошибки.
- Реализовать карту команд, standalone bootstrap/DI, API adapters/handlers, unit-tests и отдельные live integration tests. Пользователь разрешил apply 2026-10-09. Live приёмка требует предоставленного webhook и фиксируется отдельно.
- Уточнение пользователя 2026-10-11: на каждую прикладную CLI-команду иметь минимум один интеграционный happy-path сценарий на реальном портале с проверкой результата. Явно связать сценарии с именами команд и проверять полноту этой связи по CommandCatalog; добавить недостающие проверки.
- Дополнительное решение 2026-10-11: разделить integration-tests per command для дальнейшего расширения покрытия. Одна команда — отдельный test-класс с собственным happy-path test-case и дополнительными cases этой команды; fixtures/readback/cleanup выполнять через SDK, сохраняя независимость от других CLI-команд.
- Зафиксировать отсутствие `task:subtask:add` в регистрации MVP. Это исключение уже отражено в `define-task-jtbd` и `task-mvp-scope`; изменение не возвращает 29 исключённых операций через aliases или скрытые шаги.

## Упрощение архитектуры и MR review — 2026-10-11

По согласованной схеме сохраняем 30 Command-классов, standalone DI, Gateway-интерфейсы и version-specific adapters. Карта loader строится прямо из CommandCatalog без tags/compiler pass; согласованность AsCommand и MVP проверяется unit-tests. Простые task/item DTO объединяются в TaskReference/ChecklistItemReference. 17 handlers объединяются в TaskMetadataService, TaskChatService, TimeEntryService и ChecklistItemService с явными методами; 11 обработчиков остальных сценариев остаются отдельными. CommandRunner получает имя/effect из команды/каталога. API-маршруты, binding, dry-run, partial и cancellation сохраняются.

Замечания MR #11: локальная suite называется unit-tests, реальные portal tests — integration-tests; Makefile и CI явно разделяют запуски. После рефакторинга выполнить обе suite, quality checks и опубликовать исправления в существующем MR.

## Capabilities

### New Capabilities

- `task-console-runtime`: сборка и запуск команд задач через Console/DI, границы ввода и выполнения, явные API-маршруты и проверяемая регистрация MVP.

### Modified Capabilities

- `task-mvp-scope`: уточнить явный API admission для выбранного обогащения участников task:show (решение 2026-10-11). Число команд и исключения MVP сохраняются. `cli-experience` и `task-workflows` остаются входными контрактами; исключение отдельной подзадачи оформлено в `define-task-jtbd`.

## Impact

Точки реализации: launcher `b24cli`, `bin/console`, PHP service definitions, классы под существующим namespace `Bitrix24\CLI\` в `src/Console`, `src/Application`, `src/Infrastructure`, тесты команд и bootstrap. Добавляются `symfony/dependency-injection:^8.0`, `symfony/dotenv:^8.0` и PHPUnit 12; FrameworkBundle, Kernel, bundles и полный Symfony framework не требуются. Закреплённый в lock Console — 8.1.8, SDK — 3.7.0; версии и ограничения проверяются повторно при apply.

Подключение использует согласованный входящий webhook из root .env/process environment; Docker поддерживает read-only bind внешнего env-файла. OAuth/хранилище нескольких профилей остаётся отдельной областью, auth-команды не добавляются. Проверки API на тестовом портале потребуются при реализации; документированный маршрут и согласованный scope не являются runtime acceptance.

## GitHub issues

- [#10 — исследование CLI/MCP и поверхности блока задач](https://github.com/mesilov/bitrix24-cli/issues/10): архитектура реализации согласованных команд после исследования и уточнения MVP. Change: `design-task-console-architecture`, путь `openspec/changes/archive/2026-10-12-design-task-console-architecture/`.

## Related planning

RC coherence review 12.10.2026: task inventory остаётся 30; локальные connection-команды реализованы отдельно в #19. Текущий выбор credentials подчиняется connection-configuration: явные selectors, process/file env, profiles/default и последний source-only root dotenv. Standalone не ищет root dotenv. Исходные разрешения и portal records ниже сохраняются как история, а текущий runtime delta уточнён по уже поставленному #19 без изменения кода/API.

- [Текущий UX](../../../specs/cli-experience/spec.md), [граница MVP](../../../specs/task-mvp-scope/spec.md), [продуктовые JTBD](../../../specs/task-workflows/spec.md).
- [Каталог API и команд](../../define-task-jtbd/cli-candidates.json), [матрица JTBD](../../define-task-jtbd/jtbd-coverage.md), [решения по scope](../../define-task-jtbd/design.md).

## Implementation authorization 2026-10-09

Уточнение 2026-10-10: пользователь предоставил входящий webhook в root `.env.local` и разрешил прогон/исправление всех CLI-команд. Поддерживается этот filename по умолчанию: process credentials → выбранный B24CLI_ENV_FILE либо `.env.local`, при отсутствии `.env.local` — `.env`. Внешний файл выбранного checkout монтируется read-only.

Пользователь разрешил реализацию согласованной пачки команд и интеграционных тестов. Credentials будут в root .env; используется входящий webhook по BITRIX24_WEBHOOK и отложенное создание ServiceBuilder. Уточнения SDK audit применены перед кодом; runtime evidence фиксируется отдельно.

## Решение о приёмке RC 0.1.0 — 12.10.2026

По явному решению пользователя непроверенные части portal tasks 5.2–5.4 и связанного `define-task-jtbd` task 2.3 перенесены в milestone **0.2.0**, [#42](https://github.com/mesilov/bitrix24-cli/issues/42), change `complete-task-portal-acceptance` (`openspec/changes/complete-task-portal-acceptance/`). Они исключены из обязательной приёмки RC 0.1.0; перенос не является новым runtime proof. Уже выполненные 30 собственных happy-path и исходные records сохраняются.

Границы RC: denied-role matrix не подтверждена; history/elapsedTime completeness частична; file attachment ACK без независимого membership; reassignment только на текущего пользователя; native REST3 filled auditors limitation сохраняется для task:list, task:show использует заранее выбранную композицию чтений без error-triggered fallback/retry. Binding/policy/partial/outcomeUnknown/cancellation и 29 post-MVP исключений сохраняются. #19/#23/native packaging остаются критериями RC. [Полный список ограничений](../../../../docs/rc-0.1.0.md).

Связанный issue перенесённой проверки: [#42](https://github.com/mesilov/bitrix24-cli/issues/42); milestone 0.2.0, change `complete-task-portal-acceptance`. Исходный #10 сохраняет milestone 0.1.0.
