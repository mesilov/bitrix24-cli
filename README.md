# bitrix24-cli

CLI-обёртка для REST API портала Битрикс24. Первый набор прикладных команд будет работать с задачами.

## Окружение

- Docker и Docker Compose, Make; локальные PHP и Composer не требуются.
- PHP 8.4 CLI (Debian Bookworm), Composer 2.8.
- `symfony/console`: `^8.0`.
- `bitrix24/b24phpsdk`: `^3.7`; при создании проекта последний стабильный релиз v3 — [3.7.0](https://github.com/bitrix24/b24phpsdk/releases/tag/3.7.0).

Точные установленные версии зафиксированы в `composer.lock`.

Dockerfile взят из [bitrix24/b24phpsdk, ветка v3](https://github.com/bitrix24/b24phpsdk/blob/8ebd4c154d5557db949b0196825f347a3a6c5bf0/docker/php-cli/Dockerfile). Compose и Makefile адаптированы из того же коммита: добавлены имя образа проекта и передача UID/GID, оставлены команды окружения и Composer, добавлен запуск CLI. В Dockerfile исправлены комментарий о версии PHP и путь к лицензии.

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
