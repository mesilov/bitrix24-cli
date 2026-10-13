## Context

Issue [#113](https://github.com/mesilov/bitrix24-cli/issues/113) continues the delivered help/about work from #100. `ApplicationInformation` already provides a Russian overview, but its usage placeholders and the delegated global option renderer retain English text. Its flat command-name sort prints «Общие» for `completion`, then namespaces, then another «Общие» for `help` and `list`.

`TaskHelpDescriptor` localizes only part of catalogued command help, delegates argument/option rendering to Symfony `TextDescriptor`, and falls back to Symfony for builtin commands. `TaskHelpCommand` uses it only for text output; other formats obtain native metadata. Symfony supplies English headings and default/repeatability annotations, while `B24Application`, builtin commands and some application commands (including `doctor:problem-trajectory`) still contain English descriptions. `scripts/cli-reference.php` derives the documentation map from these descriptions.

## Goals / Non-Goals

**Goals:** Russian prose across root and command help; both command-help routes; a single «Общие» group; consistent human metadata in supported help formats; source-derived docs and offline regression coverage.

**Non-Goals:** additional locales or locale selectors; translating executable identifiers, enums, API fields or JSON keys; translating unrelated runtime errors/business output; changing API operations or structured output schemas; building release 0.2.0.

## Decisions

### 1. Translate metadata and render human annotations in the project layer

Inventory command descriptions, arguments, options, processed help and example explanations across all public namespaces and builtin commands. Translate ordinary prose, including generic usage placeholders, while preserving actual argument names and literal CLI/API values. Keep shared global descriptions in one project-owned source so root and command help agree.

Register thin project-owned builtin adapters or configure their metadata through supported Symfony APIs. Preserve option modes, shortcuts, defaults, suggested values and argument definitions when replacing native definitions; do not edit vendor files. Extend the project text descriptor to cover builtin and application commands and Russian headings/default/repeatability markers, including the root global option section. Keep command-specific examples and the existing help command's target-reset behavior.

Alternative: replacing English strings in a completed output buffer. Rejected because it can change literal examples and identifiers and does not localize metadata consumed by other formats. Native Markdown/XML/JSON help keeps its formatter/schema and reads the same translated human metadata.

### 2. Build groups before printing the root catalog

Read the application registry on each overview request, exclude hidden commands, and partition by the first namespace segment; commands without `:` enter «Общие». Print «Общие» once first, then namespace groups ordered by name, and sort names within each group. Preserve namespace identifiers such as `connection`, `doctor`, `task` and `users`.

Do not hardcode the current catalog or its count. Regression coverage registers extra public unnamespaced/namespace commands and a hidden command to establish membership, order and uniqueness as the catalog grows.

### 3. Verify language with an explicit inventory and representative assertions

Exercise every registered public command through `COMMAND --help` and `help COMMAND`, and explicitly cover `completion`, `help`, `list` and doctor metadata. Assert Russian headings/descriptions and removal of known English annotations using the translation inventory; do not reject all Latin text, which would reject valid command names, URLs and API identifiers. Verify supported help formats through their native structure and translated descriptions.

Reuse offline runtime smoke coverage for both application entrypoints, root help, about, list, command help and supported shell completion. Keep ANSI/non-TTY/quiet/silent behavior, exit statuses, no-credential/no-config-write guarantees, parser-valid examples and completion syntax checks. No portal access is needed for this presentation-only change.

### 4. Regenerate documentation and carry release recheck forward

Regenerate the CLI map from localized metadata, update README/help navigation and explanations, and keep exact command identifiers and existing JTBD scenario IDs/coverage. Update CHANGELOG to describe delivered behavior during implementation. Release issue [#109](https://github.com/mesilov/bitrix24-cli/issues/109) remains the final-artifact recheck boundary; this change does not build or publish that release.

## Risks / Trade-offs

- **Symfony renderers contain hardcoded English annotations:** maintain a narrow project descriptor with tests against the installed dependency; preserve definition semantics when adapting builtin metadata.
- **Mixed prose and literal tokens can be mistranslated:** review the inventory and parser-valid examples; explicitly assert stable identifiers and structured-format keys.
- **Snapshot assertions can become brittle as commands grow:** use registry-driven coverage and dynamic grouping fixtures; assert invariants rather than a fixed command count.
- **Translation can change width/wrapping:** smoke-test decorated and non-TTY output, including quiet/silent behavior.

## Delivery

Implement under #113 after approval of the plan, verify the delta against `cli-experience`, deliver a separate implementation PR into `dev`, then synchronize and archive the completed change. Planning publication does not mark implementation tasks complete.
