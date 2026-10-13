# Proposal

## Why

В `task:list` и `task:find` нет фильтра по собственной роли в задаче. Пользователю нужны те же четыре группы, что в интерфейсе Битрикс24: «делаю», «помогаю», «поручил», «наблюдаю».

## What Changes

- Добавить `--role responsible|accomplice|creator|auditor` в `task:list` и `task:find`: исполнитель, соисполнитель, постановщик и наблюдатель соответственно.
- Определять пользователя по выбранному входящему вебхуку, а не по `default.task` или ID явно заданного ответственного.
- Совмещать роль с существующими критериями команды через AND, сохраняя конечный scan budget, формат результата и честную полноту.
- Для `responsible`/`creator` проверять `responsibleId`/`creatorId` из REST3 списка. Для `accomplice`/`auditor` заранее объявлять companion REST1 `tasks.task.get` только для ID участников проверяемой задачи.
- Отклонять participant роли при `strict-rest3` до чтения credentials и запросов. Дополнительные версии API отражать в обычном accounting.
- Добавить офлайн проверки, отдельные portal cases существующих команд, help/completion и примеры четырёх ролей.

## Capabilities

### New Capabilities

- `task-role-filtering`: выборка и поиск задач по роли пользователя выбранного подключения, сочетание критериев и полнота проверки роли.

### Modified Capabilities

- `task-console-runtime`: разрешённый полный план read-маршрутов для ролевых фильтров `task:list`/`task:find` и его проверка API policy.

## Impact

- База: актуальная `origin/dev`, `45895884c221fb363eb284a4d77d0641eddbf58e`, проверена 2026-10-13. Отдельный worktree `/private/tmp/bitrix24-cli-issue-68`, ветка `feature/68-add-task-role-filters`.
- Затрагиваются `ListTaskCommand`, `FindTaskCommand`, `TaskInputMapper`, `TaskQuery`, `TaskListScanner`, handlers, `CommandCatalog`, DI и получение ID владельца выбранного вебхука. Существующий `ParticipantGateway`/`Rest1ParticipantAdapter` переиспользуется.
- README, `docs/development.md`, unit tests и отдельные `ListTaskCommandTest`/`FindTaskCommandTest` получают ролевые примеры/проверки. Новые зависимости и команды не нужны.
- Не добавляются фильтрация за другого пользователя, несколько ролей через OR, lifecycle actions, новый namespace пользователей или расширение остальных критериев `task:find` из #12.
- Фильтр роли сам не запрашивает REST3 projections `accomplices`/`auditors`. Явный `--select` остаётся отдельным контрактом; известное portal ограничение native auditors не объявляется исправленным.

## GitHub issues

- [#68 — возможность отфильтровать задачи по логическим группам: делаю, помогаю, поручил, наблюдаю](https://github.com/mesilov/bitrix24-cli/issues/68): четыре ролевые выборки; milestone `0.2.0`, label `enhancement`. REST и GraphQL попытки назначения существующего типа Feature оставили type=null; стандартная categorization подтверждена label, отсутствие issue type не объявляется успехом его назначения.

Обратная ссылка в описании issue указывает `add-task-role-filters`, `openspec/changes/add-task-role-filters/`; обе стороны проверены через GitHub API 2026-10-13 перед реализацией. Ветка обновлена до `origin/dev` `96ce502` перед apply без изменений основного checkout.
