# task-mvp-scope Specification

## Purpose

Определить согласованную границу первого релиза блока задач b24cli: исключённые пользовательские операции, условия допуска оставшихся команд и отделение MVP от общих требований task-workflows. Решение пользователя от 2026-10-09 уточняет планирование, не подтверждает реализацию команд.

## Requirements

### Requirement: Explicit exclusions from the task MVP
CLI MVP SHALL исключать 29 команд зависимостей, рабочих представлений, контекста/препятствий, изменений/клиентских договорённостей, результатов задач, жизненного цикла и отдельную task:subtask:add, перечисленных в сценарии. Каталог SHALL сохранять их в области после MVP. Отсутствие требований к маппингу CLI/REST 1:1 не SHALL разрешать вернуть эти операции в MVP под другим именем или как неявный шаг.

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
task:subtask:add
```

#### Scenario: Explicit parent field without a dedicated subtask command
- **WHEN** пользователь явно задаёт parentId через advanced writable fields обычной task:add
- **THEN** отдельная task:subtask:add не регистрируется и не вызывается; task:add сохраняет явное поле, проверяет его допустимость и не выбирает parent неявно. Рабочее предположение касается удаления отдельной команды и уточняется при запросе полного запрета подзадач

### Requirement: API admission and future lifecycle acceptance remain explicit
Каталог SHALL отделять release scope от API support: 59 операций = 30 кандидатов MVP, 0 API-policy-pending и 29 после MVP. Права SHALL проверяться для кандидатов; lifecycle SHALL оставаться после MVP независимо от возможности записи status. Наличие команды в каталоге не SHALL означать принятие иных API exceptions, доказанную семантику или runtime readiness.

#### Scenario: Legacy admission is limited to selected commands
- **WHEN** вызывается legacy маршрут вне согласованных команд/companion reads/записи TAGS либо общий tasks.task.update используется для действий за пределами participants:set и записи TAGS в task:add/task:update
- **THEN** допуск выбранных REST1 команд и чтений не разрешает такую операцию; policy отклоняет запрос и не выполняет скрытый fallback или исключённый lifecycle переход

#### Scenario: Lifecycle commands after MVP
- **WHEN** формируется MVP или пользователь передаёт смену status через task:update --fields/--fields-file
- **THEN** все семь lifecycle команд отсутствуют в MVP; raw смена status отклоняется до записи и не предоставляется через другое имя или неявный шаг; чтение status и обычное изменение полей сохраняются

#### Scenario: Future lifecycle admission preserves required result
- **WHEN** после MVP проектируется допуск lifecycle команд
- **THEN** до допуска проверяются needsControl, requireResult, права и side effects; при отсутствующем или непроверенном допустимом native result task:complete не выполняет переход, не создаёт result неявно, не подменяет его файлом/чатом и не отключает requireResult; сокращение scope не считается acceptance

#### Scenario: Explicit user enrichment of selected task participants
- **WHEN** task:show получает select auditors/accomplices в default task-v3 режиме
- **THEN** допустимы только REST3 карточка без этих relations, REST1 tasks.task.get для ID выбранных ролей и user.get для нужных полей выбранных пользователей; версии отражаются в meta, strict-rest3 отклоняет план до credentials/API, недоступные данные дают partial вместо пустого подтверждённого списка

#### Scenario: Tag writes have an explicit narrow legacy admission
- **WHEN** task:add/task:update в task-v3 policy получает явные теги
- **THEN** CLI допускает REST1 tasks.task.update только с TAGS; status, userFields и произвольные legacy поля не допускаются, число task-команд не увеличивается

#### Scenario: Strict policy rejects tag composition
- **WHEN** команда с tag/clear-tags вызвана в strict-rest3
- **THEN** весь план отклоняется до credentials и первого запроса с policy-denied/exit4; CLI не создаёт задачу без тегов и не переключает API policy

#### Scenario: Scoped Disk upload admission
- **WHEN** пользователь прикрепляет локальный файл через task:file:attach --file PATH --folder-id ID
- **THEN** допускается только disk.folder.uploadFile REST1 с последующим attach REST3; каталог сохраняет 34 task команд, общий Disk API/произвольные методы не открываются, strict-rest3 запрещает mixed plan до credentials/API

### Requirement: Initial scoped REST1 task admission
Через REST1 SHALL быть явно допущены 4 time, 8 checklist/root/item, 2 participants/history команды и выбранные companion reads участников task:show.

#### Scenario: Original legacy task scope
- **WHEN** оценивается первоначальный REST1 admission для task-команд
- **THEN** он ограничен согласованными 4 time, 8 checklist/root/item, 2 participants/history командами и выбранными companion reads task:show.

### Requirement: Milestone tag write admission
Для milestone 0.2.0 дополнительно SHALL допускаться REST1 tasks.task.update только с TAGS в task:add/task:update.

#### Scenario: Narrow legacy tag field admission
- **WHEN** task:add/task:update в task-v3 получает явные теги
- **THEN** допускается REST1 tasks.task.update только с TAGS; это не расширяет остальные API exceptions или lifecycle scope.

### Requirement: Milestone local Disk upload admission
В 0.2.0 дополнительно SHALL допускаться disk.folder.uploadFile REST1 только для явного локального режима task:file:attach.

#### Scenario: Explicit local file upload admission
- **WHEN** пользователь выбирает локальный режим task:file:attach --file PATH --folder-id ID
- **THEN** допускается только согласованный disk.folder.uploadFile REST1; это не означает допуск общего Disk API.

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

### Requirement: Participants and history are included through explicit legacy routes
MVP SHALL включать task:participants:set через REST1 tasks.task.update и task:history:list через REST1 tasks.task.history.list. Допуск SHALL ограничиваться этими действиями, показывать API version 1.0 и сохранять запрет non-v3 в strict-rest3. Participants:set SHALL изменять только наборы соисполнителей/наблюдателей; общий update не SHALL разрешать lifecycle status. Права и TASK_ID SHALL проверяться; история изменений SHALL отличаться от переписки.

#### Scenario: Explicit participant role sets
- **WHEN** пользователь задаёт --accomplice/--auditor IDs или --clear-accomplices/--clear-auditors
- **THEN** CLI меняет полный набор только указанной роли, сохраняет пропущенную роль, требует хотя бы одну явную операцию и отклоняет одновременное clear и IDs одной роли; проверяет ID/права и не меняет status задачи

#### Scenario: Task change history
- **WHEN** пользователь вызывает task:history:list TASK_ID в task-v3 режиме
- **THEN** CLI использует согласованный REST1 history маршрут и возвращает доступную историю изменений выбранной задачи, отдельно от сообщений чата; допустимые params, пагинация и полнота проверяются при реализации и не объявляются подтверждёнными самим допуском API

#### Scenario: Participants and history with strict REST3 policy
- **WHEN** вызывается task:participants:set или task:history:list в strict-rest3 режиме
- **THEN** CLI до запроса возвращает gated-unavailable, объясняет REST1 и не меняет политику или маршрут автоматически

### Requirement: MVP coverage is distinct from product JTBD
Матрица SHALL сохранять 19 согласованных JTBD и отдельно перечислять оставшиеся и исключённые связи команд для MVP. Сокращённый mapping не SHALL выдаваться за полное покрытие сценария; отсутствие mapped команды не SHALL удалять продуктовое требование. Ручные действия через базовые retained команды SHALL отличаться от исключённых сводок и автоматизации.

#### Scenario: Internal acceptance and client workflows after MVP
- **WHEN** оцениваются PM-05, AM-02, AM-03 и AM-05 после исключения lifecycle, risks/report/context/brief и result-команд
- **THEN** MVP mapping этих сценариев пуст; общие JTBD сохраняются для последующего развития и не входят в утверждение о полноте MVP

#### Scenario: Personal task selection through the base list
- **WHEN** сотрудник вызывает `b24cli task:list --responsible 42` в пределах своего доступа
- **THEN** используется оставшийся базовый список с явной фильтрацией и контролем scan completeness; исключённая task:my не запускается и не требуется для этого вызова

### Requirement: Task results admitted after the first release
Расширение 0.2.0 SHALL включать `task:result:add/list/update/delete` через REST3 по capability task-results. Исходные 30 команд и 29 исключений MVP 0.1.0 SHALL сохраняться как историческая граница первого релиза; допуск четырёх результатов SHALL не включать result:from-message, шаблоны, lifecycle или события приёмки.

#### Scenario: Task result commands in the development catalog
- **WHEN** формируется каталог после реализации #61
- **THEN** доступны 34 task-команды, включая четыре result-команды, и ни одна из них не выполняет скрытый lifecycle переход или результат из сообщения

## Решение о приёмке RC 0.1.0 — 12.10.2026

По явному решению пользователя непроверенные части portal tasks 5.2–5.4 и связанного `define-task-jtbd` task 2.3 перенесены в milestone **0.2.0**, [#42](https://github.com/mesilov/bitrix24-cli/issues/42), change `complete-task-portal-acceptance` (`openspec/changes/complete-task-portal-acceptance/`). Они исключены из обязательной приёмки RC 0.1.0; перенос не является новым runtime proof. Уже выполненные 30 собственных happy-path и исходные records сохраняются.

Границы RC: denied-role matrix не подтверждена; history/elapsedTime completeness частична; file attachment ACK без независимого membership; reassignment только на текущего пользователя; native REST3 filled auditors limitation сохраняется для task:list, task:show использует заранее выбранную композицию чтений без error-triggered fallback/retry. Binding/policy/partial/outcomeUnknown/cancellation и 29 post-MVP исключений сохраняются. #19/#23/native packaging остаются критериями RC. [Полный список ограничений](../../../docs/rc-0.1.0.md).
