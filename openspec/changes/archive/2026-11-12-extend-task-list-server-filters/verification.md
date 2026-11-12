# Verification

Ветка `bugfix/186-task-list-server-group-filter` от `dev` 8c21f45, 12.11.2026.

## Локальные проверки

| Проверка | Результат |
|---|---|
| `make unit` | OK, 1046 тестов, 18077 assertions |
| `make lint-allowed-licenses` | OK |
| `make lint-cs-fixer` | OK, 0 из 404 файлов к исправлению |
| `make lint-phpstan` | OK, ошибок нет |
| `make lint-rector` | OK после применения двух правок Rector (`TaskListScanner`, `TaskDateWindowFilterTest`) |
| `openspec validate extend-task-list-server-filters --strict` | valid |

Новые и изменённые тесты:

- `TaskDateWindowFilterTest` (новый): границы окон на 2026-11-12 и один `now`, filter `>=CREATED_DATE`/`<CHANGED_DATE` с offset, один запрос с `RESPONSIBLE_ID`, `REAL_STATUS`, `CHANGED_DATE` и `DEADLINE`, скрытые поля, `missing-predicate-field`, usage-ошибки, `policy-denied` при `strict-rest3`, `declined` рядом с группой, локальное подтверждение серверного статуса, предупреждение и прогресс в stderr в JSON/plain/human, причины `strict-rest3` и `unsupported-field`, отсутствие сообщений с `--id`, `--quiet`, `--silent`.
- `TaskServerListFilterTest`, `TaskRoleFilterTest`, `CommandContractsTest`: ожидаемые filter теперь содержат `REAL_STATUS`; локальный случай — `--status declined` или `strict-rest3`.
- `CombinedTaskSearchTest`, `RecentTaskScanTest`: на локальном маршруте stderr содержит одну строку предупреждения; с `--quiet`/`--silent` stderr пуст.

## Read-only приёмка на портале

Портал из `.env.local` основного checkout (`BITRIX24_WEBHOOK`), 146 видимых задач, все без группы. Выполнялись только чтения `tasks.task.list`; интеграционный набор не запускался, потому что он создаёт задачи.

Эталон — полный обход REST 3.0 (`--api-policy strict-rest3 --all --max-scan 1000`, `complete=true`, 146 строк) с локальным расчётом.

| Запрос | Маршрут, `serverFilter` | `scanned` | Результат |
|---|---|---|---|
| `--created-window this-quarter --timezone Europe/Moscow` | 1.0, `[created]` | 1 | [5288], совпадает с эталоном |
| `--changed-window this-quarter --timezone Europe/Moscow` | 1.0, `[changed]` | 1 | [5288], совпадает |
| `--status pending` | 1.0, `[status]` | 140 | 140 задач, совпадает |
| `--status in_progress` | 1.0, `[status]` | 3 | 3, совпадает |
| `--status completed` | 1.0, `[status]` | 3 | 3, совпадает |
| `--status deferred` / `supposedly_completed` | 1.0, `[status]` | 0 | 0, совпадает |
| `--status declined` | 3.0, `[]`, `no-server-predicate` | 146 | 0, совпадает; stderr содержит предупреждение о локальном обходе |
| `--status pending --created-window this-quarter --changed-window this-quarter` | 1.0, `[status, created, changed]` | 1 | [5288]; полей `created`/`changed` нет в строке; stderr пуст |
| `--status in_progress --created-window this-quarter` | 1.0, `[status, created]` | 0 | `[]`, `complete=true` |
| `--group-id <B24CLI_TEST_GROUP_ID> --created-window this-quarter` | 1.0, `[groupId, created]` | 0 | `[]`, `complete=true`, около 1,5 с |

Выводы:

- `REAL_STATUS` фильтрует на сервере: `scanned` равен числу совпадений, а не 146.
- `CREATED_DATE`/`CHANGED_DATE` с `DATE_ATOM` и offset фильтруют на сервере (просмотрена 1 строка из 146).
- Граница окна по моменту времени на портале не проверена: в выборке нет задач рядом с границей. Её покрывают unit-тесты `DateWindow::matches()`.
- Строка прогресса каждые 1000 задач на портале не наблюдалась: в нём 146 задач. Её покрывает unit-тест на fake transport (2200 строк).

## Не выполнено

- Интеграционный набор `make integration` (создаёт и удаляет задачи на портале) — не запускался в этой сессии.
- Публичный skill задач живёт в отдельном репозитории; перевод «созданные/изменённые за период» → `--created-window`/`--changed-window` ещё не добавлен (задача 6.4).
