# task-mvp-scope Specification

## Purpose

Определить согласованную границу первого релиза блока задач b24cli: исключённые пользовательские операции, условия допуска оставшихся команд и отделение MVP от общих требований task-workflows. Решение пользователя от 2026-10-09 уточняет планирование, не подтверждает реализацию команд.

## Requirements

### Requirement: Explicit exclusions from the task MVP
CLI MVP SHALL исключать 21 команду зависимостей, рабочих представлений, контекста/препятствий, изменений/клиентских договорённостей и результатов задач, перечисленных в сценарии. Каталог SHALL сохранять их в области после MVP. Отсутствие требований к маппингу CLI/REST 1:1 не SHALL разрешать вернуть эти операции в MVP под другим именем или как неявный шаг.

#### Scenario: Excluded command groups
- **WHEN** формируются список команд, справка и критерии приёмки MVP
- **THEN** следующие команды относятся к развитию после MVP и не предоставляются в CLI первого релиза:

```text
task:dependency:list
task:dependency:add
task:dependency:delete
task:my
task:plan
task:brief
task:risks
task:report
task:context:set
task:blocker:raise
task:blocker:resolve
task:change:propose
task:change:accept
task:delivery:record
task:feedback:record
task:acceptance:record
task:result:add
task:result:list
task:result:update
task:result:delete
task:result:from-message
```

### Requirement: API and lifecycle admission remains explicit
Каталог SHALL отделять release scope от API support: после 21 исключения остаются 38 команд в рассмотрении, 28 кандидатов MVP, включая четыре согласованные legacy time команды, и 10 команд с неутверждённым допуском прежнего API. Lifecycle gates и права SHALL сохраняться для кандидатов. Наличие команды в остатке не SHALL означать принятие API exception, доказанную семантику или runtime readiness.

#### Scenario: Remaining legacy extension
- **WHEN** команда чек-листа, участников или истории использует прежний API и решение о допуске ещё не принято
- **THEN** она остаётся в разделе API-policy-pending и не считается утверждённой частью MVP; восемь операций корней/пунктов чек-листов имеют раздельные имена, но согласование vocabulary не разрешает legacy запросы или скрытый fallback

#### Scenario: Remaining lifecycle candidate
- **WHEN** task:complete, task:approve или другая lifecycle команда сохраняется кандидатом MVP
- **THEN** до её допуска проверяются needsControl, requireResult, права и side effects; сокращение MVP не считается такой проверкой

#### Scenario: Required result with result commands outside MVP
- **WHEN** завершается задача с requireResult и native result отсутствует либо его допустимость не подтверждена
- **THEN** task:complete не выполняет переход, объясняет требование результата и не создаёт result неявно, не подменяет его файлом/чатом и не отключает requireResult; существующий native result допускается только по проверенным lifecycle условиям

### Requirement: Time entries are included through explicit legacy routes
MVP SHALL включать task:time:add/list/update/delete через явно согласованные REST 1.0 task.elapseditem.add/getlist/update/delete. Этот допуск SHALL ограничиваться четырьмя маршрутами, отражать API version в meta и сохранять запрет non-v3 запросов в strict-rest3. Update/delete SHALL проверять связь TASK_ID/ENTRY_ID и права подключения. Task:time:show SHALL сохранять свой REST3 маршрут.

#### Scenario: Time entry operations in the default task policy
- **WHEN** пользователь создаёт, читает, исправляет или удаляет записи времени в task-v3 режиме
- **THEN** CLI использует согласованный task.elapseditem маршрут, показывает API version 1.0 и не выполняет скрытый fallback; полный список и действия над своей записью проверяются при реализации EMP-06

#### Scenario: Time entries with strict REST3 policy
- **WHEN** пользователь вызывает task:time:add/list/update/delete в strict-rest3 режиме
- **THEN** CLI до запроса возвращает gated-unavailable с объяснением прежнего API; он не переключает политику и не выдаёт elapsedTime за историю записей

#### Scenario: Modification of a time entry
- **WHEN** пользователь изменяет или удаляет ENTRY_ID в указанной TASK_ID
- **THEN** CLI проверяет принадлежность записи задаче и права действующего подключения; --force для delete отменяет только подтверждение, а неверная задача или отсутствие прав не допускают запись

### Requirement: MVP coverage is distinct from product JTBD
Матрица SHALL сохранять 19 согласованных JTBD и отдельно перечислять оставшиеся и исключённые связи команд для MVP. Сокращённый mapping не SHALL выдаваться за полное покрытие сценария; отсутствие mapped команды не SHALL удалять продуктовое требование. Ручные действия через базовые retained команды SHALL отличаться от исключённых сводок и автоматизации.

#### Scenario: Client promises and status reporting after MVP
- **WHEN** оцениваются AM-02, AM-03 и AM-05 после исключения risks/report/context/brief и result-команд
- **THEN** MVP mapping этих сценариев пуст; общие JTBD сохраняются для последующего развития и не входят в утверждение о полноте MVP

#### Scenario: Personal task selection through the base list
- **WHEN** сотрудник вызывает `b24cli task:list --responsible 42` в пределах своего доступа
- **THEN** используется оставшийся базовый список с явной фильтрацией и контролем scan completeness; исключённая task:my не запускается и не требуется для этого вызова
