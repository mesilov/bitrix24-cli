# Tasks

Реализация разрешена 2026-10-09. Checkboxes отмечаются только после реализации и соответствующих проверок. Portal acceptance остаётся открытым до реального запуска. Контракты: [runtime spec](specs/task-console-runtime/spec.md), [design](design.md), [30 команд](command-map.md).

## 1. Standalone bootstrap и DI

- [x] 1.1 Добавить совместимый с PHP 8.4/8.5 `symfony/dependency-injection:^8.0` и offline test harness; проверить composer validate/check-platform-reqs, существующие license checks и запуск тестов внутри checkout-specific Docker. FrameworkBundle/Kernel не добавлять.
- [x] 1.2 Реализовать ContainerFactory/ApplicationFactory, PHP definitions, явные tags и RegisterTaskCommandsPass → ContainerCommandLoader; проверить на подставных командах duplicate/mismatched name/alias rejection, autowiring/port replacement и отсутствие credential reads при compile; runtime не читает openspec каталог.
- [x] 1.3 Реализовать единый bootstrap для bin/b24cli и bin/console, отложенный client provider и build version; проверить оба launcher, offline help/list/version/completion и zero calls через spy resolver. Полный inventory из 30 проверяется после группы 4.

## 2. Ввод, выполнение и вывод

- [x] 2.1 Добавить глобальные проектные опции в B24Application с parent InputDefinition; проверить ApplicationTester/subprocess cases flags до/после имени, -h/-V/-q/-v/-n/--silent/--no-ansi и -- separator; не переиспользовать -n для dry-run.
- [x] 2.2 Реализовать application error boundary и преобразование unknown/ambiguous имени до native typo prompt; проверить missing argument/unknown option/typo без mutation, один JSON error envelope, usage exit2 и сохранённые однозначные Symfony сокращения.
- [x] 2.3 Реализовать OptionPresence, InputSourceReader и typed input validators; проверить omission/empty description, непустой title/text, positive IDs/seconds/budgets, dates с offset, separate ordinary/advanced modes, JSON object, stdin TTY/двух потребителей и конфликтующие flags. Invalid local input не читает credentials.
- [x] 2.4 Реализовать InvocationContext, ApiPolicyGuard, prepare/execute цикл CommandRunner и delete ConfirmationPolicy; проверить default/strict policy до первого network step, dry-run только с disclosed reads, force только для confirmation, noninteractive delete exit2 и отказ TTY без mutation.
- [x] 2.5 Реализовать OperationResult, FailureMapper/ExitStatusMapper, human/plain/JSON presenters и redacted logging; проверить все profiles/TSV columns из карты, structured REST3 validation errors, empty/one/many lists, escaping, stderr separation, quiet/silent, NO_COLOR/non-TTY и коды 0/1/2/3/4/130. Документировать форматы рядом с командной справкой.
- [x] 2.6 Реализовать ExecutionLedger, Cancellation service и конечный HTTP timeout; в управляемом transport/subprocess проверить confirmed partial steps, lost write response/outcomeUnknown без retries, SIGINT между шагами и timeout. Подтвердить pcntl/signal поведение в поставляемом CLI окружении; не выдавать flag unit test за runtime evidence.

## 3. API-порты и адаптеры

- [x] 3.1 Реализовать EnvConnectionResolver и B24ClientProvider для согласованного BITRIX24_WEBHOOK из root .env (process env precedence), тестовый provider и Docker read-only env bind; OAuth/token storage вне этой пачки. Проверить deferred creation, secret-safe diagnostics, timeout propagation и отсутствие secret argv; обозначить prerequisite для portal tests в документации.
- [x] 3.2 Реализовать TaskGateway/Rest3TaskAdapter; проверить fixture-based method/version/payload/response contracts add/get/Core v3 list/sparse array update/delete/field.list/get editable/access/file.attach per-ID/elapsedTime context, relation IDs и явный Core ApiVersion. Запретить raw status и fallback; задокументировать допустимые writable/select/order поля закреплённого API.
- [x] 3.3 Реализовать TaskChatGateway/TaskChatAdapter и MessageBindingVerifier; проверить v3 send/task lookup + IM v1 routes, task/chat association, wrong MESSAGE_ID, bounded history scan/no-progress и gate4 без update/delete. Проверить boolean send ACK без ID; документировать companion API/права/current identity и finite budget.
- [x] 3.4 Реализовать TimeEntryGateway/Rest1TimeEntryAdapter и EntryBindingVerifier; проверить positional API codec/NAV_PARAMS, typed --params allowlist, TASK_ID/ENTRY_ID match, ограниченную pagination и сохранение omitted text. Strict policy не выполняет preflight или legacy mutation; права остаются portal case.
- [x] 3.5 Реализовать ChecklistGateway/Rest1ChecklistAdapter и NodeBindingVerifier; проверить Core PARENT_ID=0/explicit item parent, root/item partition, nested tree/SORT_INDEX order/Y-N normalization, чужой parent/item/root ID, циклы/неполные данные и отсутствие task lifecycle changes. В help описать subtree эффект item delete по подтверждённому API контракту.
- [x] 3.6 Реализовать ParticipantGateway/Rest1ParticipantAdapter и TaskHistoryGateway/Rest1TaskHistoryAdapter; проверить только два role sets/explicit clear/omission, version distinction общего update имени, history filter/order allowlist и нормализацию from/to/user. Проверить navigation fixtures и partial при недоказанной полноте; записать конкретное ограничение истории в help.

- [x] 3.7 Исправить method-specific REST1 подтверждения: `[null]` для elapseditem update/delete и checklistitem update, `task.id=TASK_ID` для participants update. Добавить регрессии через реальный SDK response codec, отклонение false/пустых/чужих ответов и повторить live readback/cleanup.

- [x] 3.8 Раскрыть корневые select accomplices/auditors в документированные id/name проекции, сохранить явные dotted select и исключить ненужное чтение участников из preflight participants:set. Проверить live omission/clear/history; filled relation readback через тестовый REST1 get, а отказ REST3 проекций фиксировать отдельной portal limitation без production fallback.

- [x] 3.9 Поддержать предоставленный пользователем root .env.local по умолчанию с fallback к .env, process precedence и явным B24CLI_ENV_FILE; синхронизировать fixture settings/docs и проверить секрет-safe диагностику/выбор файла офлайн.

- [x] 3.10 Отделить SDK INTERNAL_SERVER_ERROR от сетевой ошибки: безопасный api-error с apiErrorCode, outcomeUnknown для записи и отсутствие fallback/retry; проверить через реальный SDK HTTP codec и live заполненную auditor projection.

## 4. Обработчики и 30 Command классов

- [x] 4.1 Реализовать AddTask/ShowTask/DeleteTask/ListTaskFields/ShowTaskAccess handlers и соответствующие 5 Commands из карты; проверить обычный/advanced add, обязательные поля, writable metadata, read profiles, delete preview/confirmation и command help examples. Dedicated subtask class/alias не создавать; explicit parentId трактовать по текущему scope.
- [x] 4.2 Реализовать UpdateTaskRequest/Handler и три Commands update/assign/deadline:set; проверить один update вызов только с явным patch, разные field allowlists, empty versus omitted description, отдельный advanced mode и отказ status. Help показывает прямые terminal examples.
- [x] 4.3 Реализовать TaskListScanner и ListTask/FindTask Commands/handlers; проверить page2 match, Unicode literal title, empty/one/many list shape, local predicates/where, documented server order/id filter, missing fields/cap/repeated page/failure, limit/all и scope/scanned/matched/returned/limitApplied. Закрепить output id/title для find в help.
- [x] 4.4 Реализовать 4 chat Commands/handlers и file attach; проверить text/text-file, history window, task/message binding и rights failure fixtures, v3 send без legacy comments, explicit Disk IDs, multi-attachment ledger/dry-run и output profiles; документировать отсутствие неявного upload.
- [x] 4.5 Реализовать time:show и 4 time entry Commands/handlers; проверить elapsedTime context без обещания total/completeness отдельно от entries, seconds/text, list params/page scope, entry binding, dry-run/confirmation, API versions и EMP-06 cases на fake gateway; дополнить help/API ограничения.
- [x] 4.6 Реализовать 2 root и 6 item Commands/handlers; проверить explicit root/parent/title, descendants list, TITLE-only update, complete/renew только item, delete subtree preview/confirmation и 8 route plans. Help использует разные CHECKLIST_ID/ITEM_ID и повторяет version/policy ограничения.
- [x] 4.7 Реализовать participants:set/history:list Commands/handlers; проверить role replacement/clear conflict/no-op, сохранение omitted role, restricted legacy payload, history как список изменений и partial semantics; добавить прямые examples и отделить history от chat в help.

## 5. Интеграция и приёмка

- [x] 5.1 Сверить фактический command inventory и DI wiring со всеми 30 строками карты: 30 уникальных имен/classes, 28 handlers, ни одной из 29 post-MVP операций/aliases; проверить offline list/help/completion обоих launcher, common options/output/error cases и отсутствие API/credentials. Выполнить project lint/check и статический анализ в Docker.
- [ ] 5.2 На тестовом портале с согласованным connection provider проверить v3 карточку/schema/выборки/title search, task/chat association и message update/delete под допустимой и запрещённой ролью; сохранить role/API version/targets/observed results, отдельную полноту и отсутствующие cases в evidence без secrets.
- [ ] 5.3 На тестовом портале проверить 4 time и 8 checklist/root/item REST1 команды: create/list/update/delete собственных time entries, wrong-task/rights rejection, root/nested item/tree state и item complete/renew без смены task status. Сохранить runtime evidence отдельно от fixtures и scope admission.
- [ ] 5.4 На тестовом портале проверить participants REST1 в новой карточке, omitted/cleared roles, отказ прав и history navigation/completeness; проверить strict-rest3 zero-call rejection всех non-v3 планов. Недоказанную completeness показать как partial и зафиксировать ограничение.
- [x] 5.5 Проверить пользовательские terminal workflows и HTTP/signal behavior поставляемого окружения, выполнить openspec validate --specs --strict и validate change --strict, подготовить verification artifact с раздельным local/portal evidence. Открытые условия не отмечать выполненными; проверить issue↔change и MR target dev.

## Workflow follow-up

- Пользователь разрешил apply; сохранять source/fixture/portal evidence раздельно.
- После реализации и проверки применить openspec-verify-change; архивировать через openspec-archive-change и обновить путь change в issue #10.
- Сохранить связь issue #10 → research/define-task-jtbd/architecture → PR в dev; main delivery/merge не выполняются этой пачкой.
