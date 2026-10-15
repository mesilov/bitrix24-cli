## ADDED Requirements

### Requirement: Explicit task tag input
CLI SHALL принимать повторяемую опцию `--tag NAME` в task:add/task:update и `--clear-tags` в task:update. Указанный набор SHALL заменять теги; отсутствие опций SHALL сохранять их. Пустые/пробельные значения и конфликт tag/clear SHALL отклоняться до credentials/API. Названия SHALL сохранять Unicode, пробелы и # без преобразования.

#### Scenario: Create with multiple tags
- **WHEN** пользователь создаёт задачу с `--tag '#плановая' --tag 'Этап 1'`
- **THEN** CLI планирует создание и запись двух переданных названий без удаления # или изменения регистра

#### Scenario: Replace tags without ordinary fields
- **WHEN** task:update 123 получает только `--tag '#ошибка'`
- **THEN** операция допустима и заменяет набор тегов выбранной задачи без пустого REST3 update

#### Scenario: Clear and preserve are distinct
- **WHEN** task:update 123 получает `--clear-tags`
- **THEN** CLI явно планирует пустой набор тегов
- **AND** обычное изменение title без tag/clear не выполняет запись тегов

#### Scenario: Invalid or conflicting input
- **WHEN** значение tag пустое/пробельное либо tag задан вместе с clear-tags, advanced fields или fields-file
- **THEN** CLI возвращает usage-error/exit2 без чтения credentials и сетевого запроса

### Requirement: Planned task tag routes
CLI SHALL передавать обычные поля в REST3, а теги — отдельным заранее объявленным REST1 update с полем TAGS. Dry-run SHALL показывать версии, порядок, поля и зависимость ID новой задачи, не выполняя записи. Strict-rest3 SHALL отклонять весь план с тегами до credentials/API; отказ REST3 не SHALL запускать fallback.

#### Scenario: Create preview with a dependent tag write
- **WHEN** task:add с тегами выполняется с dry-run/json
- **THEN** план содержит REST3 tasks.task.add с обычными полями, затем REST1 tasks.task.update с `TAGS` и ссылкой на ID результата первого шага; оба шага не выполнены

#### Scenario: Mixed update and strict policy
- **WHEN** task:update получает обычные поля и теги
- **THEN** default policy планирует REST3 update выбранной задачи и REST1 update только TAGS той же задачи
- **AND** strict-rest3 возвращает policy-denied/exit4 до любых чтений/записей; вызов без тегов сохраняет REST3 маршрут

#### Scenario: Advanced tags remain read-only
- **WHEN** пользователь передаёт tags или TAGS через fields/fields-file
- **THEN** CLI отклоняет advanced-запись этих полей и предлагает явную опцию tag; общий editable gate не отключается

### Requirement: Account for partial task tag mutations
CLI SHALL учитывать подтверждённые, неуспешные и неизвестные шаги записи тегов. После подтверждённого первого шага и отказа второго SHALL возвращаться partial/exit3 с TASK_ID и выполненными изменениями; Ctrl-C SHALL сохранять exit130. Потеря ответа SHALL выставлять outcomeUnknown без повторной записи или автоматического удаления задачи.

#### Scenario: Creation succeeded and tag write failed
- **WHEN** REST3 add вернул ID, а REST1 TAGS update отклонён
- **THEN** результат содержит ID созданной задачи, подтверждённый add, неуспешный шаг тегов, complete=false/exit3 и рекомендацию повторить только task:update с тегами

#### Scenario: Response lost and cancellation
- **WHEN** после подтверждённого add ответ TAGS update потерян либо поступил Ctrl-C
- **THEN** CLI сохраняет известный TASK_ID, outcomeUnknown для отправленного неподтверждённого шага, прекращает запросы и не обещает откат; отмена возвращает 130

#### Scenario: No mutation was confirmed
- **WHEN** единственная tags-only запись отклонена либо её ответ потерян
- **THEN** CLI возвращает исходную ошибку/exit1 и outcomeUnknown только при неизвестном результате; успех или подтверждённый partial не заявляется

### Requirement: Independent tag readback
CLI SHALL поддерживать проверку тегов через существующий task:show с select tags.id/tags.name. Теги SHALL возвращаться как REST3 объекты id/name. ACK записи SHALL означать подтверждение операции, а доказательство сохранённых названий SHALL фиксироваться отдельным чтением в portal acceptance.

#### Scenario: Read saved tags
- **WHEN** после записи пользователь запускает task:show 123 с select tags.id и tags.name
- **THEN** CLI возвращает доступные объекты тегов выбранной задачи без переименования API-полей и без преобразования их в строковый массив записи

#### Scenario: Portal verification is unavailable
- **WHEN** unit/codec проверки успешны, но чтение реального портала не выполнено
- **THEN** отчёт подтверждает локальный контракт и явно оставляет portal acceptance непроверенным
