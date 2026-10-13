## MODIFIED Requirements

### Requirement: Policy covers the complete route plan

Runtime SHALL заранее проверять все потенциальные маршруты операции, включая preflight, по паре method/API version и разрешённым полям. task-v3 SHALL допускать выбранные REST3, IM companion, 14 согласованных REST1 команд и выбранное обогащение участников task:show и REST1 disk.folder.uploadFile только в локальном режиме task:file:attach; strict-rest3 SHALL отклонять любой non-v3 план до первого запроса. Совпадение имени метода не SHALL означать взаимозаменяемость версий или скрытый fallback.

#### Scenario: Strict policy with participant enrichment
- **WHEN** task:show с auditors/accomplices выбран при strict-rest3
- **THEN** возвращается policy-denied с кодом 4 до credentials/API calls; task:show без этих relations сохраняет маршрут только REST3

#### Scenario: Strict policy before a mixed route plan
- **WHEN** task:chat:update, task:time:update или task:participants:set выбраны при strict-rest3
- **THEN** возвращается policy-denied с кодом 4 до любого preflight или записи, даже если первый плановый маршрут использует REST3

#### Scenario: Version-specific task update
- **WHEN** выполняется task:update либо task:participants:set в task-v3 режиме
- **THEN** первая операция обращается к REST3 update writable полей, вторая к REST1 update только указанных accomplice/auditor наборов; каждая отражает свою версию API

#### Scenario: Local attachment mixed route plan
- **WHEN** task:file:attach выбрана с --file/--folder-id
- **THEN** task-v3 допускает только disk.folder.uploadFile REST1 и tasks.task.file.attach REST3; strict-rest3 возвращает policy-denied/exit4 до credentials/API, включая dry-run; режим --file-id сохраняет только REST3
