# Design

## Context

See proposal.md for motivation and scope. Planning baseline: `origin/dev` at `6efba52` (2026-10-14). README currently contains platform installation instructions, connection selectors, large task scenarios, staff workflows and license information. `docs/configuration.md` already documents schema v1, credential storage, paths, defaults and runtime options. `docs/cli-command-map.md` reports 56 public commands and has an existing generator/check (`make cli-reference`, `make cli-reference-check`); the count must be refreshed during implementation.

`src/Infrastructure/Connection/ConfigDirectoryResolver.php` confirms Unix XDG/HOME paths (including macOS), Windows APPDATA, and explicit directory overrides. `src/Console/B24Application.php` exposes the connection/runtime selectors. No REST field or API contract is being introduced or changed; no API-dependent acceptance is claimed by this documentation plan.

The existing `binary-distribution` requirements apply to documentation collectively, not solely README. Moving checksums, manual install, inspect-before-execution and platform limits to a directly linked guide therefore needs no specification delta (`skip_specs: true`).

## Goals / Non-Goals

**Goals:** Give the requested README sections a clear reading order, keep quick starts executable, and maintain one detailed home for each topic.

**Non-Goals:** Installer or CLI changes, new OS/architecture support, changing config schema or selector precedence, release publication, native platform or portal acceptance. Related release verification remains in issue #109.

## Decisions

### 1. Keep the README entry path short

Use the proposed heading in proposal.md, badges, then «Скачать», «Установка», «Подключения и конфигурация», «CLI-команды», «Конфиг», in that order. Keep existing release asset names. Each section links directly to its detailed guide. Keep any necessary license attribution as a compact footer; move lengthy license explanations to the appropriate existing notices/docs.

Linux amd64 and macOS arm64 have separate short blocks using the existing shell installer and current-shell env activation. Windows amd64 has a minimal PowerShell download/verified extraction/PATH path using the existing archive process. All paths end in `b24cli --version`. Do not invent a PowerShell installer. Installer checksum enforcement remains automatic; any manual quick start retains checksum verification before extraction. Remove the requested standalone checksum paragraph, dev build block, historical release links, group-ID tutorial and development sentence from README.

Alternative considered: keep lengthy installation in platform spoilers. Rejected for installation because the user requested all details in `docs/install.md`; platform spoilers are requested for connections.

### 2. Give three guides clear ownership

| Document | Content |
| --- | --- |
| `docs/install.md` | Supported platforms and prerequisites, installer URL, inspect-before-execution, pinned tag, custom paths, manual checksum/extraction/PATH, updates, notices, CI artifacts and troubleshooting. Preserve macOS signing/launch details and Windows limits. |
| `docs/connection.md` | Hidden webhook input, `connection:add/use/show/list/remove`, read-only portal check, platform shell syntax, selectors and precedence, env-file and Docker/CI examples, local removal versus portal revocation. |
| `docs/config.md` | Purpose and OS directory rules, overrides, config schema, non-secret metadata and separate credential files, defaults, validation, telemetry, runtime JSON and its distinction from managed `config.json`. |

Move the detailed config material from `docs/configuration.md` into `docs/config.md`. Retain a short `configuration.md` pointer to the new guide rather than a second full copy, and update maintained incoming links. This keeps old entry links useful while making the requested filename canonical. Use relative links, and adjust them for the new file depth. Connection details should link to config reference instead of duplicating the schema.

Alternative considered: duplicate the configuration guide under both filenames. Rejected because it would leave two editable sources and allow drift.

### 3. Common connection instruction with three spoilers

Explain the common flow once: save a named profile with hidden input, select it, then use `users:current --json` (an existing read command) to check the portal connection. Platform `<details>/<summary>` blocks give Linux/Bash, macOS/zsh and Windows/PowerShell syntax. Advanced selectors and CI input belong in the connection guide. Do not place a literal webhook in shell command arguments/history. Do not imply that a local `connection:show` verifies portal access.

### 4. Derive the command overview from the public catalog

Use the actual registered CLI commands and generated map as the source, grouped compactly by purpose. Include connection, users, doctor and task families as present; provide `list`, `help COMMAND` and the full map link. Lengthy task examples (including group filtering) go to `docs/task-jtbd.md` or another suitable thematic guide, with links from the compact overview. Do not lose current custom-field, lifecycle, deadlines, results, files and output-format details when shrinking README.

Run `make cli-reference-check` during implementation and refresh the map only if it differs from the actual catalog. The docs count is baseline evidence, not a hard-coded target for 0.2.0. Keep descriptions grounded in implemented commands and preserve exact command/flag/JSON names.

### 5. Validate the documentation at its actual evidence level

Check heading order, removed text, preserved badges/downloads, links/anchors after extraction, three install paths, three connection spoilers, and config coverage. Review the GitHub rendering of Markdown and spoilers; use existing CLI reference checks for the catalog. No new implementation-mirroring tests are needed for prose extraction. Record local documentation checks separately from platform execution, CI and portal results.

## Risks / Trade-offs

- Lost details during extraction → compare source sections with destination guides before deleting the originals.
- Broken anchors or image/link paths → inspect incoming references and validate relative links after the move; retain the old configuration entry stub.
- Stale workflow or command facts → recheck the target dev revision at implementation and use existing CLI catalog tooling; coordinate final snapshot with #109.
- Short Windows quick start grows due to checksum/PATH → keep the minimum working verified flow and put explanatory text in install.md.
- Confusing managed config with runtime `--config` → make the distinction explicit in config.md and concise in README.

## Migration Plan

Extract the guides first, update their relative links, replace README blocks with quick starts and references, then check the whole navigation together. Deliver through a PR to `dev`; reverting the documentation commit restores the previous layout. No user-data migration or runtime change is required.

## Review follow-up

Review of PR #135 identified a missing issue-linked changelog entry. Add the delivered README/guide result under `Unreleased` and verify the actual PR diff contains #134. Integration of dev at `5330662` also changes development packaging instructions: use the builds workflow, its selected source SHA and 30-day archive retention; refer maintainers to the existing builds/RC guide rather than retaining the obsolete dev dispatch example. README itself remains unchanged by these corrections.

A second dev update at `35ee28e` changes the moved status-duration section. Keep the compact README and preserve that upstream section exactly under `docs/task-usage.md`: exclude weekends by the created offset and describe seconds/hours/days. This resolves the move/edit conflict without dropping either documentation change.
