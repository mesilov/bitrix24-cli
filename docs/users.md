# Сотрудники и оргструктура

[Обзор CLI](../README.md#cli-команды) · [Подключение](connection.md)


```sh
b24cli users:current --json
b24cli users:find --name "Иванов Иван" --json
b24cli users:find --email "ivan@example.test" --json
b24cli users:colleagues --department-id 7 --json
b24cli users:manager --department-id 7 --json
b24cli --profile work users:manager --source config --json
b24cli users:presence --user-id 11 --user-id 22,33 --json
b24cli users:presence chat37 --json
```

`users:current` определяет пользователя выбранного подключения. Коллеги — активные сотрудники того же текущего отдела, кроме самого пользователя; дочерние отделы не включаются. При нескольких текущих отделах для `colleagues` и org-руководителя обязателен `--department-id`.

`users:manager` по умолчанию получает руководителя по оргструктуре. Если текущий пользователь сам руководит отделом или отдел без руководителя, команда проходит вверх по `PARENT` до первого другого руководителя. `--source config` явно выбирает `default.users.managerId` сохранённого профиля.

Поиск по ФИО сопоставляет все токены без регистра Unicode с `NAME`, `SECOND_NAME`, `LAST_NAME`, независимо от порядка; поиск email использует точный серверный фильтр. Результат всегда список кандидатов с ID, без автоматического назначения в задачи. Поиск и коллеги поддерживают `--limit` (default 50), `--all`, `--max-scan` (default 1000). Лимит вывода не останавливает обход; `--all` сохраняет scan budget. Неполная выборка возвращает exit3 и `complete=false`; полнота относится к сотрудникам, видимым текущему подключению.

Команды используют REST1 и права `user`/`department` выбранного подключения. JSON сохраняет API имена `ID`, `NAME`, `SECOND_NAME`, `LAST_NAME`, `EMAIL`, `UF_DEPARTMENT`; plain TSV выводит эти шесть колонок без заголовка. Доступность email и оргструктуры зависит от user scope.

`users:presence` показывает по каждому переданному ID время последней активности (`lastActivityDate`), отсутствие (`absent`) и момент наблюдения (`observedAt`). Неизвестный ID остаётся в выводе с `found=false` и перечислен в `meta.notFound`. Команда использует REST 1.0 [`im.user.list.get`](https://apidocs.bitrix24.ru/api-reference/chats/users/im-user-list-get.html) и право `im`. Статус мессенджера и признак онлайна не выводятся: на портале они остаются `online` у давно неактивных пользователей, поэтому присутствие видно только по последней активности. Plain TSV: `id`, `name`, `lastActivityDate`, `absent`. Вместо `--user-id` можно передать TARGET чата (`users:presence chat37`, ссылка портала, `sgN`, `group:N`, `task:N`): адресат проверяется как в `chats:resolve`, участники берутся из [`im.dialog.users.list`](https://apidocs.bitrix24.ru/api-reference/chats/chat-users/im-dialog-users-list.html) без ботов и системных пользователей, в `data.destination` — проверенный чат. `--max-members` (по умолчанию 1000) ограничивает число участников; при превышении результат неполный (exit 3).

В профиле можно задать `default.users.observerId`, `managerId` и boolean `autoAddObserver`. Только при `autoAddObserver=true` создание задачи автоматически добавляет наблюдателя, сохраняя уже назначенных. При ошибке после создания CLI сохраняет ID задачи и сообщает partial. Настройка: `connection:add work --observer-id 5 --manager-id 9 --auto-add-observer true`; поля и ограничения описаны в [конфигурации](config.md).
