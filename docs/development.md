# Разработка и подключение

Команды ниже выполняются из корня выбранного checkout.
Сборка, установка и проверка standalone-бинарников описаны в [руководстве сборки](../build/README.md).

## Окружение

- Docker и Docker Compose, Make; локальные PHP и Composer не требуются.
- PHP 8.4 CLI (Alpine 3.23), Composer 2.8.
- `symfony/console`: `^8.0`.
- `bitrix24/b24phpsdk`: `^3.7`.

Точные установленные версии зафиксированы в `composer.lock`.

Dockerfile первоначально взят из [bitrix24/b24phpsdk, ветка v3](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/docker/php-cli/Dockerfile), затем переведён на `php:8.4-cli-alpine3.23` с минимальным набором дополнительных модулей. Compose и Makefile адаптированы из того же коммита SDK.

| Компонент | Назначение и решение |
| --- | --- |
| `bcmath` | Обязателен для `moneyphp/money`; собирается из исходников PHP в базе |
| `intl`, `icu-libs` | Обязателен для b24phpsdk; ICU остаётся как библиотека исполнения |
| `unzip` | Распаковка Composer dist-архивов; дополнительный модуль `zip` не нужен |
| Composer 2.8 | Установка и проверка зафиксированных зависимостей, включая dev-инструменты |
| `curl`, `json`, `filter`, `ctype`, `tokenizer`, `iconv`, `hash` | Требования lock приложения и линтеров; уже предоставляются базовым PHP |
| `pcntl` | Обработка SIGINT с сохранением подтверждённых шагов; собирается в CLI-образе |
| `excimer`, `yaml`, `zip` | Текущему CLI и линтерам не нужны |
| OPcache | Уже входит в официальный PHP 8.4 образ; повторная сборка удалена |
| Extension installer, компилятор, заголовки ICU | Installer больше не используется; временные build-пакеты удаляются в том же слое |

Остальные встроенные модули официального PHP не пересобираются. При изменении `composer.lock` достаточность окружения проверяется через `make composer ARGS="check-platform-reqs"`. Контейнер по умолчанию запускается пользователем `cli` (UID/GID 10001); Make сохраняет передачу UID/GID хоста. Composer cache создаётся запускающим пользователем в `/tmp/composer/cache`.

## Быстрый старт

```sh
make docker-init
make cli
make cli ARGS="--version"
make check
```

`docker-init` собирает образ и устанавливает зависимости. Команды запускаются в одноразовых контейнерах; отдельно поднимать контейнер для них не требуется. Через Make контейнер получает UID/GID текущего пользователя, чтобы создаваемые файлы принадлежали ему.

## Работа с проектом

```sh
make help
make composer-install
make composer-update
make composer ARGS="show --direct"
make php-cli-bash
```

Для постоянно работающего контейнера доступны `make docker-up`, `make docker-down` и `make docker-restart`.

Точка входа — `bin/b24cli`; `bin/console` остаётся совместимым launcher.
Команды находятся в пространстве имён `Bitrix24\CLI\` в `src/`.
Публичные команды задач, сотрудников, локальных подключений и стандартные
`list`, `help`, `completion` перечислены в [карте CLI-вызовов](cli-command-map.md).
`b24cli --help` показывает обзор приложения с командами и примерами,
`b24cli COMMAND --help`/`b24cli help COMMAND` — командную справку,
включая русские описания встроенных команд и глобальных опций. В общем help
`completion`, `help` и `list` находятся в едином первом блоке «Общие»;
пространства имён и команды внутри них упорядочены по имени.
`b24cli --about` — метаданные приложения. Эти вызовы работают без подключения.

Обновить карту после изменения команд или примеров: `make cli-reference`.
Проверить соответствие текущему каталогу: `make cli-reference-check`.

## Проверки качества

После `make docker-init` доступны четыре проверки. Они используют dev-зависимости из `composer.lock` и выполняются в Docker; локальные PHP и Composer не требуются.

```sh
make lint-allowed-licenses
make lint-cs-fixer
make lint-phpstan
make lint-rector
make lint-all
```

| Make-команда | GitHub Actions check | Что проверяется |
| --- | --- | --- |
| `lint-allowed-licenses` | `composer-license-checker` | Лицензии зависимостей: MIT, Apache-2.0, BSD-3-Clause |
| `lint-cs-fixer` | `PhpCsFixer` | Стиль PSR-12 |
| `lint-phpstan` | `PHPStan` | Статический анализ, level 5 |
| `lint-rector` | `Rector` | Применимые правила b24phpsdk для PHP 8.4 |

Проверки кода анализируют `src/`, `config/`, PHP-тесты и оба launcher `bin/b24cli`/`bin/console`. Код зависимостей в `vendor/` не включается в область анализа исходников. PHP-CS-Fixer работает в режиме `check`, Rector — `--dry-run`: исходники не изменяются. `lint-all` запускает все четыре проверки; при первой ошибке Make останавливается. Кеши находятся в игнорируемом `var/cache/`.

В GitHub Actions четыре отдельных workflows запускаются при `push` и `pull_request`, включая PR из fork. Каждый собирает Docker-образ CLI и устанавливает зависимости из lock с dev-пакетами. GHCR login и секреты портала не требуются; token имеет только `contents: read`. Запуск внешнего PR может ожидать одобрения владельца согласно настройкам GitHub.

Ошибка подготовки окружения или нарушение правил дают ненулевой код и ошибку соответствующего check. Диагностика доступна в логе шага установки или линтера. Для воспроизведения установите зависимости через `make composer-install`, запустите нужную команду и исправьте указанное нарушение. Изменение политики допустимых лицензий требует отдельного решения; ошибку нельзя скрывать расширением allowlist.

Workflows и конфигурации адаптированы из [b24phpsdk v3](https://github.com/bitrix24/b24phpsdk/tree/8ebd4c154d5557db949b0196825f347a3a6c5bf0/.github/workflows). Существующий `make check` остаётся проверкой Composer, синтаксиса PHP и запуска CLI.

## Подключение и команды

```sh
cp .env.example .env.local
# В .env.local задайте BITRIX24_WEBHOOK — полный URL входящего webhook.
make cli ARGS='list task'
make cli ARGS='task:add --title "Подготовить договор" --creator 1 --responsible 2'
make cli ARGS='task:update 123 --title "Согласовать договор"'
make cli ARGS='task:assign 123 --responsible 3'
make cli ARGS='task:update 123 --description "Проверить реквизиты"'
make cli ARGS='task:find --title "договор" --json'
make cli ARGS='task:chat:send 123 --text "Договор готов"'
make cli ARGS='task:time:add 123 --seconds 3600 --text "Подготовка договора"'
make cli ARGS='task:checklist:add 123 --title "Проверка"'
make cli ARGS='task:checklist:item:add 123 --checklist 10 --title "Реквизиты"'
```

При установленном PHP 8.4/8.5 запускайте `bin/b24cli` напрямую; добавив `bin/` в PATH, используйте `b24cli`. `bin/console` вызывает тот же bootstrap. Справка каждой команды содержит параметры, пример и колонки вывода.

ServiceBuilder создаётся перед первым API-запросом. `BITRIX24_WEBHOOK` из process environment имеет приоритет; файл выбирается через `B24CLI_ENV_FILE`, иначе используется корневой `.env.local`, при его отсутствии — `.env`. Поддерживается SDK alias `BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK`. Оба файла игнорируются Git. Webhook не передаётся аргументом CLI и не добавляется в API trace. Нужны scopes `tasks`, `task` и `im`; действуют права пользователя webhook. Live-тесты дополнительно запрашивают `profile` для ID этого пользователя.

Для worktree можно указать env основного checkout. Wrapper монтирует файл read-only, PHP получает его путь:

```sh
B24CLI_ENV_FILE=/absolute/path/to/checkout/.env.local make cli ARGS='task:show 123'
B24CLI_ENV_FILE=/absolute/path/to/checkout/.env.local make integration
```

В [SDK integration Factory](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/tests/Integration/Factory.php) по умолчанию используется webhook (`getServiceBuilder(false)`). `getServiceBuilder(true)` подключает OAuth-токены приложения через ApplicationBridge с сохранением обновлённых токенов. CLI использует webhook.

### Вывод и автоматизация

`--json` выдаёт один объект `data/meta/error` со `schemaVersion: 1`. Список всегда находится в `data.items`, карточка — `data.task`, мутация — `data.operation` или `data.items` для нескольких шагов. Отправка сообщения возвращает boolean ACK и `resourceId: null`; ID для изменения выбирайте через `task:chat:list`.

`--plain` выдаёт TSV без заголовков; табуляция, перевод строки, CR и обратный слеш внутри значения экранируются как `\t`, `\n`, `\r`, `\\`. Human-режим выдаёт таблицы. Диагностика идёт в stderr. `-q` скрывает результат, `--silent` скрывает и ошибки. Глобальные опции доступны до и после имени команды; `--` завершает разбор опций. Уникальные сокращения Symfony поддерживаются, опечатки не запускают команду автоматически.

`--dry-run` показывает план без записей; проверочные чтения для binding видны в `meta.apiCalls`. Удаление в automation требует `--force`, на TTY запрашивает подтверждение. Force не отключает валидацию и привязку ресурса.

`--api-policy strict-rest3` отклоняет REST 1.0/IM планы до первого запроса. Default `task-v3` допускает только явную карту команд, включая семь lifecycle маршрутов. Все четыре `task:result:*` операции используют REST3 и доступны при обеих политиках. `--timeout` ограничивает HTTP-запрос (30 секунд по умолчанию, максимум 300). При потере ответа записи выставляется `outcomeUnknown`, автоматического повтора нет. SIGINT сохраняет подтверждённые шаги нескольких вложений.

| Exit code | Значение |
| --- | --- |
| 0 | Подтверждённый результат в заявленном scope |
| 1 | Ошибка API, подключения, прав или transport |
| 2 | Некорректные аргументы/локальный ввод |
| 3 | Неполная выборка или частично выполненные шаги |
| 4 | API policy или недоказанная привязка ресурса |
| 130 | Прерывание или отказ подтверждения |

`task:find` ищет буквальную подстроку title без учёта регистра локально по REST 3 списку. Серверный фильтр поддерживает ID; `--limit` ограничивает вывод, `--max-scan` — просмотр (10000 по умолчанию). `complete`, `scanned`, `matched`, `returned`, `limitApplied` обозначают полноту и ограничения. История без next/total возвращает partial/exit 3. `task:time:show` показывает `elapsedTime`, не обещая суммарное время или полноту записей. Удаление пункта удаляет его дочерние пункты; dry-run показывает их IDs.

Update отправляет только явный патч; `--description=""` очищает описание. `--fields`/`--fields-file` — отдельный advanced JSON object режим с локальной проверкой документированных имён и типов REST3. Raw status, participants, tags, userFields и object relations в REST3 патче запрещены; custom значения задаются отдельными --user-fields/--user-fields-file. Пропущенная роль в `task:participants:set` сохраняется, указанная заменяется целиком. Разрешённые select/order/writable поля закреплены в `src/Application/Task/FieldSchema.php`; `task:fields:list` показывает metadata портала.

### Фильтрация по своей роли

`task:list` и `task:find` принимают `--role responsible|accomplice|creator|auditor`:
делаю, помогаю, поручил, наблюдаю. Роль принадлежит пользователю выбранного входящего
вебхука; profile/env-file/process env выбираются по обычным правилам подключения,
`default.task` не задаёт текущего пользователя. ID извлекается из валидированного
URL без `user.current`/`user.get` и без вывода token. JSON содержит
`meta.roleFilter: {"role":"creator","userId":7}`.

```sh
make cli ARGS='task:list --role auditor --group-id 42 --status in_progress --json'
make cli ARGS='task:list --role creator --where '\''{"responsibleId":9}'\'' --json'
make cli ARGS='task:find --title "договор" --group-id 42 --role accomplice --json'
```

Критерии сочетаются через AND; несколько ролей в одном флаге не принимаются.
Сам статус роль не ограничивает, а задача может попадать в несколько групп.
Ответственный/постановщик проверяются по REST3 `responsibleId`/`creatorId` локально.
Соисполнитель/наблюдатель проверяются через заранее объявленный REST1
`tasks.task.get` с `taskId` и `select` ID/ACCOMPLICES либо ID/AUDITORS. При
`strict-rest3` эти две роли отклоняются с exit 4 до credentials/API;
responsible/creator доступны. Неизвестная/пустая роль даёт usage-error/exit 2.

Participant чтение выполняется после остальных predicates только для уникальных
подходящих задач, не более одного на задачу и не более `--max-scan` за вызов.
Стоимость зависит от числа кандидатов: ограничивайте группу, title, а для списка
также IDs. `--limit` применяется после фильтрации; `--all` не снимает scan budget.
Недостающие scalar поля, page ошибки и исчерпание budget дают partial/exit 3.
Ошибка чтения состава роли останавливает обход с `role-read-error`, сохраняя
подтверждённые совпадения; непроверенный состав не считается пустым. Чтения списка
и участников не являются атомарным snapshot. Ctrl-C прекращает следующие запросы
с exit 130. Явный `--select auditors` сохраняет отдельный контракт REST3 projections;
ролевой фильтр не добавляет эти projections и не устраняет известное ограничение
native auditors. Новых команд, writes и fallback/retry нет.

Portal cases ролей запускаются отдельно для существующих классов:

```sh
make integration TEST_ARGS='--group command:task:list'
make integration TEST_ARGS='--group command:task:find'
```

Для scalar negative fixtures нужен `B24CLI_TEST_OTHER_USER_ID` в выбранном env-file:
положительный ID другого доступного пользователя. Fixtures создаются и удаляются
через SDK; отсутствие условий фиксируется как skip, а не live acceptance.

## Тесты команд

```sh
make unit                 # unit-tests: Console + DI + SDK Core с контролируемым HTTP
make integration          # живой портал; явный skip без webhook
make integration TEST_ARGS='--group command:task:update' # все cases одной команды
make integration TEST_ARGS='--group happy-path'         # отдельный happy-path каждой команды
```

Unit suite (`tests/Unit`, PHPUnit `unit-tests`) проверяет все 43 команды задач и четыре команды сотрудников, JSON-параметры, поиск/пагинацию, patches, binding, dry-run, API policy, вывод, env precedence, SDK ошибки, timeout и SIGINT в subprocess. GitHub Actions запускает только `make unit` без секретов. `make integration` запускается отдельно с реальным webhook. Прежние `make test`/`make test-integration` и `make make-unit`/`make make-integration` остаются aliases соответствующих запусков.

Для каждой прикладной CLI-команды есть отдельный `tests/Integration/Task/<CommandClass>Test.php` с PHPUnit Group `command:<имя>` на классе. Минимум один метод помечен Group `happy-path`; новые позитивные/негативные cases добавляются в класс этой команды. Unit guard сравнивает классы и собственные happy-path методы с CommandCatalog, отклоняет объединение нескольких команд в одном классе и отсутствие покрытия. [Карта покрытия](../openspec/changes/archive/2026-10-12-design-task-console-architecture/command-map.md#integration-happy-path-coverage) описывает 30 исходных классов; четыре result-класса и их проверки описаны в [verification #61](../openspec/changes/archive/2026-10-13-add-task-results/verification.md). `TEST_ARGS` передаёт PHPUnit фильтры в выбранную suite, включая совместимые Make aliases.

Общий `tests/Support/LiveTaskCommandTestCase.php` готовит независимые SDK fixtures с уникальным `b24cli-` маркером, делает SDK readback и cleanup для каждого case. Через ConsoleHarness выполняется только команда текущего класса; вызов другой CLI-команды запрещён до её выполнения. Между tests нет зависимостей по порядку или общих задач. Созданный ID регистрируется до assertions, чтобы очистка работала при отказе. Нужен тестовый портал и пользователь с правами создавать/удалять собственные задачи и работать с их чатом. При ошибке cleanup тест сообщает IDs для ручного удаления. Неверный webhook вызывает ошибку, а не skip.

Happy-path требует ожидаемые данные и exit0. Документированные read-границы `task:time:show` (отсутствующее elapsedTime) и `task:history:list` (недоказанная navigation) проверяются с точной причиной partial-result/exit3; это не доказывает полную историю или сумму времени. Для `task:file:attach` проверяется confirmed ACK; независимое membership readback остаётся ограничением API.

Дополнительные fixtures в том же env-файле:

- `B24CLI_TEST_DISK_FILE_ID`: существующий доступный файл Диска. Предоставленный файл тест не удаляет. При отсутствии ID тест создаёт маленький временный файл в личном Диске пользователя webhook и удаляет его после проверки; нужны scope `disk`, права загрузки/удаления и ровно одно личное хранилище. Attachment проверяется по ACK: REST 3 get возвращает `fileIds: null`, поэтому не доказывает наличие вложения.
- `B24CLI_TEST_RESTRICTED_WEBHOOK`: webhook другого, не административного пользователя без права редактировать создаваемую задачу для denied-role case.

Без restricted webhook denied-role case пропускается отдельно. Без основного webhook порталные сценарии не считаются пройденными; unit-тесты не доказывают поведение конкретного портала.

Проверка 2026-10-11: REST 3.0 на этом портале по-прежнему возвращает `INTERNAL_SERVER_ERROR` даже на заполненный `auditors.id`, хотя webhook уже имеет scope `user`. В `task:show` выбранные `auditors/accomplices` теперь читаются по заранее объявленной схеме: REST3 карточка без этих relations → REST1 `tasks.task.get` только ID нужных ролей → `user.get` по уникальным ID. Остальные поля карточки остаются из REST3. `task:list` сохраняет native REST3 projections; fallback/retry после ошибки отсутствует.

```sh
b24cli task:show 123 --select title --select auditors --select accomplices --json
b24cli task:show 123 --select auditors.id --json
b24cli task:show 123 --select auditors.name --select auditors.email --json
```

Корневые participant selects означают `id/name`; name составляется из NAME/SECOND_NAME/LAST_NAME. Дополнительно поддержаны `.email`, `.gender`, `.externalAuthId`; неподдержанные projections отклоняются до подключения. Для имён достаточно scope `user_brief`, для email нужен `user_basic` или `user` ([user scopes](https://apidocs.bitrix24.com/api-reference/user/user-scope.html), [user.get](https://apidocs.bitrix24.com/api-reference/user/user-get.html)). Запрашиваются только выбранные поля, порциями до 50 ID, budget 10000. Для ID-only и пустых списков user.get не вызывается. Недоступные users/поля сохраняют карточку и известные ID, null и partial/exit3. `strict-rest3` отклоняет enriched plan до первого запроса; обычная карточка остаётся одним REST3 чтением.

## Параллельная работа в Git worktree

Для каждой задачи создавайте отдельный worktree от актуальной `dev`. Например, из чистого checkout `dev`, обновлённого fast-forward:

```sh
git fetch origin
git merge --ff-only origin/dev
git worktree add -b feature/42-task-name .worktree/42-task-name dev
cd .worktree/42-task-name
make docker-init
make worktree-info
make cli
make check
```

Вторую задачу аналогично готовьте в другом каталоге и другой ветке. Worktree можно размещать и за пределами репозитория. Каждый checkout, включая основной, автоматически получает стабильное имя Compose-проекта `bitrix24-cli-<SHA-256 физического абсолютного пути>`. Нужны `shasum` (macOS) или `sha256sum` (Linux). Одинаковые названия каталогов, пути с пробелами и запуск через символьную ссылку поддерживаются. `make -C "путь к worktree" cli` запускает команду выбранного checkout.

У окружений отдельные контейнеры, сети и теги собираемых образов. Bind mount всегда указывает на соответствующий checkout; `vendor/` и создаваемые файлы принадлежат ему и UID/GID вызывающего пользователя. Кеш неизменяемых слоёв Docker может быть общим. Локальные `.env`/`.env.local` и зависимости Git не переносит в новый worktree: `docker-init` устанавливает зависимости, нужную конфигурацию добавляйте явно. Секреты не коммитите. Портов и БД в текущем окружении нет.

Для диагностики и прямого обращения к Compose используйте тот же wrapper:

```sh
make worktree-info
sh scripts/compose.sh ps
sh scripts/compose.sh config --images
sh scripts/compose.sh run --rm -T php-cli php bin/console --version
```

Wrapper явно задаёт project name, project directory и Compose-файл; `COMPOSE_PROJECT_NAME` из окружения или `.env` не меняет идентичность. Все Make-команды используют его. Для работы с этим окружением прямой `docker compose` заменяйте вызовом wrapper.

Перед удалением worktree остановите его окружение и сохраните нужные локальные файлы:

```sh
make -C .worktree/42-task-name docker-down
git worktree remove .worktree/42-task-name
```

Остановка и перезапуск затрагивают только выбранный checkout. Git может отказаться удалять worktree с локальными файлами, в том числе установленными зависимостями; сохраните их или удалите вручную после проверки. Общую очистку Docker и принудительное удаление пользовательских файлов эти команды не выполняют. Каталоги `/.worktree/` и `/.worktree-test-results/` исключены из Git.

При переходе со старого общего окружения сначала выполните **старую** `make docker-down`, затем обновите checkout и запустите `make docker-init`. Перед перемещением checkout или откатом конфигурации остановите окружение: изменение физического пути меняет имя проекта. Зависимости и локальные файлы при обновлении сохраняются.

## Проверка изоляции

```sh
make test-worktree
```

Проверка использует **закоммиченный HEAD**, Docker и сеть для установки зависимостей. Она создаёт два временных linked worktree с одинаковыми basename и пробелами в путях, параллельно собирает различимые тестовые образы, устанавливает зависимости, запускает CLI и проверки. Затем проверяет Docker labels, image tags, mounts, UID/GID, независимость исходников/`vendor/`/конфигурации и работу второго окружения после остановки и перезапуска первого. Live credentials Битрикс24 не требуются.

Логи сохраняются в `.worktree-test-results/run.*`. Cleanup удаляет только созданные тестом контейнеры, сети, образы и временные checkout. При сбое остановки окружения соответствующий checkout сохраняется с диагностикой. Проверить ветку cleanup после намеренной ошибки можно командой `WORKTREE_TEST_FAIL_AFTER_START=1 make test-worktree`: ожидается ненулевой exit code и `cleanup=true` в `result.txt`. Linux-проверка также выполняется workflow `Worktree isolation` в GitHub Actions.

## Lifecycle runtime и приёмка

`LifecycleAction` ограничивает семь действий. `TaskLifecycleService` строит полный
route plan до credentials, выполняет REST3 get/access preflight и ровно одну
REST1 mutation с `taskId`. `Rest1TaskLifecycleAdapter` принимает только ACK
`result.task.id` выбранной задачи; `TaskStatus` задаёт единый набор REST3 status
для lifecycle и фильтров списка. Status patch через общий update запрещён.

Preflight читает `status`, `needsControl`, `requireResult`, `containsResults`,
`autocompleteSubTasks` с документированными типами. Право каждого action
проверяется отдельно; edit не заменяет complete/approve. Для requireResult=true
при complete внутренний REST3 result.list читает только одну запись с
filter taskId, select id/taskId/status, order id ASC, offset=0/limit=1.
Отсутствие/непроверенность результата блокирует запись; result.status=open/closed
не является task.status. После ACK REST3 get подтверждает фактический статус.
Неопределённые поля после failed read не подменяются preflight значениями.

Каждая новая команда имеет свой integration класс и happy-path. Случаи контроля
готовятся с другим постановщиком B24CLI_TEST_OTHER_USER_ID и текущим исполнителем
(подключение должно иметь право задавать creatorId и принимать работу), либо через SDK второго исполнителя: `B24CLI_TEST_EXECUTOR_WEBHOOK` в
защищённом выбранном env-файле, который также содержит основной webhook.
`B24CLI_TEST_OTHER_USER_ID` и `B24CLI_TEST_GROUP_ID` задают тестового исполнителя
и проект для list cases. Denied-role matrix использует
`B24CLI_TEST_RESTRICTED_WEBHOOK`. Отсутствующие подключения/роли обозначаются
skip и не считаются portal acceptance. Fixtures и переходы в ListTaskCommandTest
выполняются SDK; каждый case вызывает только свою CLI-команду.

```sh
make unit TEST_ARGS='--filter TaskLifecycleTest'
B24CLI_ENV_FILE=/absolute/secure/test.env make integration TEST_ARGS='--group lifecycle'
B24CLI_ENV_FILE=/absolute/secure/test.env make integration TEST_ARGS='--group command:task:list'
```

Проверки не обещают одинаковое поведение ролей всех порталов. Запись собственных
SDK fixtures, readback, cleanup и сведения о фактически использованной роли
сохраняются в `var/cache/portal-evidence.jsonl` без credentials.

Наблюдение на тестовом портале 13.10.2026: completion самим постановщиком
может сразу вернуть completed даже при needsControl=true; для supposedly_completed
нужно отдельное подключение исполнителя. При allowsTimeTracking=true start/pause
может создавать дополнительную запись времени серверного таймера. Tests проверяют
сохранность уже записанных трудозатрат и фактические side effects, а не неизменность
всего списка времени.

REST3 list на тестовом портале возвращает groupId=null даже для задачи группы.
Фильтры list/find с groupId используют заранее объявленный get(group.id) companion
для кандидатов с неизвестным groupId. Lookup выполняется не более одного раза
на просмотренный ID и ограничен max-scan; scalar groupId из списка сохраняется
при наличии. Недоступное чтение даёт partial/group-membership-unverified.
REST1 маршруты не добавляются и strict-rest3 остаётся допустимой политикой.

## Evidence для расчёта статусных длительностей

`HistoryScanner::scan(..., withEvidence: true)` используется новой read-only
командой `task:status:durations`. Внутренний результат сохраняет исходные
нормализованные события, включая конфликтующие дубликаты ID, offsets и
совокупность причин navigation/page-error/budget. Публичный `task:history:list`
сохраняет JSON/TSV и дедупликацию; ошибочная terminal completeness исправлена. Budget — 10000 событий; ID не является
доказательством порядка событий с одинаковым timestamp.

`StatusDurationCalculator` строит полуоткрытые интервалы в абсолютном времени;
`from == to` не создаёт новый вход. При любой недоказанной границе/распределении
все три единицы `seconds/hours/days` в интервалах и totals.<status> равны `null`;
`elapsedBetweenEventsSeconds` содержит исходную разницу меток.
`WeekdayDuration` исключает только субботу и воскресенье в серверном offset created;
полные недели считает арифметически. День = 24 часа, график/праздники/трудозатраты
не применяются. Округление независимое после точного суммирования: seconds целые,
hours/days до двух знаков; precision отсутствует. Исходные даты не переписываются.
Две REST3 карточки `status/statusChanged/changed` проверяют гонки, но не являются
атомарным snapshot. Положительные числовые STATUS 2/3/4/5 подтверждены
официальным numeric subset и независимым REST3 readback; мета-коды и строковые
aliases без подтверждения REAL_STATUS остаются partial.

Проверки: `make unit TEST_ARGS="--filter StatusDuration"` и
`make integration TEST_ARGS="--group command:task:status:durations"`.
Интеграционный happy-path требует доказанную source completeness, стабильный
created и поддержанные статусные переходы с двумя повторными открытиями. Невозможность доказать эти условия отмечается
как **incomplete**, без успешного exact CLI вызова; это не точная приёмка.
Отдельный live-тест проверяет null-единицы, concurrent-task-change и exit 3.
SDK fixtures удаляются даже при incomplete. Обезличенные live-ограничения
сохранены в архиве OpenSpec `2026-10-14-calculate-task-status-durations`;
финальная exact acceptance заменяет прежний navigation blocker.

`WeekdayStatusDurationsReplayTest` пропускает сохранённые ответы задачи 6372 через
обычную команду с fake transport: 55 переходов, 56 интервалов, неизменные даты и
evidence, исключённые старые NEW/DELETE. Это replay удалённой fixture, а не новое
обращение к порталу. Результат: `var/cache/weekday-long-cli-result.json`.

Live-сценарий `testRejectedReviewThenSuccessfulCompletionRetainsIncrementalHistory`
моделирует start/complete/disapprove/start/complete/approve с контролем,
отдельными постановщиком/исполнителем, SDK readback и capture истории после
каждого действия. Новые события выделяются по сравнению ID между чтениями;
старые NEW/DELETE не удаляются из evidence. Код 4 подтверждён как
`supposedly_completed`. Raw capture — `var/cache/review-cycle-history.json`,
сохранённый обезличенный receipt — OpenSpec `review-cycle-history-check.md`.
Этот сценарий подтверждает форму наблюдённых событий и завершение fixture;
полнота lifetime истории и exact totals остаются отдельной приёмкой.

`testFiveSecondReviewCycleShowsStatusDurationResult` выполняет тот же цикл
с паузой 5 секунд перед каждым действием и перед последним отчётом в completed.
Тест измеряет паузы монотонными часами и проверяет минимум 5 секунд;
серверные промежутки включают дополнительно время SDK/API/readback.
Полный JSON команды и incremental history сохраняются отдельно в
`var/cache/five-second-review-cycle-history.json`. Запуск:
`make integration TEST_ARGS="--filter testFiveSecondReviewCycleShowsStatusDurationResult"`.

`testRawHistoryIsolationAndMoreThanFiftyKnownChanges` сравнивает сырое HTTP тело
с SDK DTO и ID адаптера, проверяет изоляцию двух собственных задач и 60 известных
изменений TITLE. Ответы без start и со start=0/50/10000/-1, FIELD-фильтры и DESC
сохраняются в `var/cache/wire-history-investigation.json`; cleanup отражён в
`portal-evidence.jsonl`. Это проверка полноты **известной fixture**, не гарантия
источника для произвольной задачи. Запуск:
`make integration TEST_ARGS="--filter testRawHistoryIsolationAndMoreThanFiftyKnownChanges"`.

Исследование 14.10.2026 подтвердило старые NEW/DELETE в HTTP ответе новой задачи
и тот же TASK_ID через независимый legacy read. В операторском подтверждении
переинициализации БД/изменения счётчика не было; конкретная причина на сервере
не установлена. Полный receipt, неоднозначность документации pagination и
готовые вопросы поставщику — OpenSpec `history-investigation-check.md` и
`bitrix24-history-support-reproducer.md`. Production не удаляет старые записи,
не выбирает последний NEW эвристически и сохраняет null exact totals.

Длительности относятся к текущему экземпляру задачи: handler заранее объявляет
REST3 tasks.task.list, фильтр точного id и select id/created, два чтения вокруг
истории. При совпадающем ID/created калькулятор исключает только строго более
ранние события и сохраняет их в excludedHistoryEvidence. Невалидные даты,
изменение created, ошибки identity или текущий DELETE остаются partial.
creationBoundary показывает источник и доказанную дату, независимо от полноты
распределения; NEW может быть записан позднее этой границы.

Controlled five-second test отдельно сверяет known current event IDs,
начальный статус, readbacks до/после каждого своего действия и created,
затем вызывает тот же production calculator с coverage=controlled-fixture-actions.
В receipt раздельно сохраняются commandResult (обычный CLI/source gate) и
controlledResult (точные суммы для собственной fixture). API pagination не
подделывается; доказательства из теста не передаются обычной CLI-команде.

REST1 completeness исправлена по официальному контракту списочных методов:
next ведёт на следующую страницу; его отсутствие означает конец, total
необязателен. Никакого вывода по короткой странице/числу 50 нет. Если total
передан, он обязан согласоваться с offset/returned/next; malformed payload,
page-error, no-progress и budget сохраняют partial. Это исправляет и прежнее
ложное partial-result history:list, сохраняя формат событий.

Длинный сценарий `testLongReviewAndReopenCyclePreservesMoreThanFiftyStatusEvents`
(group long-running) выполняет 8 review/reopen циклов, 55 переходов и 5-секундные
паузы. Запуск занимает около 5.5 минут; receipt long-review-reopen-history.json
обновляется после каждого действия. Ordinary CLI totals сверяются с independent
control journal. Live race отдельно требует null totals; новая pending task
с полной историей и statusChanged=null начинает интервал с created.
