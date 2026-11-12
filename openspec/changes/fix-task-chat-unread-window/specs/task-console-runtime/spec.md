## ADDED Requirements

### Requirement: Task chat history starts from the latest message
Без `--before`/`--after` обход чата задачи SHALL начинаться с последнего сообщения чата (`LAST_ID = last_message_id + 1` из `im.dialog.get`), а не с окна первого непрочитанного сообщения. Чтение SHALL не менять прочитанность. Явные курсоры SHALL сохранять свою позицию без дополнительного запроса.

#### Scenario: Unread messages in the task chat
- **WHEN** у пользователя подключения есть непрочитанные сообщения в чате задачи и вызван `task:chat:list TASK_ID --limit 3`
- **THEN** CLI возвращает три последних сообщения чата, а не первые непрочитанные; с `--all` возвращаются все сообщения, включая новее окна непрочитанных
- **AND** счётчик непрочитанных после чтения не меняется

#### Scenario: Message binding with unread messages
- **WHEN** `task:chat:update` или `task:chat:delete` проверяет новое сообщение задачи при непрочитанных
- **THEN** принадлежность подтверждается обходом от последнего сообщения, и команда не возвращает ложный `message-binding-unverified`

#### Scenario: Explicit cursor
- **WHEN** задан `--before MESSAGE_ID` или `--after MESSAGE_ID`
- **THEN** обход начинается с заданной позиции, `im.dialog.get` не вызывается
