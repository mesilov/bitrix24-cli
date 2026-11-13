# Spec Delta

## Purpose

Позволить пользователю и skill назначать и переносить срок задачи относительным днём и явным временем через публичный CLI, получая детерминированный точный ISO8601 с offset без расчётов дат на стороне skill.

## ADDED Requirements

### Requirement: Relative deadline arguments
task:add и task:update SHALL принимать --deadline-day TOKEN вместе с обязательным --deadline-time HH:MM, task:deadline:set — --day TOKEN с обязательным --time HH:MM. Все три SHALL принимать --timezone IANA только вместе с относительным днём. Существующие --deadline и --at SHALL оставаться строго ISO8601 с offset и MUST NOT принимать относительные выражения.

#### Scenario: Create with a relative deadline
- **WHEN** task:add --title "Отчёт" --deadline-day tomorrow --deadline-time 18:00 --timezone Asia/Bishkek запускается 2026-11-13
- **THEN** API получает deadline 2026-11-14T18:00:00+06:00

#### Scenario: Move the deadline
- **WHEN** task:deadline:set 123 --day friday --time 17:00 --timezone Europe/Moscow запускается во вторник 2026-11-10
- **THEN** API получает только поле deadline со значением 2026-11-13T17:00:00+03:00

#### Scenario: Exact input unchanged
- **WHEN** task:deadline:set 123 --at tomorrow или task:update 123 --deadline "friday 17:00" запускается
- **THEN** CLI возвращает usage-error/exit2 до API с подсказкой о --day/--time либо --deadline-day/--deadline-time

### Requirement: Day tokens
TOKEN SHALL принимать today, tomorrow, day-after-tomorrow, monday…sunday, next-week-monday…next-week-sunday и календарную дату YYYY-MM-DD. Имя дня недели SHALL означать ближайший такой день строго после сегодняшнего; next-week-ДЕНЬ — этот день календарной недели, следующей за текущей (неделя с понедельника). Неизвестный токен или несуществующая дата SHALL давать usage-error/exit2.

#### Scenario: Weekday on the same weekday
- **WHEN** --day friday задан в пятницу 2026-11-13
- **THEN** выбирается пятница 2026-11-20, а не текущий день

#### Scenario: Weekday later this week
- **WHEN** --day friday задан в понедельник 2026-11-09
- **THEN** выбирается пятница 2026-11-13

#### Scenario: Day of next week
- **WHEN** --day next-week-friday задан в понедельник 2026-11-09 либо в воскресенье 2026-11-15
- **THEN** в обоих случаях выбирается пятница 2026-11-20 следующей календарной недели

#### Scenario: Explicit calendar date
- **WHEN** --day 2028-02-29 --time 10:00 задан, а --day 2027-02-29 задан в другом вызове
- **THEN** первый вызов даёт 2028-02-29T10:00:00 с offset timezone, второй возвращает usage-error/exit2 до API

### Requirement: Explicit time without hidden default
Время SHALL задаваться явно в формате HH:MM 24-часовых часов (00:00–23:59); секунды SHALL быть равны 00. Относительный день без времени, время без дня и смешение относительного дня с --deadline/--at или с deadline в --fields SHALL давать usage-error/exit2 до API. CLI MUST NOT подставлять час по умолчанию.

#### Scenario: Day without time
- **WHEN** task:add --title "Отчёт" --deadline-day tomorrow запускается без --deadline-time
- **THEN** CLI возвращает usage-error/exit2 до API и требует явное время

#### Scenario: Mixed exact and relative deadline
- **WHEN** task:deadline:set 123 --at 2026-12-01T12:00:00+06:00 --day tomorrow --time 18:00 запускается
- **THEN** CLI возвращает usage-error/exit2 до API

#### Scenario: Invalid time
- **WHEN** --time 24:00, 7:5 или 18:00:30 передан
- **THEN** CLI возвращает usage-error/exit2 до API с допустимым форматом

### Requirement: Deterministic deadline resolution
CLI SHALL фиксировать один now на операцию и вычислять день в выбранном IANA timezone; без --timezone SHALL использоваться локальный timezone окружения CLI. Результат SHALL сериализоваться как ISO8601 с offset этого timezone. Несуществующее (DST gap) и неоднозначное (DST overlap) локальное время SHALL отклоняться usage-error/exit2 с предложением точного ISO8601.

#### Scenario: Month and year rollover
- **WHEN** --day tomorrow --time 09:00 задан 2026-12-31 в Asia/Bishkek
- **THEN** срок равен 2027-01-01T09:00:00+06:00

#### Scenario: Nonexistent local time
- **WHEN** --day 2027-03-28 --time 02:30 --timezone Europe/Berlin задан
- **THEN** CLI возвращает usage-error/exit2 до API: такого локального времени нет

#### Scenario: Ambiguous local time
- **WHEN** --day 2026-10-25 --time 02:30 --timezone Europe/Berlin задан
- **THEN** CLI возвращает usage-error/exit2 до API: время встречается дважды; точный ISO8601 с offset снимает неоднозначность

#### Scenario: Timezone changes the day
- **WHEN** один instant соответствует разным локальным датам в двух явно выбранных timezone и задан --day today
- **THEN** каждый вызов использует сегодняшнюю дату своего timezone и offset этого timezone

### Requirement: Relative deadline must be in the future
Относительный срок, который после разрешения не позже now, SHALL отклоняться usage-error/exit2 до API. Прошедший срок по-прежнему SHALL задаваться только точным ISO8601.

#### Scenario: Earlier time today
- **WHEN** --day today --time 09:00 задан в 15:00 того же дня выбранного timezone
- **THEN** CLI возвращает usage-error/exit2 до API и называет вычисленный срок и now

### Requirement: Resolution visibility and write safety
Dry-run и результат записи SHALL показывать meta.deadlineResolution с day, time, timezone, now и итоговым deadline. Относительный срок SHALL проходить те же проверки, профиль, маршрут REST3 или REST1 (custom fields), dry-run без записи, confirmed/partial/outcomeUnknown и отсутствие автоматического повтора записи, что и точный --deadline/--at.

#### Scenario: Dry-run of a relative deadline
- **WHEN** task:deadline:set 123 --day tomorrow --time 18:00 --timezone Asia/Bishkek --dry-run --json запускается
- **THEN** mutating API не вызывается, а план содержит deadline с offset +06:00 и meta.deadlineResolution с исходными day/time/timezone и now

#### Scenario: Legacy create route
- **WHEN** task:add с --user-fields и --deadline-day tomorrow --deadline-time 18:00 выполняется
- **THEN** REST1 получает DEADLINE с тем же вычисленным ISO8601, что получил бы REST3 deadline

#### Scenario: Unknown write outcome
- **WHEN** запись относительного срока завершается сетевой ошибкой после отправки
- **THEN** результат помечается outcomeUnknown, повтор не выполняется, а meta.deadlineResolution сохраняет вычисленное значение для ручной проверки

### Requirement: Skill deadline translation
Skill SHALL переводить «завтра в 18:00», «перенеси на пятницу в 17:00» и подобные запросы в --deadline-day/--deadline-time или --day/--time с известным timezone, без самостоятельного расчёта даты. Запрос без времени SHALL уточняться у пользователя, а не дополняться часом по умолчанию.

#### Scenario: Request without time
- **WHEN** пользователь просит перенести срок на пятницу без указания времени
- **THEN** skill спрашивает время до вызова CLI и не подставляет его сам
