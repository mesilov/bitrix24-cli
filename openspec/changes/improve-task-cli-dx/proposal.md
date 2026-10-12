# Proposal

## Why

В task runtime из issue #10 / PR #11 человек читает карточку через внутренние поля, получает общую parser error и не может объединить title search с обычными фильтрами. Для milestone 0.1.0 улучшаем эти три ежедневных сценария, сохраняя действующие контракты автоматизации и исключения MVP.

## What Changes

- **O1:** упорядоченная human карточка и читаемые таблицы с именами из уже полученных данных, переносом текста и ID fallback; renderer не выполняет запросы. Списки/поиск явно показывают scope, counters, limitApplied и полноту.
- **O2:** конкретные безопасные ошибки ввода с корректным примером; help начинает с обычных действий. README согласуется с runtime; suggestions не исполняются.
- **O3:** task:find объединяет обязательную буквальную Unicode title substring с совместимыми ID/responsible/project/status/due/where filters через AND. JSON id/title array и TSV columns сохраняются.
- Сохраняем имена команд, JSON data/meta/error schemaVersion 1, native quiet/silent/no-interaction, stdout/stderr, коды 0/1/2/3/4/130, конечные scan budgets и API policy.
- O4–O8 (URL/name lookup, интерактивность, output selectors, профили, рабочие сценарии/Git/lifecycle) остаются дальнейшими направлениями issue, вне задач этого change.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `cli-experience`: уточняются human presentation, visible completeness, help/errors и совместимые фильтры title search. Дельта адресует существующую capability PR #11, не создаёт параллельную DX capability.

## Impact

- Базовая реализация: PR #11, исследованный commit `7c14c85d4ab28f91dcbfb56bc90b282f3fb32551`. Текущая `dev` ещё не содержит task runtime и canonical `cli-experience`: apply зависит от принятия актуальной базы PR #11 и сверки изменений. Структурная валидация планирования не доказывает применимость дельты к сегодняшней `dev`.
- Будущая реализация затрагивает ResultPresenter/B24Application, task help, FindTaskCommand/TaskInputMapper/TaskQuery/scanner, offline tests и README. Новые Composer dependencies и API routes не требуются.
- Сторонние Linear CLI служат UX/code референсами; их библиотеки Rust не переносятся в PHP. Основные подходы и точные pinned sources приведены в design.md и issue.
- Не возвращаются исключённые task:my, сводки, results, lifecycle, dependencies/subtask:add; status write остаётся запрещён. Portal permission/runtime acceptance из #10 не закрывается DX-проверками.

## GitHub issues

- [#12 — Улучшить DX команд задач: читаемый вывод, диагностика и комбинируемый поиск](https://github.com/mesilov/bitrix24-cli/issues/12): покрывает O1–O3, примеры и критерии приёмки; milestone 0.1.0, labels enhancement/documentation.
- [#10](https://github.com/mesilov/bitrix24-cli/issues/10) / [PR #11](https://github.com/mesilov/bitrix24-cli/pull/11): зависимая база 30 task MVP команд; данный change не изменяет их незавершённую portal acceptance.
