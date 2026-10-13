# connection-configuration Specification

## Purpose

Provide predictable local storage and selection of Bitrix24 webhook connections for source and standalone CLI execution on Linux, macOS and Windows.

## Requirements

### Requirement: User configuration location
The CLI SHALL locate user configuration through `--config-dir`, then `B24CLI_CONFIG_DIR`, then the documented OS default. It MUST NOT write configuration into the embedded application or executable directory.

#### Scenario: Unix defaults
- **WHEN** Linux or macOS has no explicit directory override
- **THEN** the CLI uses absolute nonempty XDG_CONFIG_HOME/b24cli or otherwise HOME/.config/b24cli
- **AND** relative XDG_CONFIG_HOME is ignored; an unavailable home produces configuration-error without cwd fallback

#### Scenario: Windows default and explicit paths
- **WHEN** Windows has no override or APPDATA, or an explicit directory contains spaces and Unicode
- **THEN** APPDATA/b24cli is used when available; otherwise a clear error requests an explicit directory
- **AND** explicit relative paths resolve against cwd and spaces, Unicode, drive letters and separators remain intact

### Requirement: Named profile metadata
The CLI SHALL maintain versioned non-secret config.json with independent named environment profiles and nullable defaultProfile. Each profile MUST contain a descriptive portal-domain name and nested connection with mandatory hostname and credentialFile relative to the configuration directory. name MUST NOT be a network target; requests MUST use HTTPS connection.hostname. Full webhook URLs MUST be absent from config.json; malformed or unsupported metadata MUST be rejected without mutation.

#### Scenario: Inspect four independent environments
- **WHEN** work, pet, clientA and clientB are configured
- **THEN** each profile has its own name, connection.hostname and connection.credentialFile without webhook user ID, path, token or query secrets
- **AND** each credential reference is resolved against the configuration directory independently of cwd

#### Scenario: Descriptive domain differs from connection hostname
- **WHEN** a profile name differs from connection.hostname
- **THEN** the descriptive domain is displayed without DNS or HTTP requests to name
- **AND** only the HTTPS webhook matching connection.hostname can supply the API connection

#### Scenario: Credentials remain independent
- **WHEN** two configured profiles refer to the same managed credential file
- **THEN** configuration-error rejects the shared reference before mutation or API calls
- **AND** removal of one valid profile cannot delete another profile's credentials

#### Scenario: Missing metadata or mismatched webhook authority
- **WHEN** a required profile field is missing or empty, or the credential webhook is non-HTTPS or targets a hostname different from connection.hostname
- **THEN** configuration-error occurs before any API request without fallback, URL rewriting or mutation

#### Scenario: Webhook control characters are rejected before parsing
- **WHEN** stdin, an explicit dotenv file or process environment supplies a webhook containing internal whitespace or ASCII control characters
- **THEN** configuration-error occurs before URL normalization, credential persistence or API calls without disclosing the input
- **AND** a trailing CRLF from documented stdin or dotenv input remains accepted as an input delimiter

#### Scenario: Unsupported format
- **WHEN** a selected config.json has an unsupported version or invalid structure
- **THEN** the CLI returns configuration-error before remote requests and preserves its bytes

### Requirement: Nested task defaults metadata
A stored profile SHALL accept an optional default.task object containing optional positive JSON integers groupId, creatorId, responsibleId and a JSON boolean allowsTimeTracking. Defaults MUST be non-secret. Malformed objects or values MUST produce configuration-error without mutation or API calls. Task default keys MUST match REST 3.0 field names. The former profile-root groupId MUST be rejected with guidance to default.task.groupId.

#### Scenario: Store independent task defaults
- **WHEN** work has default.task with groupId 42, creatorId 1, responsibleId 2 and allowsTimeTracking false while pet omits the optional ID defaults
- **THEN** the values are stored under each profile's default.task, independently of connection credentials
- **AND** config inspection can show the non-secret task defaults; no duplicate profile-root groupId is stored

#### Scenario: Optional defaults objects and invalid structure
- **WHEN** a selected profile omits default or default.task, or instead provides a null, scalar or array for either object
- **THEN** omission is accepted and no ID defaults are inferred; the invalid object returns configuration-error before an API call
- **AND** a supplied groupId, creatorId or responsibleId that is null, zero, negative, a string, boolean or non-integer also returns configuration-error without mutation

#### Scenario: Reject the superseded groupId location
- **WHEN** a selected profile contains groupId at its root, including together with default.task.groupId
- **THEN** configuration-error identifies default.task.groupId as the required location without merging or silently migrating the file

#### Scenario: API field names remain unchanged
- **WHEN** default.task contains an alternative creator key such as authorId rather than the API field creatorId
- **THEN** configuration-error rejects that unsupported key without silently renaming it or making an API request
- **AND** supported keys retain the exact REST 3.0 spelling and case in config and task fields

### Requirement: Profile-local task creation defaults
When task:add selects a stored profile, the CLI SHALL fill absent fields from default.task using the same API field names groupId, creatorId, responsibleId and allowsTimeTracking. Explicit fields MUST win by presence, including false. Required creation fields MUST be checked after applying defaults. No other operation or env/file source SHALL inherit a stored profile's defaults.

#### Scenario: Defaults satisfy required participants
- **WHEN** task:add selects work with default.task groupId 42, creatorId 1 and responsibleId 2, and supplies only an explicit title
- **THEN** the prepared tasks.task.add fields contain that title, groupId 42, creatorId 1, responsibleId 2 and allowsTimeTracking false
- **AND** dry-run reports each value and its source using non-secret metadata without opening credentials or introducing a new remote preflight

#### Scenario: Explicit fields override each default independently
- **WHEN** work has ID defaults and task:add supplies explicit --group-id, --creator and --responsible, or equivalent advanced JSON groupId, creatorId and responsibleId
- **THEN** every explicitly supplied API field wins and only absent fields use the profile default in either basic or advanced mode
- **AND** advanced fields and field flags remain mutually exclusive; invalid explicit values cause an error without default replacement

#### Scenario: Missing required fields remain required
- **WHEN** task:add has no explicit creatorId or responsibleId and the selected profile lacks the matching creatorId or responsibleId default
- **THEN** a safe usage-error occurs before an API call identifying the missing field
- **AND** title remains required and no participant is inferred from the credential webhook user ID

#### Scenario: Defaults do not cross profiles, commands or connection sources
- **WHEN** work has default.task and an invocation instead selects pet, an env/file credential source, or any operation other than task:add
- **THEN** no work defaults are injected into that invocation
- **AND** config and defaultProfile remain unchanged; task:add with env/file may still use the independent built-in allowsTimeTracking false

### Requirement: Boolean time tracking default
Task creation SHALL use fields.allowsTimeTracking as a JSON boolean. The CLI MUST prefer its explicit value, then default.task.allowsTimeTracking of the selected stored profile, then the built-in false. False MUST be treated as a present value. Config inspection SHALL report false as the effective default when the profile omits it.

#### Scenario: Time tracking is disabled by default
- **WHEN** task:add has no explicit time tracking value and no selected profile value, including with env/file credentials
- **THEN** the prepared payload and dry-run show allowsTimeTracking false with a built-in-default source
- **AND** no configuration is written or unrelated profile read to obtain that value

#### Scenario: Explicit false overrides profile true
- **WHEN** a profile sets default.task.allowsTimeTracking true and advanced task fields explicitly supply allowsTimeTracking false
- **THEN** the prepared payload contains false with an explicit source
- **AND** an explicit true similarly overrides a profile false; without an explicit value the selected profile boolean is used

#### Scenario: Boolean values are strict
- **WHEN** selected profile defaults contain allowsTimeTracking null, a number, a string, Y or N, or an explicit task field uses a non-boolean value
- **THEN** invalid metadata causes configuration-error and invalid task input causes usage-error, both before any API call without coercion or fallback

### Requirement: Protected credential persistence
The CLI SHALL store a complete webhook as one secret in a separate credential file. New Unix directories MUST be 0700 and managed files 0600; Windows files MUST have a documented user-restricted ACL. Failure to establish protection MUST stop persistence.

#### Scenario: Save credentials on each OS
- **WHEN** connection:add persists a valid webhook on Linux, macOS or Windows
- **THEN** the credential file has the required access protection and the documented storage location
- **AND** documentation identifies plaintext storage and administrative access limits without claiming encryption

#### Scenario: Unsafe or interrupted persistence
- **WHEN** existing managed files have unsafe permissions, protection fails, or writing is interrupted
- **THEN** the operation fails safely without broadening access or publishing a broken profile reference
- **AND** concurrent updates preserve existing profiles and readers see a complete prior or new state

### Requirement: Explicit connection selection
The CLI SHALL use only the connection selected by `--profile` or `--env-file`, ahead of ambient credentials. Without either flag, an explicit `--config` profile SHALL select that profile. `--profile` overrides config.profile; `--env-file` conflicts with a profile selector.

#### Scenario: Explicit profile isolation
- **WHEN** a valid named profile is selected and process BITRIX24_WEBHOOK or B24CLI_ENV_FILE points elsewhere
- **THEN** the chosen profile supplies the entire webhook and no ambient credential is mixed into it
- **AND** selecting the profile for this invocation does not change the stored default

#### Scenario: Explicit file isolation and conflicts
- **WHEN** --env-file selects a valid dotenv file or is combined with --profile or config.profile
- **THEN** the file alone supplies the connection in the first case; the conflicting invocation returns usage-error/exit2 before an API call in the second

#### Scenario: Runtime JSON profile compatibility
- **WHEN** --config contains a profile or --profile overrides that profile
- **THEN** the effective selector follows the documented precedence and credentials remain forbidden in runtime JSON

### Requirement: Compatible implicit selection
Without an explicit selector, the CLI SHALL prefer process BITRIX24_WEBHOOK, SDK alias, B24CLI_ENV_FILE, B24CLI_PROFILE, stored default, then source-only legacy dotenv, in that order. Simultaneous nonempty B24CLI_ENV_FILE and B24CLI_PROFILE MUST produce configuration-error.

#### Scenario: Environment and legacy file precedence
- **WHEN** no explicit selector is supplied and compatible process env and file sources exist
- **THEN** canonical process webhook wins over SDK alias and both win over B24CLI_ENV_FILE
- **AND** lower-priority files are not used to replace or merge this connection

#### Scenario: Ambient selector conflict
- **WHEN** B24CLI_ENV_FILE and B24CLI_PROFILE are both nonempty without an explicit selector
- **THEN** configuration-error/exit1 occurs before any API call, including when process credentials are present

#### Scenario: Default and source-only legacy lookup
- **WHEN** no higher-priority source exists
- **THEN** a stored default supplies the connection; only when no default exists may a source launcher read project-root .env.local or .env

### Requirement: Selected source failures
An unavailable, malformed or empty selected source and an unknown selected profile SHALL fail before a remote request. The CLI MUST NOT fall back to another profile, source or portal after a selected-source error.

#### Scenario: Missing explicit source
- **WHEN** --profile names an unknown profile or --env-file points to a missing, unreadable or invalid dotenv file
- **THEN** a safe configuration-error/exit1 identifies the corrective action and zero API calls occur
- **AND** valid ambient credentials do not hide the error

#### Scenario: Non-filesystem environment sources are rejected
- **WHEN** --env-file or B24CLI_ENV_FILE selects an HTTP URL or virtual stream instead of a filesystem file
- **THEN** configuration-error occurs before opening that source or making network/API calls, including for offline dry-run
- **AND** regular filesystem paths and local file URIs remain readable without rewriting the source

#### Scenario: Broken default or selected environment file
- **WHEN** a stored default references missing credentials or the selected B24CLI_ENV_FILE lacks a webhook
- **THEN** configuration-error/exit1 occurs without consulting lower-priority sources
- **AND** no configured source at all yields connection-unavailable/exit1 with setup guidance

### Requirement: Local profile commands
The CLI SHALL provide connection:add, connection:list, connection:show, connection:use and connection:remove. These commands MUST remain local, obey global output contracts and never contact a portal. Only connection:use SHALL select a new default; connection:remove MUST clear a deleted default.

#### Scenario: Add and select two profiles
- **WHEN** users add two profiles, run a task with --profile and then invoke connection:use NAME
- **THEN** add and the task invocation preserve the default; use explicitly persists the named default

#### Scenario: First profile before choosing a default
- **WHEN** connection:add work succeeds with no existing user configuration
- **THEN** it creates the configuration directory, credentials directory, config.json with defaultProfile null, profile name, nested connection metadata and default.task.allowsTimeTracking false, and one protected credential file
- **AND** name defaults to the safe webhook hostname unless --portal-domain supplies a descriptive domain; optional --group-id, --creator-id and --responsible-id populate the matching default.task IDs; --allows-time-tracking true|false sets the profile boolean
- **AND** no portal request occurs; using --profile work is available before connection:use work
- **AND** a subsequent connection:use work changes only config.json and leaves the credential file intact

#### Scenario: Reads and local removal preserve unrelated files
- **WHEN** a configured profile is inspected, used for task:list or removed locally
- **THEN** inspections and task reads do not persist configuration changes; removal deletes only that profile's metadata and credential file
- **AND** removal of the current default leaves defaultProfile null without selecting another profile; config.json and directories remain

#### Scenario: List inspect and remove
- **WHEN** users list/show profiles or remove one
- **THEN** output contains safe profile alias/name/connection.hostname/default.task/default.users/source/path metadata only
- **AND** remove clears a matching default and removes its credential reference without exposing or revoking the remote webhook

#### Scenario: Save optional working roles
- **WHEN** connection:add work получает --observer-id 5, --manager-id 9 и --auto-add-observer true
- **THEN** config сохраняет default.users с observerId=5, managerId=9, autoAddObserver=true без обращения к порталу
- **AND** connection:list/show отображают эти non-secret настройки; при отсутствии ID flags они не выводятся из webhook или оргструктуры

#### Scenario: Disabled automatic observer
- **WHEN** connection:add получает --observer-id 5 без --auto-add-observer либо --auto-add-observer false
- **THEN** эффективное autoAddObserver равно false, ID наблюдателя сохраняется как доступная настройка без автоматического назначения задачам


### Requirement: Secret input without argv
connection:add SHALL accept a webhook through hidden interactive input or explicitly requested `--webhook-stdin`. It MUST NOT provide a literal secret argument. Noninteractive missing input and competing stdin consumers MUST fail without waiting or exposing input.

#### Scenario: Hidden or piped input
- **WHEN** webhook input comes from a terminal or a secret file redirected to stdin with --webhook-stdin
- **THEN** interactive input is not echoed and piped input is read only when explicitly requested
- **AND** the secret does not enter subprocess argv

#### Scenario: Noninteractive missing input
- **WHEN** connection:add for a new alias runs with --no-interaction and no stdin option, or a second option also consumes stdin
- **THEN** usage-error/exit2 occurs without a prompt or partial profile creation

### Requirement: Packaged source independence
A packaged invocation SHALL obtain credentials only from process environment, an explicit file or user profile storage. It MUST NOT automatically read dotenv from cwd, the executable directory, an extracted payload or inside PHAR.

#### Scenario: Untrusted cwd dotenv
- **WHEN** a packaged command runs from a directory containing .env or .env.local without an explicit file selector
- **THEN** that file cannot supply or replace the connection

#### Scenario: Native platform execution
- **WHEN** a platform binary runs outside a checkout with profile, explicit file or process env
- **THEN** each documented source works on Linux amd64, macOS arm64 and Windows amd64 without installed PHP, Composer, Docker or an additional credential helper
- **AND** concurrent invocations use independently reserved temporary payloads, without renaming an opened temporary file, and clean their payload files/directories on exit
- **AND** Unix package executables have mode 0755 before archive creation and the public local-export directory permits ordinary-user traversal, so the package can be copied by an ordinary user

### Requirement: Safe connection diagnostics
Every output mode and error path SHALL redact the full webhook and secret parts of URLs, including debug output and parsing errors. Effective connection metadata MUST identify the chosen source truthfully without claiming a profile supplied credentials when env actually did.

#### Scenario: Error and verbose output
- **WHEN** configuration parsing or transport fails with a recognizable fixture secret
- **THEN** stdout, stderr, JSON, verbose diagnostics and error reports contain no fixture secret or full webhook

#### Scenario: Effective source inspection
- **WHEN** connection:show inspects a selected profile, file or process connection
- **THEN** it reports effective source, safe hostname and relevant profile/path, with no token or webhook path

### Requirement: Lazy read-only offline execution
Help, version, list, completion and offline dry-run SHALL require neither credentials nor configuration writes. Connection loading MUST remain lazy until an operation needs it; existing documented remote preflight behavior is preserved.

#### Scenario: Read-only offline invocation
- **WHEN** help, version, list, completion or an offline dry-run runs without credentials in a read-only user environment
- **THEN** it succeeds without creating configuration, reading secret storage or making an API request

### Requirement: Headless operation and documentation
API commands SHALL use supplied process env, explicit files or profiles without prompts or secret persistence. Documentation MUST describe OS paths, plaintext protection limits, selectors and priorities, source compatibility, and bash/zsh, PowerShell, cmd.exe and Docker/CI examples.

#### Scenario: Ephemeral CI credentials
- **WHEN** CI injects process env or mounts a credential file for one invocation
- **THEN** the task runs noninteractively without storing that secret in user profiles
- **AND** evidence distinguishes controlled fixtures, native CI acceptance and live portal checks

#### Scenario: Follow platform instructions
- **WHEN** a user follows the documented setup and selection commands for a supported OS
- **THEN** they can select a connection without putting its secret in command arguments or relying on cwd dotenv

### Requirement: Published configuration schema
The repository SHALL provide docs/configuration.md and docs/config.schema.json using JSON Schema Draft 2020-12. The schema MUST describe version 1 objects, required metadata, profile aliases, safe credential references, API-named task defaults, local default.users role selectors with an opt-in boolean, root telemetry settings and strict types. Documentation MUST identify checks that require runtime or filesystem state. Shared valid and invalid fixtures MUST verify schema/runtime agreement for structural constraints.

#### Scenario: Validate user configuration offline
- **WHEN** a user validates version 1 config.json with the published JSON Schema
- **THEN** valid profiles with optional default.task are accepted and unsupported fields, types, paths and versions are rejected without network or credential access
- **AND** documentation explains that default annotations do not rewrite files and that runtime checks defaultProfile existence, credential hostname, ownership, permissions and symbolic links

#### Scenario: Keep machine schema and runtime coherent
- **WHEN** a configuration field, type or validation rule changes
- **THEN** the shared contract fixtures run against both JSON Schema and the actual configuration repository in CI
- **AND** positive and negative examples in docs use the same REST 3.0 task field names as runtime

#### Scenario: Shared role-default validation cases
- **WHEN** fixtures проверяют default.users с observerId, managerId и autoAddObserver
- **THEN** schema и runtime согласованно принимают optional object/positive integer ID/strict boolean и отклоняют arrays вместо object, unknown fields, неверные типы и true без observerId
- **AND** ограничение literal integer в runtime отдельно отражено для 1.0/1e0, которые JSON Schema считает integer; existing fixtures сохраняются

#### Scenario: Shared telemetry schema and runtime cases
- **WHEN** fixtures проверяют omitted/partial telemetry, false, лимит, даты и unknown fields
- **THEN** schema/runtime согласованно принимают поддержанные настройки и отклоняют структурные ошибки, сохраняя existing profile fixtures
- **AND** docs объясняют локальный default true без auto send, timestamps offered/processed, лимит 100 и --config-dir location

### Requirement: Existing profile add rejection
`connection:add NAME` SHALL create a new profile only. An existing alias MUST produce configuration-error/exit 1 before webhook input, without applying supplied parameters, reading credentials or making API requests. The diagnostic MUST state that parameters were not applied and explain inspection and explicit replacement. The operation MUST preserve config.json, credential files and defaultProfile.

#### Scenario: Existing alias without redirected input
- **WHEN** `work` exists and `connection:add work --creator-id 3 -n` is invoked without `--webhook-stdin`
- **THEN** the CLI reports the duplicate profile as configuration-error/exit 1 without a webhook prompt or missing-input error
- **AND** the message states that defaults were not applied and identifies `connection:show work`, `connection:remove work`, `connection:add work` and reselection with `connection:use work` when required
- **AND** config.json and all credential files retain their bytes and references; defaultProfile and other profiles remain unchanged

#### Scenario: Existing alias with a stdin webhook
- **WHEN** `work` exists and a repeated add supplies `--webhook-stdin`, changed IDs and `--allows-time-tracking true`
- **THEN** stdin is not consumed for the webhook and existing credentials are not opened
- **AND** human, plain and JSON output report failure without fixture secrets or a success result; JSON retains the normal error contract and empty API-call metadata
- **AND** no profile metadata or credential file is created, replaced or removed

#### Scenario: Concurrent attempts to add the same alias
- **WHEN** two invocations begin adding an absent alias concurrently
- **THEN** at most one creates that profile and the other returns the duplicate failure without overwriting the winning profile
- **AND** the rejected operation does not leave an additional managed credential file

### Requirement: Documented profile replacement
Help for `connection:add`, README and the configuration guide SHALL explain that add does not update an existing profile. Instructions MUST show inspect, remove, add with all desired defaults and secret input, and use when restoring the selected default. They MUST explain that remove clears a matching defaultProfile and deletes its local credential without revoking the portal webhook.

#### Scenario: Discover replacement from help
- **WHEN** a user runs `b24cli help connection:add` without configured credentials
- **THEN** help explains create-only behavior and the replacement sequence without prompting, writing configuration or making API calls
- **AND** it explains that omitted defaults do not carry over from the deleted profile and that the webhook must be supplied again

#### Scenario: Add a missing creator default explicitly
- **WHEN** a user follows the documented replacement procedure for `work`, supplying the webhook again and `--group-id 187 --creator-id 3 --responsible-id 2 --allows-time-tracking true`
- **THEN** `connection:show work` reports `default.task.groupId=187`, `creatorId=3`, `responsibleId=2` as JSON integers and `allowsTimeTracking=true` as a JSON boolean
- **AND** an offline `task:add --profile work --title Test --dry-run --json` receives those defaults before required-field validation and makes no API calls
- **AND** a previously selected `work` becomes selected again only after the explicit `connection:use work`; other profiles remain unchanged
### Requirement: Nested user working-role defaults
Профиль schema v1 SHALL принимать optional object default.users с optional positive JSON integer observerId/managerId и boolean autoAddObserver (default false). Это локальные role selectors CLI; они SHALL не отправляться как API fields. Неизвестные ключи и неверные значения SHALL вызывать configuration-error до API без изменения файла. autoAddObserver=true SHALL требовать observerId.

#### Scenario: Independent role defaults
- **WHEN** work хранит observerId=5, managerId=9, autoAddObserver=true, а clientA хранит другие роли
- **THEN** выбранный профиль предоставляет только свои роли; конфиг с omitted default/default.users либо пустым users object принимается
- **AND** omission autoAddObserver означает false; managerId никогда не подменяет creatorId/responsibleId

#### Scenario: Invalid role IDs and boolean
- **WHEN** ID задан как null, string, boolean, 0, отрицательное, дробное число или overflow, либо autoAddObserver не boolean
- **THEN** runtime отклоняет файл; ID принимается только как integer literal в диапазоне 1..PHP_INT_MAX
- **AND** autoAddObserver=true без observerId отклоняется; false без observerId допустимо

#### Scenario: Unknown fields and invalid container
- **WHEN** default.users является null/scalar/array либо содержит unknown field, или observerId/managerId/autoAddObserver размещены в default.task
- **THEN** configuration-error возникает без mutation/API или молчаливого переноса полей
- **AND** default.task сохраняет свой существующий контракт REST3 fields; default.users является служебным контейнером, не alias какого-либо API field

### Requirement: Working roles stay within the selected connection
Профильные роли SHALL читаться только при фактическом использовании сохранённого профиля. Env/file/process подключения SHALL не наследовать observerId, managerId или autoAddObserver другого профиля. Non-secret metadata SHALL показывать эффективный boolean false при его отсутствии и источник ролей без чтения secrets для dry-run.

#### Scenario: Ambient webhook takes precedence
- **WHEN** выбранный process-env webhook используется вместо сохранённого defaultProfile
- **THEN** роли сохранённого defaultProfile не применяются, даже если в нём autoAddObserver=true

#### Scenario: Explicit profile and local inspection
- **WHEN** явно выбран --profile work
- **THEN** work роли доступны независимо от ambient webhook, connection:show показывает их источник, а read-команды не сохраняют metadata или credentials


### Requirement: Root problem trajectory telemetry
Пользовательский config.json schema v1 SHALL принимать optional root object telemetry. Effective defaults MUST быть collectProblemTrajectories=true, maxProblemTrajectoryFiles=100, lastProblemTrajectoryProcessingAt=null и lastProblemTrajectoryAnalysisOfferedAt=null. Настройки SHALL относиться к config directory, не profile/REST payload; read-only inspection MUST NOT записывать defaults.

#### Scenario: Omitted telemetry or partial object
- **WHEN** telemetry отсутствует либо содержит только collectProblemTrajectories=false
- **THEN** отсутствие даёт true/100/null/null, а explicit false сохраняется; остальные omitted fields получают defaults без изменения файла
- **AND** переключение portal profile не меняет telemetry этого config directory

### Requirement: Strict telemetry values
telemetry MUST принимать только четыре документированных поля: boolean collectProblemTrajectories, positive JSON integer maxProblemTrajectoryFiles и две nullable UTC ISO 8601 даты lastProblemTrajectoryProcessingAt/lastProblemTrajectoryAnalysisOfferedAt. Неверный объект, unknown key или тип SHALL давать configuration-error без mutation/сети.

#### Scenario: Invalid telemetry input
- **WHEN** telemetry задан null/array/scalar, max равен 0/string/fraction/overflow, boolean не строгий либо дата не null/валидный UTC timestamp
- **THEN** runtime/schema отклоняют соответствующий вход; literal integer checks runtime отражены отдельно для 1.0/1e0
- **AND** unknown key отклоняется; telemetry в profile/default/runtime --config не принимается за root user settings

### Requirement: Telemetry persistence preserves profiles
Local doctor operations SHALL обновлять только разрешённые telemetry поля под общей конфигурационной блокировкой, сохраняя profiles/defaultProfile/credential references. Первый успешный record без config SHALL создавать защищённый version=1/defaultProfile=null/profiles={} с telemetry без credentials. Profile mutations MUST сохранять существующий telemetry.

#### Scenario: Initialize before any portal profile
- **WHEN** корректный record выполняется без config.json и portal credentials
- **THEN** локальный config/карточка создаются с defaults, без credential files или portal requests
- **AND** status/help/version/list без config остаются read-only и ничего не создают

#### Scenario: Concurrent profile and telemetry writes
- **WHEN** connection:add/use/remove и doctor updates выполняются одновременно
- **THEN** ни telemetry, ни profile/defaultProfile/credential references не теряются; existing credential protections сохраняются
