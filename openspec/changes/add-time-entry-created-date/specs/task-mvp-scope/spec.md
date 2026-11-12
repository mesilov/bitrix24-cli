## ADDED Requirements

### Requirement: Time entries can be dated explicitly
task:time:add и task:time:update SHALL принимать необязательный флаг --created-date (ISO 8601 с явным смещением) и передавать его без преобразования в ARFIELDS.CREATED_DATE допущенных REST 1.0 task.elapseditem.add/update. Без флага payload SHALL не содержать CREATED_DATE. Даты в прошлом и будущем SHALL допускаться; update SHALL требовать --seconds.

#### Scenario: Backdated time entry
- **WHEN** пользователь вызывает task:time:add TASK_ID --seconds 3600 --created-date 2026-10-22T10:00:00+03:00
- **THEN** CLI вызывает task.elapseditem.add с ARFIELDS SECONDS=3600 и CREATED_DATE=2026-10-22T10:00:00+03:00, а task:time:list показывает эту запись с createdDate этой даты

#### Scenario: Correcting the date of an entry
- **WHEN** пользователь вызывает task:time:update TASK_ID --entry ENTRY_ID --seconds 1800 --created-date 2026-10-21T18:00:00Z для записи этой задачи
- **THEN** CLI после проверки принадлежности записи вызывает task.elapseditem.update с ARFIELDS SECONDS=1800 и CREATED_DATE=2026-10-21T18:00:00Z

#### Scenario: Omitted date keeps the previous payload
- **WHEN** task:time:add или task:time:update вызваны без --created-date
- **THEN** ARFIELDS содержат только SECONDS и, при --text, COMMENT_TEXT; update не меняет прежнюю дату записи, а changedFields не содержит createdDate

#### Scenario: Update without seconds
- **WHEN** task:time:update вызван с --created-date, но без --seconds
- **THEN** CLI возвращает usage error с кодом 2 без API calls, как и прежде

### Requirement: Entry date is validated before API calls
CLI SHALL проверять --created-date до credentials и API calls и возвращать usage error с кодом 2 для значения без смещения, невозможной даты или иного формата. План --dry-run и changedFields SHALL показывать createdDate только при заданном флаге.

#### Scenario: Invalid entry date
- **WHEN** --created-date не содержит смещения часового пояса, содержит невозможную дату или не является ISO 8601 датой со временем
- **THEN** CLI возвращает usage error с кодом 2 без credentials и API calls; портал не получает значение, которое он молча заменил бы текущим моментом

#### Scenario: Dry-run of a dated entry
- **WHEN** пользователь вызывает task:time:add TASK_ID --seconds 600 --created-date 2026-10-22T10:00:00+03:00 --dry-run
- **THEN** план содержит шаг task.elapseditem.add с createdDate=2026-10-22T10:00:00+03:00, и запись не выполняется

### Requirement: Entry date keeps the time entry routes
Флаг --created-date не SHALL менять маршруты, проверку TASK_ID/ENTRY_ID, права и отказ strict-rest3 и не SHALL записывать DATE_START/DATE_STOP.

#### Scenario: Dated entry under strict REST3 policy
- **WHEN** task:time:add или task:time:update с --created-date вызваны в strict-rest3 режиме
- **THEN** CLI до запроса возвращает policy-denied, как и без флага
