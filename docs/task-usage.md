# Работа с задачами

[Обзор CLI](../README.md#cli-команды) · [Полная карта команд](cli-command-map.md) · [JTBD по ролям](task-jtbd.md)

## Справка и сведения о приложении


```sh
b24cli --help
b24cli task:add --help
b24cli help task:add
b24cli --about
b24cli --version
```

Общий `--help` (или `-h`) показывает назначение b24cli, список публичных команд,
глобальные опции и примеры создания, просмотра и поиска задач. Первым идёт единый
блок «Общие» с `completion`, `help` и `list`, затем — пространства имён команд
в алфавитном порядке. Команды внутри каждого блока также упорядочены по имени.
Вся справка написана на русском, включая заголовки, описания аргументов и опций,
значения по умолчанию и пояснения встроенных команд. Имена команд, флагов,
API/JSON-полей и значений параметров сохраняют точное написание. Справка выбранной
команды содержит её аргументы, опции, ограничения и примеры; `list --help`
описывает команду списка. Без аргументов b24cli выводит список команд.

`b24cli help task:add --format=md` выводит справку в Markdown; доступны также
`txt`, `xml` и `json`. Человеческие описания во всех форматах — на русском.
`b24cli completion bash` (также `fish` или `zsh`) выводит скрипт автодополнения
для выбранной оболочки. Установку смотрите в `b24cli completion --help`.

`--about` показывает назначение приложения, фактическую версию сборки, автора,
лицензию и ссылки на репозитории приложения и скилла. Версия совпадает с
`--version`. Справка и сведения о приложении работают без подключения к порталу.

[Агентский скилл b24cli](https://github.com/mesilov/bitrix24-cli-skill) помогает
AI-агенту выполнять операции через публичные команды приложения.

## Недельный отчёт по проекту

`b24cli task:report --group-id 234` показывает закрытые/новые за прошлую неделю
и текущие задачи на контроле/в работе по всему выбранному проекту.
`--responsible ID` сужает все показатели до исполнителя, `--week` выбирает неделю,
`--timezone` задаёт IANA timezone. [Периоды, форматы и partial](task-weekly-report.md).

## Календарное время в статусах

```sh
b24cli task:status:durations 123 --json
b24cli task:status:durations 123 --as-of 2026-10-01T12:00:00+03:00 --json
```

Каждый повторный вход в статус создаёт отдельный интервал. Суббота и воскресенье
исключены по серверному offset `created`, который указан в `meta.calendar.utcOffset`.
Остальные часы будней, включая ночи и завершённый статус, учитываются полностью;
даты и offsets сохраняются без перевода в пользовательскую timezone.
`asOf` фиксируется до чтения API;
`--as-of` требует ISO 8601 с часовым поясом, не в будущем.

`totals.<status>` содержит `seconds` (целое), `hours` и `days` (до двух знаков);
один день равен 24 часам. Все единицы округляются независимо из точной суммы,
а не из округлённых интервалов. `precision` отсутствует. JSON-числа не требуют
завершающих нулей. Интервалы также содержат эти три единицы.

```json
{"completed":{"seconds":49,"hours":0.01,"days":0}}
```

Точные суммы требуют полной, непротиворечивой истории с доказанными началом
и последним состоянием. При неполноте или неоднозначности команда возвращает
`complete=false`, причины, exit 3 и `null` для сумм/времени в статусе
(«неизвестно» в human/plain). `elapsedBetweenEventsSeconds` — только разница
наблюдаемых меток, без гарантии непрерывного пребывания в статусе.
Два REST3 list чтения подтверждают `id/created` текущей задачи; строго более ранние
события сохраняются в `excludedHistoryEvidence` и исключаются из расчёта. Начало —
`created`, даже если NEW записан позже. Положительные STATUS 2/3/4/5 подтверждены
readback; мета-коды и неизвестные значения остаются partial. Полнота API-коллекции определяется обходом `next` до его отсутствия; `total`
не обязателен. Противоречащие счётчики, ошибки/лимиты, гонки или неизвестные
статусы сохраняют null totals даже после отделения старых NEW/DELETE.

Команда читает REST1 историю и REST3 состояние; `strict-rest3` запрещает
маршрут до подключения. Трудозатраты смотрите через `task:time:show`.
На проверенном портале подтверждены точный расчёт с повторными открытиями
и честный partial-result при конкурентном изменении.

## Файл к задаче

```sh
b24cli task:file:attach 123 --file './Документы/Отчёт.pdf' --folder-id 456
b24cli task:file:attach 123 --file './report.pdf' --folder-id 456 --name 'Отчёт.pdf' --dry-run --json
b24cli task:file:attach 123 --file-id 789
```

`--file` загружает **один** читаемый обычный локальный файл в явно выбранную папку
Диска и прикрепляет его к задаче. Лимит CLI — **500 MB (500000000 байт)**;
явный `maxUploadBytes` в runtime JSON `--config` задаёт другой лимит в байтах и имеет приоритет, в том числе выше 500 MB.
stdin (`-`), URLs, каталоги и специальные файлы не принимаются. `--name` задаёт
имя, иначе используется basename пути: UTF-8, 1–255 символов, без разделителей
пути и управляющих символов. При совпадении имени создаётся файл с уникальным
именем; существующий файл не перезаписывается. Этот режим нельзя смешивать
с повторяемым `--file-id`, а `--folder-id` и `--name` используются только с `--file`.

Нужны разрешения webhook **disk/tasks** и доступ к папке/задаче. Загрузка
использует REST1 `disk.folder.uploadFile`, привязка — REST3 `tasks.task.file.attach`;
`strict-rest3` запрещает локальный режим до обращения к порталу. `--dry-run`
показывает план без credentials/API и содержимого файла. Существующий Disk ID
через `--file-id` требует только attach REST3.

Операция состоит из двух записей. Если загрузка подтверждена, а привязка
отклонена, CLI возвращает exit3 и Disk ID загруженного файла: после проверки
цели/прав используйте `--file-id`, не загружая файл снова. При `outcomeUnknown`
сначала проверьте папку Диска или привязку на портале. Автоматического повтора
и удаления загруженного файла нет; Ctrl-C возвращает 130 с известными шагами.
ACK привязки подтверждает метод, самостоятельный membership readback не заявляется.

Справка по параметрам и примерам: `b24cli help task:show`.

## Справка и ошибки ввода

`b24cli task:find --help` и `b24cli help task:find` показывают назначение,
обычные примеры, затем параметры, defaults и advanced input/API ограничения.
Help, список команд, version и completion работают без подключения.

Опечатка `task:find --titel договор` возвращает `usage-error` и код 2 с подсказкой
`--title` и корректным примером. Отсутствующее значение или обязательный аргумент
называются явно; конфликтующие параметры требуют выбрать один способ ввода.
Ошибочный ввод не выполняет API-запросов. Подсказки — только текст и не запускают
команды автоматически; значения аргументов, webhook/token и stack trace не отражаются.

## Карточки и форматы вывода

```sh
b24cli task:show 123
b24cli task:show 123 --select id --select title --select status --select responsible.id --select responsible.name --select group.id --select group.name --select deadline --select description
b24cli task:list --group-id 42 --limit 25
```

Карточка показывает название/ID, статус, ответственного, проект, срок и описание,
затем дополнительные выбранные поля. Имена показываются вместе с ID только из
полученных данных; при отсутствии имени выводится ID. Renderer не запрашивает
дополнительные данные. `--select` задаёт полный набор выбранных полей; для карточки
с именами явно выбирайте также ID relations. Длинный текст переносится по ширине
терминала (при неизвестной ширине — 80 колонок), управляющие символы отображаются
без исполнения. Узкий терминал использует вертикальный список, широкий — таблицу.

Пример карточки на условных данных:

```text
123 · Подготовить договор
Статус: В работе (in_progress)
Ответственный: Анна Иванова (#42)
Проект: #7
Срок: 2026-10-15T18:00:00+06:00

Описание
Проверить реквизиты.
```

`task:list` и `task:find` показывают область доступных задач, число показанных,
найденных и просмотренных задач, `complete`, `limitApplied` и фактический порядок.
Полный пустой результат означает отсутствие совпадений в этой доступной выборке.
Полный обход с ограниченным выводом возвращает 0; неполный обход — 3 с причиной и
следующим действием. `--all` снимает только лимит вывода; при scan cap увеличьте
`--max-scan`. Неполный обход `task:list` не доказывает отсутствие подходящих задач,
неполный поиск `task:find` — отсутствие дублей; ни один из них не показывает полный размер проекта.

`--json` выводит один объект `data/meta/error` с `meta.schemaVersion=1` и без ANSI.
`--plain` выводит TSV без заголовка: карточка — `field/value`, список —
`id/title/responsibleId/groupId/status/deadline`, поиск — `id/title`.
Tabs/newlines экранируются; сводка полноты plain отправляется в stderr.
`--quiet` подавляет обычный вывод и сводки, сохраняя существенные ошибки в stderr;
`--silent` подавляет весь вывод. Коды завершения сохраняются при обоих флагах.

Группа/проект задаётся положительным целым ID:

```sh
b24cli task:add --title "Договор" --creator 1 --responsible 2 --group-id 42
b24cli task:update 123 --group-id 42
b24cli task:list --group-id 42 --json
b24cli task:find --title "договор" --group-id 42 --json
```

JSON и REST 3.0 используют `groupId`; для advanced input используйте `--fields '{"groupId":42}'`,
для JSON-фильтра списка — `task:list --where '{"groupId":42}'`.
Флаг и JSON-поля нельзя смешивать. В `task:list` и `task:find` без новых временных флагов
при политике по умолчанию `task-v3` группа, ответственный, постановщик и статус (`--group-id`, `--responsible`,
`--role responsible|creator`, `--status`, `where` по `groupId`/`responsibleId`/`creatorId`/`status`) передаются в серверный
фильтр REST 1.0 `tasks.task.list` (`GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY`, `REAL_STATUS`). Поэтому небольшой проект на большом портале просматривается
целиком за несколько запросов. Статус `declined` не задокументирован для фильтра `REAL_STATUS`
и проверяется локально; остальные статусы сервер сужает, а CLI дополнительно сверяет каждую строку.
`--due-before`, заголовок и роли участников проверяются локально по строкам этой выборки. При `--api-policy strict-rest3`, без серверных условий или если
`--select`, `--order` либо `--where` содержит поле, которого нет в REST 1.0 (например,
`--order responsibleId:asc`), остаётся REST 3.0 с локальной фильтрацией. Фактический маршрут,
серверные условия и причину показывает `meta.listRoute`. Локальный обход REST 3.0 без `--id`
пишет в stderr строку с причиной и бюджетом `--max-scan`, затем прогресс каждые 1000 задач;
stdout и JSON-конверт не меняются, `--quiet`/`--silent` подавляют эти строки. На маршруте REST 1.0 `deadline` приходит
в часовом поясе пользователя, а не сервера: момент времени тот же, отличается смещение. `--max-scan` ограничивает число
просмотренных строк выбранного маршрута, `--limit` — число результатов. `task:find` совмещает `--group-id` с поиском
по заголовку без учёта регистра; без флага ищет во всех доступных группах.
При создании задачи выбранный профиль может задавать `default.task.groupId`;
явный `--group-id` или advanced JSON имеет приоритет над этим значением.

Свои задачи можно фильтровать по роли владельца выбранного вебхука:

```sh
b24cli task:list --role responsible --json  # делаю: исполнитель
b24cli task:list --role accomplice --json   # помогаю: соисполнитель
b24cli task:list --role creator --json      # поручил: постановщик
b24cli task:list --role auditor --json      # наблюдаю: наблюдатель
b24cli task:find --title "договор" --group-id 42 --role accomplice --json
b24cli task:list --role creator --where '{"responsibleId":9}' --json
```

Роль сочетается с остальными допустимыми критериями через AND. Сама по себе она не ограничивает
статус: «делаю» включает и завершённые задачи, «поручил» включает задачи, поставленные
себе. Группы могут пересекаться. ID берётся из выбранного подключения, а не из
`default.task`; JSON содержит `meta.roleFilter` с `role` и `userId`.
Без временных флагов `responsible` и `creator` при `task-v3` фильтруются на сервере через
REST 1.0 (`RESPONSIBLE_ID`/`CREATED_BY`), а при `strict-rest3` проверяются локально по REST 3.0.
`accomplice` и `auditor` добавляют REST 1.0 чтение ID участников каждой подходящей задачи и
недоступны при `--api-policy strict-rest3`. Для ограничения обхода используйте `--group-id`,
для списка также `--id`; `--all` не снимает `--max-scan`.

## Календарные окна дедлайна, создания и изменения

Для выбора следующей работы используйте `task:my` с явным исполнителем:

```bash
b24cli task:my --user 42 --open --json
b24cli task:my --user 42 --open --due-before 2026-10-20T00:00:00+06:00 --max-scan 20000 --json
```

`--user` — обязательный положительный ID исполнителя (`responsibleId`). Он
не меняет пользователя и права подключения: доступны только видимые ему задачи.
Без `--open` возвращаются все состояния; с флагом — `pending`, `in_progress`,
`supposedly_completed`, `deferred`. `--due-before` требует ISO8601 с часовым поясом
и оставляет дедлайн строго раньше границы; задачи без дедлайна исключаются.
Все фильтры сочетаются через AND. Порядок `id DESC`, лимит вывода 50, бюджет
обхода по умолчанию 10000. Неполный обход даёт exit 3 и `meta.complete=false`;
`--max-scan` увеличивает бюджет. Human/JSON/plain соответствуют списку задач.
Команда использует чтение REST 3.0 и поддерживает `strict-rest3`. Очерёдность и
приоритет работы пользователь определяет сам; это отдельная операция EMP-01.

`task:list` и `task:find` принимают `--deadline-window`, `--created-window`,
`--changed-window` (каждый один раз), `--overdue` и `--timezone IANA`. Мнемоники
общие: `today`, `tomorrow`, `yesterday`, `day-before-yesterday`, `this-week`,
`this-month`, `this-quarter`. `--created-window` выбирает по дате создания задачи,
`--changed-window` — по дате последнего изменения, `--deadline-window` — по дедлайну.

```sh
b24cli task:list --group-id 42 --deadline-window today --timezone Asia/Bishkek --json
b24cli task:list --group-id 42 --overdue --deadline-window this-week --timezone Asia/Bishkek --json
b24cli task:list --overdue --timezone Asia/Bishkek --json
b24cli task:find --title "отчёт" --group-id 42 --deadline-window this-month --overdue --timezone Asia/Bishkek --json
b24cli task:list --group-id 42 --created-window this-quarter --timezone Asia/Bishkek --json  # задачи группы за квартал
b24cli task:list --role responsible --status in_progress --changed-window this-week --json
```

Окна календарные и полуоткрытые `[from, to)`: неделя начинается в понедельник,
месяц и квартал охватываются целиком. `this-week` отличается от следующих семи дней.
Границы учитывают DST и календарь. По умолчанию timezone берётся из `TZ`, затем из
PHP timezone окружения; CLI не угадывает пояс портала. Для запросов «сегодня» от
skill передавайте известный IANA timezone пользователя. Один `now` фиксируется
на всю команду с точностью до секунды, включая все страницы.

`--overdue` означает непустой дедлайн строго раньше `now`, исключает `completed`
и `declined`, включает `deferred` и `supposedly_completed`. Этот lifecycle фильтр
проверяется локально, а границы дат уходят в API. Без `--overdue` окно включает
все статусы. «Должны были закрыть вчера» — `--deadline-window yesterday --overdue`;
это не выборка по фактической дате закрытия. `deadline=null` не совпадает.
`tomorrow --overdue` возвращает полный пустой результат без сетевых запросов.

Временная выборка использует явный `tasks.task.list` REST 1.0: `>=DEADLINE`/`<DEADLINE`,
`>=CREATED_DATE`/`<CREATED_DATE`, `>=CHANGED_DATE`/`<CHANGED_DATE` и те же серверные
условия, что без временных флагов: `GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY` (в том числе
из `--role responsible|creator`) и `REAL_STATUS`. Это выбор маршрута до вызовов;
`--api-policy strict-rest3` возвращает `policy-denied`/exit4 без fallback.
Окна и остальные критерии — AND; один `--timezone` и один `now` действуют для всех окон.
Дата создания или изменения каждой строки дополнительно сверяется локально по моменту
времени; пустая или нераспознаваемая дата даёт `missing-predicate-field` и exit 3.
Поля `created`/`changed`, нужные только для проверки, в результат не попадают, если их нет в `--select`.
Не смешивайте временные флаги с `--due-before`, `--where`/`--where-file`;
неверные/повторные окна и неизвестный timezone дают usage-error/exit2 до API.

REST1 поддерживает общий набор `--select`: id, title, description, creatorId,
responsibleId, groupId, deadline, status, created, started, changed, closed,
activity, startPlan, endPlan, estimatedTime, priority, mark, allowsChangeDeadline,
allowsTimeTracking. Для `--order` доступны поля этого набора кроме description,
creatorId/responsibleId. Неподдерживаемые поля возвращают usage-error до API;
CLI не отправляет придуманные имена и не делает скрытые запросы другой версии.

JSON `meta.deadlineFilter` содержит window/overdue/timezone, now,
fromInclusive/toExclusive, route, deadlineApplied/overdueStatusApplied/groupApplied.
`meta.createdFilter` и `meta.changedFilter` содержат window, timezone, now,
fromInclusive/toExclusive, route и `applied: "server"`; `meta.listRoute` показывает
все серверные условия временного маршрута.
Форматы выдачи, `--limit`, `--all`, `--max-scan`, visible-task scope и partial/exit3
сохраняются. Metadata отличает полный обход от ограниченного вывода; права
подключения ограничивают видимость задач. Границы/маршрут доступны в JSON,
при обычном выводе использование REST1 явно сообщается в stderr.

Список и поиск обходят задачи от новых к старым (`id DESC`) и сохраняют этот
порядок в результатах. Обе команды принимают повторяемый `--order FIELD:asc|desc`;
без явно заданного `id` к пользовательской сортировке добавляется `id ASC`.

```sh
b24cli task:find --title "договор" --group-id 42 --max-scan 20000 --json
b24cli task:find --title "договор" --order id:asc --json
b24cli task:list --order title:asc --order id:desc --json
```

`--max-scan` по умолчанию равен 10000; `--limit` по умолчанию равен 50 и
ограничивает только вывод после обхода. `--all` снимает только лимит вывода.
REST 3.0 поддерживает серверную фильтрацию задач по `id`; группа, заголовок и
остальные критерии проверяются локально, поэтому даже поиск по группе может
просматривать много страниц. Подробнее: [контракт REST 3.0](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-list-rest-v3.html).

В JSON `meta.order` показывает фактический порядок, `meta.scanned` — число
просмотренных задач. `meta.complete=false`, `meta.reason=scan-budget-exhausted`
и код завершения 3 означают неполный результат; `meta.hint` предлагает увеличить
`--max-scan`. Пустой результат при неполном обходе **не доказывает отсутствие
дубля**. При ошибке страницы или недостающих данных причина также указывается
явно. Только полный пустой результат имеет `meta.complete=true` и код 0.

## Поиск с несколькими критериями

```sh
b24cli task:find --title "договор" --group-id 7 --responsible 42
b24cli task:find --title "договор" --status in_progress --order id:desc --limit 25
b24cli task:find --title "договор" --id 123 --id 124 --due-before 2026-10-15T18:00:00+06:00 --json
b24cli task:find --title "договор" --where '{"groupId":7,"responsibleId":42}' --all --json
b24cli task:find --title "договор" --where-file filters.json --role responsible --all --json
```

`--title` обязателен: буквальная подстрока заголовка без регистра Unicode,
без regex/wildcards и поиска по описанию. Все явно заданные критерии и роль
пересекаются через AND на каждой просмотренной странице. `--id` можно повторять;
он задаёт серверную выборку и scope `explicit-task-ids`. Остальные predicates
локальные; результат поиска содержит только `id/title`, включая 0/1/many совпадений.

`--where` и `--where-file` — отдельный режим равенств JSON object с ключами
`id`, `title`, `creatorId`, `responsibleId`, `groupId`, `status`, `deadline`.
Их нельзя смешивать друг с другом или с `--responsible`, `--group-id`, `--status`,
`--due-before`; можно сочетать с `--title`, `--id`, `--role`, `--order` и лимитами.
`--where-file=-` читает stdin явно; одновременный `--config=-` отклоняется.
`--project` не поддерживается; идентификатор группы задаётся через `--group-id`.

Роль проверяется относительно пользователя выбранного подключения. Без роли
определение пользователя и чтение участников не добавляются. `responsible/creator`
сохраняют REST3; `accomplice/auditor` используют явно выбранное REST1 чтение состава
роли после остальных критериев и запрещены при `strict-rest3` (код 4 до API).
При ошибке этого чтения сохраняются подтверждённые совпадения и partial/код 3.
Default порядок — `id DESC`, лимит вывода — 50, scan budget — 10000;
`--all` не отменяет scan budget и не обещает меньшую стоимость обхода.

## Теги задач

Повторяемая опция `--tag` задаёт полный набор названий. Имена сохраняют кириллицу,
пробелы и `#`; одинаковые названия передаются один раз. В `task:update` отсутствие
опции сохраняет существующие теги, а `--clear-tags` явно очищает весь набор.

```sh
b24cli task:add --title "Плановая работа" --creator 1 --responsible 2 --tag '#плановая' --tag 'Этап 1'
b24cli task:update 123 --tag '#ошибка'
b24cli task:update 123 --clear-tags
b24cli task:show 123 --select tags.id --select tags.name --json
b24cli task:add --title "Плановая работа" --creator 1 --responsible 2 --tag '#плановая' --dry-run --json
```

Обычные поля записываются через REST 3.0, теги — отдельным `tasks.task.update`
REST 1.0 с полем `TAGS`. Обновление только тегов выполняет один REST 1.0 запрос.
`--api-policy strict-rest3` отклоняет команду с тегами до создания или изменения
задачи. `--tag` и `--clear-tags` нельзя смешивать друг с другом или с
`--fields`/`--fields-file`. REST 3.0 `tags` остаётся полем чтения;
advanced-запись `tags`/`TAGS` не поддерживается.

Создание с тегами и смешанное обновление выполняются в два шага. Dry-run показывает
обе версии API и зависимость `taskIdFromStep` для ещё не созданной задачи.
Если первый шаг подтверждён, а запись тегов не завершилась, CLI возвращает код 3
с известным `TASK_ID`, подтверждёнными шагами и `complete=false`. `outcomeUnknown`
показывает потерю подтверждения отправленного запроса; прерывание возвращает 130.
Проверьте теги через `task:show` и повторите только `task:update TASK_ID --tag ...`
или `--clear-tags`. CLI не повторяет создание и не удаляет задачу автоматически;
составная операция не атомарна.

## Чат задачи и созвоны

```bash
b24cli task:chat:list 123 --limit 50 --json
b24cli task:chat:list 123 --all --no-call-details --plain
```

Каждое сообщение получает `kind`: `text`, `system` или `call`. Сообщение о звонке распознаётся только по компоненту `CallMessage` в `params`, текст не разбирается; у него есть `callRef` с `callId` и событием `start` или `finish`. Plain TSV: `id`, `createdDate`, `authorId`, `text`, `kind`, `callId`.

Если в окне есть сообщения о звонке, по умолчанию `data.calls` содержит по записи на звонок: UUID, начало, конец, `durationSeconds`, инициатора, завершившего, ID сообщений во время звонка, признаки `inWindow` и состояние записи с причинами её отсутствия (`ai-settings-disabled` и другие). Участники разделены на три группы:

- `eligible` — состав чата из данных звонка, а не список подключившихся;
- `confirmed` — фактические участники с `talkedSeconds` из AI Follow-up (`call.followup.get`); без Follow-up группа пуста с причиной (`followup-method-unavailable`, `followup-scope-missing`, `followup-not-found`, `followup-access-denied`);
- `inferred` — оценка по правилам `rulesVersion: 1`: вердикт `present`, `absent`, `left-early` или `unknown`, уверенность и доказательства (прочтения, сообщения во время звонка, последняя активность). Указаны `observedAt` и свежесть доказательств; для старых звонков правила прочтения дают не больше `low`.

Оценка не является фактом присутствия и не годится для дисциплинарных решений. Гости по публичной ссылке не видны. Статус мессенджера и признак онлайна не используются. Данные звонка берутся из `im.call.get`, которого нет в документации Битрикс24: при его отказе запись звонка получает `details.status = unavailable`, время берётся из сообщений, а история и код выхода не меняются. Токены подключения к звонку не выводятся и не логируются. В human и plain сводка по звонкам выводится в stderr. `--no-call-details` отключает дополнительные запросы; без сообщений о звонке их нет.

## Работа с результатами задачи

Сотрудник фиксирует итог работы, читает его и исправляет текст по ID результата:

```sh
b24cli task:result:add 123 --text "Договор готов: https://example.org/report"
b24cli task:result:add 123 --text-file result.txt --json
b24cli task:result:list 123 --all --json
b24cli task:result:update 123 --result 17 --text "Договор проверен"
b24cli task:result:delete 123 --result 17 --dry-run --json
b24cli task:result:delete 123 --result 17 --force
```

`--text-file -` читает текст из stdin. `task:result:list` возвращает отдельные
результаты с текстом, автором, датами, статусом `open`/`closed`, `fileIds`,
`rights` и `messageId`, если они доступны. `--limit` ограничивает вывод (50 по
умолчанию), `--all` снимает этот лимит, `--max-scan` ограничивает обход
(10000 по умолчанию). При неполном обходе JSON содержит `meta.complete=false`
и причину, команда возвращает код 3.

Изменение и удаление сначала проверяют принадлежность результата задаче.
`--dry-run` показывает план без записи; `--force` подтверждает удаление в
автоматизации. Все четыре команды используют REST 3.0 и доступны с
`--api-policy strict-rest3` в пределах прав подключения.

| Роль / JTBD | Когда и цель | Действие | Признак успеха |
| --- | --- | --- | --- |
| Руководитель проекта / PM-07 | Исполнитель предъявил работу; нужны данные для проверки | `task:result:list TASK_ID` | Видны текст, автор и ID каждого результата выбранной задачи |
| Аккаунт менеджер / AM-07 | Команда подготовила материалы для клиента | `task:result:list TASK_ID` | Получен сохранённый итог работы для подготовки передачи |
| Сотрудник / EMP-08 | Нужно зафиксировать итог или исправить ошибочный результат | `task:result:add/list/update/delete` | Операция подтверждена для результата выбранной задачи |

Результат хранится как отдельная сущность Bitrix24. Эти команды сами не
меняют статус задачи и не фиксируют решение о приёмке или передачу клиенту.


## Жизненный цикл и списки по статусу

```sh
b24cli task:start 123
b24cli task:pause 123
b24cli task:defer 123
b24cli task:renew 123
b24cli task:complete 123 --dry-run --json
b24cli task:complete 123 --json
b24cli task:approve 123
b24cli task:disapprove 123

# 42 — ваш ID; 99 — другой исполнитель; 7 — проект
b24cli task:list --responsible 42 --status in_progress --all --json
b24cli task:list --group-id 7 --status supposedly_completed --all --json
b24cli task:list --responsible 99 --status deferred --all --json
b24cli task:list --group-id 7 --responsible 99 --status pending --all --json
```

Фильтр `--status` принимает одно точное логическое значение: `pending`,
`in_progress`, `supposedly_completed`, `completed`, `deferred`, `declined`.
Все условия исполнителя/проекта/статуса объединяются через AND. При `task-v3` статус
кроме `declined` уходит в серверный фильтр `REAL_STATUS`. Свои задачи
выбираются вашим явным ID; автоматического `--me` нет. Числовые коды и опечатки
в `--status` или `--where.status` отклоняются. `--all` снимает лимит вывода,
но сохраняет `--max-scan` (по умолчанию 10000) и ограничения доступа подключения.

Lifecycle команды используют явный REST 1.0 маршрут; `strict-rest3` запрещает их
до запросов, включая dry-run. Проверяются текущее состояние, право действия и
обязательный native result. `--dry-run` показывает условия и шаги без записи,
включая возможность контроля и серверного завершения подзадач. Обычные переходы
не требуют дополнительного подтверждения. Заметка или файл не заменяют native
result при `requireResult=true`; CLI не создаёт его автоматически.

После действия выводится фактический REST3 `status`. `supposedly_completed`
означает ожидание контроля; `completed` — завершение. Постановщик может завершить
задачу сразу, поэтому статус не вычисляется только из `needsControl` или команды.
JSON содержит `data.operation` с `taskId/action/confirmed` и `data.task` с
`id/status/needsControl/requireResult`. Plain TSV: `taskId`, `action`, `confirmed`,
`status`, `needsControl`, `requireResult`. `meta.complete` означает полноту
выполнения и чтения, а не завершение задачи. При подтверждённой записи и ошибке
последующего чтения возвращается partial/exit3; при неизвестном исходе отправленной
записи — `outcomeUnknown=true`. Запись не повторяется; состояние можно сверить
через `task:show`. Изменение raw `status` через `task:update` запрещено.

Если список не возвращает groupId для фильтра проекта, CLI проверяет group.id
карточки подходящей по остальным условиям задачи. Эти REST3 чтения отражаются
в meta.apiCalls и ограничиваются числом просмотренных задач; непроверенная
принадлежность даёт partial вместо недостоверной пустой выборки.


## Пользовательские поля задач

`--user-fields JSON` или `--user-fields-file PATH|-` принимает непустой JSON-объект с точными custom `UF_*` кодами. Доступны строки, числа, логические значения и плоские списки поддержанных значений; `null`, вложенные объекты и системные `UF_CRM_TASK`, `UF_TASK_WEBDAV_FILES`, `UF_MAIL_MESSAGE` отклоняются. Пустая строка или список явно передаются для очистки; обязательные поля очищать может запретить портал. Пропущенные поля сохраняются.

```bash
b24cli task:add --title "План работ" --creator 1 --responsible 2 \
  --group-id 42 --user-fields '{"UF_QUERY":"5"}'
b24cli task:update 123 --user-fields '{"UF_QUERY":"6"}'
b24cli task:update 123 --user-fields-file fields.json --dry-run --json
b24cli task:show 123 --select userFields.key --select userFields.value --json
```

Поля должны существовать на выбранном портале; `UF_QUERY` — пример кода. CLI читает `tasks.task.getFields` через REST1, проверяет доступные имена и типы. Обязательность и множественность проверяет портал: их metadata может быть неполной.

Создание с custom значениями выполняет **один REST1 tasks.task.add**, включая ordinary и custom значения сразу. Basic-флаги и значения `default.task` собирают native REST1 payload: TITLE, DESCRIPTION, CREATED_BY, RESPONSIBLE_ID, GROUP_ID, DEADLINE, ALLOW_TIME_TRACKING. Поля профиля сохраняют свою описанную структуру; явные значения выше default, учёт времени без default выключен.

Advanced JSON при custom создании использует **точные REST1 имена**; он не смешивается с basic-флагами. CLI не переименовывает camelCase JSON:

```bash
b24cli task:add \
  --fields '{"TITLE":"План работ","CREATED_BY":1,"RESPONSIBLE_ID":2,"ALLOW_TIME_TRACKING":"N"}' \
  --user-fields '{"UF_QUERY":"5"}' --dry-run --json
```

Без custom input ordinary создание/обновление и `--fields` используют REST3 camelCase поля и JSON booleans. Их имена и типы проверяются локально по контракту add/update; `editable=false` из field.list не служит универсальным запретом записи. Ordinary advanced dry-run работает без API.

Custom-only update выполняет один REST1 update. Смешанный update сначала меняет ordinary поля через REST3, затем custom через REST1, затем явно заданные теги. При частичном результате CLI сохраняет TASK_ID и подтверждённые шаги; проверьте значения существующей задачи перед повторением `task:update`. Создание и записи автоматически не повторяются и не откатываются.

Custom dry-run читает схему и показывает версии API и native поля без записи. `strict-rest3` отклоняет весь custom план до credentials/API. Теги и default observer сохраняют свои отдельные объявленные шаги; ошибка прекращает последующие записи.

## Шаблоны задач

```sh
b24cli task:template:show 139 --json
b24cli task:template:fields:list --json
b24cli task:template:add --title "Типовая задача" --creator 1 --responsible 42 --dry-run
b24cli task:template:update 139 --description "Новый порядок проверки" --dry-run
b24cli task:template:delete 139 --dry-run
```

Шаблоны используют REST 1.0 и scope `task` (также принимается `tasks` по общему
контракту проверки scope). `--api-policy strict-rest3` запрещает все пять команд,
включая dry-run. ID берётся из портала либо ответа создания; список и поиск
шаблонов, создание задачи по шаблону, изменение чек-листов и расписаний повторения
в CLI пока не предоставляются. Методы и права проверены по
[официальной документации](https://apidocs.bitrix24.ru/api-reference/tasks/template/index.html),
доступность на конкретном портале зависит от его настроек и прав подключения.

Создание требует `TITLE`, `CREATED_BY`, `RESPONSIBLE_ID`. Флаги `--title`,
`--description`, `--creator`, `--responsible`, `--group-id` соответствуют нативным
`TITLE`, `DESCRIPTION`, `CREATED_BY`, `RESPONSIBLE_ID`, `GROUP_ID`. Значения профиля
`default.task` не применяются к шаблонам. Update отправляет только явные поля;
`--description=""` очищает описание.

Можно передать `--fields JSON` или `--fields-file PATH` (либо `-` для stdin):

```sh
b24cli task:template:add --fields '{"TITLE":"Проверка договора","CREATED_BY":1,"RESPONSIBLE_ID":42,"GROUP_ID":7,"TASK_CONTROL":"Y"}' --dry-run --json
b24cli task:template:update 139 --fields-file template-fields.json --dry-run
```

Набор полей записи: `TITLE`, `DESCRIPTION`, `CREATED_BY`, `RESPONSIBLE_ID`,
`GROUP_ID`, `PARENT_ID`, `ACCOMPLICES`, `AUDITORS`, `ALLOW_CHANGE_DEADLINE`,
`ALLOW_TIME_TRACKING`, `TASK_CONTROL`, `ADD_IN_REPORT`. Нативные имена сохраняются.
ID в JSON — целые числа; `GROUP_ID` и `PARENT_ID` допускают 0, остальные ID положительны.
`ACCOMPLICES`/`AUDITORS` — списки положительных целых чисел, `[]` очищает список.
Переключатели принимают строки `Y`/`N`. Алиасы REST3 (`creatorId` и другие),
неизвестные поля, дубли JSON-ключей и одного поля из JSON/флагов отклоняются до API.
`PRIORITY`, относительные сроки, повторения, `UF_*` и другие поля не входят в набор
записи; `fields:list` позволяет прочитать metadata портала без обещания поддержки записи.
Значения прочитанного шаблона сохраняются, включая сериализованные сервером строки.

Add/update dry-run полностью offline. Delete dry-run читает выбранный шаблон,
показывает название и план, не выполняет запись. Настоящее удаление в automation
требует `--force`, в терминале — подтверждение. Удаление может вернуть `false`
и при успехе, и при отсутствии подтверждения, поэтому CLI возвращает exit 3,
`operation.confirmed=false`, `meta.complete=false`, `meta.outcomeUnknown=true`.
Повторное чтение записывается в `operation.visibleAfter`: true — шаблон ещё виден,
false — больше не виден, null — чтение не удалось. Отсутствие доступа также даёт
false; это не доказательство удаления. Проверьте портал и права перед повтором.

JSON содержит `data.template`, `data.fields` либо `data.operation` с `templateId`,
`action`, `changedFields`, `confirmed`. Plain show/fields выводит отсортированные
`field`, `value`; изменения — `templateId`, `action`, `changedFields`, `confirmed`.
Только удаление добавляет столбец `visibleAfter` в human/plain: `true`, `false` или
`unknown`, если проверочное чтение не удалось (в JSON это `null`).
Tab/newline/backslash экранируются. План содержит `templateId`, а `taskId=null`.
