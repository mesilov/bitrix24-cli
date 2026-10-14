# Сотрудники и оргструктура

[Обзор CLI](../README.md#cli-команды) · [Подключение](connection.md)


```sh
b24cli users:current --json
b24cli users:find --name "Иванов Иван" --json
b24cli users:find --email "ivan@example.test" --json
b24cli users:colleagues --department-id 7 --json
b24cli users:manager --department-id 7 --json
b24cli --profile work users:manager --source config --json
```

`users:current` определяет пользователя выбранного подключения. Коллеги — активные сотрудники того же текущего отдела, кроме самого пользователя; дочерние отделы не включаются. При нескольких текущих отделах для `colleagues` и org-руководителя обязателен `--department-id`.

`users:manager` по умолчанию получает руководителя по оргструктуре. Если текущий пользователь сам руководит отделом или отдел без руководителя, команда проходит вверх по `PARENT` до первого другого руководителя. `--source config` явно выбирает `default.users.managerId` сохранённого профиля.

Поиск по ФИО сопоставляет все токены без регистра Unicode с `NAME`, `SECOND_NAME`, `LAST_NAME`, независимо от порядка; поиск email использует точный серверный фильтр. Результат всегда список кандидатов с ID, без автоматического назначения в задачи. Поиск и коллеги поддерживают `--limit` (default 50), `--all`, `--max-scan` (default 1000). Лимит вывода не останавливает обход; `--all` сохраняет scan budget. Неполная выборка возвращает exit3 и `complete=false`; полнота относится к сотрудникам, видимым текущему подключению.

Команды используют REST1 и права `user`/`department` выбранного подключения. JSON сохраняет API имена `ID`, `NAME`, `SECOND_NAME`, `LAST_NAME`, `EMAIL`, `UF_DEPARTMENT`; plain TSV выводит эти шесть колонок без заголовка. Доступность email и оргструктуры зависит от user scope.

В профиле можно задать `default.users.observerId`, `managerId` и boolean `autoAddObserver`. Только при `autoAddObserver=true` создание задачи автоматически добавляет наблюдателя, сохраняя уже назначенных. При ошибке после создания CLI сохраняет ID задачи и сообщает partial. Настройка: `connection:add work --observer-id 5 --manager-id 9 --auto-add-observer true`; поля и ограничения описаны в [конфигурации](config.md).

