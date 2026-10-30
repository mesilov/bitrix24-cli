# Tasks

Planning завершён; ниже только будущие implementation/verification tasks. Наличие этих артефактов не означает реализацию команд.

## 1. Разрешение адресата

- [ ] 1.1 Подтвердить предложенные URL-формы и permalink сообщения обезличенными примерами интерфейса; сохранить allowlist/fixtures и проверить таблицу URL → task/group/chat/message, конфликтующие ID и другой портал.
- [ ] 1.2 Добавить типизированный resolver и chat:resolve с metadata и task → chat binding; проверить положительные/пустые/denied ответы, отсутствие переходов по URL и сохранение task:chat:*.
- [ ] 1.3 Зарегистрировать chat:resolve и его routes/scope/help, добавить command integration case и документацию адресатов; проверить offline policy/strict-rest3 до credentials и каталог команд.

## 2. Поиск чата

- [ ] 2.1 Реализовать bounded im.search.chat.list обход и chat:find с native FIND/OFFSET/LIMIT; проверить страницы, next/total, дубликаты, бюджет, no-progress и ошибки доступа offline fixtures.
- [ ] 2.2 Зафиксировать точное сравнение названия и distinct no-match/ambiguous/partial; проверить, что participant-only совпадения не выбирают адресат, добавить help, CLI map и integration case поиска.

## 3. История за период

- [ ] 3.1 Добавить календарный yesterday и точные from/to, один now и IANA timezone; проверить [from,to), DST, границы года/месяца и ошибочный ввод в unit cases и документировать варианты.
- [ ] 3.2 Добавить chat:messages:list с bounded ID pagination, binding, дедупликацией, порядком и complete/reason; проверить >50 сообщений, exact page/budget boundary, пустой день, conflicting rows, page error и no-progress.
- [ ] 3.3 Проверить допустимость timestamp stop на документации/fixtures; при отсутствии доказанной монотонности реализовать исчерпание доступной истории или partial, подтвердив отсутствие пропущенных сообщений тестом немонотонных дат.
- [ ] 3.4 Добавить human/plain/JSON, help, CLI map и отдельный integration case истории; проверить наличие адресата, авторов/времени/ID, периода/timezone и сохранение partial при ограничении вывода.

## 4. Отправка

- [ ] 4.1 Добавить chat:send с TARGET или --name, text/file/stdin, IM result ID и dry-run; проверить выбор ровно одного полного exact-title результата, mutual exclusions и отсутствие записи при dry-run/ambiguous/denied.
- [ ] 4.2 Сохранить confirmed/outcomeUnknown без повтора записи; проверить timeout после возможного принятия, ID результата и readback в command integration case; обновить help/CLI map.
- [ ] 4.3 Обновить полные route plans, ScopeManifest и scope docs совместно с областью #178; проверить изоляцию команд, strict-rest3 и native REST1 payload, отсутствие read/join мутаций.

## 5. Сквозная приёмка

- [ ] 5.1 Провести offline проверки затронутых команд и каталога, сохранить результаты и обновить CHAT-01/CHAT-02, число команд и границы JTBD; подтвердить двусторонние ссылки #180 ↔ change и strict validation.
- [ ] 5.2 Выполнить отдельную live-приёмку на тестовом проекте, задаче и произвольном чате: URL/ID, история периода, отправка, права и read state до/после; сохранить evidence и cleanup только собственных fixtures.
- [ ] 5.3 Подтвердить CHAT-01 (саммари агентом с источниками и partial оговоркой) и CHAT-02 (ровно одно сообщение в выбранный чат), отделить offline/CI/live результаты в verification.md.

## Workflow follow-up

- После реализации и verify пройти проектные review/CI gates и merge в dev; планирующий MR сам по себе не закрывает #180.
- Архивировать завершённый change, синхронизировать chat-access в актуальные specs, обновить архивный путь в issue и проверить обе стороны связи.
- Сохранить архив через MR в dev, затем удалить чистый связанный worktree по правилам мейнтейнера.
