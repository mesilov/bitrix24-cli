## Why

Issue #58 blocks the task creation workflow when a custom field such as «Порядок выполнения» (`UF_QUERY`) must be saved. Two separate gates cause the failure: `FieldSchema::localPatch` rejects REST3 `userFields`, while `validateWritable` requires portal `editable === true` for every advanced field, including fields that the documented write contract and basic flags already permit. The author's portal returns false for those fields; this portal observation has not been reproduced in this change.

## What Changes

- Validate advanced ordinary fields against a versioned, documented REST3 write schema instead of treating field metadata `editable` as the sole admission gate. Preserve bounded names, types and the rejection of raw status and relation objects.
- Add explicit `--user-fields` / `--user-fields-file` JSON input to task:add/task:update, using exact custom `UF_*` API keys, such as `{"UF_QUERY":"5"}`. Keep REST3 `userFields` read-only and provide an actionable error pointing to the new input.
- Plan a narrow REST1 custom-field update after REST3 creation or ordinary update, using the existing composition/ledger/policy conventions. Reject mixed plans in strict-rest3 before credentials or API requests.
- Preserve partial outcomes and known task IDs if a subsequent step fails. Account for composition with existing tags and the optional default observer.
- Update help, README, fixtures and targeted regression tests; independently verify stored values on a portal before claiming portal acceptance.

Scope excludes custom-field definition CRUD, raw legacy API access, system UF file/CRM/mail operations, changes to tag semantics (#71), lifecycle and profile defaults for custom fields. Supported values start with JSON scalars and flat scalar arrays; field-specific semantic validation and clearing must be grounded in the selected field's metadata. Unsupported complex types must produce a clear error.

## Capabilities

### New Capabilities
- `task-user-fields`: explicit custom-field inputs, bounded version-specific write routes and partial outcome semantics.

### Modified Capabilities
- `task-console-runtime`: documented ordinary-field validation independent of misleading editable metadata, preserving strict input and composition behavior.
- `task-mvp-scope`: narrow 0.2.0 admission for custom-field REST1 writes and schema reads.

## Impact

TaskInputMapper, FieldSchema, add/update commands and requests/handlers, ApplicationFactory, CommandCatalog and API policy; a scoped custom-field gateway/adapter using ApiTransport; tests, README and OpenSpec. No dependency replacement is planned. Existing editable=true fixtures must be expanded to false, missing and null metadata.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/58 — custom fields and advanced-field editable validation; milestone 0.2.0, bug.
- https://github.com/mesilov/bitrix24-cli/issues/71 — related, already separated tag work; its implementation is a dependency, not work covered by this change.
