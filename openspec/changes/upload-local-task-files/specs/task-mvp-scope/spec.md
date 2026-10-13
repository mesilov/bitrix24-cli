## MODIFIED Requirements

### Requirement: API admission and future lifecycle acceptance remain explicit
Каталог SHALL отделять release scope от API support: 59 операций = 30 кандидатов MVP, 0 API-policy-pending и 29 после MVP. Через REST1 явно допущены 4 time, 8 checklist/root/item, 2 participants/history команды и выбранные companion reads участников task:show. В 0.2.0 дополнительно SHALL допускаться disk.folder.uploadFile REST1 только для явного локального режима task:file:attach. Права SHALL проверяться для кандидатов; lifecycle SHALL оставаться после MVP независимо от возможности записи status. Наличие команды в каталоге не SHALL означать принятие иных API exceptions, доказанную семантику или runtime readiness.

#### Scenario: Legacy admission is limited to selected commands
- **WHEN** вызывается legacy маршрут вне согласованных команд/companion reads и явного локального upload режима task:file:attach либо общий tasks.task.update используется для действий за пределами participants:set
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

#### Scenario: Scoped Disk upload admission
- **WHEN** пользователь прикрепляет локальный файл через task:file:attach --file PATH --folder-id ID
- **THEN** допускается только disk.folder.uploadFile REST1 с последующим attach REST3; каталог сохраняет 30 task команд, общий Disk API/произвольные методы не открываются, strict-rest3 запрещает mixed plan до credentials/API
