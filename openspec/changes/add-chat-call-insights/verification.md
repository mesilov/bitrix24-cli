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
