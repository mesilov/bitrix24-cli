# Verification

## 1.4 Сверка с официальным MCP документации Битрикс24

Дата: 12.11.2026. Endpoint: `https://mcp-dev.bitrix24.tech/mcp` (сервер `b24-dev-mcp` 0.2.0), подключение по Streamable HTTP: в клиенте этой сессии сервер не был подключён. Учётные данные серверу не передавались. Порядок: `bitrix-search`, затем `bitrix-method-details` / `bitrix-article-details` по точному имени из поиска.

| Запрос | Инструмент | Результат |
| --- | --- | --- |
| `im.call.get` | `bitrix-search` | метода нет среди 8 результатов (telephony, crm.calllist, voximplant, `BX24.im.callTo`) |
| `получить данные звонка по идентификатору callId` | `bitrix-search` | `im.call.get` нет; найден `call.followup.get` |
| `im.call.get` | `bitrix-method-details` | `Method 'im.call.get' not found.` |
| `call.followup` | `bitrix-search` | `call.followup.get`, `call.followup.list`, `call.followup.field.list`, `call.followup.field.get`, статьи «Поля Follow-up звонков», «Follow-up звонков в REST 3.0: обзор методов» |
| `call.followup.get` | `bitrix-method-details` | REST 3.0, scope `call`; параметры `callId` (обязателен), `select`, `mentionFormat`; `result.item` с `callId, chatId, initiatorId, startDate, endDate, durationSeconds, uuid, participants, outcomes, …`; ошибки `INSUFFICIENTSCOPEEXCEPTION`, `access_denied`, `ENTITYNOTFOUNDEXCEPTION` («Звонок не найден»), `invalid_select_field`. [Источник](https://apidocs.bitrix24.ru/api-reference/telephony/follow-up/call-followup-get.html) |
| `call.followup.list` | `bitrix-method-details` | обязательный `filter`: `startDate`, `participantId`; пример `participants[]{userId, name, avatar, talkedSeconds}`; курсор `afterCursor`. [Источник](https://apidocs.bitrix24.ru/api-reference/telephony/follow-up/call-followup-list.html) |
| Follow-up звонков в REST 3.0: обзор методов | `bitrix-article-details` | `participants` — участники звонка; Follow-up доступен участникам звонка и связанного чата, администратору — все; scope не расширяет видимость. [Источник](https://apidocs.bitrix24.ru/api-reference/telephony/follow-up/index.html) |
| `im.dialog.messages.get` | `bitrix-method-details` | scope `im`; `DIALOG_ID`, `LAST_ID`, `FIRST_ID`, `LIMIT` (по умолчанию 20, максимум 50); сообщение содержит `params: Record`. Значения `COMPONENT_ID`/`COMPONENT_PARAMS` не описаны. [Источник](https://apidocs.bitrix24.ru/api-reference/chats/messages/im-dialog-messages-get.html) |
| `im.dialog.get` | `bitrix-method-details` | scope `im`; `DIALOG_ID` в форматах `chatXXX`, `sgXXX`, ID пользователя; `readed_list[]{user_id, user_name, message_id, date}`. [Источник](https://apidocs.bitrix24.ru/api-reference/chats/im-dialog-get.html) |
| `im.user.list.get` | `bitrix-method-details` | scope `im`; `ID` массивом или JSON-строкой; поля `status, idle, last_activity_date, mobile_last_date, desktop_last_date, absent`; для экстранет-пользователя — только пользователи его экстранет-групп. [Источник](https://apidocs.bitrix24.ru/api-reference/chats/users/im-user-list-get.html) |

Структура `ParticipantDto` отдельной статьёй через MCP не получена: `bitrix-article-details` по подсказке «Поля Follow-up звонков» вернул обзорную статью. Поля участника взяты из примера `call.followup.list`; полный состав сверяется в задаче 3.5 через `call.followup.field.list` / `call.followup.field.get` на портале с Follow-up.

### Проверка Follow-up на портале

Тот же webhook, только чтение, REST 3.0: `call.followup.get` (`callId` звонка из проверки) и `call.followup.list` (фильтр `startDate` за 12 дней) вернули `BITRIX_REST_V3_EXCEPTION_METHODNOTFOUNDEXCEPTION`. На этом портале методов Follow-up нет; live-сверка `confirmed` требует другого портала.

### Выводы для дизайна

- `im.call.get` — недокументированный необязательный источник метаданных; контракт держится на проверке портала и интеграционном тесте формы ответа.
- `call.followup.get` — документированный источник фактических участников для звонков с AI Follow-up; используется для `participants.confirmed`.
- `params` сообщений, `readed_list` и `last_activity_date`, на которых держатся правила вывода, документированы; значения компонента звонка — нет.

## Реализация до #180 (12.11.2026)

Объём: задачи 2.1, 2.2, 3.1, 3.2, 3.4, 3.5, 4.3, 4.4, 5.1–5.3. Задачи `chats:*` и TARGET (2.3, 3.3, 4.1, 4.2, 4.5, 5.5) ждут merge #180; live-приёмка 5.4 не проводилась.

| Проверка | Результат |
| --- | --- |
| `make unit` | OK, 1057 тестов, 17921 assertion |
| `ChatCallInferenceTest` | каждое из 7 правил, строки таблицы вердикта, stale, повторяемость, распознавание START/FINISH/текста/системного/без `params` |
| `TaskChatCallDetailsTest` | обогащение и вердикты на fixtures формы ответов портала; токены `jwt`, подписи каналов и `logToken` отсутствуют в JSON, plain, human и stderr; Follow-up и 4 причины отказа; `unavailable` при отказе `im.call.get` без изменения полноты и exit code; звонок другого чата; два звонка и начало вне окна; `--no-call-details` и история без звонков без лишних запросов; plain-столбцы; `strict-rest3`; необязательный scope `call`; `users:presence` |
| `make lint-all` | license checker, php-cs-fixer, phpstan, rector — без замечаний |
| `make cli-reference-check` | карта соответствует 65 публичным командам |
| `openspec validate add-chat-call-insights --strict` | valid |
| Интеграционный тест `users:presence` на рабочем портале (только чтение) | OK, 1 тест, 16 assertions |

Границы:

- Отсутствие токенов в логе проверено косвенно: белый список применяется в адаптере до возврата результата, ошибки `im.call.get` не содержат тела ответа, SDK пишет в debug-лог параметры запроса, но не тело ответа (проверено по исходникам SDK). Отдельного теста на файл лога нет, потому что fake-транспорт unit-тестов обходит SDK.
- `call.followup.get` на рабочем портале отсутствует, поэтому заполнение `confirmed` проверено только на fixtures по примеру из официальной документации.
- Обогащение `task:chat:list` не проверялось на реальном чате задачи со звонком.

## После merge #180 (12.11.2026)

Ветка синхронизирована с `dev` `91e90ff` (#180 влит и архивирован). Конфликты: `ResultPresenter`, `SdkApiTransport` (оба добавляли `apiErrorCode`; оставлен общий помощник, код в верхнем регистре, как в `dev`), `config/services.php`, `ContainerRegistrationTest`, `connection-scopes.md`, карта команд. После merge: `make unit` — OK, 1189 тестов; `make lint-all` — без замечаний; карта — 69 публичных команд.

`chats:messages:list` получил `kind`/`callRef`, `data.calls` и `--no-call-details` через те же `CallMessage` и `CallEnricher`.

### Live: `chats:messages:list` на рабочем чате со звонком (только чтение)

Окно 40 минут вокруг звонка, проверка после его завершения более чем через час. Домен, имена и ID обезличены.

| Проверка | Результат |
| --- | --- |
| Код выхода, полнота | 0, `complete=true` |
| Разметка | 6 сообщений: 2 `call` (START и FINISH), 4 `text` |
| `meta.calls` | `found=1, enriched=1, unavailable=0, confirmed=0` |
| Запись звонка | `state=finished`, `isPublic=true`, `durationSeconds=1611` совпадает с текстом «26 мин 51 сек», `inWindow` start и finish, 4 сообщения во время звонка |
| Запись и ИИ | `audio=false`, `aiAnalyze=false`, причины `ai-settings-disabled`, `ai-agreement-not-accepted`, `ai-tariff-unavailable` |
| Участники | `eligible` 8 из `im.call.get.users`; `confirmed` недоступны (`followup-method-unavailable`); `inferred` — 6 `present` (finish-author high; call-initiator с прочтением medium; message-during-call medium; read-finish-at-end low ×3), 2 `unknown` без доказательств; `evidenceFreshness=stale`, оговорка `public-link-guests-invisible` |
| Секреты | в выводе нет `jwt`, `publicChannels`, `logToken`, `connectionData`, подписей и JWT-префикса |
| Маршруты | после обхода истории: `im.dialog.get`, `im.call.get`, `call.followup.get`, `im.user.list.get` |

Отличие от ручной оценки утром: тогда 2 участника получили бы `absent` по `last-read-before-start`; к моменту live-проверки они прочитали более поздние сообщения, и доказательство перезаписалось, поэтому `unknown`. Это ожидаемое поведение для `stale`. Сверка с заранее известным составом (5.4) не проводилась: фактический состав звонка неизвестен.

## `chats:calls:*` и `users:presence TARGET` (12.11.2026)

MCP документации Битрикс24: `bitrix-method-details im.dialog.users.list` — scope `im`; `DIALOG_ID`, `SKIP_EXTERNAL` (Y — без системных пользователей), `LIMIT` (по умолчанию 50, максимум 200), `OFFSET`, `LAST_ID`; ответ — массив участников с `id`, `bot`, `last_activity_date`, `absent`, `total`, `next`; ошибка `ACCESS_ERROR`. [Источник](https://apidocs.bitrix24.ru/api-reference/chats/chat-users/im-dialog-users-list.html).

| Проверка | Результат |
| --- | --- |
| `make unit` | OK, 1192 теста |
| `make lint-all`, `make cli-reference-check` | без замечаний; 71 публичная команда |
| Unit `TaskChatCallDetailsTest` | `chats:calls:list` (хронология, plain-строка на звонок, exit 3 при бюджете), `chats:calls:show` (окно звонка, без повторного `im.call.get`, ошибка привязки без чтения истории и без данных звонка, `call-unavailable`), `users:presence TARGET` (без ботов, параметры страницы, взаимоисключение с `--user-id`), `strict-rest3` для пяти команд |
| Live, интеграционные тесты (только чтение, `B24CLI_TEST_CALL_CHAT`/`B24CLI_TEST_CALL_ID` рабочего чата) | `ListChatCallsCommandTest::testKnownCallIsListedWithoutConnectionSecrets`, `ShowChatCallCommandTest::testHappyPathKnownCallMatchesCallData`, `UserPresenceCommandTest` — OK, 3 теста, 37 assertions |
| Live `users:presence` по чату | 8 участников из `im.dialog.users.list` (`total` 8), у всех есть `lastActivityDate`, признака онлайна в выводе нет; маршруты `im.dialog.get`, `im.dialog.users.list`, `im.user.list.get` |

Не запускались на рабочем портале сценарии, создающие задачи (`ListChatCallsCommandTest::testHappyPathTaskChatWithoutCallsIsCompleteAndEmpty`, `ShowChatCallCommandTest::testCallOfAnotherChatIsRejected`): запись допускается только в тестовой группе. Live-приёмка 5.4 со сверкой оценки с заранее известным составом звонка не проводилась.
