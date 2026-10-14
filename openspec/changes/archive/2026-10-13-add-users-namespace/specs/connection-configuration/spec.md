# Connection Configuration Spec Delta

## MODIFIED Requirements

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


### Requirement: Published configuration schema
The repository SHALL provide docs/configuration.md and docs/config.schema.json using JSON Schema Draft 2020-12. The schema MUST describe version 1 objects, required metadata, profile aliases, safe credential references, API-named task defaults, local default.users role selectors with an opt-in boolean and strict types. Documentation MUST identify checks that require runtime or filesystem state. Shared valid and invalid fixtures MUST verify schema/runtime agreement for structural constraints.

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


## ADDED Requirements

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
