## ADDED Requirements

### Requirement: Task chat send returns the verified message identity
`task:chat:send` SHALL до записи доказать привязку чата к TASK_ID и запомнить ID последнего сообщения чата. После единственной отправки CLI SHALL с конечным budget читать сообщения новее этого ID и считать отправленным только единственное сообщение пользователя подключения с отправленным текстом. `messageId`/`resourceId` SHALL заполняться только при таком совпадении; неудача сопоставления не SHALL повторять отправку.

#### Scenario: Single matching message after the send
- **WHEN** после подтверждённой отправки в окне новее запомненного ID ровно одно сообщение пользователя подключения с отправленным текстом
- **THEN** CLI возвращает exit 0, `operation.readback = verified`, `operation.messageId` и `operation.resourceId` с ID этого сообщения, `operation.chatId` и `operation.dialogId = chat<chatId>`

#### Scenario: Older or foreign messages are not taken as the sent one
- **WHEN** в чате есть более старое сообщение того же пользователя с тем же текстом либо новое сообщение другого автора с тем же текстом
- **THEN** они не считаются кандидатами: старое лежит до запомненного ID, чужое отсекается автором

#### Scenario: Ambiguous or missing match
- **WHEN** после подтверждённой отправки найдено несколько кандидатов, ни одного, чтение сообщений завершилось ошибкой или budget исчерпан
- **THEN** CLI возвращает exit 3, `meta.complete = false`, `meta.reason = readback-unverified`, `operation.confirmed = true`, `operation.messageId = null`, статус и ID кандидатов в `data.readback`; `outcomeUnknown` не выставляется и отправка не повторяется

#### Scenario: Unverified task chat binding before the send
- **WHEN** карточка задачи не подтверждает чат с `entityType = TASKS_TASK` и `entityId = TASK_ID`
- **THEN** CLI возвращает `message-binding-unverified` с кодом 4 и не вызывает `tasks.task.chat.message.send`

#### Scenario: Dry-run of the task chat send
- **WHEN** пользователь запускает `task:chat:send --dry-run`
- **THEN** CLI выполняет только проверку привязки чата, не вызывает `tasks.task.chat.message.send` и показывает в плане запись и проверочное чтение сообщений

### Requirement: Task chat update returns the message identity
`task:chat:update` SHALL возвращать в `operation` ID чата задачи и ID изменённого сообщения.

#### Scenario: Updated message identity
- **WHEN** `task:chat:update` подтверждённо изменил сообщение
- **THEN** `operation.resourceId` и `operation.messageId` равны MESSAGE_ID, `operation.chatId` — ID чата задачи

## MODIFIED Requirements

### Requirement: Unit acceptance and separate portal evidence
Runtime SHALL позволять проверять регистрацию, парсинг, вывод, ошибки, gates и планы с подставными API-ответами без портала. Успех этих проверок не SHALL считаться доказательством прав, схемы или поведения конкретного Bitrix24 подключения; portal acceptance SHALL фиксироваться отдельно с версией, ролью и результатом проверки.

#### Scenario: Passing command contract tests
- **WHEN** проверки команд и API планов прошли на подставных ответах
- **THEN** отчёт подтверждает локальный контракт и отдельно перечисляет ещё не выполненные portal cases, не объявляя команды принятыми на портале

#### Scenario: Portal internal server error without API fallback
- **WHEN** SDK оборачивает API INTERNAL_SERVER_ERROR в TransportException
- **THEN** CLI возвращает api-error и безопасный details.apiErrorCode, не повторяет запрос и не меняет API version; для отправленной записи сохраняется outcomeUnknown

#### Scenario: Confirmed chat send without message ID
- **WHEN** task:chat:send получает boolean подтверждение отправки без ID
- **THEN** ID сообщения определяется только проверенным readback по требованию «Task chat send returns the verified message identity»; без единственного совпадения возвращается exit 3 с resourceId=null, outcomeUnknown не выставляется и отправка не повторяется

#### Scenario: Time relation without an aggregate guarantee
- **WHEN** task:time:show читает elapsedTime из карточки
- **THEN** результат обозначает scope связанного поля и не объявляет его полной историей или total seconds без подтверждённого контракта и полноты
