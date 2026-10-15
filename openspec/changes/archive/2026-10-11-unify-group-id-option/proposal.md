# Proposal

## Why

REST 3.0 и JSON CLI используют `groupId`, а команды принимают `--project`. Это расхождение затрудняет понимание конфигурации нескольких порталов из #19; единое имя необходимо до ближайшего релиза 0.1.0.

## What Changes

- **BREAKING:** заменить `--project` на `--group-id` в `task:add`, `task:update`, `task:list` и остальных существующих поверхностях идентификатора группы; старый флаг удалить без alias.
- Добавить `task:find --group-id ID`: локальный фильтр группы совместно с title search, по уточнению пользователя 11.10.2026.
- Сохранить JSON/config/API `groupId`, положительный integer ID, существующие REST routes, advanced JSON и семантику фильтрации.
- Старый флаг завершает вызов с usage-error/exit 2 до API; указать новое имя в migration notes.
- Обновить актуальные help/completion, документацию, примеры и tests/fixtures; исторические архивы и Docker Compose project identity остаются вне переименования.
- Согласовать примеры с #19; profile-local default groupId реализуется отдельным change #19.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `cli-experience`: единый флаг group identifier, удаление прежнего имени, обновление требований редактирования и примера фильтрации.

## Impact

- `AddTaskCommand`, `UpdateTaskCommand`, `ListTaskCommand`, `FindTaskCommand`, `TaskInputMapper` и связанные CLI/unit/subprocess tests.
- Help/completion, README/docs и актуальные OpenSpec примеры; scripts пользователей должны перейти на новое имя.
- API, connection storage, profile defaults и расширение фильтрации task:find сверх groupId в объём не входят; дополнительные зависимости не нужны.
- Реализация и поставка разрешены запросом пользователя: довести #27 через PR в dev и затем в main.

## GitHub issues

- [#27](https://github.com/mesilov/bitrix24-cli/issues/27) — удалить --project и использовать --group-id везде; milestone 0.1.0, labels enhancement/documentation.
- [#19](https://github.com/mesilov/bitrix24-cli/issues/19) — конфигурация порталов; groupId профиля и его приоритеты находятся в `add-cross-platform-connection-profiles`.

## Baseline

Planning worktree: `/private/tmp/bitrix24-cli-issue-27`, branch `feature/27-unify-group-id-option`, от актуальной `dev` `b53178e`, проверенной 11.10.2026. Реализация ещё не начата.
