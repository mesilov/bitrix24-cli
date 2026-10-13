# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

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
