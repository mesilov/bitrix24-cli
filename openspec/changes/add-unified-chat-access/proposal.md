# Proposal

## Why

Пользователь хочет получить саммари вчерашней переписки проекта и сообщить об отсутствии в выбранный по названию чат. CLI сейчас читает и отправляет сообщения только в чат задачи по TASK_ID; URL, общий чат проекта, произвольный чат и календарный период не покрыты.

## What Changes

- Добавить единый адресат чата: URL, явный типизированный ID или однозначное название. Различать задачу, проект, чат и сообщение; общий чат проекта не подменять чатами задач.
- Предоставить предлагаемые `chat:resolve`, `chat:find`, `chat:messages:list`, `chat:send` с human/plain/JSON и ограниченным обходом.
- Читать сообщения за точный интервал либо вчерашний календарный день с IANA timezone, авторами, временем, ID и явной полнотой.
- Отправлять текст с dry-run, подтверждением и ID; сохранять неизвестный исход без повторной записи.
- Зафиксировать CHAT-01 «Получить контекст за период» и CHAT-02 «Сообщить команде». CLI предоставляет данные; саммари составляет вызывающий агент/skill.
- Сохранить существующие task:chat:* и их проверки связи с задачей. Обновить JTBD и правила сопровождения: GitHub только через неинтерактивный `gh`.

## Capabilities

### New Capabilities

- `chat-access`: адресация, поиск, чтение периода и отправка в доступные чаты проекта, задачи и произвольные групповые чаты; JTBD CHAT-01/CHAT-02.

### Modified Capabilities

Нет. Существующие требования task-workflows и task-console-runtime сохраняются; новые chat-команды регистрируются отдельно от подсчёта task-команд.

## Impact

Будущая реализация затронет CommandCatalog/bootstrap, новые Console/Command/Chat и Application/Chat, IM adapters, route policy, scope guard, общий вывод и календарные интервалы. `ChatSupport`, `TaskChatService` и `TaskChatAdapter` — существующие точки переиспользования; нельзя ослаблять task → chat binding.

REST1 методы: im.dialog.messages.get, im.message.add, im.search.chat.list со scope im. Задача сохраняет существующий REST3 lookup; проект адресуется документированным sgGROUP_ID. Точные контракты и ссылки — в design.md. Формы URL требуют fixtures; live-приёмка не выполнена.

В эту поставку входят planning artifacts и документы, без runtime-кода. Вне области: unread/упоминания (#148), встроенный LLM, рассылки, расписание, создание чатов, присоединение к ним и изменение read state.

## GitHub issues

- [#180](https://github.com/mesilov/bitrix24-cli/issues/180) — URL/название → адресат; чтение периода и отправка для трёх контекстов; CHAT-01/CHAT-02. Milestone 0.3.0, label enhancement.
- Смежные: [#148](https://github.com/mesilov/bitrix24-cli/issues/148) — unread; [#178](https://github.com/mesilov/bitrix24-cli/issues/178) — scope docs; [#141](https://github.com/mesilov/bitrix24-cli/issues/141) — даты задач; [#168](https://github.com/mesilov/bitrix24-cli/issues/168) — недельные показатели задач, не саммари чата.
