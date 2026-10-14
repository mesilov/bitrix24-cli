## Context

See proposal.md and the dated SDK/portal research. Worktree /private/tmp/bitrix24-cli-issue-58 preserves the dirty primary checkout. Refreshed dev includes lifecycle and normalized specs; raw status remains outside generic fields.

## Goals / Non-Goals

Deliver #58 with explicit routes, exact wire names and genuine CLI read-after-write receipts. Retain tags/observer behavior. No definition CRUD or general legacy API.

## Decisions

1. Replace validateWritable metadata gate with local documented FieldSchema validation. Add/update use explicit validation contexts; required creation fields apply to add only. Validate IDs, time, strings, enums, booleans, list elements and ISO dates. Keep the current ordinary allowlist and remote rights errors; do not derive new writable names from metadata. task:fields:list retains raw flags. Ordinary advanced dry-run is offline.
2. Custom input is a nonempty object of exact UF_* codes, no aliases, duplicate keys or nested values. Support string/integer/double/boolean/date/datetime scalar or scalar-list values and explicit empty string/list clearing. Reject null and special system types. tasks.task.getFields is an explicit preflight read for names/types. Absent/unsupported types fail before any write. Its missing mandatory/multiple flags are not fabricated: server validates field semantics. Creation relies on REST1's required-field validation before a task is created.
3. task:add with custom values uses a single REST1 tasks.task.add. Basic flags and known profile defaults map directly to documented native TITLE, DESCRIPTION, CREATED_BY, RESPONSIBLE_ID, GROUP_ID, DEADLINE, ALLOW_TIME_TRACKING (Y/N); this is an explicit CLI option mapping. Custom-create advanced --fields must use those native REST1 keys and is never translated from v3 JSON. Validate JSON ALLOW_TIME_TRACKING as Y/N; do not accept aliases. Defaults remain in their documented profile structure and apply only to missing ordinary values.
4. task:update keeps ordinary REST3 fields and uses a separate scoped REST1 update for supplied custom values. Custom-only updates make no empty v3 write. Process custom before existing tag writes. Compose confirmed ledger operations and preserve task ID, failed/unknown and skipped later steps. A sole rejected custom write remains the original error, not partial success. Cancellation preserves 130; no retry or automatic rollback.
5. Add a narrow TaskUserFieldGateway and adapter through existing ApiTransport; validate legacy result.task.id, including matching target ID on update. Handler routes declare every possible REST1 read/write before credential resolution, so strict-rest3 refuses the entire plan. Dry-run can perform custom schema reads but never mutation; show single legacy create with UFs together and exact version-specific fields.
6. Existing tag update remains separate; observer assignment follows tags as before. On a failed custom update, later tag steps are skipped. A legacy-created task retains its confirmed ID if tag/observer assignment fails.

## Sources and evidence boundaries

Official bitrix24_dev MCP was queried (search, then method/article details); its tasks.task.add record mixes v3 parameters with classic examples, so native legacy keys are additionally grounded in locked SDK Legacy/Services/Task and the raw live add receipts. See research/2026-10-14-sdk-portal-probe.md/json.

- https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-add.html — classic create and exact uppercase fields.
- https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html — taskId/fields and task.id confirmation.
- https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-fields.html — available ordinary/custom types.
- https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html — REST3 types/enums/relations and userFields write prohibition.

Locked SDK 3.7.0/8ebd4c154d5557db949b0196825f347a3a6c5bf0 and prior live probes are research, not implementation acceptance. UF_QUERY is absent on the available test portal; fresh acceptance uses existing fields of the same supported types. No claim of acceptance on the reporter's portal.

## Risks / Trade-offs

- Mixed updates are not atomic: preserve ledger and inspect existing task before retrying.
- getFields lacks mandatory/multiple flags: validate names and scalar element types locally; report portal semantic rejections. No universal metadata completeness claim.
- Custom create changes wire contract explicitly: document native JSON and reject v3 names there. Basic flags remain convenient.
- Clearing can fail for mandatory fields: surface server error, never convert omission into clearing.

## Migration Plan

Publish PR to dev with current Unreleased changelog and bidirectional issue links. Archive and deliver synced specs only after merged completion under maintainer rules. No data migration or SDK changes.
