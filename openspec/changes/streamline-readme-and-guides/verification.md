# Verification: streamline-readme-and-guides

Verified on 2026-10-14 against issue [#134](https://github.com/mesilov/bitrix24-cli/issues/134), proposal, design and the implementation in [PR #135](https://github.com/mesilov/bitrix24-cli/pull/135) targeting `dev`.

## Scorecard

| Dimension | Result |
| --- | --- |
| Completeness | 12/12 tracked tasks complete; all requested sections and guide destinations present. |
| Correctness | Documentation matches the current CLI catalog, configuration resolver and schema. Spec coverage, requirement mapping and delta scenario coverage: not applicable (`skip_specs: true`, documentation-only change). |
| Coherence | All five design decisions followed. Existing Markdown conventions and generated command-map workflow retained; PHP/Python changes only redirect documentation references. |

No critical findings, warnings or suggestions remain from this review.

## Implementation evidence

- README reduced from 993 to 177 lines. The requested five sections appear in order; the four original badges and three original latest-release download URLs are preserved. Historical release/acceptance links, standalone checksum/dev-CI prose, the group-ID tutorial and development sentence are absent.
- Separate Linux/Bash, macOS/zsh and Windows/PowerShell installation paths end in `b24cli --version`. Linux/macOS use the existing installer; Windows downloads both files from one release asset list, verifies the archive before extraction and sets user/current-session PATH. No installer or runtime behavior changed.
- Three connection spoilers use hidden webhook input, explicit profile selection and an existing read-only `users:current` portal check. `docs/connection.md` distinguishes local metadata commands from actual portal access.
- `docs/install.md`, `docs/connection.md` and `docs/config.md` contain detailed instructions. `docs/task-usage.md` and `docs/users.md` retain the long task/staff material. The initial extraction retained all 43 README examples (doctor examples already exist in its dedicated guide); after the dev synchronization, one obsolete dev-dispatch example is replaced with the builds-dispatch example.
- `docs/configuration.md` is a short pointer. Maintained links, the generated map, schema description and config-example validator use the canonical new guide. Paths agree with `ConfigDirectoryResolver`: Unix XDG/HOME and Windows APPDATA, with explicit overrides.
- README command table contains each of the 56 public commands exactly once, compared with the JSON catalog emitted by `scripts/cli-reference.php --json`.

## Checks performed

| Check | Result |
| --- | --- |
| `make cli-reference` and `make cli-reference-check` | Map regenerated for the config link; matches 56 public commands. |
| `make test-unit TEST_ARGS='--filter HelpAndAboutTest'` | 5 tests, 588 assertions passed. |
| `make test-unit TEST_ARGS='--filter "ConfigSchemaContractTest\|ConnectionProfilesTest"'` | 173 tests, 711 assertions passed. |
| `build/tests/config-schema-check.py` with CI-pinned `jsonschema[format]==4.25.1` in a temporary venv | 146 shared fixtures and 3 documentation JSON examples passed; 8 additional runtime-check distinctions retained. |
| `make lint-cs-fixer` | 0 files requiring changes out of 339 checked. |
| `make lint-phpstan` | No errors. |
| Relative Markdown navigation review | 301 file/anchor references across README, build guide and 11 docs checked; no missing targets. Explicit HTML anchors included. |
| Shell parser | `sh -n` passed for 24 blocks in README/install/connection guides; commands not executed. |
| PowerShell parser | Official PowerShell 7.6.7 on macOS parsed 9 blocks in README/install/connection/config guides; no syntax errors; commands not executed. |
| GitHub GFM renderer and browser DOM inspection | Five section headings, three tables and three initially collapsed connection spoilers rendered; each spoiler contains its platform commands. |
| GitHub source/asset readback | `main/install.sh` exists; latest release `0.1.0` exposes all three named platform archives plus SHA256SUMS. No new release claim. |
| `openspec validate streamline-readme-and-guides --strict` | Valid with intentional `skip_specs: true`. |
| `git diff --check` and staged check | Initial staged review found surplus blank EOF lines in three extracted guides; trimmed. Complete diff against dev then passed. |

## Evidence boundaries and delivery

This verifies documentation, parser syntax and existing offline contracts. No new REST contracts were introduced or changed; examples retain their existing API/JSON field names. Native installation on Linux/macOS/Windows, first-launch OS policies and live portal commands were not executed during this documentation task. Final release/native acceptance remains in #109. Remote CI is reported separately in the PR; local checks do not establish its result.

The change remains active while PR #135 is reviewed. After confirmed merge and issue closure, archive and worktree cleanup follow the repository's maintainer workflow.

## Review correction receipt (2026-10-14)

- Review thread `PRRT_kwDOVACKOM6sPBCA` requested the missing substantive issue-linked Unreleased changelog entry. `CHANGELOG.md` now describes the README, three platform install paths, 56-command overview and detailed guides, linking #134. The actual diff against current `origin/dev` includes this entry.
- Integrated dev `5330662` by a regular merge without conflicts. Its builds/RC workflow changed extracted CI instructions: `docs/install.md` now uses `--ref builds`, documents RC tag versions, 30-day archive / 7-day diagnostics retention and links to the canonical build guide. No workflow or runtime behavior is changed by the review fix.
- Rechecked 302 relative links/anchors across 13 Markdown documents, unchanged README heading order/badges/download URLs/three spoilers, all 56 commands, 24 shell blocks and schema's 146 fixtures / 3 examples. `make cli-reference-check`, OpenSpec strict validation and complete diff whitespace checks passed.
- README remains the same 177-line file. The prior local tests and remote checks above describe the original implementation; fresh CI for the review commit is reported separately in the PR.
