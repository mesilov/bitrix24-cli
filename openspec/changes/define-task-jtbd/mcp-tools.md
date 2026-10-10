# MCP tools блока задач: templates-mcp

Проверено 2026-10-09. Источник: [bitrix24/templates-mcp](https://github.com/bitrix24/templates-mcp/tree/1f825befd6bfcb620324bd87858977062fabf9ec), commit `1f825befd6bfcb620324bd87858977062fabf9ec` (актуальный HEAD при проверке). Извлечены name из defineMcpTool и literal REST method names всех 27 файлов server/mcp/tools/tasks; сверено с README. Live tools/list и вызовы на портале не выполнялись. Это каталог open-source шаблона, не список hosted mcp.bitrix24.tech/com.

| MCP tool | Назначение | Фактический REST-метод | Исходник |
| --- | --- | --- | --- |
| `b24_task_checklist_item_add` | Добавить пункт/заголовок чек-листа | `task.checklistitem.add` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/add-checklist-item.ts) |
| `b24_task_elapsed_time_add` | Записать затраченное время | `task.elapseditem.add` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/add-elapsed-time.ts) |
| `b24_task_comment_add` | Добавить legacy-комментарий | `task.commentitem.add` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/add-task-comment.ts) |
| `b24_task_dependency_add` | Добавить зависимость | `task.dependence.add` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/add-task-dependency.ts) |
| `b24_task_result_add` | Добавить результат | `tasks.task.result.add` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/add-task-result.ts) |
| `b24_task_approve` | Принять результат | `tasks.task.approve` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/approve-task.ts) |
| `b24_task_checklist_item_complete` | Отметить пункт выполненным | `task.checklistitem.complete` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/complete-checklist-item.ts) |
| `b24_task_complete` | Завершить / передать на контроль | `tasks.task.complete` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/complete-task.ts) |
| `b24_task_create` | Создать задачу | `tasks.task.add` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/create-task.ts) |
| `b24_task_defer` | Отложить | `tasks.task.defer` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/defer-task.ts) |
| `b24_task_checklist_item_delete` | Удалить пункт/чек-лист | `task.checklistitem.delete` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/delete-checklist-item.ts) |
| `b24_task_elapsed_time_delete` | Удалить запись времени | `task.elapseditem.delete` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/delete-elapsed-time.ts) |
| `b24_task_result_delete` | Удалить результат | `tasks.task.result.delete` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/delete-task-result.ts) |
| `b24_task_disapprove` | Вернуть на доработку | `tasks.task.disapprove` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/disapprove-task.ts) |
| `b24_task_checklist_item_list` | Получить пункты чек-листа | `task.checklistitem.getlist` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/list-checklist-items.ts) |
| `b24_task_elapsed_time_list` | Получить записи времени | `task.elapseditem.getlist` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/list-elapsed-time.ts) |
| `b24_task_result_list` | Получить результаты | `tasks.task.result.list` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/list-task-results.ts) |
| `b24_task_list` | Получить список задач | `tasks.task.list` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/list-tasks.ts) |
| `b24_task_pause` | Приостановить | `tasks.task.pause` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/pause-task.ts) |
| `b24_task_rate` | Поставить или убрать оценку | `tasks.task.update` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/rate-task.ts) |
| `b24_task_dependency_remove` | Удалить зависимость | `task.dependence.delete` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/remove-task-dependency.ts) |
| `b24_task_checklist_item_renew` | Снять отметку выполнения | `task.checklistitem.renew` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/renew-checklist-item.ts) |
| `b24_task_renew` | Возобновить | `tasks.task.renew` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/renew-task.ts) |
| `b24_task_start` | Начать выполнение | `tasks.task.start` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/start-task.ts) |
| `b24_task_elapsed_time_update` | Исправить запись времени | `task.elapseditem.update` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/update-elapsed-time.ts) |
| `b24_task_result_update` | Изменить результат | `tasks.task.result.update` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/update-task-result.ts) |
| `b24_task_update` | Изменить поля задачи | `tasks.task.update` | [source](https://github.com/bitrix24/templates-mcp/blob/1f825befd6bfcb620324bd87858977062fabf9ec/server/mcp/tools/tasks/update-task.ts) |

Дополнительные helpers из README: `b24_user_me`, `b24_user_find`. Они не входят в 27 tools задач. `bx24mcp_submit_feedback` — meta-tool обратной связи, не работа с задачами.

## Gap по согласованным JTBD

`b24_task_comment_add` вызывает `task.commentitem.add`; исходник прямо отмечает отложенную миграцию на `tasks.task.chat.message.send`. В проверенном каталоге нет отдельного tool чтения истории чата задачи или отправки сообщения новым task chat API. Поэтому PM-06/AM-06/EMP-07 не считаются полностью покрытыми этим набором.

Также нет отдельных task-get/delete, task-chat update/delete, task-delegate, history или kanban tools. Это отсутствие отдельного зарегистрированного инструмента в данном шаблоне, а не отсутствие соответствующих REST-методов. Сводки рисков и клиентских обязательств требуют дополнительных правил и данных; list сам по себе не доказывает JTBD-покрытие.
