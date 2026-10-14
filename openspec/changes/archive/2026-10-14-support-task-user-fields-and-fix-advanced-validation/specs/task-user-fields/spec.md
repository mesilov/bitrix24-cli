## Purpose

Allow task creation and updates to save explicitly supplied custom fields with exact API keys, declared routes and recoverable outcomes.

## ADDED Requirements

### Requirement: Explicit custom value input
CLI SHALL accept mutually exclusive --user-fields and --user-fields-file PATH|- nonempty JSON objects of exact custom UF_* keys. It SHALL reject duplicate/alias/system keys, nested values and null. Scalars and flat scalar lists SHALL preserve their types. Omitted fields SHALL remain unchanged.

#### Scenario: Execution order input and clearing
- **WHEN** user supplies --user-fields '{"UF_QUERY":"5"}', empty string or an empty list
- **THEN** supplied values are preserved, with explicit emptiness submitted to portal validation; omission never clears anything

#### Scenario: Invalid input before credentials
- **WHEN** custom JSON is malformed, contains duplicate keys, non-UF keys, system UF_CRM_TASK/UF_TASK_WEBDAV_FILES/UF_MAIL_MESSAGE, nested values or null
- **THEN** CLI returns usage-error/exit2 before credentials/API

### Requirement: Bounded custom schema preflight
Before mutation CLI SHALL confirm custom names/types through explicit REST1 tasks.task.getFields. It SHALL reject unknown/unsupported types or mismatched scalar elements. It SHALL not fabricate mandatory/multiple metadata; those semantics SHALL be validated by the portal.

#### Scenario: Unknown field before creation
- **WHEN** a supplied custom field is absent or incompatible with its documented supported type
- **THEN** the whole operation is rejected before any write with the offending code

### Requirement: Single request custom task creation
With custom values task:add SHALL use one declared REST1 add containing ordinary and custom values. Basic flags SHALL map to native REST1 fields. Advanced JSON SHALL require exact TITLE/DESCRIPTION/CREATED_BY/RESPONSIBLE_ID/GROUP_ID/DEADLINE/ALLOW_TIME_TRACKING keys and SHALL reject v3 aliases. Missing required custom values SHALL produce the portal error without dependent writes.

#### Scenario: Required custom values at creation
- **WHEN** task:add supplies the required custom values and ordinary basic flags
- **THEN** a single REST1 add submits all creation values and returns one task ID; no REST3 create or later custom update occurs

#### Scenario: Native JSON and defaults
- **WHEN** custom creation uses native ordinary JSON, profile defaults or explicit flags
- **THEN** explicit values take precedence, REST1 payload uses native keys/Y-N, and v3 JSON keys are rejected rather than renamed

### Requirement: Declared updates and strict policy
Custom-only task:update SHALL use REST1 without an empty REST3 update. Mixed updates SHALL declare ordinary REST3 then custom REST1 then any tags. Strict-rest3 SHALL reject every custom plan before credentials/API. Dry-run SHALL expose versions, native fields and custom schema reads without writes.

#### Scenario: Custom and mixed updates
- **WHEN** task:update has custom input, with or without ordinary fields
- **THEN** only needed routes execute for the same ID; omitted custom keys and tags remain unchanged

#### Scenario: Strict and preview
- **WHEN** custom creation/update is requested in strict-rest3 or with dry-run
- **THEN** strict rejects the entire plan before requests; allowed dry-run performs only schema reads and exposes unexecuted writes

### Requirement: Recoverable composed outcomes
CLI SHALL retain confirmed task ID/steps on later custom/tag/observer failure, return partial/exit3 after confirmed mutation, preserve cancellation/exit130 and unknown outcomes, and skip subsequent writes. It SHALL never retry creation/update or delete a task automatically.

#### Scenario: Mixed update fails after ordinary write
- **WHEN** ordinary update succeeds but custom update fails or loses its response
- **THEN** known ID/confirmed step remain, custom is failed/unknown, later tags are skipped, and output directs inspection before task:update retry

#### Scenario: Legacy create composition
- **WHEN** legacy creation succeeds then tags/observer fail, or the only custom write is rejected
- **THEN** creation's ID is retained for later failure; a sole rejection returns the original error without claiming confirmed partial success
