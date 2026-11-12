# Proposal

## Why

`task:list --role responsible --group-id 1096 --all` на портале с большим числом задач не даёт полного ответа: CLI просматривает 10 000 самых новых видимых задач, фильтрует их локально и завершается с `scan-budget-exhausted`, `complete=false` и exit 3. Без временных флагов REST 3.0 `tasks.task.list` фильтрует на сервере только по `id`, поэтому проектный, исполнительский и постановщицкий фильтры зависят от размера портала, а не от размера выборки.

Воспроизведение 12.11.2026 на `indigo.rarus.ru`, `b24cli 0.2.0`, владелец вебхука — пользователь 4084:

- `task:list --role responsible --group-id 1096 --all --json`: около 6 минут, 200 вызовов REST3 `tasks.task.list` и 22 вызова `tasks.task.get`, `scanned=10000`, `matched=0`, `complete=false`, exit 3.
- REST 3.0 отклоняет `filter` по `groupId` и `responsibleId` с ошибкой `BITRIX_REST_V3_EXCEPTION_VALIDATION_REQUESTVALIDATIONEXCEPTION` (у поля нет атрибута `Filterable`). Так же сказано в документации метода.
- REST 1.0 `tasks.task.list` с `GROUP_ID=1096` за один запрос возвращает `total=3`: задачи 95290, 95289 и 95286, у всех ответственный 4143. С `GROUP_ID=1096` и `RESPONSIBLE_ID=4084` — `total=0`. Значит, для пользователя 4084 правильный ответ — полный пустой результат, а CLI не смог его доказать.
- Запуск только с `--group-id 1096` дополнительно запрашивает группу через `tasks.task.get` для каждой строки без `groupId` и не завершился за 20 минут.

Кроме того, подсказка при `scan-budget-exhausted` в `task:list` говорит об отсутствии «дублей», хотя команда не ищет дубли.

## What Changes

- `task:list` и `task:find` без временных флагов при политике `task-v3` передают `--group-id`, `--responsible`, `--role responsible` и `--role creator` (а также совпадающие условия `--where` по `groupId`, `responsibleId`, `creatorId`) в серверный фильтр REST 1.0 `tasks.task.list` (`GROUP_ID`, `RESPONSIBLE_ID`, `CREATED_BY`). Остальные условия (`status`, `--due-before`, другие поля `--where`, title, участники роли) по-прежнему проверяются локально поверх серверной выборки.
- Маршрут выбирается до запросов только по входным флагам и объявляется в плане команды. Если выбранные `--select`, `--order` или поля `--where` не поддерживаются REST 1.0, команда остаётся на текущем REST3-маршруте с локальной фильтрацией. После ошибки скрытого перехода на другой маршрут нет.
- `--api-policy strict-rest3` сохраняет текущее поведение: REST3 и локальная фильтрация в пределах `--max-scan`. Отказа `policy-denied` для этих фильтров не появляется.
- В `meta` появляются фактический маршрут списка и перечень серверных условий. Поля `scope`, `scanned`, `matched`, `returned`, `complete`, `reason`, `roleFilter`, `order`, формы `data.items` и TSV-колонки не меняются.
- `--max-scan`, `--limit`, `--all`, порядок по умолчанию `id DESC`, partial/exit 3 и отмена/exit 130 сохраняют прежнюю семантику. `--max-scan` теперь считает строки уже отфильтрованной серверной выборки.
- Подсказка `scan-budget-exhausted` для `task:list` говорит о совпадениях, а не о дублях. `task:find` сохраняет предупреждение о дублях.
- Обновляются справка `task:list`/`task:find`, `docs/task-usage.md` и `CHANGELOG.md`.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `cli-experience`: требование «Explicit filtering and completeness of lists» — серверные условия группы, исполнителя и постановщика через REST 1.0, детерминированный выбор маршрута, metadata маршрута и подсказка без «дублей» для `task:list`.
- `task-role-filtering`: требование «Bounded and honest role verification» — `responsible`/`creator` проверяются серверным фильтром на маршруте REST 1.0, а scan budget относится к отфильтрованной выборке.
- `task-console-runtime`: требование «Policy covers the complete route plan» — план `task:list`/`task:find` с серверными условиями содержит REST 1.0 `tasks.task.list`; `strict-rest3` сохраняет REST3 и локальную проверку.

## Impact

- Код: `TaskListScanner`, `ListTasksHandler`/`FindTasksHandler` (объявление маршрутов), `Rest1TaskListAdapter` (общий REST1-адаптер со временными окнами и недельным отчётом), `TaskInputMapper`, `TaskHumanRenderer` (подсказка), справка `ListTaskCommand`/`FindTaskCommand`.
- API: дополнительно используется уже разрешённый маршрут `tasks.task.list` REST 1.0 (сейчас его используют `--deadline-window` и `task:report`). Новых методов и scopes нет.
- Тесты: unit-тесты сканера и плана маршрутов, `ListTaskCommandTest` и `FindTaskCommandTest` на портале.
- Документация: справка `task:list`/`task:find`, `docs/task-usage.md`, `CHANGELOG.md`.
- Не затрагивает `task:handover`, `task:my`, `task:report` и другие команды, использующие сканер.

## GitHub issues

- [#183](https://github.com/mesilov/bitrix24-cli/issues/183) — сбой фильтрации `task:list` при сочетании `--role responsible` и `--group-id`. Change покрывает полноту и скорость выборки по группе, исполнителю и постановщику, а также прозрачность маршрута в `meta`. Сверить выдачу с интерфейсом под пользователем, сообщившим о сбое, нужно отдельно, в рамках приёмки.
