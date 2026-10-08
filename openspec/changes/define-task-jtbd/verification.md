# Проверка planning artifacts: define-task-jtbd

Дата: 2026-10-09. Это проверка фиксации согласованных JTBD и статического каталога MCP; проверка реализации CLI не выполняется.

## Подтверждённые результаты

- В delta и основной spec task-workflows 17 требований и 19 JTBD-сценариев: PM-01..06, AM-01..06, EMP-01..07. Сохранены 16 прежних сценариев; 3 новых относятся к переписке.
- У каждого JTBD сохранены ситуация, желаемое действие, рабочая цель и признак успеха. Общий chat contract включает всех участников в пределах прав, чтение истории и отправку сообщения в нужную задачу.
- Основная спецификация содержит Purpose и Requirements без delta-заголовков; content соответствует delta. Product roles не предоставляют REST rights; клиентская и внутренняя приёмка различаются.
- openspec validate --specs --strict: 3 passed, 0 failed. openspec validate define-task-jtbd --strict: valid.
- MCP inventory построен по 27 файлам server/mcp/tools/tasks на commit 1f825befd6bfcb620324bd87858977062fabf9ec, актуальному HEAD при проверке. Все имена извлечены из defineMcpTool, REST имена — из исходников; Markdown/JSON сверены. b24_task_comment_add вызывает task.commentitem.add; в этом каталоге нет tool для чтения task chat или отправки через tasks.task.chat.message.send.
- Изменения ограничены planning artifacts и новой основной spec; приложение, dependencies и чужие спецификации не изменялись. Issue #10 и PR #11 сохраняют трассировку обоих changes.

## Что остаётся открытым

В tasks.md выполнены 3 задачи фиксации, 4 задачи проектирования реализации остаются открытыми. Спецификация выражает согласованный продуктовый контракт, не успешное runtime acceptance. В bin/console нет прикладных task commands; реализация этих требований не подтверждена. Полную openspec-verify-change для implementation следует выполнить после реализации, не подменяя её validation схемы документов.

Не выполнялись live tools/list MCP, установка templates-mcp и операции на портале. Каталог не приписывается официальному hosted MCP.
