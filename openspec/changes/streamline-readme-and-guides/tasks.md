# Tasks

## 1. Extract the detailed guides

- [x] 1.1 Create `docs/install.md` from the current installation/download details, including manual verification, PATH, updates, CI artifacts, inspect-before-execution, notices and platform limits; compare each removed README installation subsection with its guide destination and check relative links.
- [x] 1.2 Create `docs/connection.md` for safe credential input, profile management, selectors, env files and Docker/CI; verify examples against the current command help and distinguish a local profile read from a read-only portal check.
- [x] 1.3 Move the configuration reference to `docs/config.md` and replace `docs/configuration.md` with a short navigation pointer; verify directory paths against `ConfigDirectoryResolver`, schema coverage against `docs/config.schema.json`, and incoming links/anchors with `rg` plus link validation.

## 2. Reshape README

- [x] 2.1 Add the proposed b24cli heading/agent positioning, preserve CI badges and download links, remove the historical release/acceptance and standalone checksum/dev-CI paragraphs; verify heading order and compare badge and asset URLs before/after.
- [x] 2.2 Provide separate Linux amd64, macOS arm64 and Windows amd64 happy paths ending in `b24cli --version`, linked to install.md; verify shell syntax and existing installer/manual verification behavior without inventing unsupported installer targets.
- [x] 2.3 Replace connection details with a common instruction and three Linux/macOS/Windows `<details>` blocks, linking connection.md; review GitHub rendering and confirm no literal credential appears in shell history examples.
- [x] 2.4 Add the short «Конфиг» section with purpose and platform locations, linked to config.md; verify Linux/macOS XDG/HOME rules, Windows APPDATA and directory overrides against current implementation.
- [x] 2.5 Replace long CLI scenarios with a compact catalog and thematic doc links, relocate useful task/user details, remove the requested group-ID tutorial and development sentence; verify each removed topic has a suitable destination and run `make cli-reference-check` against the actual public catalog.

## 3. Integrated review and traceability

- [x] 3.1 Review README and guides together against every criterion in #134: section order, three installation paths, three connection spoilers, complete guide destinations, working relative links/anchors and current-build language; record documentation checks and any platform execution separately.
- [x] 3.2 Run `openspec validate streamline-readme-and-guides --strict` and `git diff --check`; confirm issue #134 still has milestone 0.2.0, documentation category and bidirectional proposal/path links before the implementation PR to dev.

## Workflow follow-up

- Preserve final release snapshot and binary acceptance in #109; do not mark those tasks done from documentation checks.
- After verified implementation and confirmed merge/issue closure, archive the change through the repository workflow, update the issue's archive path, deliver the archive to dev and remove the worktree after preserving its contents.
