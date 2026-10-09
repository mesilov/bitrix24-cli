# Карта команд Console для MVP

Дата: 2026-10-09. **Проектирование, не реализованные классы.** Исходный scope/API evidence — [cli-candidates.json](../define-task-jtbd/cli-candidates.json); требования — [UX](../../specs/cli-experience/spec.md), [scope](../../specs/task-mvp-scope/spec.md), [runtime delta](specs/task-console-runtime/spec.md). Цикл и ограничения — [design.md](design.md).

30 имён ниже — полный набор прикладных команд MVP; встроенные Symfony help/list/completion считаются отдельно. Команда `task:subtask:add` и остальные 28 post-MVP операции не регистрируются. Колонка API сохраняет основные catalog method_routes, включая штатный preflight. Колонка дополнительных чтений описывает проектные проверки внутри выбранных API семейств; она не означает проверку на реальном портале.

Общий namespace команд: `Bitrix24\CLI\Console\Command\Task`; requests и handlers: `Bitrix24\CLI\Application\Task\Request` и `Bitrix24\CLI\Application\Task\Handler`. В колонке handler для краткости указан общий stem: `AddTask` означает `AddTaskRequest` / `AddTaskHandler`. **30 Command классов / 28 пар Request–Handler**. Обработчик UpdateTask используется тремя командами; допустимый patch зависит от operation identity.

## Карточка, выборки, поля и доступ

| Команда | Command class | Request / Handler stem | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:add` | `AddTaskCommand` | `AddTask` | `tasks.task.add@3.0` | Advanced: `tasks.task.field.list@3.0` для writable validation; title/creator/responsible обязательны; обычный/advanced ввод раздельны | mutation |
| `task:show` | `ShowTaskCommand` | `ShowTask` | `tasks.task.get@3.0` | Select validation; relation IDs нормализуются; карточка выбранной TASK_ID | task-card |
| `task:list` | `ListTaskCommand` | `ListTasks` | `tasks.task.list@3.0` | Server filter только id; local predicates/where; selected fields включают поля фильтра; finite scan/limit/all | task-list |
| `task:find` | `FindTaskCommand` | `FindTasks` | `tasks.task.list@3.0` | Literal Unicode substring title, trim, id ASC; zero/one/many всегда items array; finite scan | title-matches |
| `task:update` | `UpdateTaskCommand` | `UpdateTask` | `tasks.task.update@3.0` | Advanced: `tasks.task.field.list@3.0`; nonempty explicit patch; status/read-only запрещены; поля отсутствия сохраняются | mutation |
| `task:delete` | `DeleteTaskCommand` | `DeleteTask` | `tasks.task.delete@3.0` | `tasks.task.get@3.0` для цели/названия; force либо TTY confirmation; без force/-n ошибка до запроса | mutation |
| `task:fields:list` | `ListTaskFieldsCommand` | `ListTaskFields` | `tasks.task.field.list@3.0`, `tasks.task.field.get@3.0` | list без --name либо get выбранного --name; всегда список из 0/1/many metadata records | task-fields |
| `task:access:show` | `ShowTaskAccessCommand` | `ShowTaskAccess` | `tasks.task.access.get@3.0` | Только доступ текущего подключения; role name не вычисляет права | task-access |
| `task:assign` | `AssignTaskCommand` | `UpdateTask` | `tasks.task.update@3.0` | Только --responsible → responsibleId; никакого выбора «текущего» сотрудника | mutation |
| `task:deadline:set` | `SetTaskDeadlineCommand` | `UpdateTask` | `tasks.task.update@3.0` | Только --at → deadline; ISO8601 с явным offset | mutation |

## Чат и файлы

| Команда | Command class | Request / Handler stem | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:chat:list` | `ListTaskChatCommand` | `ListTaskChat` | `tasks.task.get@3.0`, `im.dialog.messages.get@1.0` | Проверить task/chat association; before/after исключают друг друга; finite messages budget; IM version видима | chat-messages |
| `task:chat:send` | `SendTaskChatMessageCommand` | `SendTaskChatMessage` | `tasks.task.chat.message.send@3.0` | Один explicit text/text-file, нет legacy comment; MESSAGE_ID результата; acting connection identity | mutation |
| `task:chat:update` | `UpdateTaskChatMessageCommand` | `UpdateTaskChatMessage` | `tasks.task.get@3.0`, `im.message.update@1.0` | `im.dialog.messages.get@1.0` для MESSAGE_ID binding к chat выбранной задачи; budget10 000, иначе gate4; server author/admin rights | mutation |
| `task:chat:delete` | `DeleteTaskChatMessageCommand` | `DeleteTaskChatMessage` | `tasks.task.get@3.0`, `im.message.delete@1.0` | `im.dialog.messages.get@1.0` для binding; delete confirmation не отменяет связь/права | mutation |
| `task:file:attach` | `AttachTaskFilesCommand` | `AttachTaskFiles` | `tasks.task.file.attach@3.0` | Повторяемые уже загруженные Disk IDs; никаких local upload; ledger нескольких attachments | mutation |

## Время

| Команда | Command class | Request / Handler stem | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:time:show` | `ShowTaskTimeCommand` | `ShowTaskTime` | `tasks.task.get@3.0` | Select elapsedTime; агрегат не подменяет историю entries | time-summary |
| `task:time:add` | `AddTaskTimeEntryCommand` | `AddTaskTimeEntry` | `task.elapseditem.add@1.0` | Seconds integer >0, optional text; действует от текущего подключения; явный REST1 admission | mutation |
| `task:time:list` | `ListTaskTimeEntriesCommand` | `ListTaskTimeEntries` | `task.elapseditem.getlist@1.0` | Typed --params query schema; positional API codec; pagination/scope/budget; no arbitrary payload | time-entries |
| `task:time:update` | `UpdateTaskTimeEntryCommand` | `UpdateTaskTimeEntry` | `task.elapseditem.update@1.0` | `task.elapseditem.getlist@1.0` для TASK_ID/ENTRY_ID binding; seconds >0; omission text сохраняет комментарий; server rights | mutation |
| `task:time:delete` | `DeleteTaskTimeEntryCommand` | `DeleteTaskTimeEntry` | `task.elapseditem.delete@1.0` | `task.elapseditem.getlist@1.0` для binding; force/confirmation; без обхода server rights | mutation |

## Чек-листы и пункты

| Команда | Command class | Request / Handler stem | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:checklist:add` | `AddTaskChecklistCommand` | `AddTaskChecklist` | `task.checklistitem.add@1.0` | Core v1, явный PARENT_ID=0; непустой title; SDK wrapper не принимает PARENT_ID | mutation |
| `task:checklist:list` | `ListTaskChecklistsCommand` | `ListTaskChecklists` | `task.checklistitem.getlist@1.0` | Только корни PARENT_ID=0, flat response задачи; неполное дерево не объявляется полным | checklist-roots |
| `task:checklist:item:add` | `AddTaskChecklistItemCommand` | `AddTaskChecklistItem` | `task.checklistitem.getlist@1.0`, `task.checklistitem.add@1.0` | Выбранный root/parent принадлежат TASK_ID; явный PARENT_ID через Core v1; нет auto root | mutation |
| `task:checklist:item:list` | `ListTaskChecklistItemsCommand` | `ListTaskChecklistItems` | `task.checklistitem.getlist@1.0` | Root обязателен; только его descendants, включая nested; ID/PARENT_ID и deterministic tree order | checklist-items |
| `task:checklist:item:update` | `UpdateTaskChecklistItemCommand` | `UpdateTaskChecklistItem` | `task.checklistitem.getlist@1.0`, `task.checklistitem.update@1.0` | Проверенный item, не root; только nonempty title; остальные поля сохраняются | mutation |
| `task:checklist:item:complete` | `CompleteTaskChecklistItemCommand` | `CompleteTaskChecklistItem` | `task.checklistitem.getlist@1.0`, `task.checklistitem.complete@1.0` | Item binding; lifecycle задачи не меняется | mutation |
| `task:checklist:item:renew` | `RenewTaskChecklistItemCommand` | `RenewTaskChecklistItem` | `task.checklistitem.getlist@1.0`, `task.checklistitem.renew@1.0` | Item binding; lifecycle задачи не меняется | mutation |
| `task:checklist:item:delete` | `DeleteTaskChecklistItemCommand` | `DeleteTaskChecklistItem` | `task.checklistitem.getlist@1.0`, `task.checklistitem.delete@1.0` | Проверить item и descendants; API удаляет subtree, показать его в плане/подтверждении; root не допускается | mutation |

## Участники и история изменений

| Команда | Command class | Request / Handler stem | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:participants:set` | `SetTaskParticipantsCommand` | `SetTaskParticipants` | `tasks.task.update@1.0` | `tasks.task.get@3.0` для TASK_ID/текущих ролей; полная замена только явно заданной роли, explicit clear; нет status/прочего legacy payload | mutation |
| `task:history:list` | `ListTaskHistoryCommand` | `ListTaskHistory` | `tasks.task.history.list@1.0` | Только typed filter.FIELD/order.createdDate; navigation/completeness acceptance; история отдельно от чата | task-history |

## Ввод и режимы

Точный список бизнес-опций для каждой команды сохраняется в [catalog](../define-task-jtbd/cli-candidates.md). Ко всем 30 применяются общие проектные и native Symfony options из design; у всех write/delete есть `--dry-run`, у delete — `--force`. У read нет dry-run/force. Common options не копируются 30 раз в классы.

Обычный и advanced режим add/update раздельны. --fields-file/--description-file/--text-file/--where-file/--params-file принимают только явный PATH или `-`; stdin не читается автоматически. --limit/--all, --before/--after, --json/--plain и IDs/clear одной роли взаимоисключающие. Related IDs всегда flags, task ID positional. Нет TASK_ID у add/list/find/fields:list.

Каждая строка имеет собственную command contract проверку: допустимый ввод → typed Request → ожидаемый маршрут/effect/output; missing/conflicting input → usage2 без подключения. Для всех дополнительных чтений отдельно проверяются API policy и отсутствие mutation в dry-run. Общие parser/version/confirmation/scan tests перечислены в [tasks.md](tasks.md).

## Output profiles

Это проектные стабильные поля нормализованного результата, а не требование вернуть сырой API payload. JSON использует общий envelope. Для списков data.items всегда array. Human показывает те же существенные значения в таблице/карточке. Plain без заголовка; точный порядок TSV columns:

| Profile | Data shape | TSV columns по порядку |
| --- | --- | --- |
| task-card | data.task object | field, value; одна строка на нормализованное выбранное поле, lexicographic field order |
| task-list | data.items | id, title, responsibleId, groupId, status, deadline |
| title-matches | data.items, только id/title | id, title |
| task-fields | data.items metadata records | name, type, readOnly, filterable, sortable; неизвестное metadata — null |
| task-access | data.access object | permission, allowed; lexicographic permission order |
| chat-messages | data.items | id, createdDate, authorId, text; порядок ID ASC внутри выбранного window |
| time-summary | data.time object | taskId, elapsedSeconds; прочие aggregate fields остаются в JSON/human |
| time-entries | data.items | id, userId, seconds, text, createdDate; default ID ASC |
| checklist-roots | data.items | id, title; SORT затем ID, adapter сохраняет SORT в JSON |
| checklist-items | data.items | id, parentId, title, isComplete; preorder, siblings SORT затем ID |
| task-history | data.items | id, createdDate, userId, field, from, to; default createdDate DESC, tie ID DESC |
| mutation | data.operation object; при нескольких attachments data.items ledger | taskId, resourceId, action, changedFields; одна строка на confirmed step, changedFields — компактный JSON array |

У task:list adapter добавляет стабильные profile fields и predicate fields к --select; JSON сохраняет явно выбранные дополнительные поля. Partial/meta диагностируются отдельно от TSV rows. В mutation task-level resourceId равен taskId; для child операции — message/entry/checklist/item/file ID; отсутствие известного ID после неопределённого add не выдумывается. Patch values видны в dry-run, обычное подтверждение показывает changedFields без полного тела API.

Dry-run использует отдельный **plan** profile: JSON data.plan object, human цель/поля/steps, plain taskId, step, method, apiVersion, effect, fields (compact JSON). Выполненные read steps отмечаются в meta; предполагаемые write steps не выдаются за executed. Этот формат не заменяет обычный result profile команды.
