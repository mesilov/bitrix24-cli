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
