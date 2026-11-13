# Spec Delta

## ADDED Requirements

### Requirement: Explicit task unread list interface
CLI SHALL предоставлять boolean-флаг `task:list --unread` и сочетать его с существующими критериями через AND. Флаг SHALL включать также чаты задач без звука и исключать состояние только ручной метки. Help SHALL объяснять серверную семантику, отсутствие автоматического ограничения статуса/роли и отличие от бейджа или числа упоминаний.

#### Scenario: Unread combines with existing task criteria
- **WHEN** вызвана `task:list --unread --group-id 7 --status pending --role responsible --limit 5 --json`
- **THEN** все критерии проверяются до лимита вывода, а counter относится к пользователю выбранного подключения
- **AND** JSON сохраняет data.items и метаданные существующих фильтров и полноты

#### Scenario: Advanced equality and temporal modes
- **WHEN** --unread сочетается с допустимыми --where/--where-file либо --deadline-window/--overdue
- **THEN** сохраняются существующие разрешённые комбинации и конфликты; unread применяется дополнительно
- **AND** chatState не становится writable task API field, допустимым --where или полем серверного REST3 filter

#### Scenario: Plain list without the flag
- **WHEN** task:list выполняется без --unread
- **THEN** существующие маршруты, список полей и поведение сохраняются; новый снимок IM-счётчиков и chatState не добавляются

#### Scenario: Scope remains limited to task list
- **WHEN** --unread передаётся в task:find либо task:my
- **THEN** CLI возвращает usage-error/exit2 без API; наличие общего парсинга критериев не расширяет набор флагов этих команд

### Requirement: Distinct read state presentation
JSON SHALL добавлять `chatState.counter` и `chatState.manuallyMarkedUnread` только в режиме task:list --unread, а `unread` — в сообщения task:chat:list. Human SHALL различать непрочитанное, прочитанное и неизвестное состояние. Существующие JSON-поля, schemaVersion 1 и TSV-колонки обеих команд SHALL сохраняться.

#### Scenario: Structured unread task state
- **WHEN** задача включена в --unread со счётчиком 3 и без ручной метки
- **THEN** JSON содержит `chatState.counter=3`, `chatState.manuallyMarkedUnread=false` и проверенный `chatState.chatId`
- **AND** human показывает число непрочитанных и отдельную ручную метку без обещания совпадения с UI-бейджем

#### Scenario: Message state in machine and human output
- **WHEN** task:chat:list получает unread=true, false и неизвестное значение
- **THEN** JSON содержит соответственно true, false и null; human различает эти три состояния
- **AND** null не оформляется как прочитанное сообщение

#### Scenario: Existing plain columns
- **WHEN** используются --plain у task:list --unread либо task:chat:list
- **THEN** TSV сохраняет соответственно id/title/responsibleId/groupId/status/deadline и id/createdDate/authorId/text/kind/callId
- **AND** help указывает --json для извлечения новых полей; диагностика partial идёт в stderr

#### Scenario: Offline help and completion
- **WHEN** запрошены help/completion для task:list или task:chat:list без подключения
- **THEN** справка объясняет новые состояния и корректные примеры без credentials, сетевых запросов или выполнения примеров
