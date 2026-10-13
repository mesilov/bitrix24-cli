# Verification Report: clarify-existing-connection-profile

Date: 2026-10-13. Issue [#59](https://github.com/mesilov/bitrix24-cli/issues/59), PR [#73](https://github.com/mesilov/bitrix24-cli/pull/73), target `dev`.

## Summary

| Dimension | Status |
| --- | --- |
| Completeness | 7/7 tracked tasks complete; 2/2 added requirements implemented. PR #73 published as ready for review; runtime commit passed 17/17 remote checks. |
| Correctness | 2/2 requirements mapped; 5/5 scenarios covered by tests, including two concurrent processes. |
| Coherence | Early metadata check and locked duplicate check share one diagnostic; create-only behavior and replacement guidance agree across code, help, README and guide. |

## Local checks

- `make test-unit`: **371 tests, 2252 assertions**, passed on PHP 8.4.26 / PHPUnit 12.5.38 in the isolated Docker worktree.
- Targeted `ConnectionProfilesTest`: **21 tests, 372 assertions**, passed.
- Concurrent regression rerun after allowing extra time for Windows ACL operations: **1 test, 14 assertions**, passed.
- `make lint-all`: allowed licenses, CS Fixer, PHPStan and Rector passed.
- `make check`: Composer strict validation, PHP syntax and CLI bootstrap passed.
- `openspec validate clarify-existing-connection-profile --strict` and `git diff --check`: passed.

Docker address pools were exhausted. Tests used a temporary task-only Compose override with `php-cli.network_mode: bridge` through `make COMPOSE='sh ./scripts/compose.sh --file /private/tmp/bitrix24-cli-issue-59-network.yaml'`. No repository Compose configuration or other tasks' networks were changed.

## Requirement and scenario mapping

| Requirement / scenario | Implementation and evidence |
| --- | --- |
| Existing profile add rejection | `ConnectionCommandRunner::add` calls `ProfileRepository::assertCanAdd` before defaults parsing and webhook input. `ProfileRepository::add` repeats the shared alias assertion under its existing lock, before credential write. |
| Existing alias without redirected input | `testDuplicateAddRejectsBeforeWebhookInputAndPreservesFiles`: exit 1, configuration-error, explicit non-application message, show/remove/add/use guidance, no prompt. |
| Existing alias with a stdin webhook | Same test covers human/plain/JSON and both stdin modes. Stream position stays zero; config and both profiles' credential bytes/references remain unchanged, no new credential appears, JSON data is null and API calls empty. Missing saved credentials still return the metadata-only duplicate diagnostic. |
| Concurrent attempts | `testConcurrentRepositoryAddsDoNotOverwriteTheWinner`: two PHP workers both finish preflight while the alias is absent, then are released together. One exits 0 and one returns the shared duplicate error; one profile and one credential remain. `testRepositoryDuplicateUsesTheSameDiagnosticAndCreatesNoCredential` also checks both code paths share the same error. |
| Documented profile replacement | `AddConnectionCommand` help, README and `docs/configuration.md` explain full defaults, repeated secret input, defaultProfile clearing and explicit reselection; no portal webhook revocation. |
| Discover replacement from help | `testHelpExplainsReplacementWithoutConfigurationOrCredentials` runs help against an absent configuration directory and verifies guidance and no directory creation. Fake transport throws if called. |
| Add a missing creator default | `testDocumentedReplacementRestoresDefaultsAndSelectedProfile` executes show/remove/add/use and offline task:add, verifies integer groupId=187/creatorId=3/responsibleId=2 and boolean allowsTimeTracking=true, null default before use, zero API calls and unchanged pet profile/credential. |

Shared `ConfigSchemaContractTest` fixtures passed in the full unit suite, including rejection of `authorId`, unsupported keys and non-boolean values; existing default-priority tests continue to reject invalid explicit values without coercion. Config version and schema did not change.

## API and design review

Effective `bitrix24_dev` configuration was checked from the worktree; an actual official MCP search for REST 3.0 task fields and article-details call for «Поля задачи в REST 3.0» succeeded. [Official fields](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html) confirm the exact writable ID names and integer types, required creatorId/responsibleId for creation, and boolean allowsTimeTracking. No REST 1.0 aliases were introduced.

The design's shared diagnostic, metadata-only early check, final locked assertion, existing runtime validation precedence and full replacement documentation are implemented. No new dependency, schema change, upsert or automatic reselection was added.

## Findings and evidence limits

No critical issues, warnings or pattern deviations found. All applicable completeness, correctness and coherence checks ran; tracked tasks and scenario coverage are complete.

Local controlled fixtures do not prove the published 0.1.0 binary or live portal acceptance. No portal was contacted or mutated. PR merge, release and archive are separate follow-up actions; the task worktree remains while PR #73 is open.

## Remote CI receipt

Runtime/test acceptance commit: `dc3990521b8c55397772542bf5a433fd23d0c416`. PR #73 remained OPEN, ready for review, base `dev`; issue #59 remained OPEN in milestone `0.2.0` with label `bug` and reciprocal OpenSpec link. Fresh `origin/dev` `96ce5028072f90afe70104660b67e02c528d63de` was merged before publication.

All **17/17** check runs completed with SUCCESS for that PR head: duplicate push/PR license, schema, PHPStan, CS Fixer, Rector and unit runs; worktree isolation; three native package jobs and release-archive-set verification.

- [composer-license-checker](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086856) — SUCCESS.
- [composer-license-checker](https://github.com/mesilov/bitrix24-cli/actions/runs/38380082189) — SUCCESS.
- [Configuration schema contract, unit-tests](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086815) — SUCCESS.
- [Configuration schema contract, unit-tests](https://github.com/mesilov/bitrix24-cli/actions/runs/38380081789) — SUCCESS.
- [PHPStan](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086829) — SUCCESS.
- [PHPStan](https://github.com/mesilov/bitrix24-cli/actions/runs/38380082416) — SUCCESS.
- [packages / Linux amd64 package, packages / macOS arm64 package, packages / Windows amd64 package, packages / Verify release archive set](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086839) — SUCCESS.
- [PhpCsFixer](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086908) — SUCCESS.
- [PhpCsFixer](https://github.com/mesilov/bitrix24-cli/actions/runs/38380081763) — SUCCESS.
- [Rector](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086970) — SUCCESS.
- [Rector](https://github.com/mesilov/bitrix24-cli/actions/runs/38380081797) — SUCCESS.
- [linux](https://github.com/mesilov/bitrix24-cli/actions/runs/38380086823) — SUCCESS.

This receipt is committed after CI completion as documentation only. Its commit changes only tasks.md and verification.md; runtime, tests, dependencies and package inputs are byte-identical to the accepted commit. New documentation-head CI is separate from the terminal runtime receipt above. No merge, published release or live portal acceptance is claimed. Archive/sync/worktree cleanup remain follow-up after confirmed merge and issue closure.
