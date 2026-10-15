# connection-scope-validation Specification

## Purpose

Обнаруживать расхождение выданных вебхуку разрешений и требований установленного CLI, сохраняя независимые команды, изоляцию подключений и работу без сети.

## Requirements

### Requirement: Versioned route scope requirements
CLI SHALL поставлять версионируемый манифест требований для каждой поддержанной пары method/API version. Проверка MUST учитывать только маршруты выбранного режима команды. Выданные коды MUST сохраняться без переименования; лишние права MUST NOT считаться ошибкой.

#### Scenario: Optional local file upload
- **WHEN** task:file:attach использует --file-id вместо локальной загрузки
- **THEN** проверка не требует disk; для --file/--folder-id план включает disk

#### Scenario: User and task scope alternatives
- **WHEN** user.get имеет любой из user/user_basic/user_brief либо task-маршрут имеет task или tasks
- **THEN** такая группа не блокирует маршрут; наличие scope не обещает поля, объектные права или эквивалентность task/tasks
- **AND** для настройки task-группы рекомендуется task согласно справочнику scope

#### Scenario: Unsupported route
- **WHEN** поддержанный каталог содержит маршрут без описания требований
- **THEN** проверка полноты отклоняет неполный манифест; runtime не считает неизвестный маршрут unrestricted

### Requirement: Independent protected connection state
Снимки scope SHALL храниться в versioned state.json рядом с config.json, отдельно для фактических credentials и выбранного источника. Проверка MUST NOT менять config/credentials или сохранять webhook URL/token. Записи MUST быть атомарными, изолированными и защищёнными от symlinks и чужого доступа.

#### Scenario: Rotate or switch credentials
- **WHEN** меняется webhook на том же hostname, alias, source, env/file или config directory
- **THEN** несовпадающее подключение не наследует предыдущий снимок

#### Scenario: Concurrent checks
- **WHEN** два процесса одновременно проверяют подключения
- **THEN** снимки не теряются и обновлённый снимок того же подключения повторно не запрашивается внутри TTL

#### Scenario: Unsafe or malformed state
- **WHEN** state имеет неизвестную версию, неверные типы, повреждённый JSON или небезопасный путь/permissions
- **THEN** auto-check сообщает unknown без ложного missing scope и не перезаписывает файл; manual check сообщает ошибку

### Requirement: Daily lazy refresh and manifest invalidation
При реальном REST-вызове CLI SHALL обновлять права при отсутствии снимка, смене credentials/манифеста или возрасте снимка не меньше 86400 секунд. Свежий снимок MUST NOT добавлять REST-вызов. Версия приложения и версия формата state MUST быть отдельными значениями.

#### Scenario: TTL boundary and app update
- **WHEN** прошло 86399 секунд при прежнем manifest либо обновилась только версия CLI
- **THEN** используется кеш без запроса
- **WHEN** прошло 86400 секунд либо изменился manifest
- **THEN** следующий разрешённый API-вызов сначала обновляет scope

### Requirement: Actual webhook scope discovery
Проверка SHALL вызывать REST1 scope без full и принимать только список строковых кодов. Пустой успешный список MUST отличаться от ошибочного/неизвестного ответа. Полный список портала MUST NOT использоваться как права webhook.

#### Scenario: Discover actual permissions
- **WHEN** выполняется scope-check
- **THEN** запрос не содержит full, параметры равны пустому объекту/набору, result string[] сохраняется как grantedScopes
- **AND** неверный тип result считается ошибкой проверки, а не пустым набором

### Requirement: Failures preserve successful evidence and back off
При неудачной проверке CLI SHALL сохранить последний успешный снимок и дату, отметить stale либо unknown и отложить auto-повтор на 300 секунд. Неподтверждённые данные MUST NOT создавать новый запрет основной команды. Ошибки и metadata MUST NOT раскрывать секреты.

#### Scenario: Network failure after expiration
- **WHEN** истёк TTL и scope недоступен
- **THEN** старый grantedScopes сохраняется, missing по нему не блокирует основную команду, следующая попытка до истечения backoff не делает scope request

### Requirement: Only affected operations are blocked
Подтверждённая нехватка scope SHALL давать scope-missing/exit4 до первого вызова выбранной операции. Диагностика MUST содержать missing scopes, выбранный источник, дату проверки и способ исправления. Независимые команды MUST продолжать работу.

#### Scenario: Missing IM permission
- **WHEN** свежая проверка показывает task без im
- **THEN** task:chat:list/update/delete блокируются до чтения задачи/мутации, а обычный task:list выполняется
- **AND** диагностика предлагает добавить im вебхуку и вызвать connection:check

#### Scenario: Object denial and write safety
- **WHEN** scope достаточны, но API отказывает в доступе к объекту
- **THEN** сохраняется исходный permission-denied; изменяющий запрос не повторяется автоматически
- **AND** только точный insufficient_scope инвалидирует снимок для следующего обращения без повторения текущей операции

### Requirement: Explicit connection scope diagnostics
connection:check SHALL принудительно обновлять выбранное подключение независимо от TTL/backoff. Human/plain/JSON MUST показывать права, время, manifest и потенциально затронутые команды с учётом условных зависимостей. Неудачная ручная проверка MUST возвращать ошибку.

#### Scenario: Repair webhook permissions
- **WHEN** пользователь добавил im и выполнил connection:check с выбранным профилем
- **THEN** обновлённые права сохраняются и следующая чат-команда использует кеш

#### Scenario: Missing scopes retain the complete diagnostic report
- **WHEN** connection:check успешно читает права, но обнаруживает отсутствующие scope
- **THEN** команда возвращает scope-missing/exit4 и показывает grantedScopes, lastSuccessfulCheckAt, scopeManifestRevision, potentiallyAffectedCommands и выбранное подключение во всех трёх режимах
- **AND** human/plain выводят отчёт в stderr без данных в stdout; JSON сохраняет отчёт в error.details.scopeValidation

### Requirement: Preserve offline and API policy boundaries
Help/version/list/completion, существующие локальные connection-команды и dry-run MUST NOT добавлять scope-запрос или чтение/запись state. strict-rest3 SHALL использовать только свежий кеш без REST1; явный connection:check SHALL отклоняться этой policy до credentials/API.

#### Scenario: Offline and strict execution
- **WHEN** выполняются offline команды либо dry-run
- **THEN** scope state не создаётся и не открывается
- **WHEN** strict-rest3 требует обновить кеш
- **THEN** проверка сообщает unknown и не вызывает REST1; допустимый REST3 план сохраняется
