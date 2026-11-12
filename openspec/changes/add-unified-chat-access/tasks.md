# Tasks

Planning завершён; ниже только будущие implementation/verification tasks. Наличие этих артефактов не означает реализацию команд.

## 1. Разрешение адресата

- [ ] 1.1 Подтвердить предложенные URL-формы и permalink сообщения обезличенными примерами интерфейса (через entity_link уже подтверждены `/workgroups/group/G/` и `/company/personal/user/U/tasks/task/view/T/`; `/online/?IM_DIALOG=` и permalink сообщения — нет); сохранить allowlist/fixtures и проверить таблицу URL → task/group/chat/message, конфликтующие ID и другой портал.
- [x] 1.2 Добавить типизированный resolver и chats:resolve с metadata и task → chat binding; проверить положительные/пустые/denied ответы, отсутствие переходов по URL и сохранение task:chat:*.
- [x] 1.3 Зарегистрировать chats:resolve и его routes/scope/help, добавить command integration case и документацию адресатов; проверить offline policy/strict-rest3 до credentials, регистрацию только `chats:*` для новых команд и самостоятельный чат без task/group ID.

## 2. Поиск чата

- [x] 2.1 Реализовать bounded im.search.chat.list обход и chats:find с native FIND/OFFSET/LIMIT; проверить страницы, next/total, дубликаты, бюджет, no-progress и ошибки доступа offline fixtures.
- [x] 2.0 Решить, как искать по названию чаты проектов и задач (im.search.chat.list их не возвращает): выбраны поиск групп → sgN и поиск задач по заголовку → task:N; design/spec обновлены.
- [x] 2.3 Добавить в chats:find источники проектов и задач с подтверждением адресата; проверить точное совпадение и полноту по каждому источнику, задачу по заголовку, чат удалённой группы, совпадения в разных источниках и запрет записи при неоднозначности; объявить маршруты и scope (sonet_group/task).
- [x] 2.2 Зафиксировать точное сравнение названия и distinct no-match/ambiguous/partial; проверить, что participant-only совпадения не выбирают адресат, добавить help, CLI map и integration case поиска.

## 3. История за период

- [x] 3.1 Добавить календарный yesterday и точные from/to, один now и IANA timezone; проверить [from,to), DST, границы года/месяца и ошибочный ввод в unit cases и документировать варианты.
- [x] 3.2 Добавить chats:messages:list с bounded ID pagination от `LAST_ID = last_message_id + 1`, binding, дедупликацией, порядком и complete/reason; проверить старт при непрочитанных, >50 сообщений (offline; фактический предел LIMIT — в 5.2), exact page/budget boundary, пустой день, conflicting rows, page error и no-progress.
- [x] 3.3 Проверить допустимость timestamp stop на документации/fixtures; при отсутствии доказанной монотонности реализовать исчерпание доступной истории или partial, подтвердив отсутствие пропущенных сообщений тестом немонотонных дат.
- [x] 3.4 Добавить human/plain/JSON, help, CLI map и отдельный integration case истории; проверить наличие адресата, авторов/времени/ID, периода/timezone и сохранение partial при ограничении вывода.

## 4. Отправка

- [ ] 4.1 Добавить chats:send с TARGET или --name, text/file/stdin, IM result ID и dry-run; проверить выбор ровно одного полного exact-title результата, mutual exclusions и отсутствие записи при dry-run/ambiguous/denied.
- [ ] 4.2 Сохранить confirmed/outcomeUnknown без повтора записи; проверить timeout после возможного принятия, ID результата и readback со сверкой chat_id в command integration case; обновить help/CLI map.
- [ ] 4.3 Обновить полные route plans, ScopeManifest и scope docs совместно с областью #178; проверить изоляцию команд, strict-rest3 и native REST1 payload, отсутствие read/join мутаций.

## 5. Сквозная приёмка

- [ ] 5.1 Провести offline проверки затронутых команд и каталога, сохранить результаты и обновить CHAT-01/CHAT-02, число команд и границы JTBD; подтвердить двусторонние ссылки #180 ↔ change и strict validation.
- [ ] 5.2 Выполнить отдельную live-приёмку на тестовом проекте, задаче и произвольном чате: URL/ID, история периода (включая чат >50 сообщений и фактический предел LIMIT im.dialog.messages.get), отправка, права и read state до/после; сохранить evidence и cleanup только собственных fixtures.
- [ ] 5.3 Подтвердить CHAT-01 (саммари агентом с источниками и partial оговоркой) и CHAT-02 (ровно одно сообщение в выбранный чат), отделить offline/CI/live результаты в verification.md.

## Workflow follow-up

- После реализации и verify пройти проектные review/CI gates и merge в dev; планирующий MR сам по себе не закрывает #180.
- Архивировать завершённый change, синхронизировать chat-access в актуальные specs, обновить архивный путь в issue и проверить обе стороны связи.
- Сохранить архив через MR в dev, затем удалить чистый связанный worktree по правилам мейнтейнера.
