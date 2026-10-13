## 1. Ordinary advanced validation

- [ ] 1.1 Replace editable admission with a complete documented local schema for current REST3 WRITABLE names/types/enums/arrays/nullability; verify basic/advanced equivalence and false/missing/null metadata cases plus invalid types, status, relation objects and unknown-key rejection in targeted unit tests.
- [ ] 1.2 Remove ordinary advanced metadata-gate routes and update affected TaskTagsTest, CommandBehaviorTest, GroupIdOptionTest and DefaultObserverTest assertions; verify ordinary advanced dry-run makes zero API/credential calls and API permission/validation failures retain their existing classification.
- [ ] 1.3 Update command help, README, docs/development.md and docs/configuration.md to explain local schema checks and raw metadata; verify documented advanced examples and offline help through ConsoleHarness.

## 2. Custom input and metadata contract

- [ ] 2.1 Record sanitized tasks.task.getFields custom-field descriptors and corresponding official contracts, including UF_QUERY type/multiplicity and clearing representation; verify the receipt contains no credentials and identifies which scalar/list types can be enabled.
- [ ] 2.2 Add user-fields inline/file/stdin inputs, exact UF_* keys and request storage separate from REST3 fields; test duplicate JSON keys, empty/conflicting sources, complex/unsupported values and excluded system UF keys fail before credentials/API. Add help/examples describing the supported types and unchanged omitted fields.
- [ ] 2.3 Implement scoped custom schema-read/write gateway and REST1 adapter; fixture tests verify exact method/version/params, metadata shape/type/multiplicity errors before any mutation and result.task.id confirmation/mismatch/outcomeUnknown. Test actionable rejection of REST3 userFields pointing to explicit custom input.

## 3. Planned composition and outcomes

- [ ] 3.1 Wire explicit routes/catalog/policy for custom schema reads and writes; tests prove strict-rest3/exit4 before credentials/API, dependent-ID dry-run plans, custom-only update without REST3 writes and ordinary operations retaining REST3. Document custom dry-run schema reads.
- [ ] 3.2 Integrate custom fields before existing tags/observer writes using the shared ledger semantics; tests exercise create, mixed update and custom-only update, failures at each step, mismatched ACK, response loss, cancellation/exit130, retained TASK_ID, skipped later steps and no retry/rollback.
- [ ] 3.3 Test custom+tags+observer composition with the same selected/created ID, existing basic/advanced/tag mutual exclusions and profile-default precedence; update recovery help to require inspection and task:update against the existing ID.

## 4. Integration verification and delivery

- [ ] 4.1 Run make test-unit, make check and make lint-all in the isolated worktree; save actual results in verification.md and validate change with openspec validate support-task-user-fields-and-fix-advanced-validation --strict.
- [ ] 4.2 Perform bounded portal acceptance for scalar UF_QUERY create/update/read-after-write, a supported multiple field and confirmed clearing semantics; record exact field/type/route/results and distinguish them from fixture proof. Verify unknown/denied fields stop before writes and retain the required-custom-field creation limitation.
- [ ] 4.3 Capture default and explicit-select raw REST3 metadata for ordinary title/creatorId/groupId and demonstrate basic/advanced write equivalence on the tested portal. Record metadata values and effective API rights separately; do not label an untested false-metadata portal accepted.
- [ ] 4.4 Refresh dev, review issue #58 assignee/type/label/milestone and bidirectional OpenSpec links, verify all planned requirements against the final diff, publish implementation PR to dev and report actual CI state without closing #58 from a planning-only PR.

## Workflow follow-up

- After verified implementation merge and issue closure, archive/sync the change under the maintainer workflow, deliver the archive through dev, update the issue path and remove only this task's worktree after preserving all artifacts.
