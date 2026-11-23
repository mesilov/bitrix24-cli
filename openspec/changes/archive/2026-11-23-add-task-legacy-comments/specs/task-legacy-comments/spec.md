# Spec Delta

## Purpose

Дать агенту и пользователю прочитать прежнюю ленту комментариев задачи, которая осталась отдельно от нового чата задачи, и честно показать полноту и доступность этого источника.

## ADDED Requirements

### Requirement: Legacy comment feed is a separate read source
`task:legacy:comment:list TASK_ID` SHALL читать прежнюю ленту комментариев задачи как отдельный источник с `meta.source = legacy-comments`. Команда SHALL возвращать для каждого комментария `id`, `authorId`, `authorName`, `createdDate` и `text`; ID комментариев SHALL не смешиваться с ID сообщений чата. Чтение SHALL не менять задачу, комментарии и прочитанность чата.

#### Scenario: Task with legacy comments
- **WHEN** у доступной задачи четыре прежних комментария и вызван `task:legacy:comment:list TASK_ID --all --json`
- **THEN** возвращаются четыре элемента в порядке возрастания `id` с автором, датой и текстом, `meta.source = legacy-comments`, `meta.complete = true`, код выхода 0
- **AND** CLI не вызывает методы записи и методы чата задачи

#### Scenario: Comment and message IDs stay distinct
- **WHEN** у задачи есть и прежние комментарии, и сообщения нового чата с пересекающимися числовыми ID
- **THEN** `task:legacy:comment:list` возвращает только комментарии, `task:chat:list` — только сообщения, и ни одна команда не подставляет элементы другого источника

#### Scenario: Plain output
- **WHEN** вызван `task:legacy:comment:list TASK_ID --plain`
- **THEN** выводятся TSV-колонки `id`, `createdDate`, `authorId`, `authorName`, `text`, `attachmentCount`

### Requirement: Legacy namespace is visible in every surface
Команда SHALL находиться в группе `task:legacy:`, и легаси-статус SHALL быть виден в каждом формате. В `b24cli list` описание SHALL начинаться с `[legacy]`, help SHALL открываться предупреждением об устаревшем источнике и указывать `task:chat:list` для текущего обсуждения. JSON SHALL содержать `meta.legacy = true`. Человекочитаемый вывод SHALL начинаться заголовком о прежней ленте, а `--plain` SHALL писать пометку в stderr и оставлять stdout чистым TSV.

#### Scenario: Command list
- **WHEN** вызван `b24cli list task`
- **THEN** команда показана как `task:legacy:comment:list` с описанием, начинающимся с `[legacy]`; в группе `task:legacy:` нет команд записи

#### Scenario: Help
- **WHEN** вызван `b24cli task:legacy:comment:list --help`
- **THEN** первая строка справки предупреждает, что это устаревший источник ленты комментариев старой карточки на REST 1.0, который может быть отключён на портале, и называет `task:chat:list` для текущего обсуждения

#### Scenario: Output formats
- **WHEN** команда выводит результат в JSON, человекочитаемом формате и `--plain`
- **THEN** JSON содержит `meta.legacy = true` и `meta.source = legacy-comments`; человекочитаемый вывод начинается строкой о прежней ленте комментариев только для чтения; в `--plain` stdout содержит только TSV, а пометка выводится в stderr

### Requirement: Legacy comment attachments without credentials
Команда SHALL возвращать вложения комментария как список с `attachmentId`, `fileId`, `name` и `size`. Ссылки на скачивание и просмотр из ответа API SHALL не попадать в вывод, metadata, журналы и сообщения об ошибках, потому что они могут содержать авторизационный токен.

#### Scenario: Comment with an attachment
- **WHEN** комментарий содержит вложение со ссылками скачивания и просмотра
- **THEN** элемент содержит `attachments` с `attachmentId`, `fileId`, `name`, `size`, а значения ссылок отсутствуют во всех форматах вывода и в журнале

#### Scenario: Comment without attachments
- **WHEN** у комментария нет вложений
- **THEN** `attachments` — пустой список, а не отсутствующее поле

### Requirement: Bounded legacy comment pagination
Обход SHALL идти курсором по ID комментария с конечным бюджетом `--max-comments` (по умолчанию 10000). Без курсора выбираются последние комментарии; `--before ID` и `--after ID` задают позицию. `--limit` ограничивает вывод, `--all` снимает только лимит вывода. Порядок вывода SHALL быть по возрастанию `id`.

#### Scenario: Latest window by default
- **WHEN** у задачи 120 комментариев и вызван `task:legacy:comment:list TASK_ID --limit 20`
- **THEN** возвращаются 20 комментариев с наибольшими `id` в порядке возрастания и `meta.hasMore = true`

#### Scenario: Several pages
- **WHEN** у задачи больше комментариев, чем отдаёт одна страница API, и вызван `--all`
- **THEN** CLI запрашивает страницы курсором по ID до пустой страницы, возвращает все комментарии без повторов, `meta.complete = true`, `meta.scanned` и `meta.returned` равны числу комментариев

#### Scenario: Explicit cursor
- **WHEN** вызван `task:legacy:comment:list TASK_ID --after 6`
- **THEN** возвращаются только комментарии с `id` больше 6

#### Scenario: Budget exhausted
- **WHEN** число комментариев больше `--max-comments`
- **THEN** CLI останавливает обход на бюджете, возвращает прочитанные комментарии, `meta.complete = false`, `meta.reason = comment-budget-exhausted`, `meta.hasMore = true` и код выхода 3

#### Scenario: Server ignores the cursor
- **WHEN** следующая страница не содержит ни одного нового `id` за курсором
- **THEN** обход останавливается с `meta.complete = false`, `meta.reason = pagination-no-progress` и кодом выхода 3, без бесконечного повтора

### Requirement: Unavailable legacy feed is not an empty feed
Пустой ответ SHALL означать отсутствие прежних комментариев только при успешном вызове. Ошибка метода, отсутствие метода, нехватка прав или scope SHALL возвращаться как ошибка источника `legacy-comments` с ненулевым кодом выхода и без пустого успешного списка. Ошибка страницы после частично прочитанных данных SHALL давать частичный результат с причиной.

#### Scenario: Task without legacy comments
- **WHEN** метод успешно возвращает пустой список для доступной задачи
- **THEN** CLI возвращает пустой `items`, `meta.complete = true` и код выхода 0

#### Scenario: Method not available on the portal
- **WHEN** портал отвечает `ERROR_METHOD_NOT_FOUND`
- **THEN** CLI возвращает ошибку с указанием, что прежняя лента комментариев не проверена, ненулевой код выхода и не выводит пустой успешный список

#### Scenario: Task not found or not accessible
- **WHEN** метод отвечает ошибкой `ERROR_CORE` для выбранного TASK_ID (на портале так выглядит отсутствующая или недоступная задача)
- **THEN** CLI возвращает ошибку недоступности задачи или ленты с кодом API `ERROR_CORE`, а не пустую ленту

#### Scenario: Page error after partial read
- **WHEN** первая страница прочитана, а следующая завершилась ошибкой API
- **THEN** CLI возвращает прочитанные комментарии, `meta.complete = false`, `meta.reason = page-error` и код выхода 3

### Requirement: Legacy comment route and policy
Команда SHALL заранее объявлять единственный маршрут REST1 `task.commentitem.getlist` со scope `task`. Стандартная policy SHALL допускать этот маршрут, `strict-rest3` SHALL отклонять команду до чтения credentials и запросов. Нехватка scope `task` SHALL блокировать команду до API по общим правилам проверки scope.

#### Scenario: Strict policy
- **WHEN** `task:legacy:comment:list` вызвана при `strict-rest3`
- **THEN** CLI возвращает policy-denied с кодом 4 до credentials и API-запросов

#### Scenario: Route in metadata
- **WHEN** команда выполнена при стандартной policy
- **THEN** metadata показывает маршрут `task.commentitem.getlist` с версией API 1.0
