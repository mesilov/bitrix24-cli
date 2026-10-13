# Verification: add-users-namespace

Date: 2026-10-13 (Asia/Bishkek). Issue: https://github.com/mesilov/bitrix24-cli/issues/63. PR: https://github.com/mesilov/bitrix24-cli/pull/69, target dev.

## Summary

| Dimension | Result |
| --- | --- |
| Completeness | 20 implementation tasks implemented/checked; 24 ADDED/MODIFIED requirements mapped below. The change remains active pending PR delivery. |
| Correctness | 432 unit tests, 2462 assertions; 125 shared configuration fixtures, two documentation examples; 73 specification scenarios reviewed against code/tests. Positive live org-manager lookup is **not verified** because the configured portal omitted parent data. |
| Coherence | Explicit catalog, shared runtime/presenter, dedicated User ports/adapters/requests/handlers, unchanged Task user-enrichment port, profile isolation and method/version allow-list follow design. |

There are no known critical implementation gaps from checks that ran. Live org-manager acceptance remains not verified; this report does not claim unrestricted portal/org coverage or archive readiness. No removals or renames are present.

## Local checks

- `make test-unit`: **PASS**, PHP 8.4.26 / PHPUnit 12.5.38, 432 tests / 2462 assertions.
- `make lint-all`: **PASS** — allowed licenses, PHP CS Fixer, PHPStan level 5 and Rector dry-run.
- `build/tests/config-schema-check.py` with CI-pinned `jsonschema==4.25.1` in an isolated temporary Python venv: **PASS**, 125 shared fixtures / two documentation examples. Existing six additional runtime checks and integer-literal differences remain documented.
- `openspec validate add-users-namespace --strict`: **PASS**.
- `git diff --check`: **PASS**.
- Explicit inventory/offline tests: 30 task commands, four users commands, five local connection commands; no added aliases, credential reads or remote requests on help/list/completion.

Native GitHub CI for the implementation commit is separate from these local checks and must be read from the current PR head; no native package result is inferred from the above.

## Live portal receipts and limitations

Separate runs used production bootstrap/provider and independent SDK fixture/readback paths. The latest receipt for each case is in the ignored local `var/cache/portal-evidence.jsonl`; public evidence below contains no webhook, employee names or emails.

| Scenario | Latest observed result |
| --- | --- |
| task:add requested fields | exit0; SDK task-card readback; owned fixture deleted. |
| task:add configured default observer | exit0; SDK REST1 participant membership readback confirms observer; owned fixture deleted. |
| users:current | exit0; identity matches independent SDK current/profile. |
| users:find name | Expected partial/exit3: known match retained, missing directory name fields reported, no false completeness. |
| users:find exact email | exit0; returned IDs match independent exact-email SDK query. |
| users:colleagues | exit0; selected-department membership and self exclusion match SDK query. |
| users:manager config | exit0; configured ID returned with source=config and only user.get route. |
| users:manager org | **SKIP**, fixture omitted PARENT. No positive live chain/manager acceptance is claimed. Unit tests verify direct head, parent traversal, self-head, missing head/root, cycles, unavailable data and hop cap. |

The final current/colleagues run passed two cases / 428 assertions. The final find/manager read-only run passed three cases, including the expected partial name-search case, and skipped one org case (four total / 29 assertions). Earlier task:add live cases passed with independent readback and zero cleanup failures. These receipts are combined by case, not described as one all-green eight-case run.

Prior failures are retained as evidence boundaries: the initial name-search test incorrectly required exit0 despite a documented missing-field partial; the test was changed to require the exact partial reason and retained match. A later portal run returned INTERNAL_SERVER_ERROR on exact-email user.get and even SDK profile during setup. After a pause only affected read-only groups were retried successfully; production code gained no hidden API fallback or automatic retry of writes. Org fixture incompleteness remains a skip.

## Requirement mapping

Paths are relative to the repository. Existing MODIFIED scenarios remain intact; their original unit/contract tests are included in the passing full suite.

| Requirement | Implementation / evidence |
| --- | --- |
| Users namespace and current connection identity | `src/Console/Command/Users/CurrentUserCommand.php`, `Rest1UserDirectoryAdapter.php`, `UserCommandsTest::testCurrentPreservesApiNamesAndCanonicalIdentity`, current live case. |
| Employee search by name | `UserInputMapper.php`, `UserDirectoryService::find`, name/candidate/Unicode/invalid-input unit cases; expected-partial name live case. |
| Employee search by email | `UserInputMapper::find`, exact FILTER.EMAIL in directory service/adapter; candidate and exact-email unit/live cases. |
| Search returns candidates without choosing an assignee | `UserScanner` data.items for zero/one/multiple; `UserCommandsTest::testNameAndEmailSearchAlwaysReturnCandidateLists`; no task/config mutation routes. |
| Explicit current department selection | `UserDirectoryService::department`, `UserRecord::normalize`; ambiguous/foreign/missing department unit cases before further queries. |
| Colleagues are members of the current department | `UserDirectoryService::colleagues`; scoped filter/self exclusion/child-department unit cases and SDK membership readback. |
| Manager follows the selected organization branch | `Rest1DepartmentAdapter`, `UserDirectoryService::manager`; direct head/parent/self/root/cycle/hop-cap/broken hierarchy unit cases. Positive live org chain remains not verified. |
| Configured manager is an explicit source | `UserInputMapper::manager`, config-source routes; profile/config isolation unit cases and configured-manager live case. |
| Bounded lists and truthful completeness | `UserScanner`, validated adapter pagination; later-page matches, limit/all vs scan cap, malformed/repeated cursor, duplicate IDs, missing fields, partial/cancellation unit cases. |
| Stable user outputs and API policy | `CommandCatalog`, `ApiPolicyGuard`, user presenter profiles; output escaping/quiet/offline/strict regression unit cases; ordinary v1 live routes. |
| Local profile commands (MODIFIED) | `AddConnectionCommand`, `ConnectionCommandRunner`, `ConnectionProfilesTest`; role round-trip, no portal/secret output, independent metadata updates. |
| Published configuration schema (MODIFIED) | `docs/config.schema.json`, shared `schema-cases.json`, configuration docs; 125 fixtures and both documentation JSON examples pass schema/runtime checks. |
| Nested user working-role defaults | `ProfileRepository` load/validate/publish; strict integer/boolean/container/unknown-key fixtures and true-without-observer rejection. |
| Working roles stay within the selected connection | `ConnectionManager::userDefaults/metadata`, role-source/profile/env/file unit tests; no task-field aliasing or implicit manager substitution. |
| Exact MVP command registration (MODIFIED) | `CommandCatalog`, DI, `ContainerRegistrationTest`, inventory/help/contract tests preserve 30 task and add four users commands. |
| Integration happy-path coverage for every CLI command (MODIFIED) | Common `LiveCommandTestCase`, task/users specializations, `IntegrationCommandCoverageTest`: one class/command owner and required happy method for every remote command; original wrong-command guard retained. |
| Policy covers the complete route plan (MODIFIED) | Explicit user route pairs and conditional add companion routes; standard v1 support and existing explicit strict mode checked before credentials/create. |
| Opt-in default observer on task creation | `AddTaskRequest`, `TaskInputMapper`, `AddTaskHandler`, existing `ParticipantGateway`; exact AUDITORS payload, deduplicated union, existing-observer preservation/skip, disabled/env isolation and advanced preflight tests plus live membership proof. |
| Creation and observer assignment have explicit outcomes | `AddTaskHandler` retains data.operation.taskId and ledger; read/write failure, unknown write, unconfirmed create, cancellation and offline plan unit cases. No retry/rollback/create duplication. |
| USR-01 — Определить себя в выбранном портале | users:current unit/live identity evidence. |
| USR-02 — Найти коллег своего отдела | users:colleagues unit/live membership evidence. |
| USR-03 — Найти непосредственного руководителя | org semantics covered in unit; configured working manager live; explicit org live limitation above. |
| USR-04 — Сохранить рабочих адресатов профиля | role/schema/profile isolation checks; opt-in task:add unit/live observer proof. |
| USR-05 — Найти произвольного сотрудника компании | name/email candidates and ambiguous/empty/partial unit tests; exact-email live result and bounded partial name result. |

## API/source and delivery boundaries

The mandatory Bitrix24 MCP was queried during planning and implementation: effective `codex mcp get bitrix24_dev --json` shows the enabled official endpoint, and real bitrix_search/method-details calls succeeded. Version-specific source links and scope limits are in design.md; API fields remain `ID`, `UF_DEPARTMENT`, `PARENT`, `UF_HEAD` and REST1 `AUDITORS`, while configured role selectors remain local CLI metadata outside REST3 fields.

After the final dev synchronization, CHANGELOG.md was updated under Unreleased with the delivered users/role/observer scope and issue #63 link; its actual diff against origin/dev is checked before PR readiness. The dev synchronization changed documentation/workflow only, so source test receipts above remain applicable.

Issue #63 retains milestone 0.2.0 and existing enhancement label with the active change path. No merge, release, deployment or platform binary acceptance follows from local/live source checks. Archive/spec sync and worktree cleanup follow confirmed delivery per maintainer workflow.


## Dev synchronization and revalidation before merge

On 2026-10-13 the feature branch merged `origin/dev` at `1a2ef63`, preserving task results, role filtering, recent-first scans, task tags and profile replacement guidance. AddTaskHandler composes optional tag and observer steps in the documented order, stops observer steps after tag failure, and retains confirmed tag writes when observer assignment fails. Three new unit cases cover success/offline preview and both failure boundaries; live task:add adds a combined tag/observer case. Catalog acceptance now covers 34 task commands plus four users commands. Existing verification receipts above remain historical evidence; current checks below supersede their suite counts.

- `make test-unit`: PASS, **542 tests / 4020 assertions**.
- `make lint-all`: PASS, licenses, PHP CS Fixer, PHPStan and Rector; 246 PHP files.
- Configuration schema: PASS, 125 shared fixtures / two examples / six additional documented runtime checks.
- `openspec validate add-users-namespace --strict` and `git diff --check`: PASS; 20/20 tasks, 24 requirements, 74 scenarios including tag/observer composition.
- Live `--group command:task:add`: PASS, **4 tests / 45 assertions**, independent SDK tag and observer membership readback, owned fixtures removed with zero cleanup failures. Existing users live limits above remain unchanged.
- Official Bitrix24 MCP search/details succeeded again for tasks.task.update. Its combined v1/v3 record was cross-checked against the [classic update reference](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-update.html); version-specific payloads remain unchanged.

GitHub CI must still be confirmed against the newly published feature head before merge. No earlier package run is substituted for checks on that head.
