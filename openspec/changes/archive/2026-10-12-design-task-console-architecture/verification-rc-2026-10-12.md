# RC verification: task console runtime — 2026-10-12

Baseline: `dev d145b226fd048423c2ce2af93b28efd733d4ccec`; issue [#10](https://github.com/mesilov/bitrix24-cli/issues/10). Reviewed proposal/design/tasks, both deltas, [implementation verification](implementation-verification.md) and its per-command portal records.

| Dimension | Result |
| --- | --- |
| Completeness | 42/42 retained tasks, 15 runtime requirements and one revised MVP admission requirement. 30 task classes, 30 independent integration classes; 29 excluded operations absent. |
| Correctness | Runtime/spec scenarios map to existing implementation and offline cases. Original live evidence: 42 tests / 395 assertions, 41 passed / 1 restricted-role skip. New local suite: 366 tests / 2104 assertions. |
| Coherence | Console/standalone DI, explicit ports/versioned adapters, thin commands, centralized runner, deferred credentials. Current connection selection reconciled with already shipped #19; no runtime/API change. |

| Requirement | Implementation and scenario coverage |
| --- | --- |
| Exact MVP command registration | CommandCatalog, ContainerFactory/ApplicationFactory, ContainerRegistrationTest; connection commands counted separately. |
| Deferred connection resolution | CommandRunner, B24Application, B24ClientProvider; RuntimeTest/CommandBehaviorTest/ConnectionProfilesTest. |
| Integration happy-path coverage for every CLI command | IntegrationCommandCoverageTest, 30 tests/Integration/Task classes and LiveTaskCommandTestCase; original live per-command evidence, own-command guard and Unicode find. |
| Consistent application error boundary | B24Application/ResultPresenter; RuntimeTest, CommandContractsTest and subprocess checks. |
| Explicit patch and bounded advanced input | TaskInputMapper/InputSourceReader/UpdateTaskHandler; CommandBehaviorTest/CommandContractsTest. |
| Participant relation select projections | TaskParticipantEnricher/ShowTaskHandler/Rest1UserAdapter; TaskParticipantEnrichmentTest plus original filled-role live evidence. |
| Policy covers the complete route plan | ApiPolicyGuard/CommandCatalog routes; RuntimeTest/CommandBehaviorTest/enrichment strict-policy zero-call cases. |
| Verified task chat message binding | ChatSupport/TaskChatService; ScanningAndBindingTest and original own/wrong-task portal cases. |
| Verified task child resource binding | TimeSupport/ChecklistSupport and application services; ScanningAndBindingTest, command behavior and original time/checklist cases. |
| Prepared mutation preview | CommandRunner/handler prepare methods; command contracts and dry-run subprocess cases. |
| Complete result and execution accounting | OperationResult/RuntimeState/CommandRunner/versioned adapters; RuntimeTest/LegacyWriteResponsesTest and controlled HTTP codec tests. |
| Cancellation stops subsequent requests | RuntimeState/SDK transport/runner; RuntimeTest signal/timeout subprocess cases. |
| Unit acceptance and separate portal evidence | Fake transport harness/ConnectionAndSdkTest/LegacyWriteResponsesTest; separate committed sanitized portal records with partial/skip bounds. |
| Deferred root environment webhook connection | ConnectionManager source-only fallback, ConnectionProfilesTest/ConnectionAndSdkTest; connection-configuration governs explicit profile/file and standalone behavior. |
| Explicit unit and integration test entrypoints | Makefile/phpunit.xml.dist/command-tests.yml, IntegrationCommandCoverageTest; current make unit and original filtered live suite. |
| API admission and future lifecycle acceptance remain explicit | ApiPolicyGuard, TaskInputMapper and selected participant adapters; excluded status/lifecycle and strict REST3 cases. |

No critical divergence remains in retained scope after documenting the already shipped #19 connection selection and separate local command inventory. Core/API source mappings and payload fields did not change. make check and all four linters passed; main spec sync preserves unrelated requirements and Purpose.

New portal tests were not run. Full denied-role matrix, history/time completeness, independent file membership, second-user reassignment and native REST3 auditors remain **not verified** in #42/0.2.0. Original partial reasons, skipped case and source-bound counts are preserved. Verification of the retained implemented scope supports its archive; it does not assert the deferred scenarios passed.
