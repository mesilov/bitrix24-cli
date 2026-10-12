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
Доступны стандартные команды Symfony Console (`list`, `help`, `completion`)
и 30 команд задач; настройка подключения и примеры приведены ниже.

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

`--api-policy strict-rest3` отклоняет REST 1.0/IM планы до первого запроса. Default `task-v3` допускает только явную карту MVP. `--timeout` ограничивает HTTP-запрос (30 секунд по умолчанию, максимум 300). При потере ответа записи выставляется `outcomeUnknown`, автоматического повтора нет. SIGINT сохраняет подтверждённые шаги нескольких вложений.

| Exit code | Значение |
| --- | --- |
| 0 | Подтверждённый результат в заявленном scope |
| 1 | Ошибка API, подключения, прав или transport |
| 2 | Некорректные аргументы/локальный ввод |
| 3 | Неполная выборка или частично выполненные шаги |
| 4 | API policy или недоказанная привязка ресурса |
| 130 | Прерывание или отказ подтверждения |

`task:find` ищет буквальную подстроку title без учёта регистра локально по REST 3 списку. Серверный фильтр поддерживает ID; `--limit` ограничивает вывод, `--max-scan` — просмотр (10000 по умолчанию). `complete`, `scanned`, `matched`, `returned`, `limitApplied` обозначают полноту и ограничения. История без next/total возвращает partial/exit 3. `task:time:show` показывает `elapsedTime`, не обещая суммарное время или полноту записей. Удаление пункта удаляет его дочерние пункты; dry-run показывает их IDs.

Update отправляет только явный патч; `--description=""` очищает описание. `--fields`/`--fields-file` — отдельный advanced JSON object режим с проверкой `editable` metadata. Status/lifecycle, participants, tags, userFields и object relations в патче запрещены. Пропущенная роль в `task:participants:set` сохраняется, указанная заменяется целиком. Разрешённые select/order/writable поля закреплены в `src/Application/Task/FieldSchema.php`; `task:fields:list` показывает metadata портала.

## Тесты команд

```sh
make unit                 # unit-tests: Console + DI + SDK Core с контролируемым HTTP
make integration          # живой портал; явный skip без webhook
make integration TEST_ARGS='--group command:task:update' # все cases одной команды
make integration TEST_ARGS='--group happy-path'         # отдельный happy-path каждой команды
```

Unit suite (`tests/Unit`, PHPUnit `unit-tests`) проверяет все 30 маршрутов, JSON-параметры, поиск/пагинацию, patches, binding, dry-run, API policy, вывод, env precedence, SDK ошибки, timeout и SIGINT в subprocess. GitHub Actions запускает только `make unit` без секретов. `make integration` запускается отдельно с реальным webhook. Прежние `make test`/`make test-integration` и `make make-unit`/`make make-integration` остаются aliases соответствующих запусков.

Для каждой прикладной CLI-команды есть отдельный `tests/Integration/Task/<CommandClass>Test.php` с PHPUnit Group `command:<имя>` на классе. Минимум один метод помечен Group `happy-path`; новые позитивные/негативные cases добавляются в класс этой команды. Unit guard сравнивает классы и собственные happy-path методы с CommandCatalog, отклоняет объединение нескольких команд в одном классе и отсутствие покрытия. [Карта покрытия](../openspec/changes/archive/2026-10-12-design-task-console-architecture/command-map.md#integration-happy-path-coverage) перечисляет все 30 классов, test-cases и assertions. `TEST_ARGS` передаёт PHPUnit фильтры в выбранную suite, включая совместимые Make aliases.

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
