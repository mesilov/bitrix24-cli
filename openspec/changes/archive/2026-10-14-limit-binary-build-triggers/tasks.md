# Tasks

## 1. Trigger policy and documentation

- [x] 1.1 Limit `package-static.yml` to manual dispatch and pass the selected `github.sha`; verify the parsed event list excludes pull requests and branch pushes and still calls the shared builder.
- [x] 1.2 Document manual Actions/CLI dispatch and release-only automatic builds in README and the build guide; verify workflow filename, branch selection and artifact name against the configuration.

## 2. Integration verification

- [x] 2.1 Parse all workflow YAML, compare release/platform/source-quality workflows with the base revision, and run existing source/publication checks; verify successful results without compiling binaries.
- [x] 2.2 Verify the change against its scenarios, validate OpenSpec strictly, and record issue/milestone/backlink and branch-rule evidence in a verification report.

## Workflow follow-up

- Fetch and merge current `origin/dev` before opening the review PR to `dev`; repeat affected checks and inspect normal CI without dispatching packaging.
- After confirmed merge to `dev` and issue closure, sync/archive the change, update the issue path, deliver the archive through a PR and remove this task's worktree according to the maintainer skill.
