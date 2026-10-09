# task-mvp-scope Specification

## Purpose

Определить согласованную границу первого релиза блока задач b24cli: исключённые пользовательские операции, условия допуска оставшихся команд и отделение MVP от общих требований task-workflows. Решение пользователя от 2026-10-09 уточняет планирование, не подтверждает реализацию команд.

## Requirements

### Requirement: Explicit exclusions from the task MVP
CLI MVP SHALL исключать 28 команд зависимостей, рабочих представлений, контекста/препятствий, изменений/клиентских договорённостей, результатов задач и жизненного цикла, перечисленных в сценарии. Каталог SHALL сохранять их в области после MVP. Отсутствие требований к маппингу CLI/REST 1:1 не SHALL разрешать вернуть эти операции в MVP под другим именем или как неявный шаг.

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
task:start
task:pause
task:defer
task:renew
task:complete
task:approve
task:disapprove
```

### Requirement: API admission and future lifecycle acceptance remain explicit
Каталог SHALL отделять release scope от API support: 59 операций = 29 кандидатов MVP, 2 API-policy-pending и 28 после MVP. В MVP явно допущены 4 time и 8 checklist/root/item команд через REST1. Права SHALL проверяться для кандидатов; lifecycle SHALL оставаться после MVP независимо от возможности записи status. Наличие команды в каталоге не SHALL означать принятие иных API exceptions, доказанную семантику или runtime readiness.

#### Scenario: Remaining legacy extension
- **WHEN** команда участников или истории использует прежний API и решение о допуске ещё не принято
- **THEN** она остаётся в разделе API-policy-pending и не считается утверждённой частью MVP; допуск time/checklist routes не разрешает остальные legacy запросы или скрытый fallback

#### Scenario: Lifecycle commands after MVP
- **WHEN** формируется MVP или пользователь передаёт смену status через task:update --fields/--fields-file
- **THEN** все семь lifecycle команд отсутствуют в MVP; raw смена status отклоняется до записи и не предоставляется через другое имя или неявный шаг; чтение status и обычное изменение полей сохраняются

#### Scenario: Future lifecycle admission preserves required result
- **WHEN** после MVP проектируется допуск lifecycle команд
- **THEN** до допуска проверяются needsControl, requireResult, права и side effects; при отсутствующем или непроверенном допустимом native result task:complete не выполняет переход, не создаёт result неявно, не подменяет его файлом/чатом и не отключает requireResult; сокращение scope не считается acceptance

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

### Requirement: Checklist roots and items are included through explicit legacy routes
MVP SHALL включать task:checklist:add/list и task:checklist:item:add/list/update/complete/renew/delete через явно согласованные REST 1.0 task.checklistitem.* маршруты и preflight getlist. Допуск SHALL ограничиваться этими восемью командами, показывать API version и сохранять запрет non-v3 в strict-rest3. CLI SHALL различать корни/пункты и проверять TASK_ID, parent и права. Item:complete/renew не SHALL менять lifecycle status задачи.

#### Scenario: Checklist operations in the default task policy
- **WHEN** пользователь вызывает одну из восьми checklist/root/item команд в task-v3 режиме
- **THEN** CLI использует согласованный REST1 маршрут, отражает API version 1.0 и не выполняет скрытый fallback; root:list показывает только корни, item:list — потомков выбранного корня с ID/PARENT_ID

#### Scenario: Checklist hierarchy and mutation checks
- **WHEN** пользователь создаёт корень или добавляет, изменяет, выполняет, возобновляет либо удаляет пункт
- **THEN** root:add задаёт PARENT_ID=0, item:add — явный проверенный parent внутри выбранного чек-листа; IDs принадлежат TASK_ID, root ID не принимается за пункт. Item:update меняет только непустой title; права и подтверждение delete сохраняются. SDK PARENT_ID gap требует явного Core REST1; операции не меняют status задачи

#### Scenario: Checklist operations with strict REST3 policy
- **WHEN** пользователь вызывает любую из восьми checklist/root/item команд в strict-rest3 режиме
- **THEN** CLI до запроса возвращает gated-unavailable с объяснением прежнего API; он не переключает политику и не подменяет действие неподтверждённым v3 маршрутом

### Requirement: MVP coverage is distinct from product JTBD
Матрица SHALL сохранять 19 согласованных JTBD и отдельно перечислять оставшиеся и исключённые связи команд для MVP. Сокращённый mapping не SHALL выдаваться за полное покрытие сценария; отсутствие mapped команды не SHALL удалять продуктовое требование. Ручные действия через базовые retained команды SHALL отличаться от исключённых сводок и автоматизации.

#### Scenario: Internal acceptance and client workflows after MVP
- **WHEN** оцениваются PM-05, AM-02, AM-03 и AM-05 после исключения lifecycle, risks/report/context/brief и result-команд
- **THEN** MVP mapping этих сценариев пуст; общие JTBD сохраняются для последующего развития и не входят в утверждение о полноте MVP

#### Scenario: Personal task selection through the base list
- **WHEN** сотрудник вызывает `b24cli task:list --responsible 42` в пределах своего доступа
- **THEN** используется оставшийся базовый список с явной фильтрацией и контролем scan completeness; исключённая task:my не запускается и не требуется для этого вызова
