# Spec Delta

## ADDED Requirements

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

## MODIFIED Requirements

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
