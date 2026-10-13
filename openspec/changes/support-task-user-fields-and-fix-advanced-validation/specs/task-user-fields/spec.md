## Purpose

Allow task creation and updates to save explicitly supplied custom fields while preserving exact API names, declared API versions and recoverable partial results.

## ADDED Requirements

### Requirement: Explicit custom field input
CLI SHALL support mutually exclusive --user-fields and --user-fields-file PATH|- JSON objects on task:add/task:update. Keys SHALL be exact custom UF_* API codes. Basic flags or ordinary advanced fields SHALL be combinable with this input; missing keys SHALL remain untouched. REST3 userFields SHALL remain read-only.

#### Scenario: Set execution order
- **WHEN** task:add or task:update receives --user-fields '{"UF_QUERY":"5"}'
- **THEN** the custom value is planned under the unchanged UF_QUERY key, separately from REST3 ordinary fields

#### Scenario: Bounded keys and values
- **WHEN** input contains a non-UF key, system UF_CRM_TASK/UF_TASK_WEBDAV_FILES/UF_MAIL_MESSAGE, an empty object, conflicting sources, duplicate JSON keys, a nested object or unsupported value type
- **THEN** CLI returns usage-error/exit2 before credentials or API calls and does not silently rename or stringify values

### Requirement: Custom field schema preflight
Before writes CLI SHALL confirm supplied custom codes exist using REST1 tasks.task.getFields and validate supported scalar or flat scalar-list shapes against available field metadata. Unsupported or insufficient metadata SHALL fail explicitly. Field-specific clearing SHALL be accepted only when its representation is confirmed; omission SHALL never clear a field.

#### Scenario: Invalid custom field is caught before creation
- **WHEN** a custom code is absent from portal metadata or its type/multiplicity is incompatible
- **THEN** CLI rejects the operation before task creation or any other mutation and identifies the offending code

### Requirement: Declared mixed version custom writes
CLI SHALL use REST3 for ordinary fields and a separately declared REST1 tasks.task.update for custom fields. Strict-rest3 SHALL reject the full potential plan before credentials/API. Dry-run SHALL show routes, fields and new-ID dependencies without mutation; custom schema preflight SHALL be identified as a read.

#### Scenario: Create and custom-only update
- **WHEN** a task is created with UF_QUERY or an existing task is updated with only UF_QUERY
- **THEN** creation runs REST3 add followed by REST1 update with its returned ID, while custom-only update runs no empty REST3 update

#### Scenario: Existing tags and observer composition
- **WHEN** custom fields are combined with tags or an enabled default observer
- **THEN** every planned step uses the same task ID, versions are explicit, failures stop subsequent steps, and strict-rest3 prevents every request

### Requirement: Recoverable custom field outcomes
CLI SHALL preserve confirmed task IDs and step results if later writes fail, return partial/exit3 after confirmed mutation, preserve cancellation/exit130 and mark unknown outcomes. It SHALL not retry writes, create a second task or promise rollback automatically.

#### Scenario: Custom write fails after creation
- **WHEN** task creation is confirmed but its custom-field write fails or loses its response
- **THEN** output retains the created task ID, confirmed/failed/unknown/skipped steps and guidance to inspect that task before retrying only task:update

#### Scenario: Custom-only update is rejected
- **WHEN** the only mutating step is denied by the portal
- **THEN** CLI returns the original error rather than success or a claimed confirmed partial mutation
