# Tasks

## 1. Profile working roles and configuration contract

- [ ] 1.1 Extend ProfileRepository object handling, strict validation and atomic publication for default.users observerId/managerId/autoAddObserver; verify shared fixtures cover empty/omitted objects, positive integer literals, boolean false/true, overflow, unknown keys and true without observerId without file mutation/API.
- [ ] 1.2 Extend docs/config.schema.json and shared schema-cases.json with the same structural contract; verify ConfigSchemaContractTest and build/tests/config-schema-check.py agree, preserving documented integer-literal schema/runtime differences.
- [ ] 1.3 Add connection:add --observer-id/--manager-id/--auto-add-observer true|false, safe inspection and a separate ConnectionManager role accessor; verify ConnectionProfilesTest covers round-trip, local-only operation, explicit-profile precedence and no role leakage to env/file sources.
- [ ] 1.4 Document configuration paths, role fields, opt-in example and existing-profile editing in docs/configuration.md and help; verify examples match writer/schema and pre-1.0 README describes current interface without migration narrative.

## 2. User directory reads and search

- [ ] 2.1 Add UserDirectoryGateway and REST1 adapter for user.current/user.get using ApiTransport; verify fake transport tests assert exact method/version/FILTER/select names, canonical ID, missing fields, duplicate/conflicting IDs and malformed response handling without changing task enrichment behavior.
- [ ] 2.2 Implement typed users:current and users:find application requests/handlers, Unicode name-token matching and exact email criteria; verify zero/one/multiple candidates, reversed ФИО order, name-only matching, invalid inputs before credentials and no automatic assignment/config writes.
- [ ] 2.3 Implement bounded pagination shared by search and colleagues with limit/all/max-scan and RuntimeState; verify a match after the first page, complete empty output, output limit vs scan cap, later-page failure, repeated next and cancellation preserve correct partial/exit semantics.

## 3. Organization relationships

- [ ] 3.1 Add DepartmentGateway/REST1 department adapter and current-department selection; verify exact ID/PARENT/UF_HEAD fields, single/multiple/missing departments and foreign --department-id rejection before further queries.
- [ ] 3.2 Implement users:colleagues with employee/ACTIVE/UF_DEPARTMENT filters and self exclusion; verify unrelated/child departments, user duplicates, no visible colleagues and source/completeness metadata.
- [ ] 3.3 Implement users:manager org/config sources with bounded parent traversal; verify distinct head, self-head, absent head, valid root/no-manager, cycle/100-hop limit, inaccessible rows and no org/config fallback or cross-profile manager leakage.

## 4. Console, shared runtime and integration ownership

- [ ] 4.1 Register four Users commands, explicit routes and DI through CommandCatalog/config/services.php, reusing CommandRunner; verify registration retains the 30 task and 5 local connection commands, aliases do not expand scope, all new help/list/completion work offline and standard policy uses permitted v1 methods.
- [ ] 4.2 Extend ResultPresenter for user-card/user-list and document users examples in README/help; verify stable REST1 field names, TSV column order/escaping, JSON data/meta/error, unavailableFields, quiet/NO_COLOR and safe diagnostics without fixture secrets.
- [ ] 4.3 Generalize common live command harness/coverage ownership for Task and Users while keeping task fixture specialization; verify every catalog command owns one dedicated class/happy-path and the guard still rejects invoking another CLI command.
- [ ] 4.4 Add dedicated live happy paths for each users command with independent SDK readback and read-only org/user fixtures; verify actual execution when a suitable portal is available, and record absent data/scope as bounded skips rather than portal acceptance.

## 5. Opt-in default observer on task creation

- [ ] 5.1 Carry configured observer separately from REST3 fields in TaskInputMapper/AddTaskRequest; extend complete potential route plan only when selected profile autoAddObserver=true; verify basic/advanced inputs, false/omission/env isolation and existing explicitly selected strict policy rejecting before credentials/create.
- [ ] 5.2 Extend AddTaskHandler with confirmed creation, current participant read, deduplicated union and conditional REST1 assignment through ParticipantGateway; verify exact taskId/AUDITORS payload, other observers retained, already-present observer skips update and no unsupported auditors/role fields enter REST3.
- [ ] 5.3 Preserve task:add data.operation/taskId and add complete/ledger for companion outcomes; verify read/update failure after create returns partial/exit3, unknown outcomes and Ctrl-C retain known taskId, and unconfirmed create prevents all companion/retry/rollback actions.
- [ ] 5.4 Document and test basic offline dry-run and advanced preflight boundaries plus a disposable live task:add observer scenario; verify planned methods/default source, independent SDK membership readback and cleanup of only owned task fixtures without claiming skipped tests passed.

## 6. Combined verification and review readiness

- [ ] 6.1 Run make test-unit, make lint-all, config-schema check and targeted live integration groups for the changed commands; verify terminal results and record local/CI/live/skipped evidence separately, without extending tests after clean passes unless new changes or failures require it.
- [ ] 6.2 Run openspec verify/validation and create verification.md mapping all requirements/JTBD to implementation and evidence; verify issue #63 scope, milestone/type/label, exact API names/types and bidirectional OpenSpec path before marking the implementation PR ready for review.

## Workflow follow-up

- Complete the openspec-apply-change workflow after a separate implementation request; keep this planning PR draft and implementation checkboxes unchecked meanwhile.
- Deliver implementation through the existing feature PR into dev; verify actual merge and issue closure when integration is authorized, rather than equating passing checks with merge.
- Archive the fully implemented/verified change, synchronize delta specs and update issue #63 to the archive path using maintainer/openspec-archive-change workflow; preserve archive delivery before worktree cleanup.
- Preserve the current worktree while the change is pending. After delivery, remove only its own safely saved worktree and verify git worktree list.
