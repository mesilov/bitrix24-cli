# bitrix24-cli

CLI-обёртка для REST API портала Битрикс24. Первый набор прикладных команд будет работать с задачами.

## Окружение

- Docker и Docker Compose, Make; локальные PHP и Composer не требуются.
- PHP 8.4 CLI (Debian Bookworm), Composer 2.8.
- `symfony/console`: `^8.0`.
- `bitrix24/b24phpsdk`: `^3.7`; при создании проекта последний стабильный релиз v3 — [3.7.0](https://github.com/bitrix24/b24phpsdk/releases/tag/3.7.0).

Точные установленные версии зафиксированы в `composer.lock`.

Dockerfile взят из [bitrix24/b24phpsdk, ветка v3](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/docker/php-cli/Dockerfile). Compose и Makefile адаптированы из того же коммита: добавлена передача UID/GID, оставлены команды окружения и Composer, добавлен запуск CLI. Все вызовы Compose используют идентичность текущего checkout через `scripts/compose.sh`. В Dockerfile исправлены комментарий о версии PHP и путь к лицензии.

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
