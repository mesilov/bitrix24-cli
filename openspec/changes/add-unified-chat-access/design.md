# Design

## Context

Снимок `dev`: `787770ee3c1b596df8ccde4bf6e984cee46c34ef`, проверка 30.10.2026. Мотивация — [proposal.md](proposal.md). В CommandCatalog есть task:chat:list/send/update/delete, общих chats-команд нет. ListTaskChatCommand принимает TASK_ID, before/after как ID сообщений, limit/all/max-messages; date/timezone-фильтра нет.

ChatSupport получает задачу через tasks.task.get REST3 и проверяет chat.id/entityId/entityType; TaskChatAdapter читает im.dialog.messages.get REST1. Отправка задачи использует tasks.task.chat.message.send REST3. Эти ветви сохраняются. DeadlineWindowResolver и календарная логика task:report — точки для переиспользования календарных границ, но фильтры дедлайнов нельзя применять к датам сообщений.

Документы JTBD содержат 25 ролевых сценариев. PM-06/AM-06/EMP-07 используют чтение/отправку в чат задачи. PM-03/EMP-03 имеют только отправку сообщения о препятствии. AM-03 частично покрыт длительностями и task:report; PM-09 — показатели задач. Саммари переписки, URL и произвольные чаты не реализованы. Файлы integration tests доказывают наличие cases, не их успешное выполнение в этой сессии.

## Goals / Non-Goals

**Goals:** детерминированный адресат, ограниченное чтение и проверяемый результат отправки; раздельная полнота поиска и периода. Контракт CLI позволяет агенту выполнить CHAT-01/CHAT-02.

**Non-Goals:** встроенный LLM и хранение саммари, расписание, unread-счётчики, создание/присоединение к чатам, изменение прочитанности. Личные диалоги не входят в первый этап: их numeric DIALOG_ID документирован API, но здесь принимаются только групповые чаты, задачи и проекты.

## Decisions

### 1. Отдельный chats namespace и типизированный адресат

Зафиксированный публичный namespace — `chats:*`; команды пока отсутствуют в runtime:

```sh
b24cli chats:resolve 'https://portal.example/online/?IM_DIALOG=chat123' --json
b24cli chats:find --name 'Команда проекта' --json
b24cli chats:messages:list 'group:234' --period yesterday --timezone Asia/Bishkek --json
b24cli chats:messages:list 'task:123' --from 2026-10-29T00:00:00+06:00 --to 2026-10-30T00:00:00+06:00 --timezone Asia/Bishkek --json
b24cli chats:send 'chat123' --text 'Сегодня отсутствую' --dry-run
b24cli chats:send --name 'Команда проекта' --text-file -
```

Самостоятельный namespace `chats:*` работает с доступным групповым чатом без обязательного TASK_ID или GROUP_ID. Ссылки на проект и задачу — дополнительные способы разрешить адресат. Существующий `task:chat:*` сохраняет вход через ID задачи; общий namespace `chat:*` не вводится.

Ввод — один TARGET либо --name там, где поддержан поиск. TARGET: URL, chatN, sgN, task:N, group:N. Положительные ID обязательны; bare N отклоняется. group:N нормализуется к sgN. Task:N сохраняет taskId и проверенную связь с чатом через существующий task lookup. Новый namespace использует IM отправку с ID результата, task:chat:send сохраняет свой существующий REST3 ACK.

chats:resolve получает metadata через im.dialog.get и сохраняет логический адресат (например sg234) отдельно от физического chat ID из ответа. Проверять возвращённую связь с задачей и выбранным диалогом, не принимать null/неполный ответ как успех. Имена полей metadata закрепить по полному методу и fixtures до реализации. Отклонять неподтверждённую связь, не создавать чат как fallback. На playground (11.11.2026) im.dialog.get возвращает entity_type/entity_id (`SONET_GROUP`/`234` для sg234, `TASKS_TASK`/`5288` для чата задачи) и entity_link.url; это достаточные поля для сверки связи. sg234 и chat10130 — один физический чат.

Отдельный namespace выбран, потому что расширять TASK_ID до произвольного ID означало бы смешать контексты и нарушить существующий binding. Новые команды добавляются в явный каталог и route map; task-count остаётся 50 в этом снимке, public-count увеличивается только при реализации.

### 2. URL parser с явным allowlist

Начальный предлагаемый набор fixtures:

- /online/?IM_DIALOG=chatN и /online/?IM_DIALOG=sgN;
- /company/personal/user/U/tasks/task/view/T/;
- /workgroups/group/G/tasks/task/view/T/ — выбирает задачу T;
- /workgroups/group/G/ — выбирает общий чат проекта G.

Это предлагаемый контракт парсера, а не подтверждённый перечень URL всех версий Битрикс24. До кода подтвердить формы обезличенными ссылками актуального интерфейса; добавить конкретные permalink-варианты сообщения в allowlist fixtures, не угадывать ID по последнему числу URL. Известный параметр сообщения отделяется от адресата; неизвестный navigation fragment не становится chat ID. Конфликт нескольких адресатов, userinfo, неоднозначное кодирование и другой host/port выбранного профиля отклоняются. Путь задачи разбирается раньше общего пути проекта. URL не открывается и не загружается; API-клиент создаётся только для выбранного подключения.

### 3. Полный поиск до выбора по названию

Использовать im.search.chat.list с FIND, OFFSET, LIMIT; LIMIT не выше 50, конечный max-chats (предлагаемый default 1000). Учитывать next/total, дубликаты, отсутствие прогресса и ошибки страниц. API ищет также по участникам, поэтому после поиска отдельно сравнивать возвращённое название с запрошенным после trim, без молчаливого выбора похожего названия. Ноль, одно, несколько точных совпадений и неполная выдача — разные исходы. chats:find показывает кандидатов; --name для send разрешает запись только при одном точном совпадении в полной выдаче. Пользователь может явно выбрать chatN из кандидатов.

Live-наблюдение playground (11.11.2026): im.search.chat.list возвращает только чаты типа `chat`/`general`. Общие чаты проектов (`collab`, SONET_GROUP) и чаты задач (`tasksTask`) не находятся даже по точному названию; поиск по имени участника дал 286 чатов, из них 285 `chat` и 1 `general`. Поэтому пустая IM-выдача не доказывает отсутствие чата проекта или задачи с таким названием. Решение (#180, 12.11.2026): chats:find ищет по трём источникам и помечает контекст каждого кандидата:

- обычные групповые чаты — im.search.chat.list, сравнение по названию чата;
- общие чаты проектов — поиск групп по названию (sonet_group.get с `%NAME`), кандидат sgN; адресат подтверждается im.dialog.get, связь — по entity_type `SONET_GROUP` и entity_id;
- чаты задач — существующий поиск задач по заголовку (тот же обход, что task:find --title, REST3), кандидат task:N; чат и связь подтверждаются как для task:N.

Название чата задачи не равно заголовку задачи: на playground чат задачи 5288 называется `b24cli-ёж-…`, а задача — `test task 1793168624`. Поэтому для задач сравнивается заголовок задачи, а не название чата. Точное совпадение и полнота считаются по каждому источнику; кандидат, у которого адресат не подтвердился (например, чат удалённой группы даёт ACCESS_ERROR), в выбор для записи не попадает. --name для записи требует ровно одного точного совпадения среди всех трёх источников при полной выдаче каждого. Источник поиска проектов (sonet_group.get или socialnetwork.api.workgroup.list) и его маршрут закрепить в route plan; strict-rest3 отклоняет REST1-части плана.

### 4. Календарный период поверх bounded ID pagination

--period yesterday взаимоисключается с парой --from/--to. Для календарного периода --timezone обязателен; для точных дат тоже выводится выбранный IANA timezone, а явные offset определяют абсолютные границы. Обе границы должны быть валидными, from < to; ровно один clock now на запуск. Вчера — [вчера 00:00, сегодня 00:00) по timezone, с DST.

Обход начинается с `LAST_ID = last_message_id + 1` из im.dialog.get. Без LAST_ID метод отдаёт окно от первого непрочитанного (`unread_id`), а не последние сообщения: на playground chat9636 с 38 непрочитанными первая страница оказалась самой старой. Страницы im.dialog.messages.get обходятся назад с LAST_ID; LIMIT <= 50, max-messages default 10000. Опциональный limit ограничивает вывод, не бюджет; all снимает только лимит вывода. При достижении точной границы страницы без доказательства исчерпания нужна дополнительная проверка в пределах бюджета. Проверять chat_id, ID и даты, удалять дубликаты, обнаруживать конфликтующие повторные записи и отсутствие прогресса; сортировать результат по времени и ID.

API не имеет date-фильтра. Ранний stop по timestamp допустим лишь при доказанной монотонности дат относительно ID для поддержанного контракта. На playground даты росли вместе с ID во всех просмотренных чатах, но ID не непрерывны (шаг 2 и разрывы); это наблюдение, не доказательство монотонности. Иначе читать до конца доступной истории в пределах бюджета; невозможность подтвердить полноту сохранять как partial. Это дороже, но не теряет сообщения из-за предположения об ID. Конкурентные изменения истории и расхождение наблюдений отражать как ограничения/partial, не обещать атомарный snapshot.

JSON содержит выбранный адресат, период/timezone, messages с ID/автором/датой/текстом и meta.complete/reason/scanned/returned. Сохранять доступные сведения авторов из ответа, не выдавать неизвестного автора за текущего пользователя. DTO вывода следует существующему envelope; REST payload использует только native поля. Ссылки на сообщения возвращать только если поддержанная URL-форма проверена; ID обязателен и достаточен как исходная ссылка агента на источник.

### 5. Отправка и policy

Новые операции используют полный план маршрутов: metadata, при необходимости task lookup или поиск, затем im.message.add. Обновить CommandCatalog::ROUTES и ScopeManifest. Для im методов требуется im; для task lookup сохраняется существующий scope/route. strict-rest3 отклоняет non-v3 план до credentials/API. Проверки разрешений не заменяют проверки серверного доступа к конкретному чату.

Текст, файл и stdin взаимоисключаются. Dry-run показывает адресат и текст, отмечает проверочные чтения и не выполняет запись. im.message.add вызывается один раз; integer result сохраняется как ID. Ответ не содержит ID чата, поэтому подтверждённый адресат берётся из предварительного im.dialog.get, а readback по ID сверяет chat_id сообщения. Live-проверка playground: отправка в sg234 вернула 23622, readback показал chat_id 10130 и тот же текст. При timeout после возможного принятия outcomeUnknown сохраняется без повтора. Чтение не вызывает read/прочитанность, join или add-user методы. Live-проверка отдельно сравнивает состояние прочитанности до/после.

## API evidence

Официальный MCP bitrix24_dev, 30.10.2026: search → method details; effective config в worktree подтверждён `codex mcp get bitrix24_dev --json`. MCP отвечает документацией; портал не вызывался. Context7 tools в этой сессии отсутствуют.

| Метод | Native request / result |
| --- | --- |
| [im.dialog.get](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) | DIALOG_ID string: chatN / sgN / numeric user dialog; metadata result object; scope im |
| [im.dialog.messages.get](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html) | DIALOG_ID string required; LAST_ID/FIRST_ID integer optional; LIMIT integer default 20/max 50; result.chat_id, messages с id/date/author_id/text |
| [im.search.chat.list](https://apidocs.bitrix24.ru/api-reference/chats/search/im-search-chat-list.html) | FIND string >=2; OFFSET integer default 0; LIMIT integer default 10/max 50; result array, next/total |
| [im.message.add](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-message-add.html) | DIALOG_ID string, MESSAGE string для текстовой отправки; result integer message ID; scope im |
| [im.chat.get](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-get.html) | ENTITY_TYPE/ENTITY_ID string; SONET_GROUP/TASKS_TASK поддержаны; result.ID либо null. Дополнительный fallback не включён: формат ENTITY_ID по контекстам здесь не проверен |

## Live evidence (playground, 11.11.2026)

Raw-запросы вебхуком пользователя 1 к тестовому порталу; одна запись — тестовое сообщение 23622 в sg234.

| Наблюдение | Следствие |
| --- | --- |
| im.chat.get `TASKS_TASK`/5288 → `{ID:9762}`; `TASKS`/5288 → null; `SONET_GROUP`/234 → `{ID:10130}` | Для связи задачи использовать `TASKS_TASK`, не `TASKS` |
| im.dialog.get отсутствующего/чужого чата → `ACCESS_ERROR` | Отказ отличается от пустой истории |
| im.dialog.messages.get без LAST_ID → окно от `unread_id` | Начинать обход с last_message_id + 1 |
| counter/unread_id не изменились после всех чтений | Чтение не меняет read state |
| im.search.chat.list: `FIND` из 1 символа → `FIND_SHORT`; OFFSET-страницы без дублей | Отдельные исходы ошибки и пустой выдачи |
| im.search.chat.list не находит `collab` и `tasksTask` | Проекты и задачи ищутся отдельными источниками |
| sonet_group.get `%NAME` и socialnetwork.api.workgroup.list находят группу 234 по фрагменту названия | Источник для поиска проектов |
| task:find --title находит 5288 за 3 вызова tasks.task.list (REST3), complete=true; по названию её чата — 0 | Задачи искать по заголовку задачи |
| Чаты удалённых групп 222–232 остаются в im.recent.list с SONET_GROUP, а sg230 даёт ACCESS_ERROR | Кандидат без подтверждённого адресата не выбирается |
| LIMIT 51 принят без ошибки на чате из 38 сообщений | Предел 50 не подтверждён и не опровергнут; нужен чат >50 сообщений |
| entity_link.url: `/workgroups/group/234/…`, `/company/personal/user/1/tasks/task/view/5288/…` | Две URL-формы подтверждены; `/online/?IM_DIALOG=` и permalink сообщения — нет |
| Локальное время +06 12.11, портал MSK 11.11 | «Вчера» зависит от timezone; передавать её явно |

## Risks / Trade-offs

- [Различия URL между порталами] → конкретный allowlist и fixture tests; неподдерживаемая форма явно отклоняется, остаются типизированные ID.
- [Большая история без date-фильтра] → конечный бюджет, проверка пагинации и явный partial; all не обещает полноту автоматически.
- [Нет доступа или прав] → явный отказ; не считать пустым днём и не присоединять пользователя к чату.
- [Одинаковые названия] → полная выдача и явный выбор; запрет отправки в первый результат.
- [IM REST1] → заранее объявленный plan, scope guard и отказ strict-rest3; скрытого fallback нет.

## Migration Plan

Сейчас публикуются только спецификация и аудит JTBD. На этапе apply добавить четыре команды отдельно, обновить каталог/route map/scope docs/help и tests, выполнить offline проверки и отдельную live-приёмку тестовых чатов. Существующие task:chat:* сохраняются. Архивировать после выполнения и verify, обновив путь #180. Откат будущей реализации удаляет новые команды и маршруты без изменения данных чатов; отправленные сообщения не удаляются автоматически.
