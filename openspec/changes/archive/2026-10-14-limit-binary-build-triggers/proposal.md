# Proposal

## Why

Каждый MR в `dev`/`main` и push в `dev` запускает три длительные сборки standalone-бинарников и загрузку артефактов. Для повседневной разработки достаточно тестов и линтеров; бинарники нужны для явно запрошенной проверки или релиза.

## What Changes

- Запускать `Package standalone binaries` только вручную через `workflow_dispatch` для выбранной ветки или ref.
- Сохранить автоматическую сборку всех платформ по релизному тегу и существующие проверки перед публикацией.
- Сохранить тесты и линтеры на push/pull request, включая `dev`.
- Описать ручной запуск в README и руководстве сборки.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `binary-distribution`: заменить обязательную сборку на MR/push ручным запуском development packaging; релизный gate сохраняется.

## Impact

Изменяются `.github/workflows/package-static.yml`, документация и контракт `CI artifact verification`. Общий platform builder и `release.yml` сохраняются. Новые зависимости, настройки биллинга и API-контракты Битрикс24 не затрагиваются.

## GitHub issues

- [#77](https://github.com/mesilov/bitrix24-cli/issues/77): ручные development builds и автоматические релизные сборки; milestone `0.2.0`, label `enhancement`.
