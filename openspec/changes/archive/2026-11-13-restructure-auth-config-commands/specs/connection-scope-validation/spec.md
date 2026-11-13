# Spec Delta

## ADDED Requirements

### Requirement: Webhook owner identity
`auth:status` и проверка в `auth:login` SHALL определять владельца вебхука вызовом REST 1.0 `profile`, не требующим отдельного scope, и показывать ID, имя, фамилию и признак администратора в полях `ID`, `NAME`, `LAST_NAME`, `ADMIN` без переименования. Манифест прав SHALL описывать `profile` как маршрут без scope. Неактивный пользователь (пустой результат) MUST считаться ошибкой подключения.

#### Scenario: Webhook without user scope
- **WHEN** у вебхука есть только `task`, и выполняется `auth:status work --json`
- **THEN** ответ содержит `owner` с полями REST 1.0 `ID`, `NAME`, `LAST_NAME`, `ADMIN`, полученными через `profile`, а недостающие `user` и другие права перечислены в отчёте

#### Scenario: Inactive webhook owner
- **WHEN** `profile` возвращает пустой результат
- **THEN** `auth:status` завершается ошибкой подключения exit 1 с причиной «пользователь вебхука неактивен», снимок прав не помечается fresh

## MODIFIED Requirements

### Requirement: Only affected operations are blocked
Подтверждённая нехватка scope SHALL давать scope-missing/exit4 до первого вызова выбранной операции. Диагностика MUST содержать missing scopes, выбранный источник, дату проверки и способ исправления. Независимые команды MUST продолжать работу.

#### Scenario: Missing IM permission
- **WHEN** свежая проверка показывает task без im
- **THEN** task:chat:list/update/delete блокируются до чтения задачи/мутации, а обычный task:list выполняется
- **AND** диагностика предлагает добавить im вебхуку и вызвать auth:status

#### Scenario: Object denial and write safety
- **WHEN** scope достаточны, но API отказывает в доступе к объекту
- **THEN** сохраняется исходный permission-denied; изменяющий запрос не повторяется автоматически
- **AND** только точный insufficient_scope инвалидирует снимок для следующего обращения без повторения текущей операции

### Requirement: Explicit connection scope diagnostics
auth:status SHALL принудительно обновлять выбранное подключение независимо от TTL/backoff и показывать владельца вебхука. Human/plain/JSON MUST показывать права, время, manifest и потенциально затронутые команды с учётом условных зависимостей. Неудачная ручная проверка MUST возвращать ошибку. Проверка в auth:login SHALL сохранять снимок нового подключения.

#### Scenario: Repair webhook permissions
- **WHEN** пользователь добавил im и выполнил auth:status с выбранным профилем
- **THEN** обновлённые права сохраняются и следующая чат-команда использует кеш

#### Scenario: Missing scopes retain the complete diagnostic report
- **WHEN** auth:status успешно читает права, но обнаруживает отсутствующие scope
- **THEN** команда возвращает scope-missing/exit4 и показывает grantedScopes, lastSuccessfulCheckAt, scopeManifestRevision, potentiallyAffectedCommands, владельца вебхука и выбранное подключение во всех трёх режимах
- **AND** human/plain выводят отчёт в stderr без данных в stdout; JSON сохраняет отчёт в error.details.scopeValidation

#### Scenario: Login stores the first snapshot
- **WHEN** auth:login без --skip-check успешно проверил вебхук
- **THEN** state.json содержит fresh-снимок для нового подключения, и первая REST-команда после входа не выполняет дополнительный запрос scope

### Requirement: Preserve offline and API policy boundaries
Help/version/list/completion, локальные auth:list/switch/logout, все config:*, auth:login --skip-check и dry-run MUST NOT добавлять scope-запрос или чтение/запись state. strict-rest3 SHALL использовать только свежий кеш без REST1; явный auth:status и сетевая проверка auth:login SHALL отклоняться этой policy до ввода секрета, credentials и API.

#### Scenario: Offline and strict execution
- **WHEN** выполняются offline команды либо dry-run
- **THEN** scope state не создаётся и не открывается
- **WHEN** strict-rest3 требует обновить кеш
- **THEN** проверка сообщает unknown и не вызывает REST1; допустимый REST3 план сохраняется

#### Scenario: Strict policy and login
- **WHEN** auth:login work выполняется с --api-policy strict-rest3 без --skip-check
- **THEN** возвращается policy-denied до запроса секрета с подсказкой --skip-check; с --skip-check профиль сохраняется без REST-вызовов
