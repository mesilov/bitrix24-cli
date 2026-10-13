# Tasks

## 1. Contract gate

- [x] 1.1 Подтвердить exact REST1 TAGS write contract через официальный MCP и metadata/schema либо receipt реальных запросов, если metadata не содержит TAGS: taskId/fields, тип/необязательность TAGS, замена/clear, ограничения; сохранить api-contract.md со ссылками и точными ответами. При отсутствии подтверждения остановить API-зависимую реализацию.
- [x] 1.2 Проверить предложенный REST3 add → REST1 TAGS update/replace/clear на disposable fixture существующим provider/SDK, с независимым tags.id/tags.name readback и cleanup; сохранить portal receipt без credentials. Это gate до adapter, не локальное доказательство.

## 2. CLI input and policy

- [x] 2.1 Добавить repeatable --tag в add/update и --clear-tags в update, nullable request.TAGS отдельно от fields; unit tests проверяют tags-only update, null/[], кириллицу/#/пробелы, дедупликацию, пустые строки и конфликты advanced/tag/clear до credentials/API.
- [x] 2.2 Добавить узкий conditional REST1 update route в handler/catalog; unit tests подтверждают strict-rest3/exit4 до первого запроса и REST3-only маршрут без тегов. Advanced tags/TAGS остаются read-only с подсказкой --tag; общий editable gate не отключён.
- [x] 2.3 Обновить help примерами create/replace/clear и policy; offline help/contract tests подтверждают новые опции и отсутствие новых команд или profile defaults тегов.

## 3. Gateway and composed execution

- [x] 3.1 После contract gate реализовать TaskTagGateway/REST1 adapter только для fields.TAGS и подключить DI; SDK-codec tests проверяют точные taskId/fields, version1 и пустой массив, method-specific совпадающий task.id ACK, false/null/чужой ID и неизвестность неподтверждённой записи.
- [x] 3.2 Реализовать REST3 create → TAGS update и mixed ordinary update → TAGS update; CommandContracts/Behavior tests подтверждают порядок, ID одной задачи, отсутствие пустого ordinary update и отсутствие tag write без опции.
- [x] 3.3 Расширить prepared plan зависимостью taskIdFromStep; unit dry-run tests подтверждают точные fields/API versions, taskId=null + dependency после create и отсутствие write calls в preview.
- [x] 3.4 Обеспечить ledger/partial с известным TASK_ID, complete=false/exit3, outcomeUnknown, stop after failure, cancellation/exit130; unit tests покрывают create success + tags failure/lost response, первый create failure/unknown, tags-only failure и отсутствие auto retry/delete.
- [x] 3.5 Проверить human/plain/json для success/partial/error и recovery только через task:update известной задачи; renderer tests подтверждают единый envelope, доступность подтверждённых шагов/ID и отсутствие credentials/SDK объектов.
- [x] 3.6 Обновить README, каталог команд и changelog для 0.2.0: --tag/--clear-tags, exact API versions, partial recovery и отсутствие атомарности; сверить примеры с unit plans и CLI help.

## 4. Integrated acceptance and delivery

- [x] 4.1 Добавить собственные live cases AddTaskCommandTest/UpdateTaskCommandTest через production bootstrap: create с тегами, replace/clear/preserve и tags-only; проверить отдельные command groups, Unicode/#/пробелы, SDK readback и cleanup; записать роль/версию/ограничения отдельно от unit results.
- [x] 4.2 Выполнить make unit и quality targets из актуального Makefile, strict OpenSpec validation и diff check; сохранить результаты в verification.md и не засчитывать skips как portal success.
- [x] 4.3 Проверить issue #71 ↔ proposal/path, milestone0.2.0/enhancement и отсутствие userFields/общего editable fix в diff; выполнить fresh fetch + merge origin/dev, повторить затронутые проверки и открыть implementation PR в dev со ссылками и проверенными результатами.

## Workflow follow-up

- После подтверждённого merge в dev и закрытия #71 выполнить maintainer workflow: проверить соответствие реализации, синхронизировать specs, архивировать change, доставить архив в dev, обновить путь в issue и удалить только этот worktree после сохранения данных.
