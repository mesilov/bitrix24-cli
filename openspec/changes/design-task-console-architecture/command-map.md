# Карта команд Console для MVP

Проектирование: 2026-10-09; реализация и уточнение task:show: 2026-10-11. Исходный scope/API evidence — [cli-candidates.json](../define-task-jtbd/cli-candidates.json); требования — [UX](../../specs/cli-experience/spec.md), [scope](../../specs/task-mvp-scope/spec.md), [runtime delta](specs/task-console-runtime/spec.md). Цикл и ограничения — [design.md](design.md); результаты проверок — [implementation-verification.md](implementation-verification.md).

30 имён ниже — полный набор прикладных команд MVP; встроенные Symfony help/list/completion считаются отдельно. Команда `task:subtask:add` и остальные 28 post-MVP операции не регистрируются. Колонка API сохраняет основные catalog method_routes, включая штатный preflight. Колонка дополнительных чтений описывает проектные проверки внутри выбранных API семейств; она не означает проверку на реальном портале.

Классы команд используют namespace `Bitrix24\CLI\Console\Command\Task`; requests/references — `Bitrix24\CLI\Application\Task\Request`, handlers — `Bitrix24\CLI\Application\Task\Handler`, сервисы — `Bitrix24\CLI\Application\Task`. **30 Command-классов / 23 request/reference DTO / 11 handlers + 4 services**. UpdateTaskHandler используется тремя командами; допустимый patch зависит от operation identity.

## Карточка, выборки, поля и доступ

| Команда | Command class | Request / Application method | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:add` | `AddTaskCommand` | `AddTaskRequest` / `AddTaskHandler.prepare` | `tasks.task.add@3.0` | Advanced: `tasks.task.field.list@3.0` для writable validation; title/creator/responsible обязательны; обычный/advanced ввод раздельны | mutation |
| `task:show` | `ShowTaskCommand` | `ShowTaskRequest` / `ShowTaskHandler.prepare` | `tasks.task.get@3.0`; для выбранных auditors/accomplices также `tasks.task.get@1.0`, `user.get@1.0` | Карточка из REST3, role IDs выбранной TASK_ID отдельно, минимальное bounded user enrichment; strict-rest3 отклоняет enriched plan заранее | task-card |
| `task:list` | `ListTaskCommand` | `ListTasksRequest` / `ListTasksHandler.prepare` | `tasks.task.list@3.0` | Server filter только id; local predicates/where; selected fields включают поля фильтра; finite scan/limit/all | task-list |
| `task:find` | `FindTaskCommand` | `FindTasksRequest` / `FindTasksHandler.prepare` | `tasks.task.list@3.0` | Literal Unicode substring title, trim, id ASC; zero/one/many всегда items array; finite scan | title-matches |
| `task:update` | `UpdateTaskCommand` | `UpdateTaskRequest` / `UpdateTaskHandler.prepare` | `tasks.task.update@3.0` | Advanced: `tasks.task.field.list@3.0`; nonempty explicit patch; status/read-only запрещены; поля отсутствия сохраняются | mutation |
| `task:delete` | `DeleteTaskCommand` | `TaskReference` / `DeleteTaskHandler.prepare` | `tasks.task.delete@3.0` | `tasks.task.get@3.0` для цели/названия; force либо TTY confirmation; без force/-n ошибка до запроса | mutation |
| `task:fields:list` | `ListTaskFieldsCommand` | `ListTaskFieldsRequest` / `TaskMetadataService.fields` | `tasks.task.field.list@3.0`, `tasks.task.field.get@3.0` | list без --name либо get выбранного --name; всегда список из 0/1/many metadata records | task-fields |
| `task:access:show` | `ShowTaskAccessCommand` | `TaskReference` / `TaskMetadataService.access` | `tasks.task.access.get@3.0` | Только доступ текущего подключения; role name не вычисляет права | task-access |
| `task:assign` | `AssignTaskCommand` | `UpdateTaskRequest` / `UpdateTaskHandler.prepare` | `tasks.task.update@3.0` | Только --responsible → responsibleId; никакого выбора «текущего» сотрудника | mutation |
| `task:deadline:set` | `SetTaskDeadlineCommand` | `UpdateTaskRequest` / `UpdateTaskHandler.prepare` | `tasks.task.update@3.0` | Только --at → deadline; ISO8601 с явным offset | mutation |

## Чат и файлы

| Команда | Command class | Request / Application method | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:chat:list` | `ListTaskChatCommand` | `ListTaskChatRequest` / `TaskChatService.list` | `tasks.task.get@3.0`, `im.dialog.messages.get@1.0` | Проверить task/chat association; before/after исключают друг друга; finite messages budget; IM version видима | chat-messages |
| `task:chat:send` | `SendTaskChatMessageCommand` | `SendTaskChatMessageRequest` / `TaskChatService.send` | `tasks.task.chat.message.send@3.0` | Один explicit text/text-file, нет legacy comment; Подтверждение отправки без MESSAGE_ID, resourceId=null; acting connection identity | mutation |
| `task:chat:update` | `UpdateTaskChatMessageCommand` | `UpdateTaskChatMessageRequest` / `TaskChatService.update` | `tasks.task.get@3.0`, `im.message.update@1.0` | `im.dialog.messages.get@1.0` для MESSAGE_ID binding к chat выбранной задачи; budget10 000, иначе gate4; server author/admin rights | mutation |
| `task:chat:delete` | `DeleteTaskChatMessageCommand` | `DeleteTaskChatMessageRequest` / `TaskChatService.delete` | `tasks.task.get@3.0`, `im.message.delete@1.0` | `im.dialog.messages.get@1.0` для binding; delete confirmation не отменяет связь/права | mutation |
| `task:file:attach` | `AttachTaskFilesCommand` | `AttachTaskFilesRequest` / `AttachTaskFilesHandler.prepare` | `tasks.task.file.attach@3.0` | Повторяемые уже загруженные Disk IDs; никаких local upload; отдельный request с одним fileId на шаг; ledger подтверждений | mutation |

## Время

| Команда | Command class | Request / Application method | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:time:show` | `ShowTaskTimeCommand` | `TaskReference` / `TaskMetadataService.time` | `tasks.task.get@3.0` | Select elapsedTime; связанное поле без обещания total/completeness | time-context |
| `task:time:add` | `AddTaskTimeEntryCommand` | `AddTaskTimeEntryRequest` / `TimeEntryService.add` | `task.elapseditem.add@1.0` | Seconds integer >0, optional text; действует от текущего подключения; явный REST1 admission | mutation |
| `task:time:list` | `ListTaskTimeEntriesCommand` | `ListTaskTimeEntriesRequest` / `TimeEntryService.list` | `task.elapseditem.getlist@1.0` | Typed --params query schema; positional API codec; pagination/scope/budget; no arbitrary payload | time-entries |
| `task:time:update` | `UpdateTaskTimeEntryCommand` | `UpdateTaskTimeEntryRequest` / `TimeEntryService.update` | `task.elapseditem.update@1.0` | `task.elapseditem.getlist@1.0` для TASK_ID/ENTRY_ID binding; seconds >0; omission text сохраняет комментарий; server rights | mutation |
| `task:time:delete` | `DeleteTaskTimeEntryCommand` | `DeleteTaskTimeEntryRequest` / `TimeEntryService.delete` | `task.elapseditem.delete@1.0` | `task.elapseditem.getlist@1.0` для binding; force/confirmation; без обхода server rights | mutation |

## Чек-листы и пункты

| Команда | Command class | Request / Application method | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:checklist:add` | `AddTaskChecklistCommand` | `AddTaskChecklistRequest` / `AddTaskChecklistHandler.prepare` | `task.checklistitem.add@1.0` | Core v1, явный PARENT_ID=0; непустой title; SDK wrapper не принимает PARENT_ID | mutation |
| `task:checklist:list` | `ListTaskChecklistsCommand` | `TaskReference` / `ListTaskChecklistsHandler.prepare` | `task.checklistitem.getlist@1.0` | Только корни PARENT_ID=0, flat response задачи; неполное дерево не объявляется полным | checklist-roots |
| `task:checklist:item:add` | `AddTaskChecklistItemCommand` | `AddTaskChecklistItemRequest` / `ChecklistItemService.add` | `task.checklistitem.getlist@1.0`, `task.checklistitem.add@1.0` | Выбранный root/parent принадлежат TASK_ID; явный PARENT_ID через Core v1; нет auto root | mutation |
| `task:checklist:item:list` | `ListTaskChecklistItemsCommand` | `ListTaskChecklistItemsRequest` / `ChecklistItemService.list` | `task.checklistitem.getlist@1.0` | Root обязателен; только его descendants, включая nested; ID/PARENT_ID и deterministic tree order | checklist-items |
| `task:checklist:item:update` | `UpdateTaskChecklistItemCommand` | `UpdateTaskChecklistItemRequest` / `ChecklistItemService.update` | `task.checklistitem.getlist@1.0`, `task.checklistitem.update@1.0` | Проверенный item, не root; только nonempty title; остальные поля сохраняются | mutation |
| `task:checklist:item:complete` | `CompleteTaskChecklistItemCommand` | `ChecklistItemReference` / `ChecklistItemService.complete` | `task.checklistitem.getlist@1.0`, `task.checklistitem.complete@1.0` | Item binding; lifecycle задачи не меняется | mutation |
| `task:checklist:item:renew` | `RenewTaskChecklistItemCommand` | `ChecklistItemReference` / `ChecklistItemService.renew` | `task.checklistitem.getlist@1.0`, `task.checklistitem.renew@1.0` | Item binding; lifecycle задачи не меняется | mutation |
| `task:checklist:item:delete` | `DeleteTaskChecklistItemCommand` | `ChecklistItemReference` / `ChecklistItemService.delete` | `task.checklistitem.getlist@1.0`, `task.checklistitem.delete@1.0` | Проверить item и descendants; API удаляет subtree, показать его в плане/подтверждении; root не допускается | mutation |

## Участники и история изменений

| Команда | Command class | Request / Application method | Основные API method/version | Дополнительные чтения и ограничения | Output profile |
| --- | --- | --- | --- | --- | --- |
| `task:participants:set` | `SetTaskParticipantsCommand` | `SetTaskParticipantsRequest` / `SetTaskParticipantsHandler.prepare` | `tasks.task.update@1.0` | `tasks.task.get@3.0` для TASK_ID/title; полная замена только явно заданной роли, explicit clear; нет status/прочего legacy payload | mutation |
| `task:history:list` | `ListTaskHistoryCommand` | `ListTaskHistoryRequest` / `ListTaskHistoryHandler.prepare` | `tasks.task.history.list@1.0` | Только typed filter.FIELD/order.createdDate; navigation/completeness acceptance; история отдельно от чата | task-history |

## Ввод и режимы

Точный список бизнес-опций для каждой команды сохраняется в [catalog](../define-task-jtbd/cli-candidates.md). Ко всем 30 применяются общие проектные и native Symfony options из design; у всех write/delete есть `--dry-run`, у delete — `--force`. У read нет dry-run/force. Common options не копируются 30 раз в классы.

Обычный и advanced режим add/update раздельны. --fields-file/--description-file/--text-file/--where-file/--params-file принимают только явный PATH или `-`; stdin не читается автоматически. --limit/--all, --before/--after, --json/--plain и IDs/clear одной роли взаимоисключающие. Related IDs всегда flags, task ID positional. Нет TASK_ID у add/list/find/fields:list.

Каждая строка имеет собственную command contract проверку: допустимый ввод → typed Request → ожидаемый маршрут/effect/output; missing/conflicting input → usage2 без подключения. Для всех дополнительных чтений отдельно проверяются API policy и отсутствие mutation в dry-run. Общие parser/version/confirmation/scan tests перечислены в [tasks.md](tasks.md).

Дополнительно для каждой строки обязателен минимум один happy-path в tests/Integration через реальный портал. PHPUnit Group `command:<имя>` связывает сценарий с командой; unit guard сверяет группы с CommandCatalog. Список вызовов без проверки данных, negative case и skip не засчитываются. Положительный workflow может покрывать несколько команд с отдельными assertions; для time/history проверяется точная документированная граница полноты, а для file attach — confirmed ACK с явным ограничением readback.

## Integration happy-path coverage

Все методы находятся в `tests/Integration/TaskCommandsTest.php` и связаны с командами Group `command:<имя>`. Проверка наличия — `tests/Unit/IntegrationCommandCoverageTest.php`; результат live запуска фиксируется отдельно в implementation-verification.md и sanitized evidence. В таблице указаны положительные assertions, а не вспомогательные или negative вызовы.

| Команда | Метод теста | Проверка результата |
| --- | --- | --- |
| `task:add` | testTaskCreationAndDeletionReadBack | Положительный ID, confirmed, точные title/description/creator/responsible в новой карточке |
| `task:show` | testTaskCreationAndDeletionReadBack | ID и поля собственной созданной задачи |
| `task:delete` | testTaskCreationAndDeletionReadBack | Confirmed, ID, отсутствие в list по ID и отказ show после удаления |
| `task:update` | testTaskCardSchemaAndSparseUpdates | Новое title и явное очищение description через show |
| `task:assign` | testTaskCardSchemaAndSparseUpdates | responsibleId выбранного пользователя через show |
| `task:deadline:set` | testTaskCardSchemaAndSparseUpdates | Timestamp deadline совпадает с заданным offset |
| `task:list` | testTaskCardSchemaAndSparseUpdates | Список содержит ID собственной задачи |
| `task:fields:list` | testTaskCardSchemaAndSparseUpdates | Непустой metadata список и точный name=title при выборе поля |
| `task:access:show` | testTaskCardSchemaAndSparseUpdates | Непустые права совпадают с API, собственная задача имеет разрешённое действие |
| `task:find` | testTaskFindReturnsMatchingTitlesAsAList | Exit0, complete=true, ровно две пары id/title по Unicode подстроке другого регистра |
| `task:chat:send` | testTaskChatSendReadUpdateDeleteAndWrongTaskBinding | Confirmed ACK и уникальный текст в чате собственной задачи |
| `task:chat:list` | testTaskChatSendReadUpdateDeleteAndWrongTaskBinding | Ровно одно сообщение с собственным текстом и проверяемым ID |
| `task:chat:update` | testTaskChatSendReadUpdateDeleteAndWrongTaskBinding | Изменённый текст прочитан из того же чата |
| `task:chat:delete` | testTaskChatSendReadUpdateDeleteAndWrongTaskBinding | Удалённое сообщение отсутствует при повторном чтении |
| `task:time:add` | testOwnTimeEntriesReadBackSecondsAndKeepOmittedText | Собственная запись содержит 60 секунд, text и userId |
| `task:time:list` | testOwnTimeEntriesReadBackSecondsAndKeepOmittedText | Запись доступна по ID с ожидаемыми значениями |
| `task:time:update` | testOwnTimeEntriesReadBackSecondsAndKeepOmittedText | 120 секунд и сохранённый omitted text через list |
| `task:time:delete` | testOwnTimeEntriesReadBackSecondsAndKeepOmittedText | Запись отсутствует при повторном list |
| `task:time:show` | testOwnTimeEntriesReadBackSecondsAndKeepOmittedText | taskId/elapsedTime совпадают с provider, scope/historyComplete и точная причина неполноты |
| `task:checklist:add` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | Созданные roots прочитаны по ID с точными названиями |
| `task:checklist:list` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | Два независимых корня доступны по ID/title |
| `task:checklist:item:add` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | Основной и nested item доступны, parentId соответствует основному пункту |
| `task:checklist:item:list` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | Пункты выбранного root имеют ожидаемые ID/title/parent/state |
| `task:checklist:item:update` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | Новое title прочитано из выбранного root |
| `task:checklist:item:complete` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | isComplete=true через list |
| `task:checklist:item:renew` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | isComplete=false и неизменный task status |
| `task:checklist:item:delete` | testChecklistRootsNestedItemsAndStateDoNotChangeTaskStatus | Удалённый subtree отсутствует через list |
| `task:participants:set` | testParticipantsOmissionClearAndHistoryCompletenessBoundary | Auditor ID прочитан, omitted роль сохранена, explicit clear очищает её |
| `task:history:list` | testParticipantsOmissionClearAndHistoryCompletenessBoundary | Собственное изменение title: точные from/to/user, ID и известная граница navigation |
| `task:file:attach` | testAttachExistingDiskFileWithConfirmedAcknowledgement | Confirmed ACK с точными taskId/fileId; membership независимо не доказано |

`testFilledParticipantsAreEnrichedThroughExplicitUserLookup` дополнительно проверяет task:show с observers/email и task:participants:set; denied-role сценарий не заменяет happy-path. Для двух read-команд с недоказанной полнотой допускается только описанная в runtime spec точная граница exit3. Смена ответственного проверена на текущего пользователя; покрытие иных ролей остаётся отдельной приёмкой.

## Output profiles

Это проектные стабильные поля нормализованного результата, а не требование вернуть сырой API payload. JSON использует общий envelope. Для списков data.items всегда array. Human показывает те же существенные значения в таблице/карточке. Plain без заголовка; точный порядок TSV columns:

| Profile | Data shape | TSV columns по порядку |
| --- | --- | --- |
| task-card | data.task object | field, value; одна строка на нормализованное выбранное поле, lexicographic field order |
| task-list | data.items | id, title, responsibleId, groupId, status, deadline |
| title-matches | data.items, только id/title | id, title |
| task-fields | data.items metadata records | name, type, editable, filterable, sortable; неизвестное metadata — null |
| task-access | data.access object | permission, allowed; lexicographic permission order |
| chat-messages | data.items | id, createdDate, authorId, text; порядок ID ASC внутри выбранного window |
| time-context | data.time object | field, value; taskId и elapsedTime, без выдуманного агрегата |
| time-entries | data.items | id, userId, seconds, text, createdDate; default ID ASC |
| checklist-roots | data.items | id, title; sortIndex затем ID; sortIndex ← SORT_INDEX, adapter сохраняет его в JSON |
| checklist-items | data.items | id, parentId, title, isComplete; preorder, siblings sortIndex затем ID; IS_COMPLETE Y/N → boolean |
| task-history | data.items | id, createdDate, userId, field, from, to; default createdDate DESC, tie ID DESC |
| mutation | data.operation object; при нескольких attachments data.items ledger | taskId, resourceId, action, changedFields; одна строка на confirmed step, changedFields — компактный JSON array |

У task:list adapter добавляет стабильные profile fields и predicate fields к --select; JSON сохраняет явно выбранные дополнительные поля. Partial/meta диагностируются отдельно от TSV rows. В mutation task-level resourceId равен taskId; для child операции — message/entry/checklist/item/file ID; отсутствие известного ID после неопределённого add не выдумывается. Patch values видны в dry-run, обычное подтверждение показывает changedFields без полного тела API.

Dry-run использует отдельный **plan** profile: JSON data.plan object, human цель/поля/steps, plain taskId, step, method, apiVersion, effect, fields (compact JSON). Выполненные read steps отмечаются в meta; предполагаемые write steps не выдаются за executed. Этот формат не заменяет обычный result profile команды.

## Simplified implementation — 2026-10-11

30 Command-классов, 23 request/reference DTO, 11 отдельных handlers и 4 сервиса. UpdateTask shared для трёх команд. Методы routes объявляются до preflight, transport сохраняет явные версии; группировка не меняет API, permissions и output contracts.
