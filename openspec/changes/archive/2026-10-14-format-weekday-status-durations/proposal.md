# Proposal

Завершено и архивировано 14.10.2026 после выполнения 6/6 задач и проверок. PR [#137](https://github.com/mesilov/bitrix24-cli/pull/137) merged в dev как `35ee28e641c4173eff13e95f4c3f655ff6786223`; issue #136 закрыто. Актуальные требования синхронизированы в `openspec/specs/task-status-durations/spec.md`.

## Why

Секунды неудобны для чтения длинных периодов. Пользователь согласовал узел seconds/hours/days и исключение только субботы/воскресенья по серверному времени, без графика рабочего дня и преобразования дат в пользовательскую зону.

## What Changes

- **BREAKING**: totals.<status> становится объектом seconds/hours/days; seconds целые, hours/days округляются до двух знаков независимо из точной суммы. precision отсутствует.
- Интервалы получают те же три единицы; elapsedBetweenEventsSeconds остаётся исходной диагностической разницей меток.
- Исключать выходные в offset серверного created, days=seconds/86400. Сохранять исходные даты/offset, asOf и evidence. В meta явно указать weekdays-only и источник календаря.
- Сохранять все completeness/lifetime/chain/tail gates; неподтверждённые единицы null.
- Обновить human/TSV, help/docs, unit/integration проверки; пересчитать сохранённую live fixture 6372 с 55 переходами.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- task-status-durations: представление единиц и календарь без выходных.

## Impact

Calculator, ResultPresenter/TaskHumanRenderer, справка, тесты и документация. API-маршруты и имена полей Bitrix24 не меняются; новые запросы, зависимости, часовые пояса пользователя, праздники и рабочие часы не добавляются. Старый архив #121 остаётся историческим доказательством.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/136 — milestone 0.2.0, продолжение #121.
