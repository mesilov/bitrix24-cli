# Spec Delta

## MODIFIED Requirements

### Requirement: Explicit file and structured input
CLI SHALL различать литеральный текст, JSON в --fields и файл JSON в --fields-file. Текстовые и JSON файловые флаги SHALL принимать PATH или '-' для stdin. CLI SHALL проверять формат и доступность ввода до записи.

#### Scenario: Description from a file or pipe
- **WHEN** пользователь задаёт `--description-file brief.md` либо передаёт текст в pipe и задаёт `--description-file -`
- **THEN** CLI использует содержимое выбранного источника как описание; одновременный `--description` отклоняется

#### Scenario: Advanced update input
- **WHEN** пользователь передаёт `--fields '{"title":"Отчёт"}'`, `--fields-file changes.json` либо `--fields-file -` с JSON в stdin
- **THEN** CLI принимает один JSON object; неизвестные/read-only поля, конфликтующие источники и gated status changes отклоняются до записи

#### Scenario: Terminal is not a file payload
- **WHEN** файловый флаг содержит `-`, но stdin является терминалом, либо два файловых флага требуют stdin
- **THEN** CLI немедленно завершает вызов с ошибкой использования и объясняет способ передать файл или pipe

#### Scenario: Local binary attachment input
- **WHEN** task:file:attach получает --file PATH либо --file -
- **THEN** обычный локальный файл проверяется по task-file-upload; `-` отклоняется с usage-error/exit2 без чтения stdin/credentials/API, текстовые и JSON файловые флаги сохраняют собственный stdin режим

## ADDED Requirements

### Requirement: Binary attachment input restrictions
Бинарный task:file:attach --file SHALL принимать только локальный PATH с ограничениями task-file-upload, без stdin '-'.

#### Scenario: Reject stdin as a binary attachment
- **WHEN** task:file:attach получает --file -
- **THEN** CLI отклоняет бинарный stdin по task-file-upload; текстовые и JSON файловые флаги сохраняют свой stdin режим.

### Requirement: Advanced field input is a separate mode
--fields и --fields-file SHALL быть отдельным advanced режимом без смешивания с флагами редактирования полей.

#### Scenario: Advanced input cannot mix edit flags
- **WHEN** пользователь передаёт advanced JSON вместе с флагами редактирования полей
- **THEN** CLI отклоняет смешанный режим до записи; advanced input принимает один JSON object по существующему контракту.

### Requirement: Explicit stdin ownership
CLI SHALL не читать stdin неявно и не разрешать два потребителя stdin. '-' для текстового или JSON файлового ввода SHALL требовать redirected stdin, а не терминал.

#### Scenario: A single explicit stdin consumer
- **WHEN** файловый флаг содержит '-' при terminal stdin либо два файловых флага требуют stdin
- **THEN** CLI немедленно завершает вызов с usage error и объясняет явный файл или pipe; без '-' stdin не читается неявно.
