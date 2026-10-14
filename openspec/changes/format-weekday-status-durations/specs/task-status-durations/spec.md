# Spec Delta

## MODIFIED Requirements

### Requirement: Traceable intervals and status totals
JSON SHALL сохранять asOf, даты/offset, status/verified/evidenceIds и elapsedBetweenEventsSeconds. Интервалы и totals.<status> SHALL содержать seconds (целое), hours/days (до двух знаков), независимо округлённые из точной длительности; дни равны 24 часам. Для неподтверждённого времени все три единицы SHALL быть null. precision SHALL отсутствовать. Human/plain SHALL показывать единицы и неизвестность.

#### Scenario: Known boundaries but unverified occupancy
- **WHEN** две временные метки известны, но полнота между ними не доказана
- **THEN** elapsedBetweenEventsSeconds содержит разницу, seconds=null и verified=false; суммарное время по статусу не подменяется этой разницей

#### Scenario: Independent rounding after aggregation
- **WHEN** completed содержит 48.621677 точных секунд либо повторные интервалы одного статуса дробные
- **THEN** итог содержит seconds=49, hours=0.01, days=0; часы/дни не вычисляются из округлённых секунд, totals не складываются из округлённых интервалов, precision отсутствует


### Requirement: Calendar time is distinct from effort
Расчёт SHALL учитывать все часы понедельника–пятницы по серверному offset создания, исключая субботу и воскресенье, включая ночи и время completed. Рабочие часы, праздники и трудозатраты SHALL не влиять на расчёт. Точные суммы до округления SHALL покрывать только буднюю часть создания → asOf без пропусков/двойного начисления; meta SHALL указывать weekdays-only и календарь.

#### Scenario: Calendar period exceeds tracked effort
- **WHEN** задача находится в статусе будние сутки, а учёт времени содержит один час
- **THEN** календарная длительность составляет 86400 секунд независимо от трудозатрат и рабочих часов

#### Scenario: Weekend between Friday and Monday
- **WHEN** интервал длится от пятницы 16:00 до понедельника 10:00 в серверном offset
- **THEN** учитываются 18 часов, days=0.75, seconds=64800; суббота/воскресенье не учитываются, исходные даты сохранены


## ADDED Requirements

### Requirement: Server offset determines excluded weekdays
CLI SHALL определять выходные по offset подтверждённого серверного created (либо единственного доказанного NEW). Пользовательская timezone и timezone хоста SHALL не применяться. Offset SHALL быть указан в meta.calendar с timezoneSource=server; календарь не SHALL угадывать IANA-зону или праздники. Вычисления SHALL учитывать абсолютную asOf, сохраняя исходные строковые даты.

#### Scenario: Client UTC cutoff crosses server midnight
- **WHEN** asOf выражен в UTC, а created и история имеют серверный offset +03:00 либо отрицательный offset
- **THEN** суббота/воскресенье определяются в указанном серверном offset независимо от зоны хоста, а enteredAt/leftAt и evidence остаются исходными
