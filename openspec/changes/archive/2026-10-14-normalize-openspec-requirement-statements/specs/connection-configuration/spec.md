# Spec Delta

## MODIFIED Requirements

### Requirement: Published configuration schema
The repository SHALL provide docs/configuration.md and docs/config.schema.json for version 1 configuration using JSON Schema Draft 2020-12. Documentation MUST identify checks that require runtime or filesystem state.

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

## ADDED Requirements

### Requirement: Configuration schema structural coverage
The schema MUST describe version 1 objects, required metadata, profile aliases, safe credential references, API-named task defaults, local default.users role selectors with an opt-in boolean, root telemetry settings and strict types.

#### Scenario: Cover all version 1 configuration structures
- **WHEN** a version 1 configuration is validated with the published schema
- **THEN** profile metadata/aliases/credential references, API-named task defaults, role selectors with opt-in boolean and root telemetry are covered with strict types.

### Requirement: Shared configuration structure fixtures
Shared valid and invalid fixtures MUST verify schema/runtime agreement for structural constraints.

#### Scenario: Check both configuration validators
- **WHEN** the shared valid and invalid configuration fixtures run
- **THEN** JSON Schema and the actual configuration repository agree on structural acceptance/rejection; documented runtime/filesystem checks retain their existing scope.
