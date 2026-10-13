# Spec Delta

## MODIFIED Requirements

### Requirement: Explicit exclusions from the task MVP
CLI MVP 0.1.0 SHALL исключать 29 команд зависимостей, рабочих представлений, контекста/препятствий, изменений/клиентских договорённостей, результатов задач, жизненного цикла и отдельную task:subtask:add, перечисленных в сценарии. Каталог SHALL сохранять их в области после MVP. Отсутствие требований к маппингу CLI/REST 1:1 не SHALL разрешать вернуть эти операции в MVP под другим именем или как неявный шаг.

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

#### Scenario: Lifecycle admitted after the first MVP
- **WHEN** формируется каталог 0.2.0 по task-lifecycle
- **THEN** семь task:start/pause/defer/renew/complete/approve/disapprove предоставляются как развитие после MVP; остальные 22 исключения сохраняются, исторический scope 0.1.0 не расширяется

### Requirement: API admission and future lifecycle acceptance remain explicit
Каталог SHALL отделять release scope от API support: 59 операций = 30 кандидатов MVP, 0 API-policy-pending и 29 после MVP. Через REST1 явно допущены 4 time, 8 checklist/root/item, 2 participants/history команды и выбранные companion reads участников task:show. Права SHALL проверяться для кандидатов; lifecycle SHALL оставаться после MVP; в 0.2.0 допуск семи явных команд определяется task-lifecycle, независимо от возможности записи status. Наличие команды в каталоге не SHALL означать принятие иных API exceptions, доказанную семантику или runtime readiness.

#### Scenario: Legacy admission is limited to selected commands
- **WHEN** вызывается legacy маршрут вне согласованного набора (MVP команды/companion reads и семь явных lifecycle маршрутов 0.2.0) либо общий tasks.task.update используется для действий за пределами participants:set
- **THEN** допуск выбранных REST1 команд и чтений не разрешает такую операцию; policy отклоняет запрос и не выполняет скрытый fallback, raw status write или переход через неподтверждённый маршрут

#### Scenario: Lifecycle commands after MVP
- **WHEN** формируется MVP или пользователь передаёт смену status через task:update --fields/--fields-file
- **THEN** все семь lifecycle команд отсутствуют в MVP; raw смена status отклоняется до записи и не предоставляется через другое имя или неявный шаг; чтение status и обычное изменение полей сохраняются

#### Scenario: Future lifecycle admission preserves required result
- **WHEN** после MVP проектируется допуск lifecycle команд
- **THEN** до допуска проверяются needsControl, requireResult, права и side effects; при отсутствующем или непроверенном допустимом native result task:complete не выполняет переход, не создаёт result неявно, не подменяет его файлом/чатом и не отключает requireResult; сокращение scope не считается acceptance

#### Scenario: Explicit user enrichment of selected task participants
- **WHEN** task:show получает select auditors/accomplices в default task-v3 режиме
- **THEN** допустимы только REST3 карточка без этих relations, REST1 tasks.task.get для ID выбранных ролей и user.get для нужных полей выбранных пользователей; версии отражаются в meta, strict-rest3 отклоняет план до credentials/API, недоступные данные дают partial вместо пустого подтверждённого списка

#### Scenario: Required result read is an internal lifecycle preflight
- **WHEN** task:complete проверяет requireResult=true в 0.2.0
- **THEN** допускается только внутреннее ограниченное REST3 tasks.task.result.list для выбранной задачи; result-команды, создание результата и fallback не добавляются, raw task:update status остаётся запрещён
