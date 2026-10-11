# cli-experience delta

## MODIFIED Requirements

### Requirement: Convenient and explicit task field updates
CLI SHALL предоставлять `--title`, `--creator`, `--responsible`, `--group-id`, `--deadline`, `--description` и `--description-file` для создания и применимые флаги для изменения. `task:update` SHALL принимать хотя бы одно явное изменение и отправлять только заданные поля. `task:assign` SHALL изменять только responsibleId; остальные флаги SHALL отображаться на документированные writable REST3 поля.

#### Scenario: Basic task creation
- **WHEN** пользователь задаёт title, creator и responsible флагами команды task:add
- **THEN** CLI формирует title, creatorId и responsibleId; отсутствующие обязательные значения приводят к ошибке до записи, без молчаливого выбора сотрудника

#### Scenario: Several ordinary changes
- **WHEN** пользователь вызывает `b24cli task:update 123 --title "Отчёт" --responsible 84 --description "Добавить сравнение"`
- **THEN** CLI изменяет три заданных поля одним update-запросом и не отправляет значения остальных полей

#### Scenario: Empty and omitted description
- **WHEN** пользователь явно передаёт `--description ""` либо не передаёт описание
- **THEN** первый вызов задаёт пустое описание, а второй сохраняет существующее; пустой заголовок отклоняется

### Requirement: Explicit filtering and completeness of lists
Списки SHALL различать серверный фильтр id и локальные фильтры REST3; --limit ограничивает результат, --all снимает этот предел, --max-scan ограничивает просмотр. CLI SHALL сообщать scope доступных задач, scanned count и полноту. Достигнутый scan cap, ошибка страницы или недостающие данные SHALL означать partial; полнота видимых задач не SHALL выдаваться за полноту проекта.

#### Scenario: Responsible or project filter
- **WHEN** пользователь задаёт `task:list --responsible 42 --group-id 7`
- **THEN** CLI фильтрует соответствующие selected fields локально, проверяет все страницы в установленном scan budget и не отправляет неподдерживаемый v3 filter или скрытый legacy request

#### Scenario: Scan cap reached
- **WHEN** просмотр прекращён из-за --max-scan до исчерпания доступных страниц
- **THEN** CLI возвращает partial с кодом 3, сохраняет полученные данные, указывает scanned count и причину в обычном, plain и JSON режимах

#### Scenario: Empty complete result
- **WHEN** полная разрешённая выборка не содержит совпадений
- **THEN** CLI возвращает 0, пустые данные и complete=true; пустой результат не считается ошибкой

## ADDED Requirements

### Requirement: Consistent group identifier option
The CLI SHALL use --group-id as the only explicit group identifier option in every command that provides one. JSON and REST 3.0 fields and filters MUST remain groupId. The old --project option MUST be rejected with usage-error/exit 2 before API requests, without alias or fallback. Current help, completion and examples MUST present --group-id; migration notes MUST explain the replacement.

#### Scenario: Create or update with groupId
- **WHEN** task:add or task:update receives --group-id 42
- **THEN** its fields contain groupId 42 and existing positive integer validation and REST route semantics are preserved

#### Scenario: Group identifier list filter
- **WHEN** task:list receives --group-id 42
- **THEN** the list uses the existing groupId filter with unchanged selection, scan budget and completeness semantics

#### Scenario: Removed project option
- **WHEN** a command previously supporting --project receives --project alone or with --group-id
- **THEN** it returns usage-error/exit 2 before any API request without processing the old flag as an alias

#### Scenario: Invalid or absent group identifier
- **WHEN** --group-id is empty, nonnumeric, zero or negative, or is absent
- **THEN** invalid supplied values produce usage-error before API; absence preserves existing behavior without injecting a group field or filter

#### Scenario: Structured input compatibility
- **WHEN** advanced --fields or --fields-file contains groupId, or task:list uses a JSON groupId filter
- **THEN** existing validation, payloads and filtering remain unchanged
- **AND** mixing advanced JSON and ordinary field flags remains rejected; task:find keeps its existing title search when --group-id is omitted

### Requirement: Group-scoped title search
The CLI SHALL accept task:find --group-id as a positive integer and combine a local groupId filter with the existing Unicode case-insensitive title substring search. Omitting the option MUST preserve existing behavior. The search MUST retain id/title output, scan budget, limit/all, visible-task scope and partial exit 3; groupId MUST NOT be sent as a REST3 server filter.

#### Scenario: Search only the selected group
- **WHEN** task:find --title "договор" --group-id 42 scans tasks from multiple groups
- **THEN** it returns only matching titles from groupId 42, selecting groupId locally while preserving id/title output

#### Scenario: Search without a group filter
- **WHEN** task:find receives a title without --group-id
- **THEN** it searches all visible tasks within the existing scan budget

#### Scenario: Invalid search group identifier
- **WHEN** task:find receives an empty, nonnumeric, zero or negative --group-id
- **THEN** it returns usage-error/exit 2 before any API request

#### Scenario: Search limits and incomplete scan
- **WHEN** a group-scoped search uses --limit, --all or reaches --max-scan before all pages are scanned
- **THEN** output limits and scan budgets remain independent and an incomplete scan returns partial/exit 3 with scanned count and reason
