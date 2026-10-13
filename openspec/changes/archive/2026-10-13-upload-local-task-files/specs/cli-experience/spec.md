## MODIFIED Requirements

### Requirement: Explicit file and structured input
CLI SHALL различать литеральный текст, JSON в `--fields` и файл JSON в `--fields-file`; текстовые и JSON файловые флаги SHALL принимать PATH или `-` для stdin. Бинарный `task:file:attach --file` SHALL принимать только локальный PATH с ограничениями task-file-upload, без stdin `-`. `--fields` и `--fields-file` SHALL быть отдельным advanced режимом без смешивания с флагами редактирования полей. CLI SHALL проверять формат и доступность ввода до записи, не читать stdin неявно и не разрешать два потребителя stdin.

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
