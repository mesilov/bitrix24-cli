# Design

## Context

Мотивация — в `proposal.md` (раздел Why). Текущее состояние:

- `task:chat:list` в `dev` и `chats:messages:list` в ветке #180 нормализуют сообщение до `id/date/author_id/text/chat_id`; `params`, `files` и `users` теряются.
- Данных о звонках и присутствии в CLI нет.
- `chats:*` из #180 ещё не влит в `dev`; разбор TARGET, период `[from, to)` и обход истории с бюджетом нужно переиспользовать, а не дублировать.

## Goals / Non-Goals

**Goals:**

- Звонок в истории распознаётся без эвристик по тексту — только по компоненту сообщения.
- Оценка участников воспроизводима: таблица правил, версия, доказательства.
- Сбой недокументированного метода звонка не портит историю.

**Non-Goals:**

- Фактический список подключившихся и время каждого участника: API его не отдаёт.
- Запись, расшифровка и анализ содержания звонка.
- Включение записи или ИИ в настройках портала.
- Личные диалоги и звонки вне групповых чатов, проектов и задач.
- Отправка итогов: она остаётся за `chats:send` из #180.

## Decisions

### 1. Распознавание звонка по `params`, а не по тексту

Сообщение считается звонком только при `params.COMPONENT_ID = "CallMessage"`. Событие и ID берутся из `COMPONENT_PARAMS.MESSAGE_TYPE` и `COMPONENT_PARAMS.CALL_ID`. Текст «Звонок завершён (…)» не разбирается: он локализован и может меняться.

Адаптер истории чата начинает сохранять нормализованный `params`. Это меняет только внутреннюю модель: в публичный вывод попадают `kind` и `callRef`, а не сырой `params`.

### 2. Источник данных звонка — `im.call.get`

`im.call.get` с параметром `callId` возвращает поля звонка, состав, блок `ai` и токены подключения. Используются только поля из белого списка (`ID, UUID, TYPE, SCHEME, PROVIDER, STATE, INITIATOR_ID, IS_PUBLIC, PUBLIC_ID, CHAT_ID, START_DATE, END_DATE, LOG_URL, RECORD_AUDIO, AI_ANALYZE`, `users`, `userData`, `ai`). Ответ сразу после получения проходит через белый список, до логирования и обработки ошибок, поэтому `connectionData`, `publicChannels` и `logToken` не попадают никуда.

Альтернатива — ограничиться данными из сообщений START и FINISH. Отклонено: в них нет UUID, состава, флагов записи и точного времени начала, если START вне окна.

Перед реализацией нужно проверить метод по официальному MCP документации Битрикс24 (требование maintainer skill). Если метод там не найден, это фиксируется в `verification.md`, и реализация опирается на проверку портала ниже.

### 3. Обогащение по умолчанию, `--no-call-details` для отключения

Цель CHAT-03 — получить итоги одним вызовом. Обогащение даёт по одному `im.call.get` на звонок, плюс один `im.user.list.get` на окно. Для истории без звонков дополнительных запросов нет.

### 4. Таблица правил вывода участников (`rulesVersion: 1`)

| Правило | Доказательство | Влияние |
| --- | --- | --- |
| `finish-author` | автор сообщения FINISH или `FINISH.INITIATOR_ID` | present, high |
| `call-initiator` | `call.INITIATOR_ID` | present, medium |
| `read-finish-at-end` | `readed_list.message_id ≥ finish.id` и `date − finish.date ≤ 5 s` | present, high |
| `message-during-call` | сообщение пользователя при `start.date ≤ date ≤ finish.date` | present, medium |
| `last-read-before-start` | `readed_list.message_id < start.id` | absent, high |
| `last-activity-before-start` | `last_activity_date < start.date` | absent, high |
| `last-activity-before-finish` | `start.date ≤ last_activity_date < finish.date − 60 s` | left-early, low |

Итоговый вердикт: только правила присутствия → `present` с максимальной уверенностью среди них. Только правила отсутствия → `absent`. Правила обеих групп → `unknown`, `conflict: true`. Ничего не сработало → `unknown`.

Свежесть: `fresh`, если после FINISH прошло меньше часа и после него в чате нет сообщений; иначе `stale`, и правила `read-*` дают не больше `low`.

Изменение порогов или правил повышает `rulesVersion`. Каждое правило покрывается unit-тестом на fixture.

Признаки `status` и `IS_ONLINE` в таблицу не входят: на портале `IS_ONLINE = "Y"` стоял у пользователя, неактивного со вчерашнего дня.

### 5. Структура данных

Новые поля `chats:messages:list --json` (остальное — по контракту #180):

```json
{
  "items": [
    { "id": 5001, "kind": "call", "text": "Начат звонок №1001", "callRef": { "callId": 1001, "event": "start" } }
  ],
  "calls": [
    {
      "callId": 1001,
      "uuid": "00000000-0000-4000-8000-000000000001",
      "details": { "status": "ok", "source": "im.call.get", "reason": null },
      "state": "finished",
      "isPublic": true,
      "startedAt": "2026-11-12T10:02:51+03:00",
      "endedAt": "2026-11-12T10:29:42+03:00",
      "durationSeconds": 1611,
      "durationText": "26 мин 51 сек",
      "initiatorId": 11,
      "finishedById": 22,
      "messages": { "startId": 5001, "finishId": 5003, "duringIds": [5002] },
      "inWindow": { "start": true, "finish": true },
      "recording": {
        "audio": false,
        "aiAnalyze": false,
        "logUrl": null,
        "ai": { "serviceEnabled": true, "settingsEnabled": false, "agreementAccepted": false, "tariffAvailable": false },
        "unavailableReasons": ["ai-settings-disabled", "ai-agreement-not-accepted", "ai-tariff-unavailable"]
      },
      "participants": {
        "eligible": { "source": "im.call.get.users", "note": "chat-members-not-attendees", "users": [ { "userId": 11, "name": "Пользователь 11", "role": "ADMIN" } ] },
        "confirmed": { "available": false, "reason": "api-does-not-expose-attendance", "users": [] },
        "inferred": {
          "rulesVersion": 1,
          "observedAt": "2026-11-12T12:05:00+03:00",
          "evidenceFreshness": "fresh",
          "caveats": ["public-link-guests-invisible"],
          "users": [
            { "userId": 33, "verdict": "present", "confidence": "high", "conflict": false,
              "evidence": [ { "rule": "read-finish-at-end", "messageId": 5003, "at": "2026-11-12T10:29:43+03:00", "deltaSeconds": 1 } ] }
          ],
          "summary": { "present": 6, "absent": 2, "leftEarly": 0, "unknown": 0 }
        }
      }
    }
  ]
}
```

`meta.calls`: `found`, `enriched`, `unavailable`. `durationSeconds` считается по `END_DATE − START_DATE`; `durationText` — исходная строка из сообщения, для сверки.

### 6. Команды

- `chats:messages:list` — разметка и обогащение, флаг `--no-call-details`.
- `chats:calls:list TARGET (--period … | --from … --to …) [--timezone]` — PM-10. Использует тот же обход истории за период и собирает только звонки. В `--plain` — одна строка на звонок: `callId, startedAt, durationSeconds, initiatorId, present, absent, unknown`.
- `chats:calls:show TARGET CALL_ID` — один звонок, CHAT-03/CHAT-05. Проверяет `call.CHAT_ID` против чата из TARGET; при расхождении ошибка привязки.
- `users:presence (TARGET | --user-id ID…)` — PM-11, на `im.user.list.get`. Поля: `id, name, lastActivityDate, absent, observedAt`. `status` и `IS_ONLINE` не выводятся.

## Проверка на портале

12.11.2026, webhook рабочего портала, только чтение. Домен, имена и ID ниже обезличены.

| Метод (REST 1.0) | Payload | Ответ (сокращён) | Вывод |
| --- | --- | --- | --- |
| `im.dialog.messages.get` | `DIALOG_ID=chatC, LIMIT=50` | сообщения звонка с `params.COMPONENT_ID="CallMessage"`, `COMPONENT_PARAMS` `{MESSAGE_TYPE: START, CALL_ID}` и `{MESSAGE_TYPE: FINISH, CALL_ID, INITIATOR_ID, MESSAGE_TEXT}` | подтверждено: звонок распознаётся по `params` |
| `im.call.get` | `callId=K` | `call{ID, UUID, STATE: finished, START_DATE, END_DATE, INITIATOR_ID, IS_PUBLIC: Y, RECORD_AUDIO: false, AI_ANALYZE: false, LOG_URL: null}`, `users[8]`, `userData`, `ai{serviceEnabled: true, settingsEnabled: false, agreementAccepted: false, tariffAvailable: false}`, `connectionData.jwt`, `publicChannels`, `logToken: ""` | подтверждено; метода нет в ответе `methods`; `users` совпадает с составом чата |
| `im.call.get` | `CALL_ID=K` | ошибка `Could not find value for parameter {callId}` | имя параметра — `callId` |
| `call.get` | `callId=K` | `ERROR_METHOD_NOT_FOUND` | такого метода нет |
| `im.dialog.get` | `DIALOG_ID=chatC` | `readed_list[]{user_id, message_id, date}`, `last_message_views{message_id, first_viewers, count_of_viewers}` | подтверждено: 4 пользователя прочитали FINISH через 1 с; 2 пользователя последний раз читали вчерашний звонок |
| `im.user.list.get` | `ID[]=…` | `last_activity_date`, `status: online` у всех, `mobile_last_date: false`, `desktop_last_date: false`, `absent` | `last_activity_date` пригоден; `status` непригоден |
| `user.get` | `FILTER[ID][]=…` | `IS_ONLINE: Y` у всех, `LAST_ACTIVITY_DATE: {}` | `IS_ONLINE` непригоден; дата пуста при scope `user_basic` (предположение) |

Сверка с фактом: по правилам выше из 8 участников чата 6 получили бы `present`, 2 — `absent`. Фактический состав этого звонка не подтверждён; сверка с известным составом входит в live-приёмку.

## Risks / Trade-offs

- [`im.call.get` недокументирован и может измениться] → белый список полей, статус `unavailable` вместо сбоя истории, отдельный интеграционный тест на форму ответа.
- [Доказательства — снимок на момент запроса и перезаписываются] → `observedAt`, `evidenceFreshness`, понижение уверенности для `stale`.
- [Оценку присутствия могут принять за контроль посещаемости] → вердикты названы оценкой, `confirmed` всегда пуст с причиной, справка и каталог JTBD предупреждают, что данные не для дисциплинарных решений.
- [Утечка токенов звонка в лог при ошибке] → белый список применяется до логирования; тест проверяет отсутствие токенов в выводе, логе и ошибке.
- [Лишние запросы на длинной истории со многими звонками] → один `im.call.get` на звонок, `--no-call-details`, общий бюджет запросов указывается в `meta`.

## Migration Plan

Изменение аддитивное: новые поля в JSON, новые команды. Существующие поля `chats:messages:list` не меняются. `task:chat:list` получает только разметку `kind`/`callRef`; обогащение для него — отдельное решение в задачах.

## Open Questions

- Нужно ли обогащение звонков в `task:chat:list` или только в `chats:*`. Не меняет спецификацию `chat-calls`: требование сформулировано для команд чтения истории в целом, решение фиксируется в задаче 3.4.
