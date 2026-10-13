## Purpose

Определить загрузку локального файла в явно выбранную папку Диска и привязку к задаче через публичный CLI, включая наблюдаемые ограничения и восстановление после неатомарной записи.

## ADDED Requirements

### Requirement: Explicit local upload attachment mode
CLI SHALL принимать `task:file:attach TASK_ID --file PATH --folder-id ID` для одного локального файла. Режим SHALL исключать `--file-id`; `--folder-id` и optional `--name` SHALL быть допустимы только вместе с `--file`. TASK_ID и folder ID MUST быть положительными целыми. Режим существующих повторяемых `--file-id` SHALL сохранять собственный REST3 маршрут.

#### Scenario: Upload and attach a local file
- **WHEN** пользователь вызывает `b24cli task:file:attach 123 --file './Документы/Отчёт.pdf' --folder-id 456`
- **THEN** CLI загружает файл в папку 456 и прикрепляет полученный Disk ID только к задаче 123, без ручного промежуточного обращения к REST

#### Scenario: Conflicting or incomplete modes
- **WHEN** смешаны --file и --file-id, --file задан без folder ID, folder/name заданы без --file либо отсутствует любой источник файла
- **THEN** возвращается usage-error/exit2 до credentials/API, с объяснением допустимого вызова

#### Scenario: Existing Disk files
- **WHEN** заданы только повторяемые --file-id
- **THEN** CLI прикрепляет существующие файлы через REST3 без загрузки, чтения локального файла и вызовов Disk API; strict-rest3 остаётся допустимым

### Requirement: Bounded local binary file input
CLI MUST принимать только читаемый обычный локальный файл размером от 0 до 10485760 байт включительно. URL/stream wrappers, stdin `-`, каталоги и специальные файлы SHALL отклоняться с usage-error/exit2 до credentials/API. Размер и доступность SHALL проверяться до загрузки; рост/замена файла не SHALL обходить лимит. Бинарные данные MUST передаваться без преобразования текста.

#### Scenario: Invalid path or size
- **WHEN** путь отсутствует/нечитаем, указывает на каталог/FIFO/URL/wrapper/`-` либо файл больше 10 MiB
- **THEN** CLI возвращает exit2 без API и без ожидания чтения специального файла, указывает лимит CLI или проблему источника

#### Scenario: Binary bytes and portable paths
- **WHEN** файл содержит нулевые байты либо путь включает пробелы/Unicode на Windows, macOS или Linux
- **THEN** CLI принимает локальный путь и передаёт точные байты файла; пустой и ровно 10 MiB файл проходят локальную проверку, серверное принятие проверяется отдельно

#### Scenario: File changes during preparation
- **WHEN** файл растёт сверх лимита либо становится недоступным при чтении перед upload
- **THEN** CLI завершает локальную подготовку без записи, не отправляет усечённый файл и не объявляет загрузку успешной

### Requirement: Explicit upload filename and collision behavior
CLI SHALL использовать `--name NAME` либо basename локального пути. Имя MUST быть непустым valid UTF-8 до 255 символов без path separators и управляющих символов. CLI SHALL отправлять одинаковое имя в `data.NAME` и `fileContent[0]` и boolean `generateUniqueName=true`; существующий файл не SHALL перезаписываться.

#### Scenario: Explicit filename
- **WHEN** задан --name 'Отчёт.pdf'
- **THEN** оба поля upload payload содержат это имя; путь остаётся только локальным источником

#### Scenario: Invalid filename
- **WHEN** имя пусто, равно . или .., слишком длинное, содержит разделитель пути/управляющий символ либо невалидный UTF-8
- **THEN** CLI возвращает usage-error/exit2 до credentials/API

#### Scenario: Existing filename on Disk
- **WHEN** в папке уже есть файл с выбранным именем
- **THEN** CLI запрашивает генерацию уникального имени и возвращает фактическое имя из ответа upload; существующее содержимое не заменяется

### Requirement: Safe preview of local attachment
`--dry-run` SHALL локально проверить ввод и показать task ID, folder ID, имя, размер, upload route REST1 и attach route REST3 с зависимостью от ещё неизвестного Disk ID. Превью MUST обходиться без портальных запросов и не SHALL содержать байты/Base64, credentials или обещание server acceptance. Содержимое файла не SHALL кодироваться для вывода плана.

#### Scenario: Offline dry-run
- **WHEN** пользователь запускает корректный local upload с --dry-run без подключения
- **THEN** возвращается exit0 и безопасный двухшаговый план без чтения credentials/API/mutation; attach ID обозначен как результат upload

### Requirement: Method specific upload and attachment confirmation
CLI SHALL загружать через REST1 `disk.folder.uploadFile` с `id`, `data.NAME`, `fileContent=[name,base64]`, `generateUniqueName=true`, затем вызывать REST3 `tasks.task.file.attach` с `taskId` и `fileIds=[result.ID]`. Используется Disk `ID`, не внутренний `FILE_ID`. Подтверждение upload MUST содержать положительный ID; attach MUST иметь подтверждённый ACK. Права disk/tasks проверяет сервер.

#### Scenario: Confirmed two step success
- **WHEN** upload возвращает валидный Disk ID/имя и attach подтверждён
- **THEN** exit0, data.items и ledger показывают upload/attach, общий Disk ID, task ID, фактическое имя, confirmed и complete=true; meta отражает API versions 1.0 и 3.0

#### Scenario: Unusable successful upload response
- **WHEN** после отправки upload ответ не содержит валидного положительного Disk ID либо нарушает документированную форму
- **THEN** attach не выполняется, upload помечен unknown с outcomeUnknown=true; CLI не трактует FILE_ID как Disk ID и не повторяет загрузку

### Requirement: Recoverable partial attachment and cancellation
CLI SHALL сохранять подтверждённый upload и Disk ID при отказе attach: exit3, complete=false и оба шага в ledger. При неизвестном результате записи SHALL выставляться outcomeUnknown; автоматические retry/rollback/delete SHALL отсутствовать. Ctrl-C MUST прекращать последующие запросы и возвращать 130 с имеющимся результатом.

#### Scenario: Attachment denied after upload
- **WHEN** upload подтверждён, а attach запрещён либо task ID не существует
- **THEN** файл остаётся на Диске, stdout содержит его Disk ID и upload row, attach отмечен failed; пояснение предлагает `task:file:attach TASK_ID --file-id ID` после проверки цели/прав

#### Scenario: Upload rejected or response lost
- **WHEN** upload явно отклонён либо его ответ потерян
- **THEN** attach skipped, обычная ошибка exit1 либо interrupted/exit130; при потерянном ответе outcomeUnknown=true и предлагается проверка папки Диска перед ручным повтором

#### Scenario: Attachment response lost
- **WHEN** upload подтверждён, а ответ attach потерян
- **THEN** exit3 с сохранённым Disk ID и attach unknown/outcomeUnknown=true; рекомендация требует проверить привязку прежде чем повторять, новая загрузка не запускается

#### Scenario: Interrupted between steps
- **WHEN** Ctrl-C поступает после upload и до attach
- **THEN** attach не отправляется, возвращаются exit130, подтверждённый Disk ID и сведения о skipped attach без обещания отката

### Requirement: Safe output and bounded proof claims
CLI SHALL сохранять envelope schemaVersion 1 и обычные mutation TSV columns; upload/attach SHALL различаться action и ledger. Ни один формат/help/verbose/ошибка MUST NOT выводить содержимое файла, Base64 или secret-bearing upload/download URLs. ACK attach SHALL обозначать подтверждение метода; он не SHALL объявляться независимым membership readback.

#### Scenario: JSON and plain partial output
- **WHEN** local attachment завершается частично с --json либо --plain
- **THEN** JSON содержит один data/meta/error envelope с Disk ID/ledger, а TSV сохраняет taskId/resourceId/action/changedFields и upload row; diagnosis идёт в stderr без payload/URL

#### Scenario: Separate portal evidence
- **WHEN** unit fixtures подтверждают upload payload и ACK handling
- **THEN** результат фиксируется как локальная проверка; права disk/tasks, квота/коллизии и сохранение файла на конкретном портале требуют отдельного live evidence
