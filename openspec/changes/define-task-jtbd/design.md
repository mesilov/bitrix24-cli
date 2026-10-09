# Design

## Context

См. proposal.md — Why. bin/console пока создаёт только Symfony Application, task commands отсутствуют. Исследование #10 содержит предложения 79 команд; они не были приняты как MVP. Пользователь согласовал 16 JTBD и добавил переписку в чате задачи для всех участников.

## Goals / Non-Goals

Фиксация пользовательского поведения в task-workflows и перенос его в актуальную спецификацию. Этот этап не пишет код; он фиксирует 29 согласованных исключений из MVP. Auth и детали реализации требуют дальнейших решений; допуск выбранных MVP API маршрутов согласован. Модель клиентских обязательств, уведомления и все lifecycle команды проектируются после MVP.

## Decisions

- Стабильные ID PM-01..06, AM-01..06, EMP-01..07 связывают ситуацию, желаемое действие, рабочую цель и признак успеха. Альтернатива — свести JTBD к перечню методов — потеряла бы цели пользователей.
- Переписка объединена одним требованием с тремя ролевыми сценариями. Все остальные участники тоже включены, если у подключения есть права. Внешний клиент не получает доступ из названия роли аккаунт менеджера.
- История и отправка — обязательная часть переписки. Чат не заменяет result или решение о приёмке; legacy comment tool не означает полное покрытие чата.
- Главная спецификация фиксирует принятые продуктовые цели; implementation tasks остаются открытыми. Архивирование этого change до реализации и проверки будущего поведения не выполняется.
- Каталог MCP сохраняется с commit SHA и проверяется по исходникам, а не только README. Он характеризует templates-mcp, не hosted mcp.bitrix24.tech/com. Live tools/list не проверяется.
- Для текущего CLI-предложения область — новая карточка. Card/result/chat.send используют REST3; history сообщений — официальный IM companion. task.commentitem.* и result-from-comment не входят в новый каталог. Strict-rest3 оценивается отдельно: сам факт новой карточки не переводит все методы в v3.
- Native list v3 filter только id; task:my/plan используют полную выборку доступных задач и явные локальные predicates. Scan cap/недостающий доступ/сбой отражаются в partial, не маскируются под полную сводку проекта. Альтернатива legacy list не выбрана как скрытый fallback.
- Семь lifecycle commands вынесены после MVP; исследованный кандидатный маршрут — update.status, но соответствие специальным действиям при needsControl/requireResult не доказано; до acceptance они disabled. Наличие поля status и rights.approve не доказывает эквивалентность операции.
- Для blocker/change/client promise/delivery/feedback нужны явные данные. В cli-candidates.md названы необходимые поля, альтернативы provider и границы полноты; схема хранения не принята. Альтернатива выводить согласованные факты из любого текста чата отвергнута как недоказуемая.
- Каталог и coverage являются дополнительными planning artifacts, не изменяют принятые JTBD. Предыдущее предложение 79 имён остаётся историческим исследованием; проектирование продолжается по cli-candidates.md/json (59 имён, 43+16), со статической матрицей 19 сценариев.

## Risks / Trade-offs

- [Название роли ошибочно примут за права доступа] → права определяет текущая авторизация, не JTBD.
- [Согласованный JTBD примут за реализованную команду] → purpose, tasks и verification явно разделяют продуктовый контракт и runtime.
- [Сводки рисков/обязательств требуют неоднозначных данных] → при проектировании реализации определить источники и полноту; не угадывать blocker status или CRM-связь по одному полю задачи.
- [Интерфейс MCP comment расходится с task chat] → фиксировать фактический legacy вызов и отсутствие чтения истории в проверенном tool set.

## Migration Plan

Публикуется спецификация и planning artifacts в существующем PR #11 для issue #10. Runtime/dependencies не меняются. При последующей реализации нужно проверить каждый JTBD на тестовом портале с правами соответствующего участника. Откат этого документационного commit убирает новую спецификацию, не меняя поведение приложения.

## CLI Guidelines

Референс пользователя: [cli-guidelines/cli-guidelines](https://github.com/cli-guidelines/cli-guidelines), сайт [clig.dev](https://clig.dev/), источник content/_index.md на commit 697d6a29fc8c93d3981a755c0c7683507ad39c3e, проверен 2026-10-09. Attribution и лицензия приведены в cli-candidates.md. Гайд направляет UX, но не определяет Bitrix24 API, права или полноту JTBD.

| Раздел первичного источника | Решение проекта | Проверяемый контракт |
| --- | --- | --- |
| [Human-first design](https://clig.dev/#human-first-design), [Subcommands](https://clig.dev/#subcommands) | Entrypoint b24cli; команды task:add, task:show, task:list, task:update и namespaces tasks:chat/result/time; один positional ID и именованные связанные сущности | Direct and consistent command structure |
| [Help](https://clig.dev/#help), [Documentation](https://clig.dev/#documentation) | Help/version offline, примеры в начале, одинаковая справка command --help и help command, documented defaults/limitations | Discoverable help without remote operations |
| [Arguments and flags](https://clig.dev/#arguments-and-flags) | Обычные title/responsible/description флаги; advanced fields отдельно, explicit PATH/- для files, полные названия; no arbitrary abbreviations | Convenient and explicit task field updates; Explicit file and structured input |
| [Output](https://clig.dev/#output), [The Basics](https://clig.dev/#the-basics) | Human default, явные --json/--plain, native verbosity quiet/silent, stdout data / stderr messages, минимальное сообщение об успешном изменении | Human output and composable structured output |
| [Errors](https://clig.dev/#errors), [Robustness](https://clig.dev/#robustness-guidelines) | Внятные ошибки, finite timeout, validation до write, отдельные partial/outcomeUnknown, bounded scans | Explicit filtering and completeness; Actionable errors and stable exit status; Bounded requests and honest recovery |
| [Interactivity](https://clig.dev/#interactivity), [Arguments and flags](https://clig.dev/#arguments-and-flags) | --no-interaction не ждёт диалога; --force только подтверждение delete, --dry-run без write; обычный update без лишнего подтверждения | Controlled remote deletion and dry run; Predictable noninteractive and terminal behavior |
| [Configuration](https://clig.dev/#configuration), [Signals](https://clig.dev/#signals) | Нечувствительные flags/config/env/defaults, безопасные credentials, одинаковое место общих options, Ctrl-C с описанием результата | Consistent global options and protected credentials; Bounded requests and honest recovery |
| [Future-proofing](https://clig.dev/#future-proofing) | Явное имя Symfony команды без catch-all/autocorrect; полные имена в скриптах, native unambiguous abbreviations, versioned JSON, стабильные error codes; previous_proposal_name только история | Direct and consistent command structure; Human output and composable structured output |

Новая capability cli-experience дополняет task-workflows общими наблюдаемыми правилами. Основную spec синхронизируем по прямому запросу пользователя; implementation tests и packaging ещё не существуют. Shell examples представлены как будущие вызовы установленного CLI.

На этапе выбора Symfony нотации каталог содержал 55 операций (42 кандидата и 13 extensions); смена command path сохраняла API mappings. Текущий каталог после разделения чек-листов и добавления поиска содержит 59 операций (43+16). --fields-file заменяет прежнюю @JSON нотацию; два источника одного поля отклоняются. В create/update raw JSON является отдельным режимом без смешивания с полевыми флагами согласно cli-experience; create проверяет обязательные поля итогового payload. Схема прочих параметров --where/--params и допустимые operators должны быть определены отдельным implementation change, неподдержанный произвольный payload не обещается.

Отличия, выбранные проектом: один positional task ID ради краткости; creator/responsible остаются явными; limit50/max-scan10000/timeout30 и exit codes 0/1/2/3/4/130 являются нашими defaults, не требованиями CLIG. Plain TSV columns определяются отдельно по командам до реализации. Pager, interactive missing-field wizard, man pages, установка shell completion, distribution и analytics не включены в этот этап; auth/storage также отдельное решение. --no-interaction/-n — встроенная опция Symfony; отдельный --no-input не вводится. --ansi/--no-ansi, --quiet/--silent и verbosity сохраняют встроенную семантику. Short -n не назначается dry-run из-за существующей Symfony convention. CLI Guidelines не определяют обязательный разделитель namespaces; их Help пример Heroku содержит apps:create. Сохраняем нативный colon-separated интерфейс Symfony и его help/list; отдельный пробельный dispatcher не нужен.

Предыдущие имена/--yes/exit-code предложения остаются в неизменённом архиве исследования. Текущий каталог использует нативные colon namespaces; пробельные command paths отменены по уточнению пользователя до выпуска команд; compatibility aliases не нужны для несуществующего runtime. Даже --fields status и --force не обходят lifecycle/data/API gates. Новые UX сценарии не закрывают 3 открытые задачи по provider, будущему lifecycle и runtime acceptance выбранных API маршрутов и не повышают documented JTBD score.


## Symfony Console notation and entrypoint

Уточнение пользователя: приложение основано на Symfony Console, имя entrypoint — b24cli. Основная нотация `b24cli task:add ...`, `b24cli task:chat:send TASK_ID --text ...`; встроенные `b24cli list task`, `b24cli help task:update`, `b24cli task:update --help`. Операция является одним именем Command, разделённым namespaces через `:`, а значения — обычными arguments/options. Однозначные сокращения оставлены в соответствии со стандартным Console resolver; скрипты и документация используют полные имена, неоднозначность или опечатка не запускает запись автоматически.

Проверены [официальные Console docs](https://symfony.com/doc/current/console.html#running-commands), [global options](https://symfony.com/doc/current/console/input.html#command-global-options) и [Application.php](https://github.com/symfony/console/blob/4b81146e3ee248ea969186f85499b54fbc78db08/Application.php) версии 8.1.8 из composer.lock. В standalone Application native options: help/-h, version/-V, quiet/-q, silent, verbose/-v/-vv/-vvv, ansi/no-ansi, no-interaction/-n. Native -q подавляет normal output, включая JSON data; --silent подавляет всё. --json/--plain/--profile NAME/--config/--timeout — проектные опции, не встроенные в standalone Console; --dry-run отдельная long option, -n ей не назначается. FrameworkBundle profiler --profile не является опцией текущего standalone Application; при переходе на FrameworkBundle конфликт должен быть пересмотрен.

Entrypoint b24cli пока является решением интерфейса. Будущая упаковка должна запускать то же Console Application, что и текущий bin/console, без shell alias/пробельного dispatcher. Файл launcher, Composer bin/distribution и task commands этим planning change не реализуются. До их реализации нельзя объявлять b24cli доступным в PATH или работоспособность вызовов подтверждённой. API маршруты и все 19 JTBD сохраняются.


## User-facing task naming

Пользователь выбрал `task:add`: корневая сущность в единственном числе, глагол add для добавления задачи и подзадачи. Основная поверхность: `task:add`, `task:show`, `task:list`, `task:update`, `task:assign`, `task:chat:send`. Наличие list/my/plan не меняет имя сущности во множественное число. Эта нотация согласуется с выбранным Symfony Console separator `:` и entrypoint b24cli.

Требования к маппингу CLI ↔ REST 1:1 нет. Имя CLI описывает действие пользователя; implementation выбирает документированные REST routes и показывает их отдельно, когда это необходимо для API policy/dry-run/диагностики. Одно действие может использовать несколько методов, а несколько команд — один метод: task:add и task:subtask:add используют tasks.task.add; task:update/task:assign/task:deadline:set используют tasks.task.update. Имена tasks.task.* REST, scope tasks, SDK services и ссылки источников не переименовываются вслед за интерфейсом. JTBD и права также не выводятся из имени команды.

В результате переименованы 55 кандидатов без изменения их method_routes, effect, gates или оценки 19 JTBD. Перевод namespace в singular и create → add не является реализацией новых операций; historical proposal names остаются metadata и не обещают aliases.


## MVP exclusions and admission

Пользователь вынес 29 команд: 3 dependency, 5 рабочих представлений, 3 context/blocker, 5 change/delivery/feedback/acceptance, 5 task:result операций, 7 команд жизненного цикла и отдельную task:subtask:add. JSON mvp_scope содержит точный набор и mvp_stage каждой команды. Каталог переорганизован: 30 MVP candidates, 0 API-policy-pending, 29 post-MVP. Текущие kind/route и 43+16 описывают API-кандидатов полного research catalogue, а не MVP состав.

Все оставшиеся команды (30) — кандидаты MVP. Допуск legacy API согласован для четырёх time, восьми checklist/root/item и двух participants/history команд; ожидающих решения по API команд нет. Lifecycle не входит в первый релиз; его gates сохраняются для будущего допуска. Политика task-v3 допускает эти явные REST 1.0 маршруты вместе с документированными IM companions; strict-rest3 продолжает отклонять non-v3 запросы; исключение scope не разрешает скрытый fallback. Dependency:list имеет REST3 route, но вынесен по пользовательскому решению так же, как legacy dependency mutations.

Все 19 требований продукта остаются в task-workflows. В coverage добавлен отдельный MVP partition ссылок: retained candidates, API pending и post-MVP. PM-05 и AM-02/03/05 не имеют оставшихся mapped команд; EMP-01 сохраняет базовый task:list --responsible вместо исключённого task:my. Mapping-reduced — описательная структура набора команд, не доказательство частичного acceptance сценария. Старые documented/conditional counts остаются оценкой полного каталога.

Provider blockers/changes/client obligations/delivery/feedback после этого решения не является предварительным условием выпуска retained CRUD/chat/file команд: проектирование продолжается после MVP. Это не означает, что соответствующие задачи выполнены; они остаются открытыми для дальнейшего продукта. Scoped scans, права и API exceptions оставшихся команд — условия допуска MVP. Native lifecycle/result semantics проверяются перед реализацией после MVP; они не блокируют выпуск оставшихся команд. Task:update в MVP не меняет status через raw fields, aliases или неявные шаги.


## Task results after MVP

Все пять task:result команд исключены по прямому решению пользователя; task:file:attach остаётся кандидатом. Общая продуктовая карта результатов/приёмки не удаляется и API evidence result методов остаётся в полном каталоге. MVP partitions PM-05, AM-05, EMP-05 отражают исключение result операций: у PM-05 и AM-05 больше нет mapped MVP команды, у EMP-05 остаётся только file:attach.

Все семь lifecycle команд, включая task:complete/approve/disapprove, вынесены после MVP. Перед их будущим допуском сохраняются проверки gates. Если requireResult включён, чат или прикреплённый файл не выдают за native result; нельзя скрыто вызывать result:add/from-message, отключать requireResult или обещать завершение без проверенного допустимого результата. Result, уже оформленный в портале, может удовлетворять lifecycle условиям только после проверки прав и семантики. В будущей реализации при невыполненных/непроверенных условиях операция не выполняется и объясняет ограничение. Наличие task:file:attach не восстанавливает result workflow в MVP.

## Time entries in MVP

Пользователь включил task:time:add/list/update/delete. Эти команды используют явно выбранные task.elapseditem.add/getlist/update/delete (REST 1.0), сохраняют API/SDK provenance и не маскируются под REST3. Допуск времени ограничен четырьмя time routes; отдельное решение включает восемь checklist/root/item команд. Participants/history отдельно допущены пользователем; другие legacy extensions не допускаются автоматически. В meta показывается API version; strict-rest3 возвращает gated-unavailable до запроса.

EMP-06 включает создание, полный список, исправление и удаление своих записей. При update/delete проверяются связь TASK_ID/ENTRY_ID и права текущего подключения; --force отменяет только подтверждение, не права. Task:time:show читает elapsedTime через v3 и не заменяет список записей. Решение о MVP не доказывает portal acceptance.

## Checklist roots and items

В MVP включены две операции корня (add/list) и шесть операций пунктов (add/list/update/complete/renew/delete) с явным допуском REST 1.0 task.checklistitem.* маршрутов и preflight getlist. Strict-rest3 отклоняет эти команды до запроса. Старые пять неоднозначных именований заменены item namespace; это не runtime aliases. CHECKLIST_ID и ITEM_ID различаются по роли узла: корень PARENT_ID=0, пункт имеет родителя. Task:checklist:add всегда передаёт PARENT_ID=0, item:add всегда явный проверенный parent; не использовать API default с автоматическим выбором/созданием корня.

Getlist возвращает элементы всех чек-листов одним плоским списком без pagination. Для root:list выбираются корни; item:list выбирает потомков указанного корня и показывает ID/PARENT_ID. Корень, parent и item должны принадлежать TASK_ID; parent — выбранному корню. Некорректное дерево/ID не допускает mutating request. Item:update разрешает только непустой title. Все восемь команд — кандидаты MVP; runtime acceptance ещё требуется. Item:complete/renew меняют состояние пункта, не status задачи и не возвращают исключённый lifecycle в MVP.

Проверен Checklistitem.php pinned b24phpsdk: add(taskId,title,sort,completed) не передаёт PARENT_ID. Для root/item add требуется явный Core REST1; SDK wrapper update принимает fields и позволяет изменить TITLE. Контроль дерева использует getlist. API routes и SDK gap отражены в JSON, не маскируются одним wrapper name.

## Title search in MVP

Task:find --title TEXT — read-only MVP команда. Согласованы буквальная подстрока только title, trim запроса по краям, Unicode case-insensitive matching, порядок id ASC. Пустой/пробельный запрос отклоняется с usage exit2 до API call. Wildcard/regex/fuzzy и поиск description/chat не включены.

REST3 list документирует server filter только id. Find выбирает id/title и сканирует все видимые текущему подключению страницы в max-scan budget, затем возвращает совпадения в рамках limit. Limit50/--all управляет выводом; max-scan10000 просмотром, --all его не отменяет. Meta содержит query, match, scannedCount, matchedCount, returnedCount, limitApplied, complete и scope видимости; API version/local matching явно отражены. Превышение cap, ошибка или отсутствующий title означает partial exit3, даже если совпадений нет. Полная пустая выборка — success exit0. Полнота scan не означает полный вывод при limitApplied и не обещает доступ к чужим задачам/атомарный snapshot.

## Title search result and API verification

User clarification: результат find — список задач. Stable columns/fields: id/title. Human — таблица ID/TITLE, plain — TSV id/title без заголовка с общим escaping, JSON — data.items array внутри общего data/meta/error envelope. Ноль или одно совпадение сохраняет тип списка; команда не открывает задачу и не переключается на task:show. Остальные meta/partial/limit правила сохраняются.

API-контракт повторно проверен 2026-10-09 по опубликованным list v3/field.list docs и официальному MCP документации: list поддерживает server filter id, в примере field metadata title filterable=false. Это подтверждение документации, не live portal verdict. MCP summary list смешивает v3 pagination с legacy start/params и содержит недействующий URL; version-specific published v3 page используется для маршрута/пагинации. Live field.list/select id/title и запросы list на тестовом портале остаются runtime acceptance; в этом worktree нет настроенного подключения. Документированный metadata пример не выдаётся за фактическую схему конкретного портала.

## Participants and history in MVP

Пользователь явно разрешил REST1 tasks.task.update для task:participants:set и tasks.task.history.list для task:history:list. Это выбранные маршруты с показом API version в meta; strict-rest3 отклоняет обе команды до запроса. Общий метод update не разрешает остальные legacy field updates, raw status или исключённые lifecycle переходы.

Participants:set задаёт полный состав указанной роли через повторяемые IDs; omission сохраняет роль, explicit clear очищает её и несовместим с её IDs. Нужна хотя бы одна явная операция с ролью; ID и права подключения проверяются. History:list возвращает историю изменений выбранной задачи отдельно от task chat; допустимые params, полнота/пагинация и доступ проверяются при реализации. API/SDK core-gap и все существующие source mappings сохранены; это допуск scope/API, не runtime acceptance.

## Dedicated subtask command after MVP

task:subtask:add исключена из регистрации первого релиза. Рабочее предположение: пользователь удаляет отдельную команду, а явный parentId через обычную task:add и её advanced writable fields сохраняется. CLI не добавляет --parent shortcut/alias и не выбирает родителя неявно. При уточнении о полном запрете подзадач правило parentId требуется пересмотреть до реализации.

## Console architecture follow-up

Архитектура 30 retained команд проектируется отдельно в [design-task-console-architecture](../design-task-console-architecture/proposal.md): Symfony Console + DependencyInjection, без FrameworkBundle, PHP реализации и установки зависимостей. Карта Command/Request/Handler/API/output, параметры --where/--params и TSV profiles уточнены в [design](../design-task-console-architecture/design.md) и [command map](../design-task-console-architecture/command-map.md). Это не закрывает оставшиеся provider/runtime acceptance задачи данного change.
