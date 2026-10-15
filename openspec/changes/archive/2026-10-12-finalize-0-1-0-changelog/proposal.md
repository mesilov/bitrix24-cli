# Proposal

## Why

Пользователь готовит тег финального релиза 0.1.0 на main после MR #49. CHANGELOG.md ещё помечает эту версию как RC и описывает dev-кандидат, поэтому текст нужно согласовать с финальной версией до тегирования.

## What Changes

- Оформить заголовок 0.1.0 без RC и убрать описание dev-кандидата во введении.
- Сохранить список возможностей, дату, лицензию и фактические ограничения; убрать только предрелизную формулировку о ещё не выполненной публикации Release.
- Обновить ссылки README.md и docs/rc-0.1.0.md на новый changelog anchor, сохранив исторический RC acceptance record.

## Capabilities

### New Capabilities

Нет: документационная правка, `skip_specs: true`.

### Modified Capabilities

Нет изменений CLI/API или release workflow.

## Impact

CHANGELOG.md, ссылка в README.md, ссылка в docs/rc-0.1.0.md и OpenSpec bookkeeping. Код, сборка, конфигурация, зависимости, лицензии и исторические evidence не меняются. Эта правка не создаёт тег или GitHub Release и не заявляет новые portal/fork проверки.

## GitHub issues

- [#52](https://github.com/mesilov/bitrix24-cli/issues/52): оформление changelog финального релиза 0.1.0 и исправление ссылок.
