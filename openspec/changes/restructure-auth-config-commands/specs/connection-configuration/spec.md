# Spec Delta

## ADDED Requirements

### Requirement: Authentication command set
CLI SHALL предоставлять `auth:login`, `auth:status`, `auth:list`, `auth:switch` и `auth:logout` и MUST NOT регистрировать команды `connection:*`, их алиасы или скрытые варианты. `auth:list`, `auth:switch` и `auth:logout` MUST оставаться локальными и не обращаться к порталу. Все команды подчиняются общим контрактам вывода human/plain/JSON.

#### Scenario: Command inventory
- **WHEN** пользователь вызывает `b24cli list auth`
- **THEN** выводятся ровно `auth:list`, `auth:login`, `auth:logout`, `auth:status`, `auth:switch` с русскими описаниями, код 0, без запросов к порталу

#### Scenario: Removed connection commands
- **WHEN** пользователь вызывает `b24cli connection:add work` или любую другую `connection:*`
- **THEN** CLI отвечает как на неизвестную команду, без ввода секрета, записи конфигурации и обращения к порталу

#### Scenario: Local auth commands stay offline
- **WHEN** `auth:list`, `auth:switch work` или `auth:logout work` выполняются без сети
- **THEN** команды завершаются по своим локальным правилам без API-запросов и без чтения или записи `state.json`

### Requirement: Verified login
`auth:login NAME` SHALL до сохранения проверять вебхук на портале вызовами REST 1.0 `profile` и `scope`. Ошибка авторизации, transport или неактивный пользователь MUST завершать команду с exit 1 без записи профиля и credentials. Нехватка прав относительно манифеста MUST NOT отменять вход. `--skip-check` SHALL сохранять профиль без запросов к порталу.

#### Scenario: Valid webhook
- **WHEN** `auth:login work` получает вебхук, для которого `profile` возвращает пользователя, а `scope` — список прав
- **THEN** профиль и защищённый credential file сохраняются, снимок прав записывается в `state.json`, вывод показывает hostname, ID и имя владельца вебхука, код 0

#### Scenario: Invalid webhook is not saved
- **WHEN** `profile` возвращает `INVALID_CREDENTIALS`, `NO_AUTH_FOUND`, ошибку доступа к REST, ошибку transport или пустой результат неактивного пользователя
- **THEN** команда завершается с exit 1 и безопасной диагностикой с подсказкой проверить URL или использовать `--skip-check`
- **AND** `config.json`, credential files и `defaultProfile` остаются без изменений, секрет не выводится

#### Scenario: Missing scopes do not block login
- **WHEN** вебхук валиден, но у него нет части прав из манифеста
- **THEN** профиль сохраняется, в stderr выводится отчёт с недостающими правами и затронутыми командами, код 0
- **AND** точный результат с exit 4 по-прежнему даёт `auth:status`

#### Scenario: Scope check unavailable after valid profile
- **WHEN** `profile` успешен, а `scope` не ответил
- **THEN** профиль сохраняется, снимок помечается unknown, выводится предупреждение с подсказкой `auth:status`, код 0

#### Scenario: Offline login
- **WHEN** `auth:login ci --webhook-stdin --skip-check -n` получает вебхук из перенаправленного stdin
- **THEN** профиль сохраняется без API-запросов и без записи `state.json`

### Requirement: Default profile on first login
Если `defaultProfile` равен null, успешный `auth:login` SHALL делать новый профиль профилем по умолчанию. Если профиль по умолчанию уже выбран, `auth:login` MUST NOT его менять. Вывод MUST сообщать, выбран ли профиль по умолчанию. Только `auth:switch` SHALL переключать уже выбранный профиль по умолчанию.

#### Scenario: First login selects the profile
- **WHEN** конфигурации нет и `auth:login work` успешен
- **THEN** создаются каталог конфигурации, каталог credentials, `config.json` с `defaultProfile: "work"`, профилем `work` (name по hostname вебхука, `connection.hostname`, `connection.credentialFile`) и защищённый credential file
- **AND** следующая команда без `--profile` использует `work`

#### Scenario: Second portal keeps the current default
- **WHEN** `work` выбран по умолчанию и выполняется `auth:login clientA`
- **THEN** `clientA` сохраняется, `defaultProfile` остаётся `work`, вывод сообщает, что для выбора нужен `auth:switch clientA`

#### Scenario: Explicit switch
- **WHEN** пользователь выполняет `auth:switch clientA`
- **THEN** меняется только `defaultProfile` в `config.json`; credential files, профили, `logging` и `telemetry` сохраняются

### Requirement: Existing profile requires explicit replacement
`auth:login NAME` без `--replace` SHALL создавать только новый профиль. Существующий NAME MUST давать configuration-error/exit 1 до ввода вебхука, без чтения credentials и API-запросов, с подсказками `auth:login NAME --replace` и `config:set`. `--replace` для отсутствующего NAME MUST давать configuration-error/exit 1 до ввода вебхука.

#### Scenario: Existing alias without replace
- **WHEN** `work` существует и вызывается `auth:login work -n` без `--webhook-stdin`
- **THEN** возвращается configuration-error/exit 1 без запроса секрета и без ошибки об отсутствующем вводе
- **AND** stdin не читается, `config.json` и все credential files сохраняют свои байты

#### Scenario: Replace for a missing alias
- **WHEN** `ghost` не существует и вызывается `auth:login ghost --replace --webhook-stdin`
- **THEN** возвращается configuration-error/exit 1, stdin не читается, ничего не создаётся

#### Scenario: Concurrent logins for the same alias
- **WHEN** два процесса одновременно выполняют `auth:login` для отсутствующего alias
- **THEN** профиль создаёт не больше одного процесса, второй получает ошибку существующего профиля и не оставляет лишнего credential file

### Requirement: Credential replacement preserves settings
`auth:login NAME --replace` SHALL заменять только секрет существующего профиля: новый credential file, атомарное переключение ссылки и удаление старого файла после публикации. `name`, `default.task`, `default.users`, `defaultProfile`, `logging` и `telemetry` MUST сохраняться. `connection.hostname` SHALL обновляться по новому вебхуку, а снимок прав старого вебхука MUST NOT наследоваться.

#### Scenario: Rotate a webhook
- **WHEN** у `work` есть `default.task.groupId=42` и `default.users.observerId=5`, и выполняется `auth:login work --replace` с новым валидным вебхуком того же портала
- **THEN** `config:list --profile work` показывает прежние значения, ссылка указывает на новый защищённый credential file, старого файла нет
- **AND** следующий REST-вызов не использует снимок прав старого вебхука

#### Scenario: Replacement fails verification
- **WHEN** проверка нового вебхука в `auth:login work --replace` завершается ошибкой авторизации
- **THEN** старые credentials и вся конфигурация остаются без изменений, exit 1

#### Scenario: Replacement changes the portal hostname
- **WHEN** новый вебхук указывает на другой hostname
- **THEN** `connection.hostname` обновляется, вывод показывает прежний и новый hostname, `name` и `default.*` сохраняются без автоматической очистки

### Requirement: Logout removes the whole profile
`auth:logout NAME` SHALL удалять метаданные профиля и его credential file и очищать совпадающий `defaultProfile`, не выбирая другой профиль. Вебхук на портале MUST NOT отзываться. Другие профили, `logging`, `telemetry` и каталоги MUST сохраняться.

#### Scenario: Logout the default profile
- **WHEN** `work` выбран по умолчанию и выполняется `auth:logout work`
- **THEN** профиль и его credential file удалены, `defaultProfile` равен null, другие профили и credential files не изменены
- **AND** вывод сообщает, что вебхук на портале не отозван

#### Scenario: Logout an unknown profile
- **WHEN** `auth:logout ghost` вызывается для отсутствующего профиля
- **THEN** возвращается configuration-error/exit 1 с подсказкой `auth:list` без изменения файлов

### Requirement: Local connection listing
`auth:list` SHALL показывать сохранённые профили (alias, name, `connection.hostname`, отметку профиля по умолчанию) и блок фактического подключения текущего вызова: источник (profile, `--env-file`, `B24CLI_ENV_FILE`, `BITRIX24_WEBHOOK`), безопасный hostname и путь. `auth:list NAME` SHALL показывать один профиль. Команда MUST NOT читать секрет и обращаться к порталу.

#### Scenario: List profiles and the effective source
- **WHEN** сохранены `work` (по умолчанию) и `clientA`, а процесс получил `BITRIX24_WEBHOOK`
- **THEN** вывод содержит оба профиля с отметкой `work` и блок фактического подключения с источником `BITRIX24_WEBHOOK` и его безопасным hostname
- **AND** вывод не содержит токена, ID пользователя вебхука или пути REST

#### Scenario: No configuration
- **WHEN** `auth:list` выполняется без `config.json`
- **THEN** выводится пустой список с подсказкой `auth:login`, файлы не создаются, код 0

### Requirement: Configuration command set
CLI SHALL предоставлять `config:list`, `config:get KEY`, `config:set KEY VALUE` и `config:unset KEY` для документированных несекретных настроек. Команды MUST оставаться локальными: не читать credentials, не обращаться к порталу, не открывать `state.json`. Ключи `defaultProfile` и `connection.*` MUST NOT изменяться через `config:*`.

#### Scenario: Command inventory
- **WHEN** пользователь вызывает `b24cli list config`
- **THEN** выводятся ровно `config:get`, `config:list`, `config:set`, `config:unset` с русскими описаниями, код 0

#### Scenario: Change one default without re-entering the webhook
- **WHEN** у `work` есть `default.task.creatorId=1` и выполняется `config:set default.task.groupId 187 --profile work`
- **THEN** `config:get default.task.groupId --profile work` возвращает JSON integer 187, `creatorId` остаётся 1, credential file не открывался и не менялся

#### Scenario: Protected keys
- **WHEN** пользователь вызывает `config:set defaultProfile work` или `config:set connection.hostname x.example`
- **THEN** возвращается usage-error/exit 2 с подсказкой `auth:switch` или `auth:login --replace`, файл не меняется

### Requirement: Supported configuration keys
`config:*` SHALL принимать закрытый список ключей — точных путей `config.json` с именами полей REST 3.0. Профильные: `name`, `default.task.groupId|creatorId|responsibleId|allowsTimeTracking`, `default.users.observerId|managerId|autoAddObserver`. Корневые: `logging.enabled|level|directory|fileMask|retentionDays`, `telemetry.collectProblemTrajectories|maxProblemTrajectoryFiles`. Даты `telemetry.last*At` только читаются.

#### Scenario: Unknown or renamed key
- **WHEN** пользователь вызывает `config:set default.task.authorId 3` или `config:set groupId 42`
- **THEN** возвращается usage-error/exit 2 со списком поддерживаемых ключей без молчаливого переименования и без изменения файла

#### Scenario: Read-only telemetry dates
- **WHEN** пользователь вызывает `config:set telemetry.lastProblemTrajectoryProcessingAt 2026-11-12T00:00:00Z`
- **THEN** возвращается usage-error/exit 2; `config:get` того же ключа возвращает текущее значение или null

### Requirement: Strict configuration values
`config:set` SHALL разбирать VALUE по типу ключа без угадывания. ID — integer literal 1..PHP_INT_MAX, boolean — только `true`/`false`, `logging.level` — значение из списка в нижнем регистре, `name` — bare ASCII hostname, `fileMask` и `directory` — по правилам logging. После подстановки документ MUST проходить ту же runtime-проверку, что и при чтении. Ошибка MUST давать usage-error/exit 2 без записи.

#### Scenario: Invalid scalar values
- **WHEN** VALUE для ID равен `0`, `-1`, `1.0`, `1e0`, `abc` или переполняет диапазон; для boolean — `Y`, `1`, `"false"`; для `logging.level` — `Info`
- **THEN** каждый вызов возвращает usage-error/exit 2 с ожидаемым форматом, файл сохраняет свои байты

#### Scenario: Cross-field rule
- **WHEN** `config:set default.users.autoAddObserver true` выполняется для профиля без `observerId`, либо `config:unset default.users.observerId` при `autoAddObserver=true`
- **THEN** возвращается usage-error/exit 2, объясняющий зависимость, файл не меняется

#### Scenario: Unset restores the built-in default
- **WHEN** выполняется `config:unset default.task.allowsTimeTracking` или `config:unset logging.level`
- **THEN** ключ удаляется из файла, `config:get` возвращает встроенное значение `false` или `info` с источником `default`; пустые контейнеры удаляются
- **AND** `config:unset name` возвращает usage-error/exit 2, потому что поле обязательно

### Requirement: Configuration profile selection
Для профильных ключей `config:*` SHALL выбирать профиль по `--profile`, затем `B24CLI_PROFILE`, затем `defaultProfile`. `BITRIX24_WEBHOOK` и `B24CLI_ENV_FILE` MUST игнорироваться, `--env-file` MUST давать usage-error. Корневой ключ определяется префиксом `logging.`/`telemetry.` и MUST NOT принимать `--profile`.

#### Scenario: Default profile is used
- **WHEN** `defaultProfile` равен `work`, а процесс получил `BITRIX24_WEBHOOK` другого портала
- **THEN** `config:set default.task.groupId 42` меняет профиль `work`, а не подключение из окружения

#### Scenario: No profile for a profile key
- **WHEN** `defaultProfile` равен null, `--profile` и `B24CLI_PROFILE` не заданы, вызывается `config:set default.task.groupId 42`
- **THEN** возвращается usage-error/exit 2 с подсказкой `auth:login` или `auth:switch`, файлы не создаются

#### Scenario: Root key with a profile
- **WHEN** вызывается `config:set logging.enabled true --profile work`
- **THEN** возвращается usage-error/exit 2; без `--profile` настройка применяется ко всему каталогу конфигурации

### Requirement: Atomic configuration updates
`config:set` и `config:unset` SHALL выполнять запись под общей блокировкой конфигурации, перечитывая файл под lock, и атомарно публиковать результат. Все остальные профили, `defaultProfile`, ссылки на credentials, `telemetry` и `logging` MUST сохраняться, включая неверный `logging` при изменении ключа вне `logging.*`. Без `config.json` запись корневого ключа SHALL создавать защищённый файл без профилей.

#### Scenario: Concurrent writers
- **WHEN** `config:set` выполняется одновременно с `auth:login`, `auth:switch`, `auth:logout` или записью telemetry `doctor`
- **THEN** ни одно изменение не теряется, защита файлов сохраняется

#### Scenario: Invalid logging is preserved for unrelated keys
- **WHEN** `logging` содержит `{"level":"Info"}` и выполняется `config:set default.task.groupId 42`
- **THEN** профиль обновляется, `logging` в файле остаётся `{"level":"Info"}`
- **WHEN** выполняется `config:set logging.enabled true` при том же неверном `level`
- **THEN** возвращается usage-error/exit 2 с причиной неверного `logging.level`, файл не меняется

#### Scenario: Root setting before any profile
- **WHEN** `config.json` отсутствует и выполняется `config:set logging.enabled true`
- **THEN** создаётся защищённый `config.json` с `version: 1`, `defaultProfile: null`, `profiles: {}` и `logging.enabled=true`, без credential files

### Requirement: Effective settings in config:list
`config:list` SHALL показывать эффективные значения ключей выбранного профиля и корневых настроек с источником `profile`, `root` или `default` и блок `logging`: значения пяти полей, абсолютные пути каталога и файла текущих суток, статус `ok`, `disabled` или `invalid` с причиной. Вычисление MUST NOT создавать каталог логов и записывать значения по умолчанию.

#### Scenario: Enabled logging shown
- **WHEN** `logging.enabled=true` и выполняется `config:list --json`
- **THEN** JSON содержит блок `logging` с `enabled=true`, `level`, `retentionDays`, абсолютным путём к `b24cli-YYYYMMDD.log` текущих суток и статусом `ok`

#### Scenario: Invalid logging shown
- **WHEN** `logging.retentionDays=0` и выполняется `config:list`
- **THEN** вывод содержит статус `invalid` с причиной, код выхода не меняется из-за logging

#### Scenario: Effective defaults without a profile value
- **WHEN** у профиля нет `default.task.allowsTimeTracking` и `default.users.autoAddObserver`
- **THEN** `config:list` показывает для обоих значение `false` с источником `default`, файл не меняется

### Requirement: Documented auth and config commands
README, `docs/auth.md`, `docs/auth-scopes.md` и `docs/config.md` SHALL описывать первый запуск как `auth:login NAME` с последующим `auth:status`, замену вебхука через `--replace`, выход через `auth:logout`, все ключи `config:*` с типами и примерами для bash/zsh, PowerShell и CI с `--webhook-stdin --skip-check`. До 1.0 документация MUST NOT упоминать удалённые команды `connection:*`.

#### Scenario: Follow the first-run instructions
- **WHEN** пользователь выполняет команды первого запуска из README
- **THEN** после `auth:login work` и `auth:status` он может выполнять команды задач без `auth:switch` и без ручной правки `config.json`

#### Scenario: No removed commands in docs
- **WHEN** проверка документации ищет `connection:` в README, `docs/` (кроме `docs/releases/` и CHANGELOG) и справке команд
- **THEN** совпадений нет

## MODIFIED Requirements

### Requirement: Protected credential persistence
The CLI SHALL store a complete webhook as one secret in a separate credential file. New Unix directories MUST be 0700 and managed files 0600; Windows files MUST have a documented user-restricted ACL. Failure to establish protection MUST stop persistence.

#### Scenario: Save credentials on each OS
- **WHEN** auth:login persists a valid webhook on Linux, macOS or Windows, including a replacement with --replace
- **THEN** the credential file has the required access protection and the documented storage location
- **AND** documentation identifies plaintext storage and administrative access limits without claiming encryption

#### Scenario: Unsafe or interrupted persistence
- **WHEN** existing managed files have unsafe permissions, protection fails, or writing is interrupted
- **THEN** the operation fails safely without broadening access or publishing a broken profile reference
- **AND** concurrent updates preserve existing profiles and readers see a complete prior or new state

### Requirement: Secret input without argv
auth:login SHALL accept a webhook through hidden interactive input or explicitly requested `--webhook-stdin`. It MUST NOT provide a literal secret argument. Noninteractive missing input and competing stdin consumers MUST fail without waiting or exposing input.

#### Scenario: Hidden or piped input
- **WHEN** webhook input comes from a terminal or a secret file redirected to stdin with --webhook-stdin
- **THEN** interactive input is not echoed and piped input is read only when explicitly requested
- **AND** the secret does not enter subprocess argv

#### Scenario: Noninteractive missing input
- **WHEN** auth:login for a new alias, or auth:login --replace for an existing alias, runs with --no-interaction and no stdin option, or a second option also consumes stdin
- **THEN** usage-error/exit2 occurs without a prompt or partial profile creation

### Requirement: Safe connection diagnostics
Every output mode and error path SHALL redact the full webhook and secret parts of URLs, including debug output and parsing errors. Effective connection metadata MUST identify the chosen source truthfully without claiming a profile supplied credentials when env actually did.

#### Scenario: Error and verbose output
- **WHEN** configuration parsing, auth:login verification or transport fails with a recognizable fixture secret
- **THEN** stdout, stderr, JSON, verbose diagnostics and error reports contain no fixture secret or full webhook

#### Scenario: Effective source inspection
- **WHEN** auth:list inspects the effective profile, file or process connection
- **THEN** it reports effective source, safe hostname and relevant profile/path, with no token or webhook path

### Requirement: Working roles stay within the selected connection
Профильные роли SHALL читаться только при фактическом использовании сохранённого профиля. Env/file/process подключения SHALL не наследовать observerId, managerId или autoAddObserver другого профиля. Non-secret metadata SHALL показывать эффективный boolean false при его отсутствии и источник ролей без чтения secrets для dry-run.

#### Scenario: Ambient webhook takes precedence
- **WHEN** выбранный process-env webhook используется вместо сохранённого defaultProfile
- **THEN** роли сохранённого defaultProfile не применяются, даже если в нём autoAddObserver=true

#### Scenario: Explicit profile and local inspection
- **WHEN** явно выбран --profile work
- **THEN** work роли доступны независимо от ambient webhook, config:list --profile work показывает их источник, а read-команды не сохраняют metadata или credentials

### Requirement: Telemetry persistence preserves profiles
Local doctor operations SHALL обновлять только разрешённые telemetry поля под общей конфигурационной блокировкой, сохраняя profiles/defaultProfile/credential references. Первый успешный record без config SHALL создавать защищённый version=1/defaultProfile=null/profiles={} с telemetry без credentials. Profile mutations MUST сохранять существующий telemetry.

#### Scenario: Initialize before any portal profile
- **WHEN** корректный record выполняется без config.json и portal credentials
- **THEN** локальный config/карточка создаются с defaults, без credential files или portal requests
- **AND** status/help/version/list без config остаются read-only и ничего не создают

#### Scenario: Concurrent profile and telemetry writes
- **WHEN** auth:login/switch/logout, config:set/unset профильных ключей и doctor updates выполняются одновременно
- **THEN** ни telemetry, ни profile/defaultProfile/credential references не теряются; existing credential protections сохраняются

### Requirement: Invalid logging does not break profile storage
Неверный root logging MUST NOT давать configuration-error при чтении и записи профилей и telemetry. При чтении CLI SHALL считать логирование выключенным; при записи MUST сохранять logging без изменений. Остальные root-ключи проверяются как раньше.

#### Scenario: Profile command with invalid logging
- **WHEN** config.json содержит logging с level="Info" и выполняется auth:switch work либо config:set default.task.groupId 42 --profile work
- **THEN** команда выполняется, logging в файле остаётся `{"level":"Info"}` без изменений

### Requirement: Logging persistence preserves configuration
Profile mutations, config:set/unset ключей вне logging и doctor telemetry updates MUST сохранять существующий root logging без изменений. Изменения profiles, defaultProfile, credential references и telemetry MUST NOT терять logging.

#### Scenario: Profile and telemetry writes keep logging
- **WHEN** config содержит logging и выполняются auth:login, auth:login --replace, auth:switch, auth:logout, config:set профильного или telemetry ключа или doctor configure
- **THEN** logging после записи совпадает с исходным

## REMOVED Requirements

### Requirement: Local profile commands
**Reason**: Команды `connection:add|list|show|use|remove` заменены пространствами `auth:*` (учётные данные и выбор) и `config:*` (настройки). Профиль больше не создаётся вместе со значениями по умолчанию, а первый профиль выбирается автоматически.
**Migration**: `connection:add NAME` → `auth:login NAME` + `config:set …`; `connection:list` → `auth:list`; `connection:show` → `auth:list NAME` и `config:list`; `connection:use` → `auth:switch`; `connection:remove` → `auth:logout`.

### Requirement: Existing profile add rejection
**Reason**: Заменено требованием «Existing profile requires explicit replacement» для `auth:login` с явным `--replace`.
**Migration**: Для нового вебхука используйте `auth:login NAME --replace`, для значений по умолчанию — `config:set KEY VALUE --profile NAME`.

### Requirement: Documented profile replacement
**Reason**: Удалять и заново создавать профиль больше не нужно: `config:set` меняет отдельные значения, `auth:login --replace` меняет секрет с сохранением настроек.
**Migration**: Вместо `connection:show` → `connection:remove` → `connection:add` → `connection:use` выполните `config:set` для нужных ключей или `auth:login NAME --replace`.

### Requirement: Effective logging in connection:show
**Reason**: Блок `logging` переехал в `config:list` вместе с остальными несекретными настройками.
**Migration**: Используйте `config:list` или `config:list --json`; состав блока и статусы сохраняются (требование «Effective settings in config:list»).
