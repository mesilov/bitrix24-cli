# Tasks

## 1. Calendar arguments and resolver

- [x] 1.1 Добавить canonical windows/overdue/timezone в public task:list/task:find и TaskQuery/TaskInputMapper; проверить help/completion и usage-error до API для unknown/repeated tokens, неверного timezone и конфликтов due-before/where.
- [x] 1.2 Реализовать общий calendar resolver с Clock и metadata; проверить frozen-clock тестами все 7 windows, понедельник, полные месяц/квартал, timezone, DST, високосный февраль, deadline=now и пересечение будущего окна с overdue.
- [x] 1.3 Документировать предложенную бизнес-политику просрочки, отличие от actual closed date и default timezone в help/README; проверить examples и status semantics по spec.

## 2. Server route and bounded scanner

- [x] 2.1 Добавить REST1 list adapter с >=DEADLINE/<DEADLINE/GROUP_ID, select, start и result.tasks normalization; проверить request fixtures, response shape, IDs/dates и точное соответствие register/enum обеих версий.
- [x] 2.2 Проверить numeric REAL_STATUS mapping и допустимую серверную композицию статусов по MCP/metadata и read-only portal examples; закрепить один выбранный payload или локальный lifecycle predicate, проверив completed/declined/deferred/supposedly_completed отдельно от STATUS=-1.
- [x] 2.3 Выбирать temporal REST1 route до prepare, обновить CommandCatalog/ApiPolicyGuard; проверить strict-rest3 policy-denied/exit4 без сетевых запросов и отсутствие REST3 retry/legacy fallback.
- [x] 2.4 Подключить adapter к scanner двух команд; проверить pagination, AND group/role/status/title, literal Unicode title, null/missing predicate fields, max-scan/partial/exit3, empty/full results и output limit/all.
- [x] 2.5 Обновить README/help с route, API scope, timezone/interval metadata и limits; проверить обычный/plain/JSON output и regression fixtures для non-temporal REST3/due-before.

## 3. Skill and integrated acceptance

- [x] 3.1 Обновить отдельный bitrix24-cli-skill после доступности flags; проверить перевод каждой user story в public CLI args и отказ от direct HTTP/SDK/MCP обхода при неподдерживаемой установленной версии.
- [x] 3.2 Проверить реализацию CLI на текущем портале read-only для date windows, проекта и overdue с сохранением безопасного receipt; не засчитывать api-evidence.json постановки как доказательство реализации CLI.
- [x] 3.3 Запустить предусмотренные repository unit/command checks и linters, OpenSpec verify/strict validate; проверить trace issue #95 ↔ proposal и внести CHANGELOG для реализации/MR, отделив native CI и live evidence от локальных проверок.

## Workflow follow-up

- После реализации/verify и подтверждённого merge в dev архивировать change, обновить путь в issue и сохранить результат в dev по правилам maintainer; worktree удалить только после сохранения всех артефактов.
- Постановка не реализует flags и не закрывает issue; задачи отмечаются только по факту выполнения.
