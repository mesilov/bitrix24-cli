# Tasks

## 1. Recent-first bounded scans

- [x] 1.1 Set default id DESC for list/find, expose shared validated --order on find and preserve output order; verify >10000-task regression, explicit/custom order and invalid-input tests.
- [x] 1.2 Document current order, local filtering and examples in command help/README/CHANGELOG; verify help/completion without API calls and check documentation against the mapper.

## 2. Partial scan diagnostics

- [x] 2.1 Add effective meta.order, budget hint and partial reason/scanned/order diagnostics in all formats; verify empty/nonempty partial results, stdout separation, exit3 and --all budget independence.

## 3. Integration verification

- [x] 3.1 Run full offline unit suite, Composer/platform/syntax checks and all quality linters; validate OpenSpec and record scenario-to-test/code evidence plus portal verification boundary.

## Workflow follow-up

- Fetch and merge current origin/dev immediately before publishing the branch and opening the MR to dev; verify issue category, milestone and bidirectional OpenSpec links.
- After confirmed merge to dev and issue closure, synchronize/archive OpenSpec, deliver archive through dev MR and remove the clean task worktree.
