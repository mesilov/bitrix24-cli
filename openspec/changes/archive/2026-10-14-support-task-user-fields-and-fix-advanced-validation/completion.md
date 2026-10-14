# Completion — issue #58

2026-10-14. Implementation: [PR #127](https://github.com/mesilov/bitrix24-cli/pull/127), merged into `dev` at 06:11:55 UTC. Final head `ad36f23c6273f5120bc08417695ab130ed423377`; merge commit `464bdb9947aaba4dbeb1794e080d1a2e0226f75c`. All 13 exact-head CI checks succeeded. [Issue #58](https://github.com/mesilov/bitrix24-cli/issues/58) was then closed as completed.

After the final dev synchronization and JTBD documentation fix, local unit verification passed: 796 tests, 12,545 assertions. JTBD has 22 unique scenario IDs and 123 existing local links. Portal evidence remains bounded by verification.md and the research receipts; archive-only work does not add new portal or runtime proof.

## Specification synchronization

Six added and four modified requirements were synchronized across task-console-runtime, task-mvp-scope and the new task-user-fields capability. Existing Purpose sections, unrelated requirements/scenarios and RC acceptance notes were preserved. New Purpose was copied from the delta. All delta blocks match their canonical requirements and no delta operation headings remain in main specs. `spec-sync-verification.json` records the comparison.

All 18 canonical specs pass strict validation. The active add-task-lifecycle delta retains its own admission/result changes and now preserves the accepted custom-field exception. A temporary-copy forward sync passed all 18 specs while preserving task:my, the 42 task commands, ordinary advanced validation and custom routes; see `active-delta-rebase-check.json`.

## Archive and retained results

All 9 tracked tasks and all schema artifacts are complete. Archive: `openspec/changes/archive/2026-10-14-support-task-user-fields-and-fix-advanced-validation/`. The archive and canonical specs are delivered in a separate MR to dev. Its merge must be confirmed before removing the task worktree.

Tracked SDK/portal receipts, verification, planning and initial delivery history move with the change. Ignored raw portal receipts and local check logs were copied outside the worktree to `/private/tmp/bitrix24-cli-issue-58-preserved-20261014/`; SHA256 copies were checked. The final implementation CI/merge and issue-close receipts are also saved there. Dependencies and generated test caches can be discarded. No credentials are included.
