# Proposal

## Why

В b24cli 0.1.0 нельзя определить пользователя подключения, найти коллегу или узнать руководителя без ручного ID либо обращения в обход CLI. Issue #63 и уточнения пользователя расширяют эту потребность до отдельного namespace `users` и профильных настроек рабочих ролей в milestone 0.2.0.

## What Changes

- Добавить `users:current`, `users:find`, `users:colleagues` и `users:manager` с общими форматами вывода и политикой API.
- Получать коллег из текущего отдела пользователя подключения; при нескольких отделах требовать явный `--department-id`. Выдавать только активных сотрудников этого отдела, исключая самого пользователя, без включения дочерних отделов.
- Определять непосредственного руководителя по оргструктуре. Отдельный `--source config` читает настроенного руководителя и не подменяет ответ `--source org` по умолчанию.
- Искать сотрудников по ФИО и точному email, возвращая все совпадения в пределах явно указанного бюджета, без автоматического выбора ID и назначения в задачи.
- Добавить в schema v1 необязательные `default.users.observerId` и `default.users.managerId`: положительные ID локальных рабочих ролей выбранного профиля, а также boolean `autoAddObserver` (default false). Это служебные настройки CLI, а не поля Bitrix24 API; они никогда не передаются в REST как одноимённые параметры.
- Расширить JTBD отдельной capability рабочих сценариев сотрудников и предусмотреть независимые проверки каждой новой команды.

По уточнению пользователя наблюдатель автоматически добавляется при `task:add` только при включённом профильном `default.users.autoAddObserver=true`. После создания задачи читаются её текущие наблюдатели через REST1 `tasks.task.get`, затем выполняется REST1 `tasks.task.update` с `AUDITORS` — объединением существующих ID и `observerId`; при отказе второго шага CLI сохраняет ID созданной задачи и сообщает partial, без повторного создания.

## Capabilities

### New Capabilities

- `user-directory`: пользователь текущего подключения, поиск сотрудников и чтение отношений по оргструктуре через namespace `users`.
- `user-workflows`: JTBD определения себя, поиска коллег, обращения к руководителю и сохранения рабочих ролей.

### Modified Capabilities

- `connection-configuration`: профильные `default.users`, флаг opt-in автодобавки наблюдателя, CLI-флаги настройки и согласованная schema/runtime validation.
- `task-console-runtime`: регистрация четырёх users-команд, расширение разрешённых REST1 маршрутов и интеграционного покрытия, opt-in добавление наблюдателя после task:add с явным учётом двух шагов.

## Impact

Изменятся явный CommandCatalog и DI, presenter, новые Console/Command/Users и Application/User handlers/ports, REST1 adapters, ProfileRepository/ConnectionManager, `docs/config.schema.json`, shared schema fixtures, configuration docs, README и unit/integration coverage. Также изменятся подготовка и выполнение task:add с существующим ParticipantGateway. Существующий task UserGateway выполняет только обогащение по ID и не должен становиться владельцем поиска или оргструктуры. Общий ApiTransport остаётся транспортной границей; новых зависимостей не требуется.

Реализация использует REST1 `user.current`, `user.get`, `department.get`; строгая `strict-rest3` блокирует remote users-команды до credentials/API. Ссылки на официальную документацию, точные параметры и границы подтверждения приведены в design. Создание/редактирование сотрудников, изменение оргструктуры, автоматический name-to-ID mapping в task-командах и публикация релиза в этот change не входят.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/63 — текущий пользователь, namespace users, коллеги и руководитель по оргструктуре, профильные роли и поиск сотрудников по ФИО/email. Milestone: 0.2.0.
- #12 остаётся связанным контекстом UX; этот change не реализует весь объём #12.
