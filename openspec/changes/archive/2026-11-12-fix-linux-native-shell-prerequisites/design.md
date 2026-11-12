## Context

Job 117136679833 (Linux amd64 package), run 38949388094, падает на `build/tests/shell-installer-check.py:346`: `shutil.which('zsh')` возвращает `None`, assertion «Bash and zsh are required for documentation acceptance» срабатывает. Образ `ubuntu-24.04` содержит bash, но не zsh. В `.github/workflows/shell-installer.yml` для Linux уже есть шаг `apt-get install -y zsh`; в `package-platforms.yml` его нет. macOS-раннер содержит zsh штатно, Windows job эту проверку не запускает.

## Goals / Non-Goals

Обеспечить на Linux packaging runner обязательные Bash и zsh. Не менять тесты, installer, продукт и macOS/Windows jobs; не делать zsh необязательным.

## Decisions

- Отдельный шаг `Prepare Bash and zsh acceptance` в Linux job перед `Test without PHP or checkout dependencies`: `sudo apt-get update` и `sudo apt-get install -y --no-install-recommends bash zsh`.
- В том же шаге `command -v bash` и `command -v zsh` с выводом версий; отсутствие любого из них завершает job до долгих проверок.
- Шаг ставится после Docker-сборки, чтобы не задерживать кэшируемую сборку и падать близко к месту использования; установка занимает секунды.
- Пакеты берутся из стандартных репозиториев Ubuntu раннера, как в `shell-installer.yml`; версии не фиксируются, тест проверяет поведение, а не версию.

## Risks

Временная недоступность apt-зеркала уронит job — это видимая инфраструктурная ошибка, не ложный успех. Тот же риск уже принят в `shell-installer.yml`.

## Verification

Локально: YAML разбирается, `actionlint` (если доступен), strict OpenSpec validate. Решающая проверка — CI: intermediate run через `builds` на трёх платформах после merge в `dev` и доставки в `builds`, затем загрузка артефактов успешного run перед live EMP-09.
