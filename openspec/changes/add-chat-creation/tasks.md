# Tasks

## 1. Трассировка и контракт API

- [x] 1.1 Сверить `im.chat.add`, `im.dialog.get`, поля чата и `im.message.add` через официальный MCP документации Битрикс24; сохранить ссылки и границы в proposal/design.
- [x] 1.2 Добавить раздел `## OpenSpec` с change `add-chat-creation` и путём в описание issue #185; проверить milestone `0.3.0` и label `enhancement`.

## 2. Реализация

- [x] 2.1 Порт `ChatCreateGateway` и адаптер `Rest1ChatCreateAdapter` (`im.chat.add`, один вызов, `outcomeUnknown` без ID, `ACCESS_ERROR`/`ACCESS_DENIED` → `chat-create-denied`).
- [x] 2.2 Вынести просмотр источника групповых чатов в публичный метод `ChatFindService` без изменения поведения `chats:find`.
- [x] 2.3 `CreateChatRequest`, `ChatInputMapper::create`: обязательный `--title` (2…255 символов после обрезки), `--type CHAT|OPEN`, `--user-id` (повтор и запятые, до 500), `--description`, `--allow-duplicate-title` и `--max-scan` взаимоисключающие.
- [x] 2.4 `ChatCreateService`: маршруты, проверка дубля (`chat-exists`, `search-incomplete`), план dry-run, запись, проверка названия и участников (`verified`, `title-mismatch`, `members-missing`, `failed`).
- [x] 2.5 Команда `chats:add`, регистрация в `CommandCatalog` (команда и маршруты), `ScopeManifest` (`im.chat.add` → `im`), `config/services.php`, профиль `chat-created` в `ResultPresenter`.

## 3. Тесты

- [x] 3.1 Unit: успешное создание и проверка, payload `im.chat.add` с точными именами полей, dry-run без записи, дубль, неполный поиск, `--allow-duplicate-title`, потеря ответа без повтора, недостающий участник, несовпадение названия, ошибки ввода, `strict-rest3`.
- [x] 3.2 Integration: `tests/Integration/Chats/CreateChatCommandTest.php` — dry-run, создание закрытого чата, повторный запуск → `chat-exists`, запись в созданный `chatN` через SDK (live-harness запрещает вызывать другую CLI-команду, поэтому `chats:send` не вызывается).
- [x] 3.3 Обновить контрактные тесты каталога и покрытия integration-тестами при необходимости.

## 4. Документация

- [x] 4.1 `docs/chats.md` (раздел «Создание чата», граница каналов), `docs/cli-command-map.md`, `docs/connection-scopes.md`, README.
- [x] 4.2 `docs/task-jtbd.md`: CHAT-06 со статусом и тестом, счётчики, снимок.
- [x] 4.3 `CHANGELOG.md` → `Unreleased` со ссылкой на #185.

## 5. Проверка

- [x] 5.1 Unit-тесты, линтеры (php-cs-fixer, phpstan, rector), `openspec validate add-chat-creation --strict`.
- [x] 5.2 Integration-тест `chats:add` на тестовом портале либо явная фиксация, почему он не выполнен.
- [x] 5.3 `verification.md` с результатами и границами проверки.
