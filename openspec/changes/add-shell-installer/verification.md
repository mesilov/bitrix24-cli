# Verification: add-shell-installer

Date: 2026-10-13. Issue: https://github.com/mesilov/bitrix24-cli/issues/28.
Milestone: `0.2.0`; existing labels: `enhancement`, `documentation`.

## Scorecard

| Dimension | Evidence |
| --- | --- |
| Completeness | 9/10 tasks complete; native installer CI succeeded; public script task 5.1 remains open |
| Correctness | All 8 added requirements mapped below; 14 of 15 scenarios locally exercised, public quick-start URL scenario pending |
| Coherence | POSIX shell, existing Release assets, staged replacement, shell profiles and opt-out follow design |

## Requirement and scenario mapping

| Requirement | Implementation | Acceptance |
| --- | --- | --- |
| Unix shell installer | install.sh option/prerequisite/target checks | CLI errors, macOS/Linux targets, unsupported target, missing utility |
| Public release selection | fixed GitHub Releases URL; latest effective tag resolved once; explicit tag; curl -q without credentials | fixture URL log, stable and prerelease, missing latest/archive/SHA256SUMS; actual anonymous 0.1.0 explicit/latest on macOS |
| Verified user installation | unique exact checksum record, SHA-256, archive member/type validation, required notices, candidate and destination-stage --version | invalid/missing/malformed/duplicate/mismatched checksum; invalid archive, missing license, traversal/symlink, extraction refusal, version and execution failures |
| Preserve existing installations | candidate checks before destination changes; binary staged in destination filesystem then renamed; owns only binary/package/env | sentinel bytes preserved in 18 failure scenarios; repeat/update retains connection config and user data |
| Persistent shell activation | env-file with quoted absolute directory; PATH deduplication; ZDOTDIR .zshrc, .bashrc, selected bash login profile | current sh, bash interactive, selected login profile, native macOS bash login, zsh interactive/login; clean initial PATH; other working directory; repeat activation |
| Installer customization | --install-dir and --no-modify-path | spaces and apostrophes in HOME/custom paths, exact output path, unchanged profiles, no dot PATH entry |
| Installer distribution documentation | README + build/README quick-start, inspect-before-execution, manual alternative, #23/#25 | files/installation anchor checked; public main URL currently HTTP 404, documented explicitly |
| Installer acceptance evidence | shell-installer-check.py, Shell installer workflow, additive Unix package-platforms checks | local/native/public evidence kept separate below; existing packaging checks retained |

## Local evidence

- `sh -n install.sh`: passed.
- `python3 build/tests/shell-installer-check.py`: 6 tests passed, native test skipped without archive argument; failure test contains 18 subcases.
- `python3 build/tests/shell-installer-check.py --assets /private/tmp/b24cli-issue28-public-assets --version 0.1.0`: 7/7 passed on native macOS arm64, including actual compressed public archive.
- Real production curl, isolated HOME: `sh install.sh --version 0.1.0 --no-modify-path`, then default latest installation and env activation from `/`: passed, `b24cli 0.1.0`.
- Archive URL: https://github.com/mesilov/bitrix24-cli/releases/download/0.1.0/b24cli-macos-arm64.tar.gz.
- SHA-256: `d216957d7973a8f8a74d93c55a6984097afd77c395691d382bbadc9798aba91b`, matched public `SHA256SUMS` from the same tag.
- `openspec validate add-shell-installer --strict` and `git diff --check`: passed.
- Documentation files and README installation anchor exist. No Bitrix24 API fields or contracts are changed; documentation MCP is not needed for this distribution-only scope.

## Native CI evidence

[Shell installer run 38395262888](https://github.com/mesilov/bitrix24-cli/actions/runs/38395262888): **success**,
head SHA `c9bf39b3bc8231fb8aa594e98f8165669f24c89b`. Both jobs completed successfully:

- Linux amd64 job `115288738899`: fixtures, 7/7 native public archive tests, anonymous explicit/latest production-curl installs. Public archive SHA-256 `0e934f42f7d563b64dbac8017a83a5d4d06afc5c5b5376f52d36d73559964aaf`.
- macOS arm64 job `115288739354`: fixtures, 7/7 native public archive tests, anonymous explicit/latest production-curl installs. SHA-256 matches local macOS evidence above.
- Both `0.1.0` binaries reported the exact version through the generated env file from `/`.
- The subsequent fixture addition specifically tests extraction refusal; 18 failure subcases passed locally. Production installer bytes are unchanged since the successful native run.
- Full package build run `38395262878` was still in progress at this evidence capture. It additionally exercises new build artifacts; its success is not claimed here. Existing smoke/install commands are retained unchanged in the workflow.

Workflow `Shell installer` covers Linux amd64 and macOS arm64,
fixtures, pinned real public archive through controlled curl and real anonymous
explicit/latest download. Unix package jobs additionally use newly built archives
with version identity from `build-info.json`, retaining all existing smoke/install checks.

## Public distribution and signing

- Public Release `0.1.0` and both Unix archives/`SHA256SUMS` confirmed through current GitHub release state.
- https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh: anonymous HTTP **404** on 2026-10-13. The local installer successfully downloads public assets; this does not prove script publication.
- Task 5.1 requires script promotion to main and an anonymous smoke from that published URL. Explicit 0.1.0 uses the main script with `--version 0.1.0`; the 0.1.0 tag itself predates installer publication.
- Installer never invokes `xattr`, disables Gatekeeper or modifies quarantine. Native runner/local executable checks do not prove browser quarantine acceptance or Developer ID/notarization; #23/#25 remain separate.

## Findings

- **CRITICAL C1 (public delivery gate):** task 5.1 is incomplete because the main script URL is absent. Keep issue and change open, deliver implementation for review in dev, then perform normal main promotion and anonymous published-script smoke before closing/archiving.
- **WARNING W1 (platform environment limit):** on Linux, system `/etc/profile` can overwrite temporary HOME. The harness explicitly loads the selected login profile in an isolated bash; native macOS also exercises real bash login. zsh interactive/login and bash interactive are exercised directly.

Implementation can be reviewed in dev. Full change completion/archive is not claimed.
