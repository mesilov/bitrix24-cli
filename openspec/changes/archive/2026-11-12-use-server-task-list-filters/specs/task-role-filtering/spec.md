## MODIFIED Requirements

### Requirement: Bounded and honest role verification

Ролевые списки SHALL сохранять limit/all/max-scan, scope видимых задач, scanned/matched/returned, limitApplied и существующие форматы. `--all` MUST NOT снимать scan budget. Непроверенная роль MUST NOT считаться отсутствующей. Неполный обход или ошибка чтения состава роли SHALL возвращать только подтверждённые совпадения с complete=false, причиной и exit 3; отмена SHALL оставаться exit 130.

#### Scenario: Match after the first page and output limit
- **WHEN** ролевое совпадение есть после первой страницы и выбран `--limit 1`
- **THEN** проверяются доступные страницы в scan budget; output limit применяется после фильтрации, matched/returned и limitApplied различаются

#### Scenario: Missing scalar role field
- **WHEN** REST3 строка для responsible/creator не содержит корректный `responsibleId`/`creatorId`
- **THEN** эта задача не добавляется к совпадениям, результат помечается incomplete/exit 3, отсутствующее поле не трактуется как несовпадение

#### Scenario: Scalar role filtered by the server
- **WHEN** `--role responsible` или `--role creator` выбран при task-v3 и маршрут REST1 доступен для выбранных полей
- **THEN** ID пользователя выбранного подключения передаётся в RESPONSIBLE_ID или CREATED_BY; scan budget считает только строки серверной выборки; строка с другим или некорректным значением роли не добавляется к совпадениям и помечает результат incomplete/exit 3

#### Scenario: Participant role with server group filter
- **WHEN** `--role accomplice` или `--role auditor` сочетается с `--group-id` при task-v3
- **THEN** группа фильтруется на сервере REST1 списком, а участники проверяются прежним чтением только для кандидатов этой группы

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
