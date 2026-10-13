# Planning check — 14.10.2026

- Issue #118: milestone 0.2.0, enhancement, полный URL в proposal и идентификатор/путь change в issue.
- Proposal/design/tasks и две delta specs созданы; strict OpenSpec PASS. Это готовность планирования, не реализации: 0/6 implementation tasks, код и main specs не менялись.
- Источники: ListTaskCommand, TaskInputMapper, ListTasksHandler, TaskQuery, TaskListScanner, DeadlineWindow::OPEN_STATUSES и актуальная task-console-runtime spec на dev 1741b9c.
- Effective MCP в свежем checkout enabled=true, endpoint https://mcp-dev.bitrix24.tech/mcp; реальные search/details для tasks.task.list выполнены. Официальный REST3 URL и границы проверки сохранены в design; portal acceptance не выполнялась.
- Новый --user остаётся явным required ID по согласованному кандидату; четыре open states сохранены из текущего проекта; строгое due-before и scan cap/partial следуют существующим контрактам списка.
- Runtime registration delta сохраняет полное требование и все его сценарии с добавлением отдельной task:my (35 task-команд на этой базе); исходные 22 JTBD и EMP-01 не переименованы.
- docs/task-jtbd.md остаётся планом до реализации. Будущая MyTaskCommandTest.php — задача, не существующий пройденный тест.
- git diff --check PASS. Runtime/unit/live suites сейчас не запускались: опубликован только план.

Перед draft PR интегрирована свежая dev f7dc2a7; git merge без конфликтов, CHANGELOG сохраняет обе записи. Strict validation повторена; итоговый diff к dev содержит только CHANGELOG и этот change.
