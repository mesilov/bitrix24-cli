# Changelog

## [Unreleased]

### Добавлено

- **Созвоны в истории чатов:** `task:chat:list` и `chats:messages:list` размечают сообщения (`kind`: `text`, `system`, `call`; `callRef` у сообщений о звонке) и по умолчанию добавляют `data.calls`: UUID, начало, конец, длительность, инициатор, завершивший, состояние записи с причинами её отсутствия, состав чата, фактические участники из AI Follow-up (`call.followup.get`, необязательный scope `call`) и оценку присутствия по правилам `rulesVersion: 1` с доказательствами. Токены подключения к звонку не выводятся. `--no-call-details` отключает дополнительные запросы; plain получает столбцы `kind`, `callId`. Новые `chats:calls:list` (звонки чата за период) и `chats:calls:show` (один звонок с проверкой привязки к чату). [#208](https://github.com/mesilov/bitrix24-cli/issues/208)
- **`users:presence`:** последняя активность, отсутствие и момент наблюдения по списку `--user-id` или участникам чата из TARGET через `im.user.list.get`; статус мессенджера и признак онлайна не используются, неизвестные ID видны в `meta.notFound`. [#208](https://github.com/mesilov/bitrix24-cli/issues/208)
- **Дата записи времени:** `task:time:add` и `task:time:update` принимают `--created-date` (ISO 8601 с часовым поясом) и передают его в `CREATED_DATE` записи REST 1.0 `task.elapseditem.*`. Время можно внести задним числом или наперёд и исправить ошибочную дату; без флага поведение прежнее. Неверная дата отклоняется до запросов к порталу, который иначе молча записал бы текущий момент. [#179](https://github.com/mesilov/bitrix24-cli/issues/179)
- JTBD CHAT-03…05, PM-10, PM-11 и OpenSpec change `add-chat-call-insights` со сверкой по официальной документации и live-проверкой на портале. [#208](https://github.com/mesilov/bitrix24-cli/issues/208)

### Исправлено

- `task:list` и `task:find` при `task-v3` передают `--status` и `where.status` в серверный фильтр REST 1.0 `REAL_STATUS`; выборка по статусу больше не обходит до 10 000 задач портала. `declined` не задокументирован для этого фильтра и проверяется локально. Временные флаги теперь передают на сервер не только группу, но и исполнителя, постановщика (в том числе из `--role responsible|creator`) и статус; `meta.listRoute` появился и на временном маршруте. Локальный обход REST 3.0 без `--id` пишет в stderr причину, бюджет `--max-scan` и прогресс каждые 1000 задач; `--quiet`/`--silent` подавляют эти строки, stdout и JSON не меняются. [#186](https://github.com/mesilov/bitrix24-cli/issues/186)

- `task:chat:list` без `--before`/`--after` больше не теряет новые сообщения, когда в чате задачи есть непрочитанные: обход начинается с последнего сообщения (`im.dialog.get` → `last_message_id`), а не с окна первого непрочитанного. Та же проверка в `task:chat:update`/`task:chat:delete` теперь находит новые сообщения. Прочитанность не меняется. [#202](https://github.com/mesilov/bitrix24-cli/issues/202)

- `task:list` и `task:find` без временных флагов передают `--group-id`, `--responsible`, `--role responsible|creator` и одноимённые поля `--where` в серверный фильтр REST 1.0 `tasks.task.list`. Выборка небольшого проекта на портале с десятками тысяч задач стала полной и быстрой, вместо `scan-budget-exhausted` после 10 000 задач. `meta.listRoute` показывает маршрут и серверные условия; `--api-policy strict-rest3` сохраняет прежнюю локальную фильтрацию REST 3.0. Подсказка неполного обхода `task:list` больше не упоминает дубли. [#183](https://github.com/mesilov/bitrix24-cli/issues/183)

- Архивирован OpenSpec change серверных фильтров `task:list`/`task:find`; требования синхронизированы в основные спецификации `cli-experience`, `task-console-runtime` и `task-role-filtering`. [#183](https://github.com/mesilov/bitrix24-cli/issues/183)

- Устранены 8 security advisories `composer audit`: `symfony/filesystem` 8.1.11 (GHSA-6prc-h956-hxjq), `symfony/http-client` 8.1.11 (CVE-2026-121971, CVE-2026-123119), `symfony/http-foundation` 8.1.11 (CVE-2026-118514 и три GHSA), `symfony/mime` 8.1.10 (CVE-2026-45070). Обновлён только `composer.lock`; ограничения `composer.json` и поведение CLI не менялись. [#196](https://github.com/mesilov/bitrix24-cli/issues/196)

- Архивирован OpenSpec change установки Bash и zsh на Linux packaging runner: сохранены результаты трёхплатформенной intermediate-сборки run 43092552553 (`builds` 201e643), прохождение `test_documented_shell_sequences` на Linux и проверка SHA256SUMS скачанных артефактов. [#177](https://github.com/mesilov/bitrix24-cli/issues/177)

- Linux native packaging явно устанавливает Bash и zsh перед приёмкой документированных shell-последовательностей и проверяет их наличие; обязательные проверки `shell-installer-check.py` не ослаблены. Устранён блокер трёхплатформенной intermediate-сборки. [#177](https://github.com/mesilov/bitrix24-cli/issues/177)

- Архивирован OpenSpec change исправления native connection fixture: сохранены причина общего сбоя packaging, ограниченный scope/list контракт и результаты проверки PHAR; повторная native-сборка и live-приёмка отслеживаются отдельно. [#174](https://github.com/mesilov/bitrix24-cli/issues/174)

- Native connection acceptance учитывает проверку scope: fixture обслуживает ограниченную readonly последовательность `scope` → `tasks.task.list`, а smoke проверяет свежий и кэшированный scope для profiles/env/process credentials. Исправлен общий блокер intermediate macOS/Windows/Linux packaging. [#174](https://github.com/mesilov/bitrix24-cli/issues/174)

- Синхронизирована основная спецификация EMP-09 (scope проекта и восстановление статуса); OpenSpec change архивирован с итогами 18 live-проверок, 4 integration tests и cleanup. [#170](https://github.com/mesilov/bitrix24-cli/issues/170)

- `task:handover` проверяет фактический scope `sonet_group` для чтения участников проекта; устранена ложная блокировка EMP-09 с разрешённым API. Серверный сброс статуса в `pending` после назначения обрабатывается штатным достижением цели или восстановлением исходного статуса с проверками прав/result/control. Обновлены help, JTBD и регрессионные контракты. [#170](https://github.com/mesilov/bitrix24-cli/issues/170)

- Установщик Linux/macOS повторяет временные HTTP-ошибки, таймауты и отказы соединения до трёх раз с ограничением времени повторов. При окончательном сбое команды из README и руководства установки не подключают env и не запускают CLI. [#151](https://github.com/mesilov/bitrix24-cli/issues/151)

### Добавлено

- **Отправка в чат (CHAT-02):** `chats:send TARGET|--name NAME --text|--text-file` отправляет одно сообщение через `im.message.add`. По названию запись разрешена только при единственном точном совпадении в полном поиске; иначе `chat-not-found`, `chat-ambiguous` с кандидатами или `search-incomplete`, и ничего не отправляется. `--dry-run` проверяет адресат без записи. Потерянный ответ даёт `outcomeUnknown` без повтора; созданное сообщение читается по ID и сверяется с чатом. [#180](https://github.com/mesilov/bitrix24-cli/issues/180)

- **Поиск чата по названию:** `chats:find --name NAME` ищет групповые чаты (`im.search.chat.list`), проекты (`sonet_group.get` → `sgN`) и задачи по заголовку (→ `task:N`), потому что поиск чатов не возвращает чаты проектов и задач. Совпадения только по участнику скрыты; точные совпадения проекта и задачи подтверждаются как в `chats:resolve`. `data.selection` различает `unique`, `none`, `ambiguous`, `unavailable` и `incomplete` (exit 3); неполный источник не превращается в «не найдено». [#180](https://github.com/mesilov/bitrix24-cli/issues/180)

- **История чата за период (CHAT-01):** `chats:messages:list TARGET --period yesterday --timezone ZONE` или `--from/--to` возвращает сообщения интервала [from, to) с авторами, временем и ID. Обход идёт от последнего сообщения, а не от окна непрочитанных, и не меняет прочитанность. Порядок дат по ID не предполагается: полнота подтверждается только началом истории, иначе exit 3 с причиной (`message-budget-exhausted`, `page-error`, `output-limit` и др.). [#180](https://github.com/mesilov/bitrix24-cli/issues/180)

- **Адресат чата:** `chats:resolve TARGET` проверяет общий чат проекта (`sgN`, `group:N`), чат задачи (`task:N`) и групповой чат (`chatN`) по типизированному ID или ссылке портала. Ссылка разбирается локально; ссылка другого портала, голое число и личный диалог отклоняются до запросов. Недоступный чат возвращает `chat-unavailable`, а не пустой результат; strict-rest3 отклоняет REST 1.0 маршрут. [#180](https://github.com/mesilov/bitrix24-cli/issues/180)

- Подготовлен OpenSpec `add-unified-chat-access` с самостоятельным namespace `chats:*`: вход по URL/названию, история периода и отправка в чат проекта, задачи и произвольный чат. Зафиксированы планируемые CHAT-01/CHAT-02 и текущее покрытие JTBD; runtime ещё не реализован. В инструкциях проекта закреплена работа с GitHub только через неинтерактивный `gh`. [#180](https://github.com/mesilov/bitrix24-cli/issues/180)

- **Окна по дате создания и изменения:** `task:list` и `task:find` принимают `--created-window` и `--changed-window` с мнемониками `--deadline-window` (`today` … `this-quarter`). Окна идут на сервер REST 1.0 как `CREATED_DATE`/`CHANGED_DATE`, сочетаются между собой и с остальными критериями через AND и показывают границы в `meta.createdFilter`/`meta.changedFilter`. Пример: `b24cli task:list --group-id 42 --created-window this-quarter --json`. При `--api-policy strict-rest3` флаги отклоняются до запросов. [#186](https://github.com/mesilov/bitrix24-cli/issues/186)

- **Файловое логирование:** корневой объект `logging` в `config.json` включает суточный лог JSON Lines (`b24cli-{YYYY}{MM}{DD}.log` в `logs/` рядом с config, хранение 7 дней) при любом способе подключения. Пишутся начало и завершение команды, ошибки и события b24phpsdk с `runId`; выше `debug` о REST-вызовах только сводка, данные на `debug` ограничены, webhook и токены маскируются. Неверный `logging` не ломает команды и профили, `connection:show` показывает статус и путь к файлу. Версии CLI без поддержки `logging` отклоняют такой `config.json`: перед их запуском удалите ключ. [#189](https://github.com/mesilov/bitrix24-cli/issues/189)

- **Скилл grill-me:** в `.agents/skills/` добавлены `grill-me` и обязательный для него `grilling` из mattpocock/skills (MIT, commit `fcf0071`). В `grill-me` добавлен запасной путь для агентов без инструмента `Skill` (Codex): прочитать `../grilling/SKILL.md`; остальное совпадает с upstream. Скилл проводит интервью по плану раундами перед подготовкой OpenSpec change; происхождение и лицензия лежат рядом со скиллами, требования — в `openspec/specs/agent-skills/`. [#190](https://github.com/mesilov/bitrix24-cli/issues/190)

- **Недельный отчёт (PM-09):** `task:report --group-id ID` показывает закрытые и новые за неделю, текущие задачи на контроле и количество в работе. По умолчанию весь проект и прошлая календарная неделя; `--responsible` сужает область. Период учитывает timezone/DST, неполные секции сохраняют строки и observedCount с неизвестным точным count. [#168](https://github.com/mesilov/bitrix24-cli/issues/168)

- **Передача задач проекта:** `task:handover --group-id ID --responsible ID` передаёт все доступные незавершённые задачи текущего пользователя другому участнику; `--from ID` позволяет руководителю передать работу между двумя сотрудниками. Проверяются членство, полная выборка (включая точную границу бюджета) и результат каждого назначения; dry-run и отчёт сохраняют частичные/неизвестные исходы без автоматического повтора. Добавлен EMP-09, уточнён PM-04. [#162](https://github.com/mesilov/bitrix24-cli/issues/162)

- **Шаблоны задач:** `task:template:show`, `task:template:fields:list`, `task:template:add/update/delete` через REST 1.0 с нативными полями, JSON/plain и dry-run. Добавлен JTBD PM-08; неоднозначное подтверждение удаления отражается как partial (exit 3), доступность после запроса видна в JSON/human/plain. [#72](https://github.com/mesilov/bitrix24-cli/issues/72)

- **Проверка прав вебхука:** требования выбранной команды сверяются с разрешениями подключения; отдельный защищённый `state.json` хранит снимок на сутки. Смена credentials/манифеста обновляет проверку, нехватка scope блокирует только затронутую команду, а `connection:check` принудительно обновляет права и показывает диагностику в human/plain/JSON. При сбое сохраняется последний успешный снимок и применяется backoff 300 секунд. [#149](https://github.com/mesilov/bitrix24-cli/issues/149)

### Изменено

- Архивирован OpenSpec change `add-unified-chat-access`: спецификация `chat-access` перенесена в `openspec/specs/chat-access/`, материалы приёмки — в `openspec/changes/archive/2026-11-12-add-unified-chat-access/`. [#180](https://github.com/mesilov/bitrix24-cli/issues/180)

- Архивирован OpenSpec change серверных фильтров `task:list` после merge MR #210 в `dev`: требования о серверном `REAL_STATUS`, окнах `--created-window`/`--changed-window` и сообщениях локального обхода в stderr перенесены в `openspec/specs/cli-experience/`, `task-deadline-windows/` и `task-console-runtime/`; change лежит в `openspec/changes/archive/2026-11-12-extend-task-list-server-filters/`. Перевод новых окон в skill задач остаётся открытой задачей 6.4 до выпуска флагов. [#186](https://github.com/mesilov/bitrix24-cli/issues/186)

- Архивирован OpenSpec change исправления окна непрочитанных в `task:chat:list` после merge MR #203 в `dev`: требование «Task chat history starts from the latest message» перенесено в `openspec/specs/task-console-runtime/`, change лежит в `openspec/changes/archive/2026-11-12-fix-task-chat-unread-window/`. [#202](https://github.com/mesilov/bitrix24-cli/issues/202)

- Архивирован OpenSpec change обязательной метки типа при назначении issue в milestone после merge MR #206 в `dev`; delta specs нет, актуальные спецификации не менялись. [#205](https://github.com/mesilov/bitrix24-cli/issues/205)

- **Правила мейнтейнера:** при назначении issue в milestone обязательно ставится метка типа (`bug`, `enhancement` или `documentation`) по анализу содержания issue; без метки milestone не назначается. Проверка метки добавлена в разбор issue и в проверки перед MR. [#205](https://github.com/mesilov/bitrix24-cli/issues/205)

- Архивирован OpenSpec change файлового логирования после merge MR #195 в `dev`: новая спецификация `cli-logging` и требования к `logging` в `connection-configuration` перенесены в `openspec/specs/`, change лежит в `openspec/changes/archive/2026-11-12-add-file-logging/`. [#189](https://github.com/mesilov/bitrix24-cli/issues/189)

- Архивирован OpenSpec change шага проверки сценариев issue сырыми запросами к REST API после merge MR #194 в `dev`; delta specs нет, актуальные спецификации не менялись. [#192](https://github.com/mesilov/bitrix24-cli/issues/192)

- Завершённый OpenSpec change обновления Symfony-пакетов архивирован после merge MR #197 в `dev` и 13 успешных CI checks на проверенном head; `composer audit` без advisories. [#196](https://github.com/mesilov/bitrix24-cli/issues/196)

- **Правила мейнтейнера:** добавлен шаг проверки сценариев issue сырыми запросами к REST API Битрикс24 до фиксации design/spec/tasks — условия релевантности, передача webhook без argv, только чтение по умолчанию, запись только в тестовой группе с cleanup, фиксация в `design.md` и повтор при verify. В `AGENTS.md` и скилл перенесено правило «только `gh` в неинтерактивном режиме». [#192](https://github.com/mesilov/bitrix24-cli/issues/192)

- Спецификации выборочной передачи EMP-09/PM-04 синхронизированы после merge MR #166; change расширения task:handover архивирован с evidence исправления P2 review и 15 успешных checks на проверенном head. [#165](https://github.com/mesilov/bitrix24-cli/issues/165)

- **Выборочная передача задач (EMP-09/PM-04):** `task:handover` поддерживает `--source-status`, повторяемый `--exclude-id` и `--target-status` для пяти достижимых состояний, включая завершение и контроль. Проверяются ответственный, проект и ожидаемый статус; результаты назначения и переходов отражаются отдельно, без автоматических повторов. Обновлены JTBD, справка и проверки. [#165](https://github.com/mesilov/bitrix24-cli/issues/165)

- Завершённый change передачи задач проекта архивирован после merge MR #163 в `dev`, исправления P2 review и 15 успешных CI checks на проверенном head. Актуальные EMP-09, PM-04 и task-project-handover сохранены в `openspec/specs/`; receipt и границы portal-приёмки находятся в архиве. [#162](https://github.com/mesilov/bitrix24-cli/issues/162)

- Требования пяти команд шаблонов задач и JTBD PM-08 синхронизированы в `task-templates`; завершённый change архивирован после merge MR #160 в `dev`, ссылки JTBD переведены на актуальную спецификацию. [#72](https://github.com/mesilov/bitrix24-cli/issues/72)

- Завершённый OpenSpec change правил мейнтейнера архивирован после merge MR #157 в `dev`, проверки changelog, успешного CI текущего head и снятия замечания review. [#156](https://github.com/mesilov/bitrix24-cli/issues/156)
- **Правила мейнтейнера:** каждый MR требует собственной содержательной записи changelog со ссылкой на issue. Через пять минут после открытия проверяются reviews, inline threads и обсуждение; перед merge все замечания должны быть сняты, активные запросы изменений отсутствовать, а обязательные GitHub CI checks — завершиться SUCCESS на текущем head SHA. [#156](https://github.com/mesilov/bitrix24-cli/issues/156)
- Требования сетевых повторов установщика и условных команд установки синхронизированы в `binary-distribution`; завершённый OpenSpec change архивирован после merge #155 и успешных post-merge проверок. [#151](https://github.com/mesilov/bitrix24-cli/issues/151)
- Требования проверки scope вебхука синхронизированы в актуальную спецификацию `connection-scope-validation`; завершённый OpenSpec change архивирован после доставки MR #150 в `dev` и устранения всех замечаний review. [#149](https://github.com/mesilov/bitrix24-cli/issues/149)
- Финальные receipts выпуска 0.2.0 и завершённый OpenSpec релиза архивированы после production/native/public-install проверки ([#109](https://github.com/mesilov/bitrix24-cli/issues/109)).

## [0.2.0] — 2026-10-14

В CLI теперь 56 публичных команд: 43 для задач, 4 для сотрудников, 5 для подключений, `doctor:problem-trajectory` и 3 общие команды.

### Добавлено

- **Жизненный цикл задачи:** `task:start`, `task:pause`, `task:defer`, `task:renew`, `task:complete`, `task:approve`, `task:disapprove`. Проверяются права действия и подтверждённый результат; частичное выполнение и неизвестный результат записи отражаются явно. [#60](https://github.com/mesilov/bitrix24-cli/issues/60)
- **Результаты задач:** `task:result:add`, `task:result:list`, `task:result:update`, `task:result:delete`. [#61](https://github.com/mesilov/bitrix24-cli/issues/61)
- **Мои задачи:** `task:my --user ID` с фильтром `--open`, границей `--due-before` и ограничением обхода `--max-scan`. ID исполнителя задаётся явно. [#118](https://github.com/mesilov/bitrix24-cli/issues/118)
- **Время задачи в статусах:** `task:status:durations` учитывает повторные открытия и границу `--as-of`. Суббота и воскресенье исключаются по серверному offset `created`; результаты каждого статуса представлены в `seconds`, `hours` и `days`. Часы и дни округляются независимо до двух знаков, день равен 24 часам. При недоказанной полноте возвращаются `complete=false`, неизвестные суммы `null` и exit code 3. Это календарное время, а не трудозатраты. [#121](https://github.com/mesilov/bitrix24-cli/issues/121), [#136](https://github.com/mesilov/bitrix24-cli/issues/136)
- **Сотрудники:** `users:current`, `users:find`, `users:colleagues`, `users:manager`. В профилях поддержаны `observerId`, `managerId`, `autoAddObserver`; наблюдатель может добавляться после создания задачи с сохранением подтверждённого ID при частичном выполнении. [#63](https://github.com/mesilov/bitrix24-cli/issues/63)
- **Фильтрация по роли:** `--role responsible|accomplice|creator|auditor` в `task:list` и `task:find`. Роль относится к пользователю подключения и сочетается с остальными критериями через AND. [#68](https://github.com/mesilov/bitrix24-cli/issues/68)
- **Календарные фильтры сроков:** `--deadline-window`, `--overdue`, `--timezone` в `task:list` и `task:find`; совместимы с фильтрами проекта, роли и названия. [#95](https://github.com/mesilov/bitrix24-cli/issues/95)
- **Теги:** повторяемый `--tag` в `task:add`/`task:update` и `--clear-tags` в `task:update`. Явный набор заменяет теги; отсутствие опции сохраняет их. [#71](https://github.com/mesilov/bitrix24-cli/issues/71)
- **Пользовательские поля:** `--user-fields`/`--user-fields-file` для создания и изменения задач; обязательные custom-поля отправляются при создании одним REST 1.0 вызовом. [#58](https://github.com/mesilov/bitrix24-cli/issues/58)
- **Локальные вложения:** `task:file:attach --file PATH --folder-id ID` загружает файл на Диск и прикрепляет к задаче. Поддержаны `--name`, потоковая передача, лимит по умолчанию 500 MB и сохранение Disk ID для восстановления после частичной привязки. [#62](https://github.com/mesilov/bitrix24-cli/issues/62)
- **Диагностика нехватки CLI:** `doctor:problem-trajectory` хранит обезличенные локальные карточки, показывает состояние и выполняет анализ снимков. Реальный endpoint отправки не включён. [#96](https://github.com/mesilov/bitrix24-cli/issues/96)
- **Общая справка и сведения о приложении:** `--help`/`-h` показывают назначение, полный каталог, глобальные опции и примеры задач; `--about` — версию, автора, лицензию и ссылки. Оба режима работают без подключения. [#100](https://github.com/mesilov/bitrix24-cli/issues/100)
- **Установка macOS/Linux:** POSIX installer проверяет SHA-256 и версию до замены бинарника, поддерживает свой каталог установки и `--no-modify-path`. [#28](https://github.com/mesilov/bitrix24-cli/issues/28)
- **Промежуточные сборки и RC:** ручной выбор снимков `dev` через ветку `builds`, теги `MAJOR.MINOR.PATCH-rc-N`, три native платформы и архивы с `SHA256SUMS`. RC не публикует Release и не меняет latest. [#109](https://github.com/mesilov/bitrix24-cli/issues/109)

### Изменено

- **Вся CLI-справка на русском:** заголовки, аргументы, опции, значения по умолчанию, примеры и ограничения. `completion`, `help`, `list` объединены в первый блок «Общие». Имена команд, флагов и API/JSON-полей сохранены. [#113](https://github.com/mesilov/bitrix24-cli/issues/113)
- **Читаемый вывод задач:** статусы, адаптация к ширине терминала, явные результаты полного и частичного обхода. Поиск совмещает название, ID, ответственного, статус, срок и JSON-фильтр через AND; JSON/TSV-контракты сохранены. [#12](https://github.com/mesilov/bitrix24-cli/issues/12)
- **README и руководства:** короткая входная страница, установка по платформам, подключение и конфигурация, 13 таблиц «Команда / Назначение / Пример». Детали вынесены в руководства установки, подключений, конфигурации, задач и сотрудников. Карта CLI актуализирована для 56 команд. [#134](https://github.com/mesilov/bitrix24-cli/issues/134)
- **Документация сценариев:** сохранены 22 JTBD с ролями, командами, статусами реализации, ссылками на тесты и границами покрытия; их актуализация обязательна при изменении связанных команд. [#80](https://github.com/mesilov/bitrix24-cli/issues/80), [#105](https://github.com/mesilov/bitrix24-cli/issues/105)
- **Сопровождение:** OpenSpec-трассировка и архивы завершённых изменений, обязательная запись CHANGELOG для PR по issue, ручные сборки и отдельная приёмка скачанных бинарников. [#65](https://github.com/mesilov/bitrix24-cli/issues/65), [#77](https://github.com/mesilov/bitrix24-cli/issues/77), [#78](https://github.com/mesilov/bitrix24-cli/issues/78), [#109](https://github.com/mesilov/bitrix24-cli/issues/109)

### Исправлено

- `task:list`/`task:find` обходят свежие задачи первыми (`id DESC`). В поиске доступен `--order`; неполный результат сообщает сортировку, объём обхода и необходимость увеличить `--max-scan`. [#64](https://github.com/mesilov/bitrix24-cli/issues/64)
- `connection:add` отклоняет существующий alias до ввода webhook и сообщает, что параметры не применены. Для изменения профиля документировано повторное создание. [#59](https://github.com/mesilov/bitrix24-cli/issues/59)
- Advanced-поля add/update проверяются по допустимости записи; `editable=false` не блокирует разрешённые поля создания. Обычный advanced dry-run работает без API. [#58](https://github.com/mesilov/bitrix24-cli/issues/58)
- Если `groupId=null` в REST 3.0 list, принадлежность проекту проверяется чтением карточки по заранее выбранному маршруту. Непроверенные данные дают partial result. [#60](https://github.com/mesilov/bitrix24-cli/issues/60)
- Обход истории `tasks.task.history.list` завершён при отсутствии `next`; `total` не обязателен. Уточнены границы текущей жизни задачи, повторные открытия и сохранение более ранней истории. [#121](https://github.com/mesilov/bitrix24-cli/issues/121)
- Устранены пять предупреждений strict-валидации OpenSpec без изменения CLI/API-контрактов. [#114](https://github.com/mesilov/bitrix24-cli/issues/114)

### Ограничения выпуска

- Полная portal-матрица прав, независимая проверка принадлежности вложений и полнота навигации учёта времени не подтверждены во всех случаях. #42 закрыто по решению пользователя после остановки проверок; это не означает полной приёмки. [#42](https://github.com/mesilov/bitrix24-cli/issues/42)
- По умолчанию CLI разрешает объявленные маршруты REST 3.0, REST 1.0 и IM. Только явно выбранный `--api-policy strict-rest3` запрещает non-v3 команды: `policy-denied`, exit code 4, без API-запросов.
- Архивы предназначены для Linux amd64, macOS arm64 и Windows amd64. macOS Developer ID/notarization и Windows Authenticode не заявляются. [#25](https://github.com/mesilov/bitrix24-cli/issues/25)
- Проверки RC, окончательной версии `0.2.0` и публикации Release фиксируются отдельно. Агентский скилл обновлён на все 56 команд: [PR #9](https://github.com/mesilov/bitrix24-cli-skill/pull/9) merged, [#6](https://github.com/mesilov/bitrix24-cli-skill/issues/6) закрыто.

### Предрелизная проверка

Кандидат `0.2.0-rc-2` (source `6931fd0440143878f6a19601bd87bbcfd51cbe70`) прошёл [сборку трёх платформ](https://github.com/mesilov/bitrix24-cli/actions/runs/38545765746) и [проверку скачанных бинарников](https://github.com/mesilov/bitrix24-cli/actions/runs/38548634484): 56 команд, 112 command-help вызовов, 125 CLI запусков и сверка 56 примеров на каждой ОС. Source CI: 825 тестов / 13 125 assertions. Это приёмка кандидата; опубликованная стабильная 0.2.0 получает собственные артефакты и проверку.

### Финальный выпуск

[Release 0.2.0](https://github.com/mesilov/bitrix24-cli/releases/tag/0.2.0) опубликован 14 октября 2026 и назначен latest. Source SHA: `6bba00c0599913a9f5cb0751bdf0d5dd7d7c489b`. [Production CI](https://github.com/mesilov/bitrix24-cli/actions/runs/38551708207): 6/6 jobs success. [Финальные бинарники](https://github.com/mesilov/bitrix24-cli/actions/runs/38554068826): 3/3 native платформы, 56 команд, 112 help-вызовов и 125 CLI запусков каждая. [Публичная установка Linux/macOS](https://github.com/mesilov/bitrix24-cli/actions/runs/38554073724) и все version/latest ссылки проверены.

## [0.1.0] — 2026-10-12

Первая версия CLI для работы с задачами Битрикс24.

### Добавлено

- **30 команд задач:** создание, карточка, список и поиск по заголовку; изменение полей,
  ответственного и срока; удаление, схема полей и права пользователя.
- Чат задачи: чтение, отправка, изменение и удаление сообщений; прикрепление
  существующих файлов Диска по ID.
- Учёт времени: контекст, список, добавление, изменение и удаление записей.
- Чек-листы и вложенные пункты: создание, чтение, изменение, выполнение,
  возобновление и удаление. Управление участниками и чтение истории изменений.
- Именованные подключения: `connection:add/list/show/use/remove`, выбор через
  `--profile`, явный dotenv через `--env-file` и process webhook для автоматизации.
  Секреты хранятся отдельно от metadata, с Unix permissions или Windows ACL.
- Defaults создания задачи в профиле: `groupId`, `creatorId`, `responsibleId`,
  `allowsTimeTracking`; явно заданные поля имеют приоритет.
- Поле группы `--group-id`, фильтрация списка и поиска, ограниченный обход данных
  и признаки неполного результата. Advanced JSON и файловый ввод.
- Обычный вывод, JSON envelope и plain/TSV, справка и shell completion,
  режим без диалогов, `--dry-run`, подтверждение удаления и явные коды ошибок.
- Политики `task-v3`/`strict-rest3`, проверки привязки связанных объектов,
  timeout и прерывание; учёт подтверждённых шагов и неизвестного результата записи.
- Сжатые standalone-пакеты Linux amd64, macOS arm64 и Windows amd64 с PHP runtime,
  production-зависимостями, metadata, license notices и `SHA256SUMS`.
  Конечный macOS executable проходит strict проверку ad-hoc подписи.
- Изолированное Docker/Make окружение для Git worktree, unit и per-command
  integration suites, четыре CI-линтера и native packaging/acceptance matrix.
- [bitrix24-cli-license](LICENSE) на основе BSD-3-Clause, Copyright © 2026
  Maksim Mesilov <mesilov.maxim@gmail.com> и уведомление о владельцах товарных знаков.
  Полный текст поставляется в каждом архиве как `BITRIX24-CLI-LICENSE.txt`;
  исходные лицензии сторонних компонентов сохранены отдельно.

### Область сборки

- Проверенные native системы: Debian 12 amd64, macOS 15 arm64 и Windows Server 2022 amd64.
  Архивы Linux arm64 и macOS Intel пока не предоставляются.
- Некоторые операции используют явно выбранные REST 1.0/IM маршруты;
  `strict-rest3` отклоняет их до обращения к API.
- Полная denied-role матрица, history/elapsedTime completeness, независимая проверка
  file membership, передача задачи второму пользователю и native REST3 auditors
  остаются в [#42](https://github.com/mesilov/bitrix24-cli/issues/42), milestone 0.2.0.
- Проверка CI через реальный внешний fork остаётся в
  [#41](https://github.com/mesilov/bitrix24-cli/issues/41), milestone 0.2.0.
- Lifecycle/result и остальные 29 post-MVP операций остаются вне этой сборки.
  Developer ID/notarization пока не выполняются.

[Подробные границы приёмки и evidence](docs/rc-0.1.0.md).

[Unreleased]: https://github.com/mesilov/bitrix24-cli/commits/dev
[0.1.0]: https://github.com/mesilov/bitrix24-cli/milestone/1

[0.2.0]: https://github.com/mesilov/bitrix24-cli/releases/tag/0.2.0
