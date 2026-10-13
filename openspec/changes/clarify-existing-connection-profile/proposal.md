# Proposal

## Why

Повторный `connection:add work --creator-id …` не обновляет `default.task`, но help не объясняет, как изменить существующий профиль. На актуальной `dev` дубликат отклоняется только после ввода webhook, поэтому пользователь может повторно ввести секрет и всё равно не получить ожидаемые defaults.

## What Changes

- Сохранить `connection:add` как создание нового профиля; использовать разрешённый issue вариант с явным объяснением замены через remove/add.
- Проверять существование alias до запроса или чтения webhook и возвращать `configuration-error`/exit 1 с сообщением, что параметры не применены, и последовательностью `connection:show NAME`, `connection:remove NAME`, `connection:add NAME`, при необходимости `connection:use NAME`.
- Сохранить проверку дубликата под блокировкой репозитория для конкурентных вызовов.
- Описать в help, README и configuration guide, что при повторном создании требуется заново передать все нужные defaults и webhook; удаление выбранного default очищает `defaultProfile`.
- Добавить проверки наблюдаемого CLI-поведения, сохранности metadata/credentials и сценария из issue без запросов к порталу.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `connection-configuration`: однозначная семантика duplicate add, ранняя диагностика без секретного ввода и документированный порядок замены профиля.

## Impact

`ConnectionCommandRunner`, `ProfileRepository`, help `AddConnectionCommand`, `tests/Unit/ConnectionProfilesTest.php`, README и `docs/configuration.md`. Формат config v1, API routes и зависимости не меняются. Пользовательский checkout сохраняется; работа ведётся в отдельном worktree от `dev` `6d9fe39b4f6d431c70680adefdab970cb0ad2db4`.

## GitHub issues

- [#59](https://github.com/mesilov/bitrix24-cli/issues/59) — пояснение отказа повторного add, корректный способ настройки `creatorId` и проверка отсутствия незаметного изменения defaults. Milestone `0.2.0`, категория `bug`.

## Planning status

13.10.2026 пользователь согласовал сохранение создания профиля с улучшением ошибки и документации и запросил реализацию с обновлением PR #73. Обновление defaults повторным add не входит в согласованный объём. Результаты реализации и проверки фиксируются в tasks.md и verification.md.
