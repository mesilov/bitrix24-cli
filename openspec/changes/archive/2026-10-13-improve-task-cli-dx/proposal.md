# Proposal

## Why

В текущей dev человек читает карточку через внутренние поля, получает общую parser error и не может объединить title search со всеми фильтрами task:list. Для milestone 0.2.0 улучшаем эти три ежедневных сценария, сохраняя действующие контракты автоматизации и уже принятые возможности.

## What Changes

- **O1:** упорядоченная human карточка и читаемые таблицы с именами из уже полученных данных, переносом текста и ID fallback; renderer не выполняет запросы. Списки/поиск явно показывают scope, counters, limitApplied и полноту.
- **O2:** конкретные безопасные ошибки ввода с корректным примером; help начинает с обычных действий. README согласуется с runtime; suggestions не исполняются.
- **O3:** task:find получает повторяемый --id, --responsible, --status, --due-before, --where/--where-file и объединяет их с обязательной буквальной Unicode title substring через AND. Уже существующие --group-id, --role и --order сохраняются; --project остаётся недопустимым. JSON id/title array и TSV columns сохраняются.
- Сохраняем имена команд, JSON data/meta/error schemaVersion 1, native quiet/silent/no-interaction, stdout/stderr, коды 0/1/2/3/4/130, default limit 50/max-scan 10000, id DESC и явный --order с id ASC tie-breaker, конечные scan budgets и API policy.
- O4–O8 не расширяют scope этого change. Уже принятые профили, user-команды, роли, results, lifecycle и local upload не удаляются и не объявляются будущими исключениями; новые URL/name resolution, интерактивность, output selectors, шаблоны и Git-сценарии здесь не добавляются.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `cli-experience`: уточняются human presentation, visible completeness, help/errors и совместимые фильтры title search. Пять MODIFIED blocks включают все сценарии актуальной canonical spec, в том числе recent-first scan и явную сортировку.
- `task-role-filtering`: один MODIFIED block согласует композицию --role с новыми фильтрами task:find; identity, роли и API routes сохраняются. Убирается устаревшая фраза о недоступных фильтрах другого незавершённого change.

## Impact

- Сверено 2026-10-13 с origin/dev `0aeb25b68dad641be57e6356222373d4fc9782d3`. PR #11 и planning PR #13 merged; task runtime и canonical cli-experience уже доступны. Это обновление только планирования, не реализация O1–O3.
- Будущая реализация затрагивает ResultPresenter/B24Application, task help, FindTaskCommand, общий src/Console/TaskInputMapper.php, offline tests и README. TaskQuery/TaskListScanner уже поддерживают predicates, dueBefore, роли и сортировку; используем их контракт без отдельного search backend. Новые Composer dependencies и API routes не требуются.
- Сторонние Linear CLI служат UX/code референсами; их библиотеки Rust не переносятся в PHP. Основные подходы и точные pinned sources приведены в design.md и issue.
- Сохраняются принятые API gates и явные REST1 routes, включая participant-role чтение только при выбранном --role accomplice/auditor. Общий task:update не получает запись status; действующие lifecycle-команды сохраняются. Portal acceptance отслеживается отдельно в #42 и не закрывается DX-проверками.

## GitHub issues

- [#12 — Улучшить DX команд задач: читаемый вывод, диагностика и комбинируемый поиск](https://github.com/mesilov/bitrix24-cli/issues/12): покрывает O1–O3, примеры и критерии приёмки; milestone 0.2.0, labels enhancement/documentation, assignee mesilov.
- [PR #13](https://github.com/mesilov/bitrix24-cli/pull/13): первоначальное планирование, уже merged в dev; обновление сохраняет тот же change.
- [#10](https://github.com/mesilov/bitrix24-cli/issues/10) / [PR #11](https://github.com/mesilov/bitrix24-cli/pull/11): принятая первоначальная база. [#42](https://github.com/mesilov/bitrix24-cli/issues/42): отдельная live portal acceptance.
