# Verification

Ветка `feature/180-unified-chat-access`, runtime на `f5331b2`. Дата: 2026-11-12. Портал — тестовый (SDK playground), подключение основного пользователя (ID 1); второй пользователь тестовых fixtures — ID 16.

## Offline

| Проверка | Результат |
| --- | --- |
| `make unit` | 1139 тестов OK. Сценарии chats: `ChatTargetParserTest`, `ChatsResolveCommandTest`, `MessagePeriodResolverTest`, `ChatsMessagesListCommandTest`, `ChatsFindCommandTest`, `ChatsSendCommandTest`, `TaskChatUnreadWindowTest` |
| `make lint-all` | composer-license-checker, php-cs-fixer, phpstan, rector — без замечаний |
| `make cli-reference-check` | Карта CLI соответствует 68 публичным командам |
| `openspec validate add-unified-chat-access --strict` | valid |
| Трассировка | `proposal.md` → #180; #180 → `openspec/changes/add-unified-chat-access/` |

## CI

Проверки PR #181 запускаются на каждый push; итог по последнему коммиту смотреть в PR. Этот файл не подменяет CI.

## Live: integration cases

`make integration --group chats`: 8 тестов (`ResolveChatCommandTest` ×3, `ListChatMessagesCommandTest`, `FindChatCommandTest` ×2, `SendChatMessageCommandTest`, задача-фикстура удаляется в tearDown). Первый прогон после добавления `chats:send` упал (1 failure, 2 errors; в выводе `INTERNAL_SERVER_ERROR` портала на `im.dialog.get sg234`), два повторных прогона без изменений кода — 8/8.

## Live: сквозная приёмка

Фикстура: задача `b24cli-180-acceptance …` (ID 6134) в тестовой группе `B24CLI_TEST_GROUP_ID` = 234, постановщик — пользователь 1, ответственный — 16; чат задачи 10618. Пользователь 16 добавлен в чат (`im.chat.user.add`, только фикстура) и отправил 60 сообщений m1–m60. Название чата задачи (`b24cli-test-…`) не совпадает с заголовком задачи.

Записи вне фикстуры не выполнялись: для `chat9636` и `sg234` — только чтение и dry-run.

### Адресат

| Ввод | Результат |
| --- | --- |
| `task:6134`, `/workgroups/group/234/tasks/task/view/6134/`, `/company/personal/user/16/tasks/task/view/6134/` | `task:6134` → chat 10618, `entity_type` `TASKS_TASK`, `entity_id` 6134 |
| `/online/?IM_DIALOG=chat9636&IM_MESSAGE=19436` | `chat9636`, `messageId` 19436 отдельно от `chatId` |
| `/workgroups/group/234/` | `sg234` → chat 10130 |

Формы ссылок разбираются по allowlist; примерами из интерфейса портала формы `/online/` не подтверждены (задача 1.1 остаётся открытой).

### CHAT-01: история за период

| Шаг | Результат |
| --- | --- |
| Состояние до чтения (`im.dialog.get chat10618`, пользователь 1) | `counter` 60, `unread_id` 23992, `last_message_id` 24110, `message_count` 62 |
| `chats:messages:list task:6134 --from 2026-11-12T08:45:15+00:00 --to 2026-11-12T09:20:15+00:00 --timezone Europe/Moscow` | `complete` true, `scanned` 62, `matched` 61 (60 фикстурных и системное 23988 о создании задачи), 2 страницы; порядок по времени и ID; маршрут `tasks.task.get` → `im.dialog.get` → `im.dialog.messages.get` ×2 |
| Состояние после чтения | `counter` 60, `unread_id` 23992 — без изменений |
| `im.dialog.messages.get` с `LAST_ID` 24111 и `LIMIT` 50 / 51 / 100 | 50 / 50 / 50 строк: фактический предел — 50 |
| `im.dialog.messages.get` без курсора, `LIMIT` 3 | 23996, 23994, 23992, 23988, 21430 — окно первого непрочитанного, больше `LIMIT`; CLI этот режим не использует |
| `chats:messages:list chat9636 --period yesterday --timezone Europe/Moscow` | [11.11 00:00, 12.11 00:00) +03:00; `complete` true, `scanned` 38, `matched` 0; `counter` 38 / `unread_id` 19372 до и после |
| `chats:messages:list sg234 --period yesterday …` | `complete` true, `scanned` 3, `matched` 1; `counter` 0 до и после |

Саммари агента по выводу CHAT-01 (источник — `data.items`, `meta.complete = true`, оговорка о неполноте не нужна):

- Решения: релиз переносится на 20.11 (23992); бюджет нагрузочного тестирования согласует руководитель проекта (24110).
- Договорённость: чек-лист релиза готовит второй пользователь (23994).
- Открытые вопросы: вопрос о согласовании бюджета (23996) закрыт решением 24110; открытых не осталось.
- Остальные 56 сообщений — обсуждение пунктов без решений; 23988 — системное сообщение (`authorId` 0) о создании задачи.

Если бы `meta.complete` был false, агент обязан был бы сказать, что саммари построено по неполной истории, и назвать `meta.reason`.

### CHAT-02: отправка по названию

| Шаг | Результат |
| --- | --- |
| `chats:find --name '<заголовок задачи>'` | `selection` `unique` → `task:6134`; источники chat/project/task полные (задач просмотрено 147) |
| `chats:send --name … --text … --dry-run` | план `im.message.add` в `chat10618` с текстом; `im.message.add` не вызван; `message_count` 62 до и после |
| `chats:send --name … --text '[b24cli #180] CHAT-02: сегодня меня не будет (приёмка)'` | `messageId` 24112, `confirmed` true, `readback` `verified`, `outcomeUnknown` false; `im.message.add` вызван один раз |
| Сырое чтение `LAST_ID` 24113 | 24112, `chat_id` 10618, автор 1, тот же текст; `message_count` 62 → 63 — ровно одно сообщение |
| Состояние после отправки | `counter` 0, `unread_id` 0: портал сам отмечает чат прочитанным для отправителя при `im.message.add`. Чтение (`chats:messages:list`, `chats:find`) прочитанность не меняло |

### Отказы

| Сценарий | Результат |
| --- | --- |
| Пользователь 16 не в `chat9636`: `chats:messages:list`, `chats:send` | `chat-unavailable` после `im.dialog.get`; записи нет |
| `chats:messages:list chat2147483646` | `chat-unavailable` |
| `--api-policy strict-rest3 chats:send sg234` | `policy-denied`, без API-вызовов |
| `chats:send https://other.invalid/workgroups/group/234/` | `foreign-portal`, без API-вызовов |

Отсутствие scope `im` на живом портале не проверялось (нет ограниченного вебхука в fixtures); покрыто offline-тестами проверки scope.

### Cleanup

`tasks.task.delete` 6134 → `{"task":true}`; `im.dialog.get chat10618` → `ACCESS_ERROR`, `tasks.task.get` 6134 → пусто.

## Границы

- Формы `/online/?IM_DIALOG` и ссылка на сообщение не подтверждены примерами из интерфейса (1.1).
- Граница DST и пустой день проверены offline; live — вчерашний день без перехода времени.
- Dry-run `chats:send --name` проверяет адресат дважды (поиск и повторное `chats:resolve`): лишние чтения, на результат не влияют.
