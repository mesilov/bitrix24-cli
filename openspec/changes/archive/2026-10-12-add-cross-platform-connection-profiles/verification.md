# Verification — connection profiles (#19)

Date: 12.10.2026. Issue: https://github.com/mesilov/bitrix24-cli/issues/19.
Change: `add-cross-platform-connection-profiles`; schema: `spec-driven`.
Main specification: `openspec/specs/connection-configuration/spec.md`.
Archive: `openspec/changes/archive/2026-10-12-add-cross-platform-connection-profiles/`.

## Final assessment

All 20 implementation and verification tasks are complete. Implementation and evidence cover all 16 requirements and 45 scenarios; no unresolved CRITICAL, WARNING or SUGGESTION findings remain. Counts describe reviewed specification coverage, not a separate 45-test native suite.

Core implementation/docs/schema are delivered by [PR #36](https://github.com/mesilov/bitrix24-cli/pull/36), dev merge `ae7bd81b5ce00f8767c5c35ee4715ba20ede58c4`. Raw webhook control validation/source-only fallback regression/terminology are delivered by [PR #38](https://github.com/mesilov/bitrix24-cli/pull/38), merge `ed0957182b235079f99f04648346c484ae9b3479`. Physical temporary-path fixture is delivered by [PR #40](https://github.com/mesilov/bitrix24-cli/pull/40), merge `7e589a59db62346bb8d7d299ee51660849d5c1e7`.

Final filesystem-selector/Windows ACL/bootstrap fixes and this archive are reviewed through [PR #46](https://github.com/mesilov/bitrix24-cli/pull/46) into dev. Acceptance source: **b8a873915b3ace2b55488d779811a28ee177a2b0**. The archive commit `76f8a259685fc0c8d081de7f97d67336f76accc9` changes only OpenSpec artifacts; its runtime/package/test sources remain identical to that accepted source. Subsequent integration is documented separately below. Verification does not imply PR merge or release publication.

## Verification scorecard

| Dimension | Evidence |
| --- | --- |
| Completeness | 20/20 tasks; 16 requirements / 45 scenarios mapped; artifacts complete; main spec preserves all delta requirements/scenarios and authored Purpose. |
| Correctness | 366 unit tests / 2104 assertions; 108 shared schema/runtime cases; one docs JSON example; 5 explicit runtime constraints; all three native platforms and archive set PASS. |
| Coherence | Exact API keys groupId/creatorId/responsibleId/allowsTimeTracking across config/schema/spec/payload; no authorId alias or root groupId. 30 task commands retained, 5 local commands added; issue/proposal/milestone/labels verified. |

## Requirement mapping

| Requirement | Implementation and scenario evidence |
| --- | --- |
| User configuration location | ConfigDirectoryResolver; testPlatformPathsAndOverrides; native missing-user-home/Unicode-space-paths |
| Named profile metadata | ProfileRepository; testFourProfileLifecycleAndPrivateFiles, testMalformedSchemaNeverMutatesAndRejectsObjectArraysIdsBooleansAndTraversal; native full-SDK-controlled-transport |
| Nested task defaults metadata | ProfileRepository; shared schema fixtures; testMalformedSchemaNeverMutatesAndRejectsObjectArraysIdsBooleansAndTraversal |
| Profile-local task creation defaults | TaskInputMapper; testOfflineDefaultPriorityByPresenceAndNoCredentialReads, testMissingRequiredFieldsAndInvalidExplicitValuesNeverUseFallback; native profile-defaults |
| Boolean time tracking default | TaskInputMapper/FieldSchema; tests for explicit false and builtin false; shared schema invalid booleans |
| Protected credential persistence | FileProtection/FileCredentialStore/ProfileRepository; testPublicationFailureCleansNewCredentialAndPreservesMetadata, testSymlinkCredentialsAreRejected; native Unix-modes/Windows-ACL/concurrent-writers |
| Explicit connection selection | ConnectionManager; testSelectorsNeverMixWebhooksAndProviderResetsBetweenInvocations, testRuntimeSelectorsAndFailurePrecedence; native source-precedence |
| Compatible implicit selection | ConnectionManager; testExplicitFilesSdkAliasAndPackagedSourceIsolation, testSourceDotenvIsOnlyTheFinalProviderAndNeverUsedByPackagedMode; native source-precedence |
| Selected source failures | FileCredentialStore/ConnectionManager; testHostMismatchAndPrivatePermissionFailureDoNotFallBack, testEnvironmentSourcesRejectRemoteAndVirtualStreamsBeforeReading; native filesystem-only-env-file/missing credentials |
| Local profile commands | ConnectionCommandRunner; testLocalCommandsAreRegisteredAndHaveSafeOutputInAllModes; native lifecycle/default clearing |
| Secret input without argv | ConnectionCommandRunner; hidden Question input; testInvalidInputAndSelectorFailuresAreSafeAndDoNotWrite; native documented-native-shells |
| Packaged source independence | Bootstrap/ConnectionManager; testExplicitFilesSdkAliasAndPackagedSourceIsolation; native no-cwd-dotenv/read-only-executable-dir and Unix export mode |
| Safe connection diagnostics | ConnectionCommandRunner/redaction; local all output modes and negative URL cases; native no-secret-output/raw-URL-controls |
| Lazy read-only offline execution | Lazy connection provider and TaskInputMapper; testOfflineDefaultPriorityByPresenceAndNoCredentialReads; native initial offline surfaces/missing credential dry-run |
| Headless operation and documentation | README/docs/configuration.md/Compose wrapper; compose-connection-check.py; native shell/process/file controlled SDK calls |
| Published configuration schema | docs/config.schema.json; ConfigurationSchemaContractTest and config-schema-check.py; 108 shared structural cases, docs example and 5 runtime constraints |

## Final native acceptance

[Run 38176264075](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264075), source `b8a873915b3ace2b55488d779811a28ee177a2b0`, completed successfully 11.10.2026 UTC / 12.10.2026 Asia/Bishkek.

| Job | Result |
| --- | --- |
| [Linux amd64](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264075/job/114579193737) | PASS: ordinary UID, private Unix modes, concurrency, profiles/files/process env and defaults, full SDK controlled transport, bash, CRLF/Unicode/spaces, read-only executable directory, missing HOME, offline/negative/redaction matrix, binary immutability and archive installation. Public export root/binaries report 0755. |
| [macOS arm64](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264075/job/114579193643) | PASS: signed native binaries, physical temp paths, private storage/concurrency, sources/defaults/SDK fixture, bash/zsh, offline/errors/redaction and archive installation. |
| [Windows amd64](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264075/job/114579193792) | PASS: inbox PowerShell direct .NET ACL fixture 0.14–0.17 s, protected DACL/current SID, injected Everyone Read ACE rejected before API, four concurrent writers, PowerShell/cmd.exe, source/default/offline matrix, separators/Unicode/spaces, read-only binary directory, redaction, binary immutability and archive installation. |
| [Release archive set](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264075/job/114580170715) | PASS: three archives, exact contents/checksums, executable permissions, notices and intentional corruption rejection. [Artifact 11716132540](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264075/artifacts/11716132540). |

All native builders repeat the real-PHAR regression: success, entry/source failure cleanup and four simultaneous processes PASS. Windows reserves independent temporary directories and creates PHARs exclusively without rename; complete and incomplete payloads are cleaned.

Native smoke checks output for recognizable fixture credentials, absence of the fixture webhook in the production binary, unchanged binaries after configuration, and absence of credentials/configuration beside binaries. Release archives exclude raw PHAR/vendor/runtime probe. Supplementary scan of all 6,956 decompressed production PHAR files at `5977e2799beab9bdb5a2a1febdcfef7faea7636e` found no fixture credentials or local state; that earlier scan does not claim every final-head file was rescanned.

Archive SHA256 values:

```text
516014c75e98a9f941513a8bd52f0fa8a54d4bf1246b0a6788c7a221185b358c  b24cli-linux-amd64.tar.gz
969cd89fb3480731f9827e56cbf91d2bbe6732082db23eb56756bd8d5c7ba0a2  b24cli-macos-arm64.tar.gz
dab6274740f9f6373b60a7277b3be0ece446f19b946dd20f3ef3ec19e9f87482  b24cli-windows-amd64.zip
```

## Local and source CI checks

- `make check`: PASS (strict Composer/platform validation, syntax/startup).
- `make unit`: PASS, 366 tests / 2104 assertions. Separate full invocation: 408 cases, including 42 integration cases skipped without live credentials; skips provide no acceptance proof.
- `make lint-all`: PASS; final-source license/PHP-CS-Fixer/PHPStan/Rector [PR checks](https://github.com/mesilov/bitrix24-cli/pull/46/checks) PASS.
- Schema contract: 108 shared cases/docs example/5 runtime constraints PASS; [CI job](https://github.com/mesilov/bitrix24-cli/actions/runs/38176264042/job/114579193834).
- Worktree isolation CI PASS; dirty primary checkout preserved.
- Compose connection guard: 4 fixtures/readonly overlays PASS; source guard: 12 checks PASS; publish guard: 7 PASS.
- Real-PHAR temporary-payload regression: local ordinary UID on PHP 8.5.11 PASS, repeated by every native builder.
- Strict change validation, synchronized main-spec validation and diff whitespace checks PASS. Semantic comparison confirms all 16 requirement blocks/45 scenarios and Purpose preserved verbatim under canonical main-spec headers.

## Resolved native findings

Earlier failed/cancelled runs are not acceptance. Native findings resolved: macOS /var symlink alias fixture, BuildKit export-root 0700 traversal denial, cmd.exe quoting, PowerShell ACL module-discovery delay, Windows temporary-PHAR rename access denial. Accepted run uses physical fixture paths, 0755 only for public distribution, fixed cmd batch file, direct ACL APIs and independent exclusive temporary payloads. Private storage rules/assertions/deadlines are retained.

Selected --env-file/B24CLI_ENV_FILE reject HTTP/php/data/ftp streams before credentials, metadata planning or dry-run; regular files/local file URIs remain supported. Negative cases assert zero API calls, redaction and no fallback.

## Scope boundaries

No live portal mutation or live authorization-scope check was performed. Connection selection uses the real SDK with controlled MockHttpClient; packaged TLS uses separate trusted/untrusted local HTTPS certificates. Plaintext storage protected by OS permissions is the v1 contract; keyring/OAuth/token refresh are outside scope. Separate #27/PR #31 establishes --group-id without --project; archive `2026-10-11-unify-group-id-option` is retained.

## Integration with dev after compression delivery

On 12.10.2026, merge conflicts with dev `f7923633ba5a8cebfafa31929a538877697ad598` (PR #39, archived `2026-10-12-compress-standalone-binaries`) were resolved in PR #46. Before this integration, archive head `76f8a25` passed all source/native/archive checks in [run 38177010384](https://github.com/mesilov/bitrix24-cli/actions/runs/38177010384).

Resolution retains both contracts: UPX/wrappe compression, original/final executable checks, notices and reports from dev; filesystem-only env sources, direct .NET ACL/protected storage, unsafe-ACE rejection, exclusive independent PHAR directories and failure cleanup from #19. Native shell acceptance keeps dev's explicit no-BOM PowerShell encoding and direct cmd quoting. Public export traversal is normalized without changing private configuration modes. The 90-second command budget and 30-second direct ACL helper budget from #19 remain; the 600-second allowance for the replaced module-discovery implementation is unnecessary. The collision fixture reserves the occupied directory explicitly and still proves that its sentinel is untouched; native cleanup checks both payload files and directories.

Post-resolution local checks: `make check` PASS; `make unit` 366 tests / 2104 assertions PASS; compressor regressions 19 PASS; source/publish guards 12/7 PASS; real-PHAR loader collision/unavailable-directory checks and success/entry/source failure/four-process cleanup PASS as ordinary UID on PHP 8.5.11; Python syntax, strict validation of all 7 main specifications and diff whitespace PASS. New native CI must establish combined compressed-binary acceptance; previous native proof remains scoped to its named pre-integration sources.
