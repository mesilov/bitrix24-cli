# Tasks

## 1. Трассировка и контракт API

- [x] 1.1 Сверить `tasks.task.chat.message.send`, `im.dialog.messages.get`, `im.dialog.get` через официальный MCP документации Битрикс24; сохранить ссылки и границы (включая отсутствие документированного формата ссылки на сообщение) в proposal/design.
- [x] 1.2 Назначить issue #223 milestone `0.3.0`; добавить раздел `## OpenSpec` с change `return-task-chat-message-id` и путём в описание issue.

## 2. Реализация

- [ ] 2.1 `TaskChatService::send`: проверка привязки чата до записи, `baseline` через `im.dialog.get` перед записью, единственный вызов `tasks.task.chat.message.send`, readback через сканер с `FIRST_ID = baseline` и budget 200, сопоставление по автору подключения и нормализованному тексту, исходы `verified`/`missing`/`ambiguous`/`failed`/`incomplete`.
- [ ] 2.2 Ответ `task:chat:send`: `operation.messageId/resourceId/chatId/dialogId/readback`, `data.readback`, `meta.complete/reason`, exit 3 без повтора отправки; Ctrl-C пробрасывается.
- [ ] 2.3 `sendRoutes` и план dry-run: `tasks.task.get`, `im.dialog.get`, запись, проверочное `im.dialog.messages.get`; маршруты `task:chat:send` в `CommandCatalog`.
- [ ] 2.4 `task:chat:update`: `operation.chatId` и `operation.messageId`.
- [ ] 2.5 Справка `task:chat:send` без фразы об отсутствии ID: описать `messageId`, `chatId`, `dialogId` и exit 3 при неподтверждённом сопоставлении.

## 3. Тесты

- [ ] 3.1 Unit: единственное совпадение; старое своё и новое чужое сообщение с тем же текстом не выбираются; несколько совпадений → `ambiguous`; ни одного → `missing`; ошибка чтения → `failed`, отправка один раз; непроверенная привязка → exit 4 без записи; dry-run без записи; блокировка без scope `im`; `task:chat:update` с `chatId`/`messageId`.
- [ ] 3.2 Обновить `CommandContractsTest` (payload отправки без изменений, `resourceId` заполнен) и фикстуры подставного портала.
- [ ] 3.3 Integration `SendTaskChatMessageCommandTest`: `messageId` совпадает с ID сообщения, найденного SDK-чтением чата, `chatId` — с чатом задачи, `readback = verified`; многострочный текст.

## 4. Документация

- [ ] 4.1 README и `docs/cli-command-map.md` при необходимости; `docs/task-jtbd.md` — проверить затронутые сценарии.
- [ ] 4.2 `CHANGELOG.md` → `Unreleased` со ссылкой на #223.

## 5. Проверка

- [ ] 5.1 Unit-тесты, линтеры (php-cs-fixer, phpstan, rector), `openspec validate return-task-chat-message-id --strict`.
- [ ] 5.2 Integration-тест `task:chat:send` на тестовом портале либо явная фиксация, почему он не выполнен.
- [ ] 5.3 `verification.md` с результатами и границами проверки.
