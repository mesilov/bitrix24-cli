# bitrix24-cli

[![Allowed licenses](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/license-check.yml?query=branch%3Adev+event%3Apush)
[![PHP-CS-Fixer](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/php-cs-fixer.yml?query=branch%3Adev+event%3Apush)
[![PHPStan](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/phpstan.yml?query=branch%3Adev+event%3Apush)
[![Rector](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml/badge.svg?branch=dev&event=push)](https://github.com/mesilov/bitrix24-cli/actions/workflows/rector.yml?query=branch%3Adev+event%3Apush)

CLI-обёртка для REST API портала Битрикс24. Первый набор прикладных команд будет работать с задачами.

## Окружение

- Docker и Docker Compose, Make; локальные PHP и Composer не требуются.
- PHP 8.4 CLI (Alpine 3.23), Composer 2.8.
- `symfony/console`: `^8.0`.
- `bitrix24/b24phpsdk`: `^3.7`; при создании проекта последний стабильный релиз v3 — [3.7.0](https://github.com/bitrix24/b24phpsdk/releases/tag/3.7.0).

Точные установленные версии зафиксированы в `composer.lock`.

Dockerfile первоначально взят из [bitrix24/b24phpsdk, ветка v3](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/docker/php-cli/Dockerfile), затем переведён на `php:8.4-cli-alpine3.23` с минимальным набором дополнительных модулей. Compose и Makefile адаптированы из того же коммита SDK.

| Компонент | Назначение и решение |
| --- | --- |
| `bcmath` | Обязателен для `moneyphp/money`; собирается из исходников PHP в базе |
| `intl`, `icu-libs` | Обязателен для b24phpsdk; ICU остаётся как библиотека исполнения |
| `unzip` | Распаковка Composer dist-архивов; дополнительный модуль `zip` не нужен |
| Composer 2.8 | Установка и проверка зафиксированных зависимостей, включая dev-инструменты |
| `curl`, `json`, `filter`, `ctype`, `tokenizer`, `iconv`, `hash` | Требования lock приложения и линтеров; уже предоставляются базовым PHP |
| `excimer`, `pcntl`, `yaml`, `zip` | Текущему CLI и четырём линтерам не нужны; дополнительные установки удалены |
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

Точка входа — `bin/console`, пространство имён будущих команд — `Bitrix24\CLI\` в `src/`. Сейчас доступны стандартные команды Symfony Console (`list`, `help`, `completion`). Команды задач и подключение к порталу ещё не добавлены.

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

Проверки кода анализируют `src/` и явно включённый `bin/console` без расширения `.php`. Код зависимостей в `vendor/` не включается в область анализа исходников. PHP-CS-Fixer работает в режиме `check`, Rector — `--dry-run`: исходники не изменяются. `lint-all` запускает все четыре проверки; при первой ошибке Make останавливается. Кеши находятся в игнорируемом `var/cache/`.

В GitHub Actions четыре отдельных workflows запускаются при `push` и `pull_request`, включая PR из fork. Каждый собирает Docker-образ CLI и устанавливает зависимости из lock с dev-пакетами. GHCR login и секреты портала не требуются; token имеет только `contents: read`. Запуск внешнего PR может ожидать одобрения владельца согласно настройкам GitHub.

Ошибка подготовки окружения или нарушение правил дают ненулевой код и ошибку соответствующего check. Диагностика доступна в логе шага установки или линтера. Для воспроизведения установите зависимости через `make composer-install`, запустите нужную команду и исправьте указанное нарушение. Изменение политики допустимых лицензий требует отдельного решения; ошибку нельзя скрывать расширением allowlist.

Workflows и конфигурации адаптированы из [b24phpsdk v3](https://github.com/bitrix24/b24phpsdk/tree/8ebd4c154d5557db949b0196825f347a3a6c5bf0/.github/workflows). Существующий `make check` остаётся проверкой Composer, синтаксиса PHP и запуска CLI.

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
