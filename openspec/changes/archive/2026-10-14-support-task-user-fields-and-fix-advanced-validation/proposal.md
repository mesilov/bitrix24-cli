## Why

#58 prevents creation of tasks that need custom values, and advanced ordinary creation wrongly requires editable=true for creatorId. Live SDK probes confirmed REST3 rejects both flat UF_* and userFields input; REST1 create/save and readback work. See research/2026-10-14-sdk-portal-probe.md.

## What Changes

- Ordinary add/update keep REST3 and validate exact field names, types and per-operation contracts locally, independently of portal editable flags.
- Add --user-fields / --user-fields-file PATH|- for exact custom UF_* keys and scalar/flat-list values. Existing field schema supplies names/types; the server validates mandatory fields and multiplicity because getFields does not expose these reliably.
- With custom input, task:add uses one explicit REST1 add containing ordinary and custom values. It never creates through REST3 then attempts to fill required custom values afterwards.
- Basic flags and profile defaults select documented version-specific wire fields. Advanced JSON is never silently renamed: custom creation accepts native REST1 TITLE/DESCRIPTION/CREATED_BY/RESPONSIBLE_ID/GROUP_ID/DEADLINE/ALLOW_TIME_TRACKING; other advanced input uses exact REST3 names.
- Custom-only update uses REST1; mixed ordinary/custom update explicitly runs REST3 then REST1 and reports partial outcomes. Tags/observer composition and strict-rest3 are preserved.
- Add tests, Russian help, README, CHANGELOG and fresh CLI portal receipts.

## Capabilities

### New Capabilities
- `task-user-fields`: explicit input, single-request legacy creation, bounded update and outcomes.

### Modified Capabilities
- `task-console-runtime`: local ordinary write validation and native JSON names for custom create.
- `task-mvp-scope`: explicit legacy custom creation/update/schema admission for 0.2.0.

## Impact

Task input, request/handler/port/adapter, DI/catalog, tests and documentation. No SDK replacement. No custom definition CRUD, raw API access, system UF_CRM_TASK/UF_TASK_WEBDAV_FILES/UF_MAIL_MESSAGE, custom profile defaults, or arbitrary legacy lifecycle fields.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/58 — custom values and advanced editable validation; bug, milestone 0.2.0.
- https://github.com/mesilov/bitrix24-cli/issues/71 — existing tag behavior preserved; its implementation is outside this change.
