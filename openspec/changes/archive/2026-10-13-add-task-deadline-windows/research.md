# Documentation and live evidence

## Current model

Основной checkout — старый main с чужими незакоммиченными файлами. Planning создан в отдельном /private/tmp/bitrix24-cli-issue-95 от актуальной dev 0aeb25b. Источники модели: ListTaskCommand/FindTaskCommand, TaskInputMapper, TaskQuery, TaskListScanner, Rest3TaskAdapter, ApiPolicyGuard, CommandCatalog и cli-experience spec; portable skill references/commands.md. Сейчас only id фильтруется сервером, due-before и остальные predicates локальны.

## Official documentation via MCP

MCP bitrix24_dev отвечает; effective project config проверен из worktree командой codex mcp get bitrix24_dev --json. Выполнены bitrix-search → bitrix-method-details для tasks.task.list и tasks.task.field.get; article-details для полей задачи/общего REST3 filtering. Краткая выдача list смешивает сведения о pagination/start и не раскрывает id-only ограничение. Некоторые article-details по точному hint возвращают другой заголовок/URL; такие ответы не использованы как доказательство нужного метода. Общие операторы REST3 не доказывают поддержку каждого поля DTO.

- [REST3 tasks.task.list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html): filter только id; ошибки фильтра требуют Filterable; deadline доступен как selected/sortable field, что не означает filterable.
- [REST3 field metadata](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-field-get.html): name и select metadata, включая filterable; живую metadata в этом опыте не запрашивали.
- [REST3 поля](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html): deadline datetime|null, status string enum, groupId integer.
- [Классический list](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list.html): filter object, DEADLINE/CREATED_DATE/CLOSED_DATE/CHANGED_DATE/DATE_START, GROUP_ID; операторы !, <, <=, >, >=; REAL_STATUS lifecycle, STATUS мета-статус (включая -1 overdue); start и фиксированные страницы по 50.

Полные официальные страницы были дополнительно прочитаны для проверки ограничения REST3 и версии classic, которые короткий MCP response не раскрывает. Не делаем вывод о поддержке deadline только из общего синтаксиса.

## Live test 2026-10-13

По явному запросу пользователя выполнены read-only HTTP tasks.task.list через webhook существующего проекта. Credentials считаны внутри процесса, не выведены и не сохранены в receipt. Запрашивались только id/deadline, task titles/contents не сохранены.

Контрольный REST3 запрос без filter дал HTTP200/50 задач. По существующему deadline выбран непустой календарный день: [2026-10-05T00:00:00+03:00, 2026-10-06T00:00:00+03:00).

| Запрос | HTTP | Результат |
| --- | --- | --- |
| REST3 filter [[deadline,>=,from],[deadline,<,to]] | 400 | REQUESTVALIDATIONEXCEPTION, field deadline, отсутствует Filterable |
| REST1 filter {>=DEADLINE:from,<DEADLINE:to} | 200 | 5 задач; 0 вне интервала; 0 null/некорректных дат |
| REST1 противоречивые границы (>=to,<from) | 200 | 0 задач |

Raw sanitized receipt: api-evidence.json. Все 4 запроса завершились. На портале REST3 date filter отклонён, REST1 server date range подтверждён непустой выборкой и отрицательным контролем. Это проверка этого портала/двух bounds, не всех статусов, полей, TZ/DST, проектов/ролей, иных порталов и не будущего CLI. Mutating запросы не выполнялись.
