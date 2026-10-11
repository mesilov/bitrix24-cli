# Verification — connection profiles (#19)

Date: 12.10.2026. Issue: https://github.com/mesilov/bitrix24-cli/issues/19.
PR: https://github.com/mesilov/bitrix24-cli/pull/36 (merged into dev at ae7bd81b5ce00f8767c5c35ee4715ba20ede58c4).
Follow-up https://github.com/mesilov/bitrix24-cli/pull/38: strict raw webhook control validation, source-only dotenv regression, native negative cases and maintainer API terminology rule; merged into dev at ed0957182b235079f99f04648346c484ae9b3479.
Implementation commit: 6d319e1a6ebf073103a6bf5d1a534e498854b2ad.
Updated dev integration: ce9c6c23a8ea2d6380404b54c5bc79f966fcc2cd.

## Verification scope

| Dimension | Evidence / status |
| --- | --- |
| Completeness | 12/20 tasks; implementation mapping covers 16 requirements / 45 scenarios. Native CI and final archive tasks remain pending. |
| Correctness | 366 unit tests / 2104 assertions; 108 shared schema/runtime cases; one docs JSON example; 5 documented supplementary runtime constraints. |
| Coherence | API field names match issue/spec/schema/config/payload; CommandCatalog retains all 30 task commands and adds separate 5-command local catalog; lazy provider cache resets per invocation. |

## Requirement mapping

- User configuration location: ConfigDirectoryResolver; unit OS/default/override/absence/relative-XDG cases; README paths; native connection-smoke pending.
- Named profile metadata and nested defaults: ProfileRepository validates JSON object shapes, required fields, exact allowed keys and positive integer/boolean values; shared Schema fixtures cover both valid and invalid documents. Unknown root groupId reports default.task.groupId guidance. No network resolution of descriptive name.
- Profile-local task creation defaults and boolean tracking: TaskInputMapper applies field-wise defaults before required validation; explicit false retained, bad explicit fields fail without fallback; FieldSchema strict boolean validation. Unit basic, advanced, missing-participant, other-profile and env/file isolation cases. No default injection into other commands.
- Protected persistence: FileProtection/FileCredentialStore/ProfileRepository; private files/directories, symlink rejection, serialized mutations, temp metadata rename, injected permission failure cleans new credential while retaining prior JSON; PHAR concurrency fixture. Windows native ACL evidence pending.
- Explicit and implicit selection: ConnectionManager/CommandRunner; CLI and runtime-config selectors, ambient conflicts, canonical process env/SDK alias, explicit/env file, selected-profile defaults. Selected errors precede SDK requests and cannot mix host/token. Migration documented.
- Local commands and secret input: five connection commands and ConnectionCommandRunner; hidden TTY input or explicit bounded stdin; no secret argv flag, no-interaction/competing-stdin errors, output redaction in human/plain/JSON, add/use/remove semantics.
- Packaged source independence, lazy offline behavior and diagnostics: explicit Phar execution context; production PHAR runs from clean cwd/PATH; offline commands and dry-run create no storage/read no secret file; planted cwd/executable dotenv ignored; recognizable fixture secret absent from output; complete webhook hostname matched before transport.
- Filesystem environment sources: FileCredentialStore rejects non-file stream schemes before reading; ConnectionManager validates explicit and ambient selected file paths before metadata/planning, so HTTP/data/php/ftp inputs fail even for dry-run. Unit tests retain plain paths and local file URIs, reject both selector sources with zero API calls and redacted errors. Native cases added; pending final delivery/CI.
- Raw webhook validation: whitespace and ASCII control characters are rejected before parse_url can normalize them. Unit coverage exercises stdin, explicit dotenv and process env, with safe diagnostics and no persisted credentials. Native negative cases await updated artifact CI.
- Headless operation/documentation: README and docs/configuration.md; Compose wrapper readonly mounts and explicit write mode; four argument fixtures and Docker Compose overlay parse.
- Published JSON Schema: docs/config.schema.json Draft 2020-12; shared fixtures through Python jsonschema 4.25.1 and actual PHP repository; CI Configuration schema contract succeeded. Extra runtime checks are explicit: lexical integers, default alias existence, unique credential reference, permissions/symlinks and HTTPS host match; Schema annotations do not apply defaults.

## Local checks

- make check: PASS (Composer strict/platform requirements, PHP syntax and CLI startup).
- Unit suite: PASS (366 tests, 2104 assertions). Latest full PHPUnit invocation ran 408 total tests, of which the 42 integration cases were skipped without live portal credentials; 366 unit tests executed unprivileged. Native/integration acceptance is not inferred from these skips.
- make lint-all previously PASS (licenses, PHP-CS-Fixer, PHPStan, Rector); latest filesystem-selector changes passed PHP-CS-Fixer, PHPStan and Rector individually. No dependency/license changes.
- openspec validate add-cross-platform-connection-profiles --strict: PASS.
- build/tests/compose-connection-check.py: PASS (4 argument fixtures and readonly overlays).
- build/tests/source-check.py: PASS (12 checks); build/tests/publish-check.py: PASS (7 checks).
- Production PHAR built from 5977e2799beab9bdb5a2a1febdcfef7faea7636e using PHP 8.5.11 and locked no-dev dependencies: PASS.
- PHAR smoke (unprivileged packaging image, outside checkout): PASS (version/help/list/30 task commands, UTF-8, task dry-run, no credentials, SIGINT, trusted TLS and rejected certificate).
- PHAR connection-smoke: PASS (private storage, concurrent writers, defaults, full SDK controlled transport, CRLF, Unicode/space paths, readonly executable directory, no cwd dotenv, no secret output or binary mutation, missing HOME, raw URL controls and filesystem-only env-file selection).

## CI

- Initial source 6d319e1: unit/schema/license/style/PHPStan/Rector passed.
- Configuration Schema job: https://github.com/mesilov/bitrix24-cli/actions/runs/38169916829/job/114560177486.
- Initial macOS job stopped before build because merge workflow referenced macos-signing-check.py absent from the older head. Updated feature branch from current dev; this does not establish native acceptance.
- Updated head ce9c6c2: command tests, schema, all linters and worktree isolation passed. Native run https://github.com/mesilov/bitrix24-cli/actions/runs/38170127812 built and signed macOS binaries, then general smoke failed: temporary HOME used the /var symlink alias, correctly rejected by the managed-storage protection. Local controlled symlink fixture reproduces configuration-error and passes with the physical path; smoke now resolves its own temporary root, retaining runtime link rejection. Native acceptance awaits this fixture correction.
- Merged dev ae7bd81: command tests, schema, all linters and worktree isolation passed. Native run 38170322978 cancelled when dev advanced.
- Follow-up 1130acf: all unit/schema/linters/worktree checks passed in CI; native run https://github.com/mesilov/bitrix24-cli/actions/runs/38170833832 remains pending and needs the same smoke fixture correction.

## Native findings after #40

Run 38171333726 (source 567c980): macOS arm64 native acceptance passed, including private storage, concurrent writes, full SDK controlled transport, shells and raw control cases. Linux built and passed general smoke, then the unprivileged connection smoke could not read the export. package.py normalizes Unix executable mode to 0755, matching final archive mode. Run 38173086031 still failed at the copy: the remaining cause was the BuildKit local-export root, created with 0700. A minimal local BuildKit export confirmed directory 0700/file 0755; a Linux effective-UID fixture denied traversal before directory chmod and read successfully afterward. The workflow now sets only that public export directory to 0755 and prints directory/binary modes before smoke. Windows passed general smoke and connection/profile/file/process cases up to the cmd.exe setup assertion. The inline command was being passed through Python CRT argument quoting to cmd /s; a fixed batch fixture now carries the documented redirection and safe diagnostics. Final Windows acceptance is pending; this finding does not establish completed ACL/concurrency checks after that assertion.

Primary module/cache references: [PSModulePath](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.core/about/about_psmodulepath), [Windows PowerShell environment](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.core/about/about_environment_variables?view=powershell-5.1).

Primary quoting references: [cmd](https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/cmd), [Python argument conversion](https://docs.python.org/3/library/subprocess.html#converting-an-argument-sequence-to-a-string-on-windows).

Run 38173086031 head 98cfa778: macOS native PASS again; Linux export-root failure reproduced as above. Windows general smoke PASS, then its first inbox PowerShell Get-Acl fixture timed out after 30 seconds before any CLI operation. The same-head Windows rerun passed initial ACL setup but timed out at first connection:add after 90 seconds. The fixture had removed normal Windows ProgramFiles/USERPROFILE/LOCALAPPDATA context that inbox PowerShell uses for module/cache startup; this may explain the inconsistent startup delays. It now retains only OS runtime variables while still clearing credential selectors and keeping PATH limited to copied binaries, uses EncodedCommand with closed stdin for its ACL calls, and prints non-secret stage markers. This is a targeted environment hypothesis; Windows acceptance remains pending until the corrected native fixture passes.

Run 38174964824 head 31c5f58: Linux and macOS native acceptance PASS, including connection smoke and archive installation. Windows OS context did not resolve the delay: two fixture ACL calls took 42 seconds, then first connection:add exceeded 90 seconds. The backend and fixture now use direct .NET Framework Directory/File.GetAccessControl/SetAccessControl and intrinsic constructors inside the inbox PowerShell host, avoiding module-discovery cmdlets while retaining identical owner/protected-DACL/current-SID-only checks. This change requires updated Windows native proof; timeouts and assertions are not relaxed.

## Final delivery pending

- PR #40 merged at 7e589a59db62346bb8d7d299ee51660849d5c1e7; native smoke resolves its own temporary roots while runtime link protection remains intact.
- Merged dev unit/schema/all linters/worktree checks passed. Native run https://github.com/mesilov/bitrix24-cli/actions/runs/38171464691 was cancelled by a subsequent docs merge into dev; this is not acceptance evidence.
- Filesystem-only env-file correction and historical design wording updates are prepared in bugfix/19-local-credential-files; publication and updated native acceptance pending. Native smoke also explicitly normalizes Unix executable mode to 0755 before export and uses a fixed batch fixture for cmd.exe redirection.

## Boundaries and final assessment

No live portal mutation or permissions validation was performed. SDK transport selection uses a controlled MockHttpClient fixture; TLS runtime stack is exercised separately against local trusted/untrusted HTTPS certificates. Plaintext storage with access controls is the v1 contract; native keyring/OAuth feasibility remains outside scope.

Runtime/documentation checks have no unresolved correctness findings. Native requirements remain unverified until CI passes; no archive readiness or issue completion is claimed yet.
