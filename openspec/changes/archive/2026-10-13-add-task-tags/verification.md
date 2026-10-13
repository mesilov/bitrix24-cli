# Verification — add-task-tags

Дата: 13.10.2026. Issue [#71](https://github.com/mesilov/bitrix24-cli/issues/71), PR [#75](https://github.com/mesilov/bitrix24-cli/pull/75), target dev.

## Scorecard

| Dimension | Result |
| --- | --- |
| Completeness | 14/14 задач завершены; implementation опубликован в PR #75 с target dev |
| Correctness | 5/5 ADDED/MODIFIED requirements реализованы; 18 scenarios сверены с кодом и tests, включая сохранённые границы MVP |
| Coherence | D1–D4 соблюдены, точные API-поля/версии сохранены; код использует существующие DI/ApiTransport/ExecutionLedger слои |

## Requirement mapping

- Explicit task tag input: TaskInputMapper, AddTaskRequest/UpdateTaskRequest и CLI options; TaskTagsTest create/mixed/tags-only/null/clear/validation. Точная дедупликация, кириллица/#/пробелы сохраняются, whitespace-only Unicode и invalid UTF-8 отклоняются локально.
- Planned task tag routes: AddTaskHandler/UpdateTaskHandler, CommandCatalog::ROUTES, ResultPresenter; unit strict policy and create dependency в JSON/human/plain. REST3 fields не содержит TAGS/tags, legacy gateway допускает только TAGS; advanced editable gate сохранён.
- Account for partial task tag mutations: TaskTagMutation и ExecutionLedger; unit create/update success + tag failure/lost response, tags-only/first-write failure и cancellation; настоящий SDK mock подтверждает, что cancel между шагами не отправляет второй запрос. Известный TASK_ID и подтверждённые поля сохраняются, auto retry/delete отсутствуют.
- Independent tag readback: существующий task:show projection (unit), SDK readback tags.id/name на реальном портале (contract probe и CLI integration).
- API admission and future lifecycle acceptance remain explicit: CommandCatalog + ApiPolicyGuard и conditional routes; unit policy/canonical catalog/unsupported advanced checks подтверждают узкий допуск и сохранение исключённых операций. Число task-команд не изменяется; future lifecycle остаётся прежней областью после MVP.

## Local checks

Runtime: изолированный Docker PHP 8.4.26, locked Composer dependencies. Команды: `make unit`, `make lint-all`, `make check`, `openspec validate add-task-tags --strict`, `git diff --check`.

Для Docker на этой машине понадобился локальный ignored override `var/cache/compose-tags-network.yaml` с отдельной подсетью 10.250.71.0/24: автоматический pool исчерпан. Make выполнялся с `COMPOSE='sh ./scripts/compose.sh --file var/cache/compose-tags-network.yaml'`. Сети других проектов не удалялись, tracked Docker config не менялся.

- Полная unit suite, четыре quality checks (licenses/CS/PHPStan/Rector), Composer strict/platform checks, syntax и offline CLI startup прошли. Итог: **397 tests, 2384 assertions**, без skips; CS: 0/191 файлов требуют правки; PHPStan/Rector/licenses успешны. Native CI проверяется на SHA опубликованного PR отдельно.
- Ранее неуспешные codec assertions исправлены: SDK добавляет request ID и trailing slash к URL; payload TAGS=[] сохраняется. Style/Rector findings исправлены; прежние неуспешные попытки не объявляются успешными проверками.

## Portal checks

- [api-contract.md](api-contract.md) и [portal-contract-receipt.json](portal-contract-receipt.json): fixture5870, actingUserId1; REST3 create, REST1 set/replace/clear, независимый REST3 readback. Все три проверки совпали; cleanupConfirmed=true.
- [portal-cli-receipt.json](portal-cli-receipt.json): 2 CLI integration tests, 37 assertions, 0 skips. Fixture5892 (task:add с тегами) и fixture5894 (tags-only/mixed update, omission preservation, clear); cleanupFailures пусты.
- Реальный getFields не содержит TAGS: write-тип и семантика подтверждены выполнением на этом подключении, не универсальной metadata-гарантией. Максимальные лимиты, другие роли/порталы не проверялись. В integration не было forced network failure; fault/cancellation paths проверены локально.

## Findings and assessment

Critical implementation findings: 0. Warnings: 0. Suggestions: 0 в выполненной сверке требований и дизайна. Доставка implementation подтверждена PR #75: head feature/71-add-task-tags, base dev; перед публикацией свежий fetch/merge origin/dev — Already up to date. Remote CI проверяется отдельно; merge/release не заявляются. Change остаётся активным до review/merge и последующего maintainer archive workflow.

## Dev sync after the first successful CI

Во время native CI в dev вошёл PR #81 / issue #78 (обязательный issue-linked changelog). Первоначальный SHA `bca2a57f46a3b7c78f2cb43c0e1006340dcb1990` прошёл 17/17 checks, receipts опубликованы в PR/issue. Новый dev `de98df7` интегрирован через merge с сохранением двух секций changelog и добавлением ссылки #71 к обеим feature-записям. Runtime и тесты не изменены. После синхронизации повторены unit/OpenSpec/diff checks; CI проверяется заново на merge SHA.

## Completion receipt — 13.10.2026

PR #75 merged to dev at `e6accd2c8b96177f08b84e458f7f2f18fc408af4`; issue #71 CLOSED. Before merge, HEAD `3c473bee0a9d35741ca4a7d0b9431ff453a0884d` passed 17/17 CI checks. New dev changes #59/#61/#78 were preserved during conflict resolution; combined suite: 422 tests / 2789 assertions, all quality and startup checks passed. Tag integration repeated: 2 tests / 37 assertions, cleanup confirmed.

Archive sync: 4 runtime requirements added, 1 MVP API admission requirement updated. Existing titles/Purpose and requirements not named in the deltas were preserved. `openspec validate --specs` succeeded; all five resulting requirement blocks match the delta specs. Original failure and earlier success receipts remain above. Archive is delivered through a separate PR to dev; its merge and worktree removal are confirmed in the final GitHub receipt.

Validation boundary: normal main-spec validation passed 9/9 specs, with one non-blocking warning that the complete modified API admission requirement exceeds 500 characters. Its normative text/scenarios were preserved rather than shortened; the archive does not claim strict global-spec validation passed.
