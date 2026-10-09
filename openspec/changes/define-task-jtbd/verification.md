# Проверка planning artifacts: define-task-jtbd

Дата: 2026-10-09. Это проверка фиксации согласованных JTBD и статического каталога MCP; проверка реализации CLI не выполняется.

## Подтверждённые результаты

- В delta и основной spec task-workflows 17 требований и 19 JTBD-сценариев: PM-01..06, AM-01..06, EMP-01..07. Сохранены 16 прежних сценариев; 3 новых относятся к переписке.
- У каждого JTBD сохранены ситуация, желаемое действие, рабочая цель и признак успеха. Общий chat contract включает всех участников в пределах прав, чтение истории и отправку сообщения в нужную задачу.
- Основная спецификация содержит Purpose и Requirements без delta-заголовков; content соответствует delta. Product roles не предоставляют REST rights; клиентская и внутренняя приёмка различаются.
- openspec validate --specs --strict: 4 passed, 0 failed после добавления cli-experience. openspec validate define-task-jtbd --strict: valid.
- MCP inventory построен по 27 файлам server/mcp/tools/tasks на commit 1f825befd6bfcb620324bd87858977062fabf9ec, актуальному HEAD при проверке. Все имена извлечены из defineMcpTool, REST имена — из исходников; Markdown/JSON сверены. b24_task_comment_add вызывает task.commentitem.add; в этом каталоге нет tool для чтения task chat или отправки через tasks.task.chat.message.send.
- Изменения ограничены planning artifacts и новой основной spec; приложение, dependencies и чужие спецификации не изменялись. Issue #10 и PR #11 сохраняют трассировку обоих changes.

## Что остаётся открытым

В tasks.md выполнены 6 из 9 задач фиксации/проектирования, 3 задачи закрытия условного покрытия остаются открытыми. Спецификация выражает согласованный продуктовый контракт, не успешное runtime acceptance. В bin/console нет прикладных task commands; реализация этих требований не подтверждена. Полную openspec-verify-change для implementation следует выполнить после реализации, не подменяя её validation схемы документов.

Не выполнялись live tools/list MCP, установка templates-mcp и операции на портале. Каталог не приписывается официальному hosted MCP.

## Дополнение: потенциальный CLI новой карточки

- Актуальный при проверке b24restdocs commit b4d3dc81619625c2bf21c28dd0227116f36c8743; сверены index, fields v3, list/add/update, chat.send, access, result, Gantt, миграция новой карточки, IM history/update/delete, complete/approve/disapprove и time add/update. Source paths всех записей проверены по recursive tree этой версии.
- cli-candidates.json/md содержит 55 уникальных имён: 42 кандидата и 13 legacy extensions. Task card/result/send — v3; legacy comments отсутствуют. SDK version/reference сверены с composer.lock; method_routes различает wrapper/core-gap/outside-task-inventory. Presence SDK wrapper — только статическое свидетельство.
- jtbd-coverage.json/md содержит 19 уникальных ID, полностью совпадающих со scenario IDs основной spec. Все ссылки commands существуют в каталоге; каждая строка содержит условие/gap и будущий acceptance case. JSON/Markdown/source links и разделители таблиц проверены временным скриптом.
- Количественные проверки: new card documented 7, conditional 12; strict REST3 documented 4, conditional 14, gap 1 (EMP-06 write/correct time). Documented означает основной маршрут, не выполненный end-to-end сценарий.
- Подробные условия не превращаются в успешный coverage score: provider рабочих фактов, lifecycle semantics, IM exception, scan completeness и time policy не приняты или не проверены.

## Дополнение: CLI Guidelines и пользовательский контракт

- Первичный источник content/_index.md из cli-guidelines/cli-guidelines проверен на commit 697d6a29fc8c93d3981a755c0c7683507ad39c3e; ссылки, дата, авторы и лицензия указаны в каталоге, соответствие разделам и проектные решения — в design.md.
- Добавлена cli-experience delta и основная spec: 11 требований, 29 проверяемых сценариев. Сверены полное совпадение после преобразования заголовков, уникальность имён, WHEN/THEN и предел длины нормативных описаний.
- Все 55 command names используют namespaces Symfony Console с двоеточиями и entrypoint b24cli; сложные связанные IDs переведены в named flags. Markdown/JSON синтаксис каждой записи совпадает, таблицы и локальные ссылки валидны. previous_proposal_name сохраняет историю, не runtime alias.
- Проверены удобные update/create flags, файловый ввод PATH/-, --fields/--fields-file, форматы/потоки, help, exit codes, finite defaults, dry-run/delete confirmation и ограничения автоматизации. Shell examples проверены bash -n и token parsing без исполнения task commands.
- Baseline comparison подтверждает неизменность всех methods, method_routes/API versions/SDK wrappers, sources, route/kind/effect и связей JTBD. Все 19 coverage references обновлены; conditions/acceptance отличаются только именами. Counts 7/12 и 4/14/1 сохранены. Принятая task-workflows spec и её delta не менялись.
- Повторная openspec validate --specs --strict: 4 passed, 0 failed; validate define-task-jtbd --strict: valid. git diff --check проходит. Runtime/PHP, auth, packaging и portal acceptance не выполнялись; обновлён проект интерфейса.


## Уточнение: нативная нотация Symfony Console

- По уточнению пользователя entrypoint изменён на b24cli, все 55 команд возвращены к colon namespaces; task-workflows не меняется. JSON/Markdown и 19 JTBD references согласованы. API methods/routes/SDK evidence и counts 7/12, 4/14/1 сохранены.
- Справка использует list [namespace], help COMMAND и COMMAND --help. Root запускает list; отдельный tasks dispatcher не проектируется. No-interaction/-n, no-ansi, quiet/silent и verbosity согласованы с Console 8.1.8 из composer.lock, проверены официальные docs и pinned Application.php.
- Обновлён контракт cli-experience delta/main с теми же 11 требованиями и 29 сценариями. Требования к scriptable flags и удобному редактированию сохранены; shell examples имеют будущий b24cli entrypoint, не доказывают runtime.
- Launcher b24cli и task commands не добавлены. Это исправление planning artifacts; validation и статическая сверка не заменяют implementation/portal acceptance.


## Уточнение: task:add и независимость от REST vocabulary

- По выбору пользователя корневой namespace — task, команда добавления — task:add; добавление подзадачи — task:subtask:add. Обязательный маппинг имён/количества CLI-команд на REST 1:1 отсутствует и это явно закреплено в spec/design/JSON metadata.
- Согласованы все 55 имён, примеры и 19 JTBD references; имена REST tasks.task.*, API versions/scopes, SDK routes, effects, gates, sources и counts 7/12, 4/14/1 сохраняются. Task-workflows delta/main не меняются.
- Проверки planning: strict validation 4 specs и change, canonical/delta equality, Markdown/JSON command syntax и tables/links, baseline сравнение API evidence и coverage conditions, shell example parsing, diff check. Task commands и launcher остаются будущей реализацией.
