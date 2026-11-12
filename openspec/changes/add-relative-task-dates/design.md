# Design

## Context

См. proposal.md. В dev ff9481e:

- `DeadlineWindowResolver` содержит список `WINDOWS` из 7 мнемоник, фиксирует один now (точность — секунда) в IANA timezone (`--timezone` → `TZ` → `date_default_timezone_get`) и статический `bounds()`. Выдаёт `DeadlineWindow` (deadline/overdue) и `DateWindow` (`FIELDS = created → CREATED_DATE, changed → CHANGED_DATE`).
- `TaskInputMapper::query()` читает `--{deadline,created,changed}-window`. `TaskListRoute::plan()` при temporal-запросе выбирает REST1. `TaskListScanner` читает поле окна как скрытое, локально проверяет `DateWindow::matches()`; `null` даёт `missing-predicate-field`. Completion берёт `suggestedValues` из `WINDOWS`.
- `Rest1TaskListAdapter` уже сопоставляет `closed ↔ CLOSED_DATE/closedDate` и нормализует пустую строку в `null`.
- Срок записи: `TaskInputMapper::patch()` передаёт `--deadline` в `FieldSchema::localPatch`, `setTaskDeadline()` — `FieldSchema::date(--at)`. Строгий ISO-regex отклоняет `tomorrow`. Для custom fields `LegacyTaskFields::BASIC` сопоставляет `deadline → DEADLINE`.
- `WeeklyTaskReport` уже использует `REAL_STATUS=5` вместе с `CLOSED_DATE` для секции closedTasks. Отчёт этим change не меняется.

## Goals / Non-Goals

**Goals:** один детерминированный календарный resolver для поиска и записи; точный ISO8601 на выходе; явная видимость now/timezone в metadata; сохранение всех write-safety и bounded-scan контрактов.

**Non-Goals:** разбор естественного языка в CLI; `--at`/`--deadline` с относительными значениями; относительные `startPlan`/`endPlan`; изменение `--due-before`, task:my, task:report; часы по умолчанию; профильная настройка timezone; переход окон на REST3.

## Decisions

### 1. Calendar module shared by search and assignment

Выделить из `DeadlineWindowResolver` общий класс календаря (например, `TaskCalendar`): получение now/timezone, `bounds(window)`, `day(token)` и `at(day, time)`. Resolver поиска и новый `DeadlineAssignmentResolver` используют его с тем же инъецируемым `Clock`. Так поиск и назначение одинаково понимают timezone и неделю с понедельника, а frozen-clock тесты покрывают одну реализацию. Отдельный resolver для записи без общего календаря отклонён: правила разошлись бы.

### 2. Window table instead of a match per token

`WINDOWS` становится таблицей `mnemonic → (unit, offset)`: day ±N, week/month/quarter с offset −1/0/+1 и rolling `next-7-days`/`last-7-days`. Начало периода считается от `setTime(0,0)` текущего дня, конец — `modify('+1 week|+1 month|+3 months')` от начала. Для rolling: `[today, today+7 days)` и `[today−6 days, today+1 day)` через `modify` по календарным дням, что сохраняет DST. Порядок `WINDOWS` в help/completion: дни, текущие периоды, прошлые/следующие, rolling.

Окна с будущим для created/changed/closed (например `--created-window next-week`) не отклоняются: значение у всех флагов одно, а пустой результат корректен. Дополнительная валидация была бы политикой без пользы.

### 3. `--closed-window` as a third DateWindow field

Добавить `closed → CLOSED_DATE` в `DateWindow::FIELDS` и флаг в task:list/task:find. `DateWindow` получает признак nullable: для `closed` значение `null`/`''` означает «не завершена» и даёт `false`, а не `null`. Непустая нераспознаваемая строка по-прежнему даёт `missing-predicate-field`. REST3-поле `closed` (`datetime | null`) совпадает по имени с CLI/REST1-нормализацией. Metadata — `meta.closedFilter` по образцу created/changed. Текущий статус не добавляется в фильтр. Требование, чтобы задача была закрыта сейчас, задаётся пользователем через `--status completed` (AND в одном REST1 запросе). Альтернатива «CLOSED_DATE + REAL_STATUS=5», как в weekly report, отклонена по согласованию 2026-11-13: она подменила бы факт завершения текущим статусом.

### 4. Relative deadline flags and resolution

Флаги: task:add/task:update — `--deadline-day`, `--deadline-time`, `--timezone`; task:deadline:set — `--day`, `--time`, `--timezone`. Mapper разрешает их до `FieldSchema::localPatch`, т.е. дальше идёт обычное поле `deadline` с ISO строкой. Поэтому REST3, legacy REST1 (`DEADLINE`), профиль, dry-run и классификация результата не меняются.

Правила:

- Token: `today`, `tomorrow`, `day-after-tomorrow`; `monday…sunday` — первый такой день в `(today, today+7]`; `next-week-<day>` — день недели `[monday(today)+7, +14)`; `YYYY-MM-DD` с проверкой `checkdate`.
- Time: `^([01]\d|2[0-3]):[0-5]\d$`, секунды 00.
- Локальное время строится `new DateTimeImmutable("Y-m-d H:i:00", tz)`. Gap обнаруживается, если форматированное обратно `H:i` не совпадает с запрошенным. Overlap — если тот же wall-time с другим offset (проверка `getTransitions` вокруг instant: значение −1h/+1h даёт тот же local time). В обоих случаях usage-error со ссылкой на точный ISO8601.
- Результат `≤ now` → usage-error с вычисленным значением и now.
- Взаимоисключения: day без time, time без day, day + `--deadline`/`--at`, day + `--fields` (при наличии там deadline или вообще: `--fields` уже запрещает смешение с field flags — сохраняем), `--timezone` без day.
- `meta.deadlineResolution = {day, time, timezone, now, deadline}` прокидывается в request и выводится в dry-run и в результате записи, в т.ч. при partial/outcomeUnknown.

Альтернатива с выражением внутри `--at` (`"friday 17:00"`) отклонена по согласованию: смешивает точный и относительный ввод и требует грамматики.

### 5. Validation stays before credentials/API

Все ошибки нового ввода — `Failure::usage` в mapper, т.е. до чтения подключения и сети. Это согласуется с текущими тестами windows. strict-rest3 для `--closed-window` проверяется через существующий temporal route gate (policy-denied/exit4).

## Risks / Trade-offs

- [Портал может не сбрасывать CLOSED_DATE при renew, а может выставлять её при переходе в «ожидает контроля»] → спецификация описывает поведение по фактическому CLOSED_DATE без опоры на статус. Live-проверка на тестовой задаче выполняется только с явного разрешения на запись. До этого поведение portal фиксируется как непроверенное в verification.
- [Неоднозначность «пятница» в пятницу] → закреплено правило «строго после сегодня» и токен `today`. Skill уточняет у пользователя при сомнении.
- [Timezone среды skill и CLI различаются] → skill передаёт явный `--timezone`, metadata показывает выбранный timezone и now.
- [Разрастание help ListTaskCommand/FindTaskCommand] → новые значения перечисляются один раз через общую константу, без дублирования текста.
- [Overlap detection зависит от tzdata PHP] → тесты на Europe/Berlin 2026-10-25/2027-03-28. Нативные бинарники собираются со своей tzdata, что проверяется native smoke.

## Migration Plan

Добавочные флаги и значения; существующие ISO-флаги и 7 окон не меняются. Поставка через feature → dev MR с CHANGELOG. Skill обновляется отдельным MR после доступности команды в сборке. Откат — снятие новых флагов/значений; данные портала не затрагиваются.

## Open Questions

- Фактическое поведение CLOSED_DATE при renew и при переходе в статус 4 на тестовом портале. Это не меняет контракт: результат фиксируется в verification и README.
