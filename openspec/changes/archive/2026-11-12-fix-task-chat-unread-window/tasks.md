# Tasks

## 1. Проверка и исправление

- [x] 1.1 Воспроизвести баг сырыми запросами на собственной фикстуре в тестовой группе и зафиксировать результат в design.md; удалить фикстуру. Проверка: раздел «Проверка на портале», cleanup подтверждён.
- [x] 1.2 Добавить `TaskChatGateway::lastMessageId()` через `im.dialog.get` с проверкой `id` чата и начинать `ChatSupport::scan()` без курсора с `last_message_id + 1`. Проверка: `TaskChatUnreadWindowTest` падает без исправления (4 из 5) и проходит с ним.
- [x] 1.3 Объявить `im.dialog.get` в маршрутах `task:chat:list/update/delete`, `TaskChatService` и `ScopeManifest`; обновить fixtures. Проверка: `CommandContractsTest` (payload с `LAST_ID`), `ScopeValidationCommandTest`, strict-rest3 → `policy-denied` без вызовов.

## 2. Проверки и поставка

- [x] 2.1 `make unit`, `make lint-all`, `make cli-reference-check`; live-проверка исправленной команды на фикстуре до удаления.
- [x] 2.2 CHANGELOG и документация scope.
- [x] 2.3 MR в `dev`, разбор замечаний и CI.
- [x] 2.4 После merge архивировать change, синхронизировать spec и обновить путь в #202.
