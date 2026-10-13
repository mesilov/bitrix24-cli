# Tasks

## 1. CLI и идентичность выбранного подключения

- [x] 1.1 Добавить typed role в TaskQuery и --role с четырьмя completion values в task:list/task:find; проверить unit cases неизвестного/пустого/составного значения, отсутствия флага и offline help/completion без credentials/API.
- [x] 1.2 Добавить application port текущего пользователя, connection adapter и DI на выбранном ConnectionResolver с действующей URL validation и positive integer overflow guard; проверить unit cases explicit profile/env-file/process env, отличающихся default.task IDs, отсутствующего/неверного/переполненного ID и нераскрытия URL/token.
- [x] 1.3 Добавить четыре ролевых примера и соответствие русским группам в help/README, описать владельца выбранного подключения без истории миграции; проверить consistency имён role и API fields с design/spec.

## 2. Проверка роли в существующем bounded scanner

- [x] 2.1 Реализовать responsible/creator по нормализованным REST3 responsibleId/creatorId, выбирая нужное поле и сохраняя AND с where/convenience flags; проверить обе команды на positive/negative, self-created/overlapping roles, недостающих/повреждённых fields и противоречивом responsible.
- [x] 2.2 Реализовать accomplice/auditor через существующий ParticipantGateway::ids для уникальных задач после остальных локальных predicates; проверить обе команды на positive/negative/empty membership, правильную TASK_ID, отсутствие ненужных REST3 participant projections и user.get/user.current, дедупликацию и read budget.
- [x] 2.3 Сохранить pagination/max-scan/limit/all/partial/cancellation и добавить meta.roleFilter role/userId без credentials; проверить second-page match, output limit, scan cap, role-read-error с сохранёнными подтверждёнными результатами, чужую задачу, malformed participant IDs, отсутствие следующего запроса после ошибки/отмены и human/plain/json shapes.
- [x] 2.4 Описать AND, scan budget, стоимость companion reads и неполную проверку роли в docs/development.md; сверить документированные примеры task:list --where/--group-id и task:find --title/--group-id с реализованным интерфейсом, не добавляя scope #12.

## 3. API policy и разрешённые маршруты

- [x] 3.1 Обновить handlers/command closures и CommandCatalog::ROUTES для условного REST1 tasks.task.get read только при accomplice/auditor; проверить unit strict-rest3 rejection/exit 4 до credentials/API (включая потенциально пустой список), strict-rest3 scalar success и отсутствие новых маршрутов без role.
- [x] 3.2 Добавить policy примеры/ограничения в help/docs и проверить route accounting, отсутствие writes/retry/fallback и сохранение существующего participant --select контракта отдельными unit cases; известный native auditors error не объявлять исправленным.

## 4. Отдельная portal acceptance обеих команд

- [x] 4.1 Расширить ListTaskCommandTest и FindTaskCommandTest четырьмя role cases на собственных подходящих/неподходящих fixtures с SDK setup/readback/cleanup, сохраняя правило одной CLI-команды на класс; проверить PHPUnit discovery и независимый запуск command groups без requirement изменять остальные integration классы.
- [x] 4.2 Выполнить доступные целевые portal cases через make integration TEST_ARGS='--group command:task:list' и отдельный запуск command:task:find; сохранить evidence для каждого role/command, scopes и второго пользователя, явно отметить skips/отсутствующие условия как live evidence gaps, не подменяя их unit success.

## 5. Проверка соответствия и review

- [x] 5.1 Обновить origin/dev и сверить входящие изменения scanner/handlers без перезаписи чужой работы; выполнить make unit, make lint-all, make check и openspec validate add-task-role-filters --strict, сохранив точные результаты и SHA в verification.md.
- [x] 5.2 Выполнить openspec-verify-change: сопоставить все требования/scenarios с кодом и tests, проверить issue ↔ proposal, milestone 0.2.0, Feature/enhancement и exact API names/types; подготовить PR в dev с реальным объёмом, локальными проверками и отдельной границей portal evidence.

## Workflow follow-up

- После завершения реализации и verify архивировать change через openspec-archive-change и синхронизировать delta specs в актуальные openspec/specs; доставить архив/спецификации в dev через PR, не оставлять их только в worktree.
- Обновить фактический архивный путь в issue #68 и проверить обе стороны ссылки.
- Merge выполнять только в разрешённом пользователем объёме и подтверждать live состоянием PR/base/merge SHA. После подтверждённого merge в dev и закрытия issue выполнить maintainer cleanup: сохранить все данные, выйти из worktree и удалить только связанный worktree без --force; проверить git worktree list.
