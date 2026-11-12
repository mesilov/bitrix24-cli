# Чаты: адресаты

[Обзор CLI](../README.md#cli-команды) · [Подключение](connection.md) · [JTBD: CHAT-01/CHAT-02](task-jtbd.md#общие-сценарии-чатов--план-180) · [JTBD: созвоны CHAT-03…05](task-jtbd.md#chat-calls)

Namespace `chats:*` работает с групповым чатом, общим чатом проекта и чатом задачи. Команды: `chats:resolve` (проверка адресата), `chats:find` (поиск по названию), `chats:messages:list` (история за период), `chats:send` (отправка), `chats:add` (создание группового чата), `chats:calls:list` (звонки за период) и `chats:calls:show` (один звонок). Issue — [#180](https://github.com/mesilov/bitrix24-cli/issues/180), созвоны — [#208](https://github.com/mesilov/bitrix24-cli/issues/208), создание чата — [#185](https://github.com/mesilov/bitrix24-cli/issues/185).

```sh
b24cli chats:resolve sg234 --json
b24cli chats:resolve task:5288 --json
b24cli chats:resolve 'https://portal.example/online/?IM_DIALOG=chat9636&IM_MESSAGE=19436' --json
b24cli chats:find --name 'Команда проекта' --json
b24cli chats:send --name 'Команда проекта' --text 'Сегодня меня не будет' --dry-run --json
b24cli chats:add --title 'ИИ-новости' --description 'Новости про ИИ для компании' --user-id 12,34 --dry-run --json
b24cli chats:messages:list sg234 --period yesterday --timezone Europe/Moscow --json
b24cli chats:messages:list task:5288 --from 2026-11-11T00:00:00+03:00 --to 2026-11-12T00:00:00+03:00 --json
b24cli chats:calls:list sg234 --from 2026-11-09T00:00:00+03:00 --to 2026-11-16T00:00:00+03:00 --plain
b24cli chats:calls:show chat37 1001 --json
b24cli users:presence chat37 --json
```

## Адресат

| TARGET | Что выбирает | Проверка |
| --- | --- | --- |
| `chatN` | Групповой чат N | `im.dialog.get`: `id` = N |
| `sgN`, `group:N` | Общий чат проекта N, а не `chatN` и не чаты задач проекта | `im.dialog.get`: `entity_type` = `SONET_GROUP`, `entity_id` = N |
| `task:N` | Чат задачи N | `tasks.task.get` (REST 3.0) даёт `chat.id`; `im.dialog.get`: `entity_type` = `TASKS_TASK`, `entity_id` = N |

Голое число отклоняется: оно не говорит, какой это ID. Личные диалоги не поддерживаются.

## Ссылки

CLI разбирает ссылку локально и не переходит по ней. Хост ссылки должен совпадать с порталом выбранного подключения, иначе команда завершается кодом `foreign-portal` до запросов к API.

| Форма | Адресат |
| --- | --- |
| `/online/?IM_DIALOG=chatN` или `sgN` | Чат или общий чат проекта |
| `…&IM_MESSAGE=M` | Тот же чат; M возвращается как `messageId` и не становится адресатом |
| `/company/personal/user/U/tasks/task/view/T/` | Чат задачи T |
| `/workgroups/group/G/tasks/task/view/T/` | Чат задачи T (путь задачи разбирается раньше пути проекта) |
| `/workgroups/group/G/` | Общий чат проекта G |

Формы задачи и проекта подтверждены полем `entity_link.url` метода `im.dialog.get` на тестовом портале. Форма `/online/?IM_DIALOG=chatN` подтверждена ссылкой из интерфейса портала; формы `sgN` и `IM_MESSAGE` разбираются по тому же allowlist. Повтор параметра адресата, `IM_DIALOG` вне `/online/`, неизвестный путь, другой порт и данные пользователя в ссылке отклоняются.

## Результат и ошибки chats:resolve

JSON `data.destination`: `kind`, `target`, `source`, `chatId`, `groupId`, `taskId`, `messageId`. `data.dialog` содержит поля `im.dialog.get`: `id`, `dialog_id`, `name`, `type`, `entity_type`, `entity_id`, `owner`, `role`, `message_count`.

| Код | Когда |
| --- | --- |
| `usage-error` (exit 2) | Неподдерживаемый или неоднозначный TARGET; учётные данные не читаются |
| `foreign-portal` (exit 2) | Ссылка другого портала |
| `chat-unavailable` (exit 1) | Чат не существует или недоступен пользователю (`ACCESS_ERROR`); это не пустой чат |
| `resource-binding-unverified`, `message-binding-unverified` (exit 4) | API не подтвердил связь адресата с чатом; обходного пути нет |
| `policy-denied` (exit 4) | `--api-policy strict-rest3`: маршрут `im.dialog.get` относится к REST 1.0 |

Команда требует область доступа `im`, для `task:N` — также `task`. Чтение не меняет прочитанность и не добавляет пользователя в чат.

## Поиск по названию

`chats:find --name NAME` ищет адресат в трёх источниках, потому что `im.search.chat.list` не возвращает чаты проектов и задач:

| Источник | Метод | Кандидат | Сравнивается |
| --- | --- | --- | --- |
| Групповые чаты | `im.search.chat.list` (REST 1.0, `im`) | `chatN` | Название чата |
| Проекты | `sonet_group.get` с `%NAME` (REST 1.0, `sonet_group`) | `sgN` | Название группы |
| Задачи | `tasks.task.list` (REST 3.0, `task`), как `task:find --title` | `task:N` | Заголовок задачи, а не название её чата |

Поиск чатов находит чаты и по именам участников. Такие совпадения не выводятся, их число — в `meta.sources.chat.participantOnly`. Кандидат показывается, если его название содержит NAME без учёта регистра. Точное совпадение — название после обрезки пробелов с учётом регистра. Для точных совпадений проекта и задачи чат подтверждается так же, как в `chats:resolve`; остальные кандидаты не проверяются, чтобы не тратить запросы.

`data.selection.status` — итог выбора по названию:

| status | Смысл |
| --- | --- |
| `unique` | Ровно одно точное совпадение с подтверждённым чатом при полном поиске; `target` — адресат |
| `none` | Точных совпадений нет |
| `ambiguous` | Точных совпадений несколько, в том числе в разных источниках; укажите TARGET явно |
| `unavailable` | Единственное точное совпадение без доступного чата (`reason`: `chat-unavailable` или код ошибки связи) |
| `incomplete` | Хотя бы один источник просмотрен не полностью; exit 3, адресат не выбирается даже при одном точном совпадении |

`meta.sources.chat|project|task`: `complete`, `reason` (`scan-budget-exhausted`, `page-error`, `pagination-no-progress`), `scanned`, `matched`, при ошибке — `error`. Ошибка источника не превращается в «не найдено». `--max-scan` (по умолчанию 10000) — бюджет на каждый источник; `--limit` (50) и `--all` меняют только вывод, точные совпадения идут первыми.

JSON `data`: `name`, `selection` (`status`, `target`, `exactMatches`), `items` (`source`, `target`, `name`, `exact`, `writable`, `chatId`, `reason`, `chatType` / `groupId` / `taskId`). TSV при `--plain`: `source`, `target`, `name`, `exact`, `writable`, `chatId`, `reason`; итог выбора — в stderr.

## История за период

`chats:messages:list TARGET` принимает тот же TARGET и один из периодов:

- `--period yesterday --timezone ZONE` — вчерашние сутки от местной полуночи до местной полуночи. В день перехода на летнее или зимнее время сутки длятся 23 или 25 часов.
- `--from` и `--to` — точные границы ISO 8601 со смещением. `--timezone` для них необязателен и только переводит границы в эту зону для вывода.

Интервал полуоткрытый: сообщение ровно в `from` входит, ровно в `to` — нет. «Сейчас» читается один раз на запуск и возвращается в `data.period.now`.

### Как читается история

`im.dialog.messages.get` не фильтрует по дате. CLI начинает с последнего сообщения (`LAST_ID = last_message_id + 1`) и идёт страницами по 50 к началу истории. Без `LAST_ID` метод вернул бы окно первого непрочитанного сообщения, и новые сообщения потерялись бы.

Порядок дат по ID не доказан, поэтому CLI не останавливается на первом старом сообщении и просматривает историю до начала в пределах `--max-messages` (по умолчанию 10000). Для большого чата это много запросов; уменьшите бюджет, если полнота не нужна.

### Полнота

| meta | Смысл |
| --- | --- |
| `complete: true` | История просмотрена до начала; exit 0 |
| `reason: message-budget-exhausted` | Закончился `--max-messages`; exit 3 |
| `reason: page-error` | Ошибка на второй или следующей странице; найденные сообщения сохранены; exit 3 |
| `reason: pagination-no-progress` | API вернул уже просмотренные ID; exit 3 |
| `reason: conflicting-rows` | Одинаковый ID с разным содержимым; exit 3 |
| `reason: output-limit` | `--limit` обрезал вывод; `matched` — сколько найдено; exit 3 |
| `periodStartPassed` | Просмотр ушёл раньше `from`; при неполном результате это подсказка, а не доказательство полноты |

Ошибка первой страницы или чужой `chat_id` — ошибка команды, а не пустой результат.

JSON `data`: `destination`, `period` (`kind`, `from`, `to`, `timezone`, `now`), `items` (`id`, `createdDate`, `authorId`, `text`, `chatId`, `kind`, `callRef`) по времени и ID, `authors` (`id`, `name` из ответа API; `authorId` 0 — системное сообщение), `calls`. TSV при `--plain`: `id`, `createdDate`, `authorId`, `text`, `kind`, `callId`.

## Созвоны

Сообщение о звонке распознаётся только по компоненту `CallMessage` в `params`; локализованный текст «Звонок завершён (…)» не разбирается. У такого сообщения `kind: call` и `callRef` (`callId`, событие `start` или `finish`); у остальных `kind: text` или `system`.

Если в периоде есть сообщения о звонке, `chats:messages:list` по умолчанию добавляет `data.calls` — по записи на звонок. `--no-call-details` отключает дополнительные запросы; без сообщений о звонке их нет. `chats:calls:list` возвращает только записи звонков периода в хронологическом порядке (PM-10); полнота такая же, как у истории. `chats:calls:show TARGET CALL_ID` читает один звонок в окне от его начала до пяти минут после конца (CHAT-03, CHAT-05); звонок другого чата отклоняется ошибкой привязки (exit 4), а при недоступных данных звонка — `call-unavailable`.

Запись звонка:

| Поле | Содержимое |
| --- | --- |
| `callId`, `uuid`, `state`, `isPublic` | из `im.call.get` |
| `startedAt`, `endedAt`, `durationSeconds` | из данных звонка, иначе из сообщений; `durationText` — текст сообщения для сверки |
| `initiatorId`, `finishedById` | инициатор звонка и завершивший из сообщения FINISH |
| `messages` | `startId`, `finishId`, `duringIds` — ID сообщений в окне |
| `inWindow` | попали ли START и FINISH в окно; начало до периода берётся из данных звонка, сообщения вне периода не добавляются |
| `recording` | `audio`, `aiAnalyze`, `logUrl`, флаги ИИ и причины отсутствия записи: `ai-service-disabled`, `ai-settings-disabled`, `ai-agreement-not-accepted`, `ai-tariff-unavailable` |
| `participants.eligible` | состав чата из данных звонка (или читатели чата, если данных нет) — не список подключившихся |
| `participants.confirmed` | фактические участники с `talkedSeconds` из AI Follow-up `call.followup.get`; без Follow-up пусто с причиной `followup-method-unavailable`, `followup-scope-missing`, `followup-not-found` или `followup-access-denied` |
| `participants.inferred` | оценка остальных по правилам `rulesVersion: 1` |

Правила оценки:

| Правило | Доказательство | Влияние |
| --- | --- | --- |
| `finish-author` | автор сообщения FINISH | present, high |
| `call-initiator` | инициатор звонка | present, medium |
| `read-finish-at-end` | прочитал FINISH не позже 5 секунд после него | present, high |
| `message-during-call` | писал в чат во время звонка | present, medium |
| `last-read-before-start` | последнее прочтение раньше START | absent, high |
| `last-activity-before-start` | последняя активность раньше начала | absent, high |
| `last-activity-before-finish` | последняя активность во время звонка, раньше чем за минуту до конца | left-early, low |

Присутствие вместе с отсутствием и ранний уход вместе с присутствием в конце дают `unknown` с `conflict: true`. Ранний уход с присутствием во время звонка — `left-early`. Ничего не сработало — `unknown`. Доказательства — снимок на `observedAt`: если после звонка прошло больше часа или в чате появились новые сообщения, `evidenceFreshness: stale`, и правила прочтения дают не больше `low`. Для публичного звонка есть оговорка `public-link-guests-invisible`.

Оценка не является фактом присутствия и не годится для дисциплинарных решений. Статус мессенджера и признак онлайна не используются: на портале они остаются `online` у давно неактивных пользователей.

`im.call.get` не описан в документации Битрикс24. Его отказ даёт `details.status: unavailable` с причиной и не меняет полноту истории и код выхода. Ответ проходит через белый список полей, поэтому токены подключения (`jwt`, подписи каналов, `logToken`) не выводятся и не логируются. `call.followup.get` (REST 3.0) требует необязательной области доступа `call`. В human и plain сводка по звонкам выводится в stderr; TSV `chats:calls:list` и `chats:calls:show`: `callId`, `startedAt`, `durationSeconds`, `initiatorId`, `present`, `absent`, `leftEarly`, `unknown`.

`users:presence TARGET` показывает последнюю активность участников того же чата (без ботов и системных пользователей, [`im.dialog.users.list`](https://apidocs.bitrix24.ru/api-reference/chats/chat-users/im-dialog-users-list.html)); подробнее — в [сотрудниках](users.md).

## Отправка

`chats:send` отправляет одно текстовое сообщение через `im.message.add` (REST 1.0, `im`). Адресат — TARGET или `--name`, текст — `--text` или `--text-file PATH|-` (stdin).

Адресат проверяется до записи:

- TARGET — как в `chats:resolve`.
- `--name` — как в `chats:find`; запись разрешена только при `selection.status = unique`. Иначе ничего не отправляется:

| Код | Когда |
| --- | --- |
| `chat-not-found` (exit 1) | Точных совпадений нет |
| `chat-ambiguous` (exit 4) | Несколько точных совпадений; кандидаты в `error.details.candidates`, выберите TARGET |
| `chat-unavailable` (exit 1) | Чат недоступен пользователю подключения, в том числе отказ `im.message.add` с `ACCESS_ERROR` |
| `search-incomplete` (exit 4) | Поиск неполный (`error.details.sources`); укажите TARGET или увеличьте `--max-scan` |

`--dry-run` выполняет проверочные чтения адресата и показывает план (`im.message.add` с `target`, `dialogId`, `chatName`, `text`, затем чтение для проверки) без отправки. Сообщение уходит в физический чат `chatN`, найденный при проверке.

### Исход записи

`im.message.add` вызывается один раз и возвращает ID сообщения. Затем CLI читает сообщение по ID (`im.dialog.messages.get`, `LAST_ID = ID + 1`, `LIMIT 1`) и сверяет чат.

| Результат | Смысл |
| --- | --- |
| `operation.readback = verified`, exit 0 | Сообщение создано и найдено в выбранном чате |
| `readback = missing`, `failed` или `chat-mismatch`, exit 3 | Сервер вернул ID, но проверка не подтвердила сообщение; `messageId` сохранён |
| ошибка с `meta.outcomeUnknown = true` | Ответ потерян или пришёл без ID: сообщение могло быть создано. CLI не повторяет запись; проверьте `chats:messages:list` перед повтором |

Отправка сама по себе отмечает чат прочитанным для отправителя: это поведение портала при `im.message.add` (приёмка 12.11.2026: 60 непрочитанных → 0). Чтение через `chats:*` прочитанность не меняет.

JSON `data`: `destination`, `operation` (`action`, `target`, `chatId`, `messageId`, `confirmed`, `readback`), `readback`. TSV при `--plain`: `target`, `chatId`, `messageId`, `confirmed`, `readback`.

## Создание чата

`chats:add` создаёт групповой чат через [`im.chat.add`](https://apidocs.bitrix24.ru/api-reference/chats/im-chat-add.html) (REST 1.0, `im`) от имени пользователя подключения: он становится владельцем и участником автоматически. Сценарий — завести чат с коллегами и затем писать в него через `chats:send` ([JTBD CHAT-06](task-jtbd.md#chat-06)).

```sh
b24cli users:colleagues --json
b24cli chats:add --title 'ИИ-новости' --description 'Новости про ИИ для компании' --user-id 12,34 --json
b24cli chats:send chat9901 --text 'Первый выпуск новостей' --json
```

| Опция | Поле `im.chat.add` | Значения |
| --- | --- | --- |
| `--title` (обязательна) | `TITLE` | 2–255 символов после обрезки пробелов |
| `--description` | `DESCRIPTION` | непустой текст; не передаётся, если опция не задана |
| `--type` | `TYPE` | `CHAT` — закрытый (по умолчанию), `OPEN` — открытый |
| `--user-id` | `USERS` | ID сотрудников; повторите опцию или перечислите через запятую, до 500 |

Каналы Битрикс24 REST не создаёт: документация `im.chat.add` допускает только `CHAT` и `OPEN`, другие значения `--type` отклоняются. Писать в уже существующий канал можно через `chats:send chatN`, если у пользователя есть право публикации. Удалить чат через REST нельзя, поэтому создание защищено от повторного запуска.

### Защита от дубля

До записи CLI ищет доступные групповые чаты с тем же названием через `im.search.chat.list`, как источник групповых чатов в `chats:find`: сравнение после обрезки пробелов с учётом регистра, совпадения по имени участника и личные диалоги не считаются дублем, бюджет — `--max-scan` (по умолчанию 10000). Проекты и задачи не проверяются: создаётся обычный групповой чат.

| Код | Когда |
| --- | --- |
| `chat-exists` (exit 4) | Доступный чат с таким названием уже есть; адресаты в `error.details.candidates`. Пишите в него через `chats:send` |
| `search-incomplete` (exit 4) | Поиск неполный (`error.details.source.reason`: `scan-budget-exhausted`, `pagination-no-progress`, `page-error`); увеличьте `--max-scan` |
| `chat-create-denied` (exit 1) | Портал отказал в создании (`ACCESS_ERROR`, `ACCESS_DENIED`) |

`--allow-duplicate-title` пропускает проверку и создаёт ещё один одноимённый чат; вместе с `--max-scan` — `usage-error`. Проверка видит только чаты, доступные пользователю подключения.

### Исход записи

`--dry-run` выполняет только проверку дубля и показывает план: `im.chat.add` (`title`, `type`, `description`, `userIds`, `duplicateCheck`), затем проверочные чтения. `im.chat.add` вызывается один раз и возвращает ID чата. Затем CLI читает чат (`im.dialog.get`) и его участников без ботов (`im.dialog.users.list`).

| Результат | Смысл |
| --- | --- |
| `operation.readback = verified`, exit 0 | Чат создан, название совпало, все запрошенные участники в чате |
| `readback = title-mismatch`, `members-missing` или `failed`, exit 3 | Чат создан, но проверка не подтвердила название, участников (`readback.members.missing`) или не выполнилась (`readback.error`); `chatId` сохранён, повторно создавать не нужно |
| ошибка с `meta.outcomeUnknown = true` | Ответ потерян или пришёл без ID: чат мог быть создан. CLI не повторяет запись; проверьте `chats:find --name` перед повтором |

Поле `type` из `im.dialog.get` возвращается как есть и не сверяется: его значения в документации не перечислены. ID участников заранее не проверяются; несуществующий или недоступный ID обнаруживается проверкой участников.

JSON `data`: `chat` (`target`, `chatId`, `title`, `type`, `description`, `userIds`), `operation` (`action: create`, `target`, `chatId`, `confirmed`, `readback`), `readback` (`status`, `dialog`: `id`, `name`, `type`, `owner`; `members`: `requested`, `present`, `missing`, `complete`), `duplicateCheck` (`status`: `none` или `skipped`, `scanned`). TSV при `--plain`: `target`, `chatId`, `title`, `type`, `confirmed`, `readback`.
