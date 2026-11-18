# Research: прежняя лента комментариев задачи

## Документация (MCP `bitrix24_dev`, 18.11.2026)

Порядок: `bitrix-search` → `bitrix-method-details` по точному имени.

- [`task.commentitem.getlist`](https://apidocs.bitrix24.ru/api-reference/tasks/comment-item/task-comment-item-get-list.html), модуль `tasks`, scope `task`. Развитие метода остановлено с tasks 25.700.0, для чата рекомендуется `im.dialog.messages.get`. Метод не помечен как удалённый.
  - Параметры: `TASKID` (integer, обязательный), `ORDER` (поля `ID`, `AUTHOR_ID`, `AUTHOR_NAME`, `AUTHOR_EMAIL`, `POST_DATE`; `asc`/`desc`), `FILTER` (поля `ID`, `AUTHOR_ID`, `AUTHOR_NAME`, `POST_DATE`; документированы префиксы `!` и `>`).
  - Ответ: массив объектов `ID`, `AUTHOR_ID`, `AUTHOR_NAME`, `AUTHOR_EMAIL`, `POST_DATE`, `POST_MESSAGE`, `POST_MESSAGE_HTML`, `ATTACHED_OBJECTS`.
  - В примере SDK сказано, что метод отдаёт одну страницу, максимум 50 записей.
  - По документации сортировка по умолчанию — по убыванию ID.
- `task.commentitem.get`, `task.commentitem.add/update/delete` — не развиваются; `delete` не работает в новой карточке задач. В этом change не используются.
- `tasks.task.history.list` описан как история изменений, «включая комментарии». Как источник текста комментариев не проверялся и в change не используется.

Границы: документация не описывает, как метод ведёт себя на задачах, переведённых на новый чат, и не гарантирует его работу на всех редакциях.

## Проверка на тестовом портале (18.11.2026)

Портал SDK playground, вебхук из `.env.local` основного checkout. Выполнялись только чтения, портал не менялся. Домен, токены и содержимое вложений в evidence не сохранены.

| Вызов | Результат |
| --- | --- |
| `tasks.task.list` | Задача 2 (создана 12.10.2024): `commentsCount=4`, `forumTopicId=2` |
| `tasks.task.get` taskId=2 | `chatId=9640` |
| `im.dialog.messages.get` `chat9640` | Только два служебных сообщения нового чата; одно — приглашение открыть прежние комментарии кнопкой (текст как в #182) |
| `task.commentitem.getlist` `{TASKID:2}` | Четыре комментария, ID 4, 6, 8, 10, по возрастанию |
| `{TASKID:2, ORDER:{ID:asc}, FILTER:{">ID":6}}` | 8, 10 |
| `{TASKID:2, ORDER:{ID:desc}, FILTER:{"<ID":8}}` | 6, 4 — префикс `<` работает, хотя не документирован |
| `{TASKID:2, ORDER:{ID:asc}, start:2}` | 4, 6, 8, 10 — `start` игнорируется; `next` и `total` в ответе нет |
| `{TASKID:2, FILTER:{">ID":8}}` без `ORDER` | `ERROR_CORE`, `Param #1 (arOrder) ...` — без `ORDER` фильтр не принимается; с `ORDER:{}` работает |
| `{taskId:2}` | Тот же результат, что с `TASKID` |
| `{TASKID:4}` (задача без комментариев) | `result: []` |
| `{TASKID:99999999}` | `ERROR_CORE`, `ACTION_FAILED_TO_BE_PROCESSED` |
| `task.commentitem.get` `{TASKID:2, ITEMID:1}` | `ERROR_CORE`, `ITEM_NOT_FOUND_OR_NOT_ACCESSIBLE` |
| Второй пользователь (`BITRIX24_SECOND_USER_WEBHOOK`) | Те же комментарии; является ли он участником задачи, не проверялось |

Типы полей в ответе: `ID`, `AUTHOR_ID`, `POST_DATE`, `POST_MESSAGE`, `AUTHOR_NAME`, `AUTHOR_EMAIL` — строки; `POST_MESSAGE_HTML` — `null`; `ATTACHED_OBJECTS` — объект, ключ — ID вложения; значение содержит `ATTACHMENT_ID`, `NAME`, `SIZE`, `FILE_ID`, `DOWNLOAD_URL`, `VIEW_URL`. **В `DOWNLOAD_URL` и `VIEW_URL` есть параметр `auth[ap]` с токеном доступа.** У комментариев без вложений ключа `ATTACHED_OBJECTS` нет.

Расхождения с документацией: сортировка по умолчанию на портале — по возрастанию, а не по убыванию; префикс `<` работает, хотя не описан; `FILTER` без `ORDER` отклоняется.

## Не проверено

- Реальный размер страницы: в задаче всего четыре комментария, утверждение о 50 записях не подтверждено.
- Поведение для пользователя без доступа к задаче.
- Портал автора #182 и другие редакции; метод `ERROR_METHOD_NOT_FOUND` на этом портале не воспроизвёлся.
- Формат `POST_MESSAGE` (BBCode) на комментариях с упоминаниями и цитатами.
