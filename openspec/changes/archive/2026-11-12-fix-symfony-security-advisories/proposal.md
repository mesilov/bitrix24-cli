## Why

`composer audit` на `dev` (201e643) находит 8 security advisories в четырёх пакетах Symfony из `composer.lock`. Уязвимые версии попадают в PHAR и native-сборки. `symfony/filesystem` — прямая зависимость CLI (`LocalFileSource`); `symfony/http-client`, `symfony/http-foundation`, `symfony/mime` приходят транзитивно через `bitrix24/b24phpsdk`.

| Пакет | Было | Исправлено в |
|---|---|---|
| `symfony/filesystem` | 8.1.6 | 8.1.11 |
| `symfony/http-client` | 8.1.8 | 8.1.11 |
| `symfony/http-foundation` | 8.1.8 | 8.1.11 |
| `symfony/mime` | 8.1.7 | 8.1.10 |

## What Changes

- Обновить четыре пакета в `composer.lock` до исправленных патч-версий 8.1.x. Ограничения в `composer.json` (`^8.0`) не меняются.
- Добавить запись в CHANGELOG.

## Capabilities

### New Capabilities
Нет.

### Modified Capabilities
Нет: обновляются только версии зависимостей, поведение CLI не меняется (`skip_specs: true`).

## Impact

`composer.lock`, CHANGELOG. Команды, API-поля, конфигурация и тесты не меняются.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/196 — milestone 0.3.0; устранить 8 advisories `composer audit` в symfony/filesystem, http-client, http-foundation, mime.
