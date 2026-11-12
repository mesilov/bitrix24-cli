# Чаты: адресаты

[Обзор CLI](../README.md#cli-команды) · [Подключение](connection.md) · [JTBD: CHAT-01/CHAT-02](task-jtbd.md#общие-сценарии-чатов--план-180)

Namespace `chats:*` работает с групповым чатом, общим чатом проекта и чатом задачи. Доступны `chats:resolve` (проверка адресата) и `chats:messages:list` (история за период). Поиск по названию и отправка — в плане [#180](https://github.com/mesilov/bitrix24-cli/issues/180).

```sh
b24cli chats:resolve sg234 --json
b24cli chats:resolve task:5288 --json
b24cli chats:resolve 'https://portal.example/online/?IM_DIALOG=chat9636&IM_MESSAGE=19436' --json
b24cli chats:messages:list sg234 --period yesterday --timezone Europe/Moscow --json
b24cli chats:messages:list task:5288 --from 2026-11-11T00:00:00+03:00 --to 2026-11-12T00:00:00+03:00 --json
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

Формы задачи и проекта подтверждены полем `entity_link.url` метода `im.dialog.get` на тестовом портале. Формы `/online/` пока не подтверждены примерами из интерфейса. Повтор параметра адресата, `IM_DIALOG` вне `/online/`, неизвестный путь, другой порт и данные пользователя в ссылке отклоняются.

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

JSON `data`: `destination`, `period` (`kind`, `from`, `to`, `timezone`, `now`), `items` (`id`, `createdDate`, `authorId`, `text`, `chatId`) по времени и ID, `authors` (`id`, `name` из ответа API; `authorId` 0 — системное сообщение). TSV при `--plain`: `id`, `createdDate`, `authorId`, `text`.
