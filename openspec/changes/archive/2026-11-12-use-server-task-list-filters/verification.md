# Verification

Ветка `bugfix/183-task-list-server-filters` от `dev` 787770e, 12.11.2026.

## Локальные проверки

| Проверка | Результат |
| --- | --- |
| `make unit` | OK, 944 теста, 17 427 проверок; после правки по ревью (aba1379) — 945 тестов |
| `make lint-all` (licenses, php-cs-fixer, phpstan, rector) | exit 0 |
| `make integration --filter "(ListTaskCommandTest\|FindTaskCommandTest)"`, playground | OK, 19 тестов, 485 проверок |
| `make integration --filter "(MyTaskCommandTest\|HandoverTasksCommandTest)"`, playground | OK, 8 тестов; 4 пропуска `task:handover` — нет `B24CLI_TEST_HANDOVER_GROUP_ID`, не связано с change |
| `task:list --help` без подключения | Справка описывает серверные условия, `strict-rest3` и `meta.listRoute` |

До изменений `make unit` на той же базе: OK, 926 тестов.

## Изменённые unit-тесты

Прежние тесты локальной фильтрации группы, исполнителя и постановщика теперь запускаются с `--api-policy strict-rest3`: это сохранённое поведение для строгой политики. Тесты сочетания ролей участников с группой переведены на REST1-фикстуры. Новый серверный маршрут покрыт `tests/Unit/TaskServerListFilterTest.php`.

## Integration: паритет маршрутов

`ListTaskCommandTest::testRoleAndGroupUseTheServerFilterWithLocalRouteParity` создаёт в группе 234 задачу текущего пользователя, задачу пользователя 14 и задачу без группы. Серверный маршрут возвращает только первую. На тех же ID результат совпадает с `strict-rest3` по набору полей и значений, кроме двух отличий:

- `deadline` в REST1 приходит в часовом поясе пользователя (`2026-11-19T19:00:00+06:00`), в REST3 — в поясе сервера (`2026-11-19T16:00:00+03:00`); момент времени одинаковый;
- порядок ключей JSON-строки другой; столбцы TSV и human фиксированы.

Отличия описаны в `docs/task-usage.md` и design.md.

## Повтор #183 на indigo.rarus.ru

Запуск из исходников ветки (`make cli`, Docker, PHP 8.4) с `--env-file` профиля indigo, пользователь вебхука 4084. Временный файл credentials лежал в игнорируемом `runtime/` и удалён после запуска. Повтор собранным standalone-бинарником остаётся частью приёмки сборки.

| Команда | До (b24cli 0.2.0) | После |
| --- | --- | --- |
| `task:list --role responsible --group-id 1096 --all --json` | ~6 мин, 200 × REST3 list + 22 × `tasks.task.get`, `scanned=10000`, `complete=false`, exit 3 | 3 с, 1 × REST1 list, `scanned=0`, `complete=true`, exit 0, `items=[]` |
| `task:list --group-id 1096 --all --json` | не завершилась за 20 мин (остановлена) | 3 с, 1 × REST1 list, `complete=true`, exit 0, задачи 95290, 95289, 95286 (ответственный 4143) |
| `task:find --title задач --group-id 1096 --all --json` | — | 1 с, 1 × REST1 list, `scanned=3`, `matched=1` (95289), exit 0 |

`meta.listRoute` во всех трёх: `apiVersion=1.0`, `serverFilter` = `[groupId, responsibleId]` либо `[groupId]`, `reason=null`.

## Ревью и merge

Замечание Codex: на маршруте REST1 задача без срока приходит с `deadline=null`, и сканер помечал её `missing-predicate-field`. Исправлено в aba1379: `deadline` сравнивается локальным равенством, добавлен `testNullDeadlineIsAValueForLocalEqualityOnTheServerRoute`; `make unit` 945 OK, `make lint-all` exit 0. CI-проверки на aba1379 не запускались. MR #193 слит в `dev` squash-коммитом 3b1c247 12.11.2026, issue #183 закрыто.

## Не проверено

- Выдача под пользователем, сообщившим о сбое; это follow-up в tasks.md.
- Standalone-бинарник с изменениями: нужна сборка через `builds`.
