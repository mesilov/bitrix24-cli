## Context

See proposal.md — Why. Analysis baseline: dev `0aeb25b68dad641be57e6356222373d4fc9782d3`. The dirty primary checkout is preserved; work is isolated in `/private/tmp/bitrix24-cli-issue-58` on `bugfix/58-task-user-fields-editability`.

TaskInputMapper::patch always applies localPatch; advanced requests additionally call validateWritable in AddTaskHandler/UpdateTaskHandler. That function reads tasks.task.field.list and insists on editable=true. Rest3TaskAdapter passes metadata through without synthesizing false. Basic flags skip this gate. CommandBehaviorTest supplies editable=true fixtures; TaskTagsTest explicitly asserts rejection with false, so tests currently preserve the reported defect.

The tag change already introduced a scoped REST1 tasks.task.update adapter, explicit routes, a dependent-ID plan and ExecutionLedger. AddTaskHandler also composes optional observer reads/writes after tags. Reuse these patterns with a custom-field port instead of exposing general legacy update.

## Goals / Non-Goals

Goals: equivalent ordinary-field admission, explicit custom input and a verifiable mixed-version result. No universal guarantee of portal write permission or acceptance of every custom type is implied. Scope exclusions are in proposal.md.

## Decisions

### 1. Distinguish schema, writable contract and effective rights

Replace ordinary-field metadata admission with a complete local schema for the existing WRITABLE subset: exact REST3 names, scalar/array element types, enums, dates, nullable representations and required create fields. Keep basic/advanced mutual exclusion and profile default precedence. No arbitrary field is admitted just because metadata says editable=true. task:fields:list continues returning raw portal metadata; missing editable remains unknown, not inferred true/false.

Remove field.list from ordinary advanced handler routes/preparation. All ordinary dry-runs become offline schema checks. Actual API rights/validation errors retain their existing classifications. Update docs/development.md, docs/configuration.md and README references to remote preflight. The existing tag and observer requirements explicitly referring to the old gate are modified in the delta.

Alternative: require an explicit metadata select and continue hard-blocking editable=false. That cannot satisfy #58's reported valid writes with false metadata. Select experiments can diagnose a portal discrepancy but are not a reliable admission contract. Removing all validation is also rejected: the static write schema replaces the gate.

### 2. Custom fields use their exact legacy API keys

New optional --user-fields / --user-fields-file PATH|- accepts an object of exact `UF_*` custom codes, e.g. `{"UF_QUERY":"5"}`; input must preserve JSON value types and detect duplicate keys. These are separate from request.fields, which remains REST3. Basic or ordinary advanced input may be combined with custom fields; tags keep their existing advanced mutual-exclusion behavior. REST3 `userFields` remains rejected with a specific new-option hint.

Permit scalars and flat scalar lists whose type/multiplicity can be confirmed by custom metadata. Read REST1 tasks.task.getFields before any mutations; reject missing fields or insufficient/unsupported metadata with a field-specific error. Exclude system UF_CRM_TASK, UF_TASK_WEBDAV_FILES and UF_MAIL_MESSAGE. Initial support is existing field values; defining fields, special file/CRM binding and arbitrary object payloads are out of scope. Empty string/list clearing requires matching field semantics; null is not an unverified universal clearing command.

Alternative: allow `userFields` inside --fields and silently convert to UF keys. This would mix distinct read/write contracts and API versions. Moving all task creation to REST1 is unnecessary and would alter ordinary field semantics.

### 3. Declare the full composition before credentials

Add a narrowly scoped TaskUserFieldGateway/REST1 adapter using existing ApiTransport. Route admission permits only custom schema read and verified UF update for explicit input. strict-rest3 checks every potential route, including custom schema read, before credentials/API.

Execution order: custom schema preflight; REST3 ordinary create/update if needed; REST1 custom update; existing TAGS write; optional observer steps. The plan and execution share this order, the same TASK_ID and cancellation checks. Custom-only update performs no empty REST3 write. The custom adapter confirms the selected task ID from the documented REST1 update result and treats malformed/mismatched confirmation as outcomeUnknown. No automatic API fallback, retry or deletion is introduced.

Dry-run with custom input performs only declared schema reads and displays unexecuted write steps; creation references the ID returned by its first mutating step. Document this distinction from offline ordinary advanced dry-run.

Use the existing ledger contract for confirmed/failed/unknown/skipped steps. Failure after create retains the task ID and exit3; cancellation preserves 130; a denied sole custom write remains the original failure. Recovery tells users to inspect that existing task and retry only task:update. Tests cover custom+tags+observer at each failure boundary.

## Source contract and evidence boundaries

Official documentation read on 2026-10-13:

- [REST3 task fields](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html): exact camelCase fields and booleans; userFields/tags/participants/object fields cannot be sent to add/update. Effective write rights depend on task/user context.
- [REST3 add](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-add-rest-v3.html): title, creatorId and responsibleId are required.
- [REST3 field.list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-field-list.html): editable is a selectable metadata attribute; examples omit it. Documentation does not establish why the author's portal returns false for every field.
- [REST1 update](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html): taskId plus fields object; result.task.id confirmation.
- [REST1 getFields](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-fields.html): description of standard and user fields, result.fields map. The exact custom descriptor/type/multiplicity shape must be recorded from a supported portal before enabling that type, rather than assuming REST3 metadata compatibility.
- [Legacy task fields](https://apidocs.bitrix24.ru/api-reference/tasks/fields.html): UF_* user fields; selected reads can return names using different casing, which must not rename write keys.

UF_QUERY legacy add/read success is an issue-author report, not a fresh check here. No live portal reads/writes have been performed in this planning turn. Implementation must add a sanitized raw metadata receipt and read-after-write verification for custom creation/update, plus ordinary advanced writes on the reported false-metadata shape.

## Risks / Trade-offs

- [Portal metadata defect remains unexplained] → capture explicit-select and default raw responses during acceptance; retain raw output, distinguish metadata from rights and avoid universal portal claims.
- [Required custom field prevents initial REST3 creation] → retain the API validation error and perform no dependent calls; this composition does not claim support for portals requiring an unsupported custom value during create. Record this limitation in help/acceptance.
- [Field types differ by portal] → enable only descriptor shapes grounded in docs and sanitized receipts; unsupported types fail before mutation.
- [Multiple writes are not atomic] → ledger, known-ID recovery and no automatic retry/rollback.
- [Parallel work changes handlers] → refresh dev before implementation and PR; preserve tags and observer regressions.

## Migration Plan

Deliver through a PR to dev for milestone 0.2.0. Existing basic flags remain unchanged; custom input is additive. Advanced ordinary input gains local documented validation and offline previews. Rollback reverts this scoped change; no data migration or custom-field definition changes are needed. Archive only after implementation and verification, then update the issue path under the repository completion workflow.
