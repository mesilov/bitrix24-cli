# Planning check — 14.10.2026

- Issue: [#121](https://github.com/mesilov/bitrix24-cli/issues/121), OPEN, enhancement, milestone 0.2.0 (number 2).
- Source dev: `5ed034980e58ed950c3c733f3f01ec5ca08dd990`.
- Before publication integrated fresh dev `48e010c7365b996fe58c8fda14d93f5d94693193`. Registration delta follows its normalized requirement statement and retains its separate Command registration validation requirement. Strict validation/diff check rerun after synchronization.
- MR #123 update from dev on 14.10.2026: integrated `475f652560b4d88c4aeb9f93d5f2caad28c2fca1`, including delivered #60 lifecycle and #124 normalization archive. Current CommandCatalog has 41 task commands; duration delta plans 42 and preserves lifecycle integration coverage. Canonical runtime main spec still says 34 until lifecycle archive; the plan records this rather than editing that unrelated spec. Original implementation worktree has uncommitted work, so publication uses only committed plan in a separate temporary checkout. Strict validate/diff/changelog checks repeated after sync; no runtime changes belong to this MR.
- Fresh isolated worktree effective MCP config: enabled bitrix24_dev, Streamable HTTP https://mcp-dev.bitrix24.tech/mcp; real search/method-details/history and search/article-details/REST3 fields answered.
- Official sources are linked in proposal/design; get id/select/status/statusChanged/changed are REST3, history taskId/createdDate/field/value.from/to are REST1. REST3 get does not return created.
- `openspec validate calculate-task-status-durations --strict`: PASS.
- `openspec status --change calculate-task-status-durations`: 4/4 planning artifacts complete; implementation 0/8 tasks, no runtime changes.
- `git diff --check`: PASS.
- Reviewed against HistoryScanner/Rest1TaskHistoryAdapter, CommandCatalog, task-console-runtime, ResultPresenter/TaskHumanRenderer and ListTaskHistoryCommandTest.
- Fixtures example checks expected calendar sum 30+50+40=120 with two reopen cycles; example is a proposed acceptance fixture, not an executed implementation test.
- Portal calls/status event mapping/history completeness/calculator tests were not run. Existing title-history integration coverage does not prove complete status history.
- Changes are planning files plus a substantive CHANGELOG entry linking #121. API code/main specs remain unchanged. Before implementation reconcile inventory with current dev/#118 and run live status acceptance.

- Dev advanced again before push: integrated `2b24edd1a808150fb382812c52d22b235e25d940` (#122 task:my); resolved only CHANGELOG conflict retaining both entries. Current catalog baseline is 42, duration plan 43; preserved seven lifecycle commands and task:my. Earlier counts are dated checkpoints, not the current baseline. Validation repeated after this merge.
