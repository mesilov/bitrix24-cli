# Tasks

Постановка ведётся в ветке `feature/208-chat-call-insights` от `dev` `3b5f21f`. По решению пользователя от 12.11.2026 часть, не зависящая от #180, реализуется сразу (задачи с пометкой «task:chat:list» и `--user-id`); задачи `chats:*` и TARGET выполнены после merge #180 в `dev` (`91e90ff`).

## 1. Постановка и трассировка

- [x] 1.1 Создать issue #208 (enhancement, milestone 0.3.0) с разделом `## OpenSpec`, добавить ссылку в proposal; проверить обе стороны через `gh`.
- [x] 1.2 Выполнить сырые REST1-запросы по сценариям issue и сохранить обезличенные результаты в разделе «Проверка на портале» design.md.
- [x] 1.3 Добавить CHAT-03…05, PM-10, PM-11 в `docs/task-jtbd.md` со статусом «план» и запись в CHANGELOG.
- [x] 1.4 Сверить `im.call.get`, `im.user.list.get`, `im.dialog.get` (`readed_list`), `im.dialog.messages.get` по официальному MCP документации Битрикс24 (search → method details); записать найденное и отсутствие `im.call.get` в документации в verification.md и design. Найденный `call.followup.get` внесён в design и spec как источник подтверждённых участников.
- [x] 1.5 После merge #180 синхронизировать ветку с `dev`, сверить каталог команд, адаптер истории и контракт `chats:messages:list`; записать SHA (`dev` `91e90ff`, verification.md).

## 2. Разметка звонков в истории

- [x] 2.1 Сохранять нормализованный `params` в адаптере истории чата задачи (`TaskChatAdapter`); распознавать звонок только по `COMPONENT_ID = CallMessage`; unit-тестами проверить START, FINISH, обычный текст, системное сообщение и сообщение без `params`.
- [x] 2.2 Выводить `kind` и `callRef` в human, plain и JSON `task:chat:list` без изменения существующих полей (plain получает столбцы `kind`, `callId` в конце); formatter-тестом сверить одинаковые ID во всех форматах.
- [x] 2.3 После merge #180 сохранить `params` в `Rest1ImDialogAdapter` и выводить `kind`/`callRef` в `chats:messages:list` тем же `CallMessage::mark`.

## 3. Данные звонка и вывод участников

- [x] 3.1 Добавить порт и REST1-адаптер `im.call.get` (`callId`) с белым списком полей, применяемым до логирования; тестом проверить, что `jwt`, `publicChannels`, `logToken` не попадают в результат, лог и ошибку.
- [x] 3.2 Реализовать таблицу правил `rulesVersion: 1`, таблицу итогового вердикта (включая `left-early` и его конфликты), свежесть и оговорки; unit-тест на каждое правило, на каждую строку таблицы вердикта и на повторяемость результата на одних fixtures.
- [x] 3.3 После merge #180 обогащать звонки в `chats:messages:list` по умолчанию через `CallEnricher` и добавить `--no-call-details`; повторить для неё проверки двух звонков в окне, звонка с началом вне окна и статуса `unavailable` при ошибке `im.call.get` (для `task:chat:list` они выполнены в 3.4).
- [x] 3.4 Решить и зафиксировать в design, нужно ли обогащение в `task:chat:list`: да, по умолчанию с `--no-call-details` (решение 8 design). Проверены два звонка в окне, начало вне окна, `unavailable` при ошибке `im.call.get` без изменения полноты и exit code, звонок другого чата, отсутствие запросов без звонков и с `--no-call-details`.
- [x] 3.5 Добавить необязательный REST3-адаптер `call.followup.get` (scope `call`) для `participants.confirmed`; unit-тестами проверить заполнение `confirmed`, исключение подтверждённых из вывода по правилам и четыре причины недоступности без изменения полноты и exit code истории.

## 4. Новые команды

- [x] 4.1 Добавить `chats:calls:list` (PM-10) поверх обхода истории за период; проверить хронологию, сводку участников, partial и exit 3, plain-строку на звонок.
- [x] 4.2 Добавить `chats:calls:show` (CHAT-03/CHAT-05); проверить ошибку привязки для звонка другого чата и причины отсутствия записи.
- [x] 4.3 Добавить `users:presence` (PM-11) на `im.user.list.get` для списка ID (`--user-id`); проверить неизвестный ID и отсутствие `status`/`IS_ONLINE` в выводе.
- [x] 4.5 После merge #180 добавить выбор участников чата по TARGET в `users:presence` (`im.dialog.users.list`, сверен по MCP; без ботов; `--max-members`).
- [x] 4.4 Объявить маршруты `im.call.get`, `im.user.list.get` (REST 1.0, scope `im`) и необязательный `call.followup.get` (REST 3.0, scope `call`) в route policy и ScopeManifest; unit-тестами проверить scope, что нехватка `call` не блокирует команды, `strict-rest3` до учётных данных и отсутствие записывающих маршрутов.

## 5. Документация и приёмка

- [x] 5.1 Обновить `docs/task-usage.md` (раздел «Чат задачи и созвоны»), `docs/users.md`, README, CLI map, scope docs и справку команд с предупреждением, что оценка присутствия не годится для дисциплинарных решений.
- [x] 5.5 После merge #180 дополнить `docs/chats.md` для `chats:calls:*` и обогащения `chats:messages:list`.
- [x] 5.2 Обновить статусы CHAT-03…05, PM-10, PM-11 в `docs/task-jtbd.md`, ссылки на команды и интеграционные тесты, число команд (71 публичная) после задач `chats:*`.
- [x] 5.3 Запустить затронутые unit-, formatter- и payload-тесты, обязательные offline-проверки и `openspec validate add-chat-call-insights --strict`; сохранить результаты в verification.md.
- [ ] 5.4 Live-приёмка на тестовом чате: звонок с заранее известным составом, сверка `inferred` с фактом, звонок с началом до границы периода, отсутствие токенов в выводе и логе; на портале с Follow-up — сверка `confirmed` с фактом; сохранить обезличенный evidence.

## Workflow follow-up

- Архивировать change после реализации и проверки; обновить путь в issue #208.
