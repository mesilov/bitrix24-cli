## 1. Ordinary validation

- [x] 1.1 Replace editable gate with documented local per-operation types/enums/lists/dates; add tests for valid false/missing metadata and invalid raw fields.
- [x] 1.2 Remove ordinary metadata routes and update advanced/default/group/tag assertions; verify zero-request ordinary dry-run and remote errors.

## 2. Custom creation and update

- [x] 2.1 Implement inline/file/stdin custom inputs and native REST1 ordinary create JSON; test exact names/default precedence, duplicate keys, unsupported values and special UF rejection before credentials.
- [x] 2.2 Implement scoped schema/add/update adapter and type validation; fixture tests confirm native payload, single legacy create, required-field API errors and task-ID ACK checks.
- [x] 2.3 Integrate declared routes/DI/catalog, strict pre-credential rejection, custom-only and mixed updates with cancellation/partial/unknown/skipped steps; test tags/observer composition and known-ID recovery.

## 3. Documentation and acceptance

- [x] 3.1 Update Russian help, README/configuration/development and Unreleased CHANGELOG for #58; verify offline help and documented native examples.
- [x] 3.2 Run targeted and full unit tests, make check and make lint-all; record exact results and validate OpenSpec --strict.
- [x] 3.3 Perform fresh CLI portal create/update/readback for existing string/numeric/multiple custom values, clearing, required/unknown field errors, ordinary advanced equivalence and strict/dry-run; remove all owned fixtures and record bounded evidence.
- [ ] 3.4 Verify requirements/design/task coverage, refresh and merge dev, confirm issue #58 milestone/type/links and changelog diff; publish PR to dev and record current CI separately.

## Workflow follow-up

- After verified implementation merge and issue closure, archive/sync/deliver the change, update the issue path and clean up only this worktree under maintainer rules.
