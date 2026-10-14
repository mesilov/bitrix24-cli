# Proposal

## Why

README mixes a quick start with almost a thousand lines of installation, configuration and task scenarios. For 0.2.0 it should explain the agent-friendly CLI and lead users through download, installation and connection, while detailed references remain accessible in docs.

## What Changes

- Use the proposed positioning: **b24cli — command line client for Bitrix24: автоматизируйте работу с задачами из терминала и AI-агентов**. Preserve the CI badges.
- Keep the download table for Linux amd64, macOS arm64 and Windows amd64. Remove the introductory 0.1.0/acceptance links and the standalone SHA256SUMS and dev-build paragraphs from README.
- Retain three short platform installation paths ending in `b24cli --version`; move detailed installation, checksum, PATH, updates, CI artifacts and troubleshooting to `docs/install.md`.
- Give a common connection workflow and platform details in GitHub spoilers; move advanced connection selection and examples to `docs/connection.md`.
- Refresh the compact CLI overview against the registered public catalog and `docs/cli-command-map.md`; move lengthy scenarios into appropriate docs and link them from the overview.
- Add a short «Конфиг» section explaining purpose and OS locations, with details in `docs/config.md`. Reconcile the existing `docs/configuration.md` and its incoming links.
- Remove the requested group/project ID examples and explanatory block, and the closing development/connection/build sentence. Preserve useful technical information in suitable docs.

## Capabilities

### New Capabilities

None. This is a documentation reorganization with `skip_specs: true`.

### Modified Capabilities

None. The existing `binary-distribution` documentation requirements continue to apply: checksum verification, activation, inspect-before-execution, manual installation and supported-platform information are retained in the linked guide. CLI, connection, API, config schema and installer behavior remain as currently specified.

## Impact

Documentation only: `README.md`, new `docs/install.md`, `docs/connection.md`, `docs/config.md`, existing configuration links and thematic guides as needed. No dependencies, runtime or REST contracts change. Describe the current build without pre-1.0 migration history. Final release-binary verification remains in #109.

## GitHub issues

- [#134](https://github.com/mesilov/bitrix24-cli/issues/134) — all README restructuring and guide extraction requirements; milestone `0.2.0`, label `documentation`, intended type `Task`.
- Related [#109](https://github.com/mesilov/bitrix24-cli/issues/109) owns final 0.2.0 release checks.
