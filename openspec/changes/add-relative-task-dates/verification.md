# Verification

Ветка `feature/141-relative-dates`, база `dev` `ff9481e`, рабочее дерево до коммита. Окружение — Docker `php-cli` проекта, PHP 8.4.27.

## Локальные проверки (2026-11-13)

| Проверка | Результат |
| --- | --- |
| `make unit` | OK, 1260 тестов, 19813 assertions (до изменений — 1217) |
| `make lint-all` (licenses, php-cs-fixer, PHPStan, Rector) | OK |
| `make lint` | OK |
| `make cli-reference-check` | карта соответствует 71 публичной команде |
| `openspec validate add-relative-task-dates --strict` | valid |

Новые тесты:

- `tests/Unit/TaskExtendedWindowsTest.php` — прошлые/следующие периоды и rolling-окна (2026-11-13, переход года 2027-01-05, 31 января, високосный февраль); next-7-days через DST Europe/Berlin (169 часов, 7 календарных дней); overdue + next-7-days; все окна для всех полей дат; `--closed-window`: payload REST1 без DEADLINE/REAL_STATUS, null/'' → несовпадение без incomplete, нераспознаваемая дата → `missing-predicate-field`/exit3, возобновлённая задача и `--status completed`, usage-error и strict-rest3 policy-denied/exit4 без сети, help/completion.
- `tests/Unit/TaskRelativeDeadlineTest.php` — 15 сценариев разрешения (включая пятницу в пятницу, next-week-* с понедельника и воскресенья, переход года, 2028-02-29, летний offset, timezone меняет «сегодня»), 11 отказов (неизвестный токен, 2027-02-29, неверное время, DST gap 2027-03-28 02:30 и overlap 2026-10-25 02:30 Europe/Berlin, срок не позже now, неверный timezone), неполные аргументы без часа по умолчанию; командные контракты: REST3 `deadline` для task:add, REST1 `DEADLINE` с `--user-fields`, sparse patch task:update/task:deadline:set, dry-run без записи с `meta.deadlineResolution`, outcomeUnknown без повтора с `error.details.deadlineResolution`, 10 неверных вызовов как usage-error/exit2 до credentials/API, help/completion.
- Существующие `DeadlineWindowTest`, `TaskDeadlineFilterTest`, `TaskDateWindowFilterTest` проходят. В `TaskDateWindowFilterTest` изменилось только ожидаемое сообщение ошибки `--timezone` без временного флага: в список флагов добавлен `--closed-window`.

Ручной запуск CLI в контейнере (без портала): `task:deadline:set 123 --day friday --time 17:00 --timezone Europe/Moscow --dry-run --json` вернул план с `deadline 2026-11-13T17:00:00+03:00` и `meta.deadlineResolution` (now 2026-11-12T23:19:59+03:00, четверг). `task:add ... --deadline-day tomorrow --deadline-time 18:00 --timezone Asia/Bishkek --dry-run` вернул `2026-11-14T18:00:00+06:00`. `task:deadline:set 123 --at tomorrow` дал usage-error с подсказкой `--day/--time`. `task:list --closed-window this-week --api-policy strict-rest3` завершился с exit 4.

## Не выполнено

- **Native binary (4.3):** сборка через `builds`/CI не запускалась; нужен отдельный запрос на промежуточную сборку.
- **Live (4.4):** read-only проверка новых окон и `--closed-window` на портале не выполнялась. Не проверено поведение портала, которое не описано в документации: сбрасывается ли CLOSED_DATE при renew и выставляется ли при переходе в «ожидает контроля». Для этого нужно отдельное разрешение на запись в тестовую задачу.
- **Skill (4.5):** bitrix24-cli-skill не обновлялся; это отдельный MR после поставки флагов в сборку.
