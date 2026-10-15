# Verification: research-task-command-surface

Дата: 2026-10-08. Область проверки: завершённое исследование и проект команд; runtime CLI не меняется. База: dev 5d236a0; issue [#10](https://github.com/mesilov/bitrix24-cli/issues/10), milestone 0.1.0, label documentation.

## Результат по измерениям

| Измерение | Результат |
| --- | --- |
| Completeness | 6/6 документальных задач подтверждены; Spec Coverage — Not applicable, skip_specs: true |
| Correctness | Индекс/матрица/источники/SDK сверены статически; Requirement Implementation Mapping и Scenario Coverage — Not applicable, runtime specs намеренно пропущены |
| Coherence | Design Adherence подтверждён: предложения, версии, SDK gaps и ограничения явно разделены; Code Pattern Consistency — Not verified: runtime-код не изменялся |

CRITICAL: 0. WARNING для завершённой документационной области: 0. SUGGESTION: 0. В выполненных проверках критических проблем не найдено. Code Pattern Consistency не проверялся, поскольку приложение не изменялось. Это не оценка работоспособности будущих команд на портале.

## Выполненные проверки и доказательства

- `openspec validate research-task-command-surface --strict`: exit 0, change valid; informational message подтверждает skip_specs и допустимость нулевых delta specs.
- `openspec status --change research-task-command-surface --json`: proposal/design/tasks done, specs skipped; нет delta specs для синхронизации.
- Проверка данных временным Python/JavaScript-скриптом: набор 98 пар method/path полностью равен извлечённому официальному индексу; нет повторяющихся пар. Дополнительно 6 записей, всего 79 различных команд, 22 P0.
- Для каждой записи проверены соответствующие source URL и command/syntax в Markdown, существование source path в recursive tree закреплённого b24restdocs и существование SDK file path в установленной версии. Проверены относительные Markdown-ссылки и разделители таблиц.
- SDK inventory содержит 61 literal Core call в Task services. Все wrapper mappings сверены по method/function/path/version; core-gap проверен на отсутствие подходящего literal call. В матрице 62 wrapper records (включая повторное использование обёрток), 4 legacy-batch, 35 core-gap, 1 composite, 2 outside-task. В planner явно сохранено различие регистра: индекс getList, actual SDK getlist.
- composer.lock содержит b24phpsdk 3.7.0 и SHA 8ebd4c154d5557db949b0196825f347a3a6c5bf0, совпадающие с матрицей. Детальная схема всех оставшихся payload пока не проверена; это явно обозначенные эскизы.
- Примеры используют существующий Makefile argument ARGS. Прикладные команды в них ещё отсутствуют.
- GitHub issue прочитан после обновления: ссылка/идентификатор change, milestone 0.1.0 и label documentation сохранены. В proposal находится полный URL issue #10. После архивирования путь в issue обновляется и повторно проверяется при публикации.
- `git diff --cached --check`: exit 0. Staged diff ограничен каталогом этого change; PHP, зависимости, CI и openspec/specs не изменялись. Основной checkout пользователя не использовался для записи материалов.

## Границы и завершение

Не выполнялись: установка сторонних CLI/MCP, их тесты/CI, tools/list официального hosted MCP, запросы к тестовому порталу, PHP runtime acceptance. Такие проверки не входят в документационный change; они нужны после принятия MVP в отдельном implementation change.

Архивирование завершает исследование и проект интерфейса. Оно не принимает команды как действующие требования. Публикация ветки/PR и обновление archive path в issue — workflow follow-up; их состояние подтверждается на GitHub после отправки.
