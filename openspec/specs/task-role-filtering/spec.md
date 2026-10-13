# task-role-filtering Specification

## Purpose

Дать пользователю предсказуемую выборку и поиск доступных задач по своей роли в выбранном подключении, сохраняя границы доступа и проверяемую полноту результата.

## Requirements

### Requirement: Four personal task role filters

`task:list` и `task:find` SHALL принимать `--role responsible|accomplice|creator|auditor`. Роль SHALL означать соответственно исполнителя, соисполнителя, постановщика или наблюдателя. Отсутствие флага SHALL сохранять текущий набор критериев. Неизвестное, пустое или составное значение SHALL давать usage-error/exit 2 до credentials и API.

#### Scenario: Doing tasks
- **WHEN** пользователь выбирает `--role responsible`
- **THEN** включаются только задачи, где `responsibleId` равен ID пользователя выбранного подключения; статус задачи сам по себе не ограничивается

#### Scenario: Helping with tasks
- **WHEN** пользователь выбирает `--role accomplice`
- **THEN** включаются только задачи, где пользователь выбранного подключения входит в состав `accomplices`

#### Scenario: Created tasks
- **WHEN** пользователь выбирает `--role creator`
- **THEN** включаются только задачи, где `creatorId` равен ID пользователя выбранного подключения; задача, поставленная себе, тоже включается

#### Scenario: Watching tasks
- **WHEN** пользователь выбирает `--role auditor`
- **THEN** включаются только задачи, где пользователь выбранного подключения входит в состав `auditors`

#### Scenario: Overlapping roles
- **WHEN** одна задача имеет одного пользователя как постановщика, исполнителя и наблюдателя
- **THEN** задача может присутствовать в каждой соответствующей выборке; совпадение одной роли не исключает остальные

#### Scenario: Invalid role and omitted role
- **WHEN** `--role` содержит неизвестное, пустое или составное значение, либо отсутствует
- **THEN** неверное значение даёт usage-error/exit 2 без credentials/API; отсутствие не добавляет роль, чтение её состава или определение текущего пользователя

### Requirement: Role identity follows the selected connection

Роль SHALL проверяться для владельца выбранного входящего вебхука. CLI MUST соблюдать действующий выбор profile/env-file/process env и не подменять ID через `default.task.creatorId`, `default.task.responsibleId` или другой профиль. ID SHALL быть положительным целым без переполнения. Ошибка выбранного подключения SHALL завершать операцию без подмены пользователя или раскрытия credentials.

#### Scenario: Explicit profile overrides ambient connection
- **WHEN** выбранный profile содержит вебхук пользователя 7, process env содержит вебхук пользователя 2, а `default.task.responsibleId` равен 9
- **THEN** роль проверяется для 7; URL вебхука и token не попадают в результат/диагностику

#### Scenario: Explicit environment file
- **WHEN** пользователь выбирает `--env-file` с вебхуком пользователя 8 при сохранённом default profile другого пользователя
- **THEN** роль проверяется для 8, без чтения defaults другого профиля и без нового запроса `user.current`

#### Scenario: Invalid or unavailable identity
- **WHEN** выбранный вебхук отсутствует, повреждён или содержит ID вне диапазона положительного integer
- **THEN** CLI возвращает ошибку подключения/конфигурации без API-запроса и не считает роль пустой выборкой

### Requirement: Role filters compose with existing query criteria

Роль SHALL сочетаться с допустимыми критериями текущей команды через AND. `task:find` SHALL сохранять буквальный Unicode title поиск и groupId; `task:list` SHALL сохранять id, responsibleId, groupId, status, due-before и where. Роль SHALL оставаться отдельным критерием, не заменяя явные условия или меняя действующие конфликты обычных фильтров и where.

#### Scenario: Role and list criteria
- **WHEN** вызван `task:list --role auditor --group-id 42 --status in_progress`
- **THEN** выводятся только доступные задачи группы 42 со статусом in_progress, где текущий пользователь подтверждён как наблюдатель

#### Scenario: Role and advanced filter
- **WHEN** вызван `task:list --role creator --where '{"responsibleId":9}'`
- **THEN** возвращаются только задачи, созданные текущим пользователем и назначенные 9; where не заменяет роль

#### Scenario: Contradictory criteria
- **WHEN** выбран `--role responsible` и явный `--responsible` другого пользователя
- **THEN** критерии пересекаются через AND и дают полный пустой результат после полного разрешённого обхода

#### Scenario: Role and title search
- **WHEN** вызван `task:find --title "ДОГОВОР" --group-id 42 --role accomplice`
- **THEN** возвращаются только id/title задач с буквальным совпадением title без регистра Unicode, groupId 42 и подтверждённым текущим соисполнителем; поиск не добавляет остальные флаги из другого незавершённого change

### Requirement: Bounded and honest role verification

Ролевые списки SHALL сохранять limit/all/max-scan, scope видимых задач, scanned/matched/returned, limitApplied и существующие форматы. `--all` MUST NOT снимать scan budget. Непроверенная роль MUST NOT считаться отсутствующей. Неполный обход или ошибка чтения состава роли SHALL возвращать только подтверждённые совпадения с complete=false, причиной и exit 3; отмена SHALL оставаться exit 130.

#### Scenario: Match after the first page and output limit
- **WHEN** ролевое совпадение есть после первой страницы и выбран `--limit 1`
- **THEN** проверяются доступные страницы в scan budget; output limit применяется после фильтрации, matched/returned и limitApplied различаются

#### Scenario: Missing scalar role field
- **WHEN** REST3 строка для responsible/creator не содержит корректный `responsibleId`/`creatorId`
- **THEN** эта задача не добавляется к совпадениям, результат помечается incomplete/exit 3, отсутствующее поле не трактуется как несовпадение

#### Scenario: Unknown participant membership
- **WHEN** чтение ID участников проверяемой задачи не удалось, вернуло чужую задачу или повреждённый список IDs
- **THEN** обход останавливается с reason=role-read-error и exit 3, уже подтверждённые совпадения сохраняются; ошибка не превращается в empty role и не запускает повтор или смену маршрута

#### Scenario: Confirmed empty participant role
- **WHEN** успешно прочитан пустой состав выбранной роли всех проверенных задач и весь обход завершён
- **THEN** возвращаются data.items=[], complete=true и exit 0

#### Scenario: Scan budget and cancellation
- **WHEN** достигнут max-scan до окончания списка либо поступила отмена между task list и participant чтением
- **THEN** cap даёт partial/exit 3 и scan-budget-exhausted; отмена даёт exit 130 без следующих запросов, предел companion reads не превышает числа уникальных проверенных кандидатов

#### Scenario: Visible scope and result formats
- **WHEN** ролевой список или поиск выполнен в human/plain/json режиме
- **THEN** task:list сохраняет текущие колонки и поля, task:find сохраняет id/title; JSON всегда содержит data.items array и meta.roleFilter с role/userId; полнота относится только к доступной выборке, а не ко всем задачам портала

### Requirement: Offline role help and separate acceptance evidence

Help/completion SHALL перечислять четыре значения роли с русскими назначениями без credentials/API. Unit evidence MUST NOT считаться доказательством portal поведения. Portal cases SHALL проверяться отдельно для `task:list` и `task:find` на собственных fixtures с явными предпосылками и cleanup; skip SHALL фиксироваться как отсутствие live evidence.

#### Scenario: Role help without credentials
- **WHEN** пользователь вызывает help или completion для task:list/task:find без настроенного подключения
- **THEN** справка доступна, содержит четыре роли, их соответствие группам и ограничения API policy, не читает credentials и не вызывает API

#### Scenario: Portal fixtures for both commands
- **WHEN** выполняются новые portal cases существующих ListTaskCommandTest и FindTaskCommandTest
- **THEN** каждый класс вызывает только свою CLI-команду, проверяет включение подходящей и исключение неподходящей fixture; SDK выполняет setup/readback/cleanup, отсутствующие scopes/второй пользователь явно ограничивают evidence
