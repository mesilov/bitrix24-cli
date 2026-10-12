# Проверка архитектурного проектирования

Дата: 2026-10-09. База сравнения — commit `885890be6081ae4bb955fe0f7d931f3f1ba5b53c`, branch `feature/10-research-task-command-surface`, worktree issue #10. Проверяется только планирование; PHP runtime/portal acceptance отсутствуют.

## Последующая реализация

Этот отчёт относится к исходному documentation-only этапу. Пользователь разрешил реализацию 2026-10-09; актуальные code/test результаты и открытая portal acceptance находятся в [implementation-verification.md](implementation-verification.md).

## Артефакты

- Proposal, design, delta `task-console-runtime`, карта 30 команд и tasks существуют; OpenSpec status сообщает planning complete для 4/4 артефактов. Это file/artifact status, не реализация.
- Delta содержит 11 requirements / 17 WHEN–THEN scenarios; requirement descriptions укладываются в 500 characters. Новая capability пока не синхронизируется в `openspec/specs/` и не архивируется.
- 27 implementation/verification tasks открыты, 0 выполнены. Production auth/storage — отдельный prerequisite для portal checks; интерфейс и offline substitute описаны без выдуманного подключения.
- Выбран Console + DependencyInjection standalone, без FrameworkBundle/Kernel. DI зависимость только предложена, composer/launcher/src/config не изменены.

## Статическая согласованность

- Полный каталог: 59 уникальных имён, 30 MVP / 0 pending / 29 post-MVP, API research classification 43/16 сохранена. Единственное изменение release stage — `task:subtask:add` из MVP в post-MVP.
- 14 REST1 admissions byte-identical к базе; methods/method_routes, sources, SDK gaps, syntax, effects и исходные JTBD links всех 59 команд сохранены. Stale note add и соответствующий design приведены к уже существующему UX: ordinary/advanced fields отдельно. Это не новый API маршрут.
- Все 19 исходных coverage rows/conditions/acceptance/API assessments сохранены; только MVP partition PM-01 сокращён. Counts: 6 mapping-unchanged / 9 mapping-reduced / 4 empty; исходные API оценки 7/12 и 4/14/1 неизменны.
- В `command-map.md` ровно 30 имён и уникальных Command classes; 28 Request/Handler stems, только UpdateTask используется тремя командами. Основные method/version совпадают с JSON catalogue для каждой строки. Дополнительные preflight явно отделены от source evidence; 12 обычных output profiles и отдельный plan profile определены.
- Canonical/delta task-mvp-scope совпадают: 6 requirements / 16 scenarios. Task-workflows и cli-experience delta/main byte-identical к базе. Никакая post-MVP команда не попала в architecture map.
- Markdown tables, парность fences и относительные ссылки новых документов/затронутого планирования проверены; случайные patch markers в scope удалены. Изменения tracked/untracked project files ограничены `openspec/` этого worktree; основной checkout не редактировался.

## Выполненные проверки

- `openspec validate --specs --strict` — 5 passed, 0 failed.
- `openspec validate define-task-jtbd --strict` — valid.
- `openspec validate design-task-console-architecture --strict` — valid.
- Статическая сверка JSON/Markdown/scope/map с базой — passed, включая exact sets/counts/route versions/handler sharing и отсутствие изменений code/dependencies.
- `git diff --check` — passed.

## Граница доказательств

Проверены repo/lock, закреплённые исходники Console/SDK и официальные документационные контракты. Это read-only исследование; скачанные исходники не исполнялись. PHPUnit/CommandTester/ApplicationTester, DI compile, HTTP transport, SIGINT, реальные task/chat/time/checklist/participants/history операции **не выполнялись**: приложение ещё не реализовано, vendor и настроенного подключения в worktree нет.

В proposal указан [issue #10](https://github.com/mesilov/bitrix24-cli/issues/10). При публикации этого planning результата существующий [PR #11 → dev](https://github.com/mesilov/bitrix24-cli/pull/11) и описание issue получают `design-task-console-architecture`, путь `openspec/changes/archive/2026-10-12-design-task-console-architecture/` и проверку обратной трассировки. Runtime acceptance и merge не заявляются.

## Решение о приёмке RC 0.1.0 — 12.10.2026

По явному решению пользователя непроверенные части portal tasks 5.2–5.4 и связанного `define-task-jtbd` task 2.3 перенесены в milestone **0.2.0**, [#42](https://github.com/mesilov/bitrix24-cli/issues/42), change `complete-task-portal-acceptance` (`openspec/changes/complete-task-portal-acceptance/`). Они исключены из обязательной приёмки RC 0.1.0; перенос не является новым runtime proof. Уже выполненные 30 собственных happy-path и исходные records сохраняются.

Границы RC: denied-role matrix не подтверждена; history/elapsedTime completeness частична; file attachment ACK без независимого membership; reassignment только на текущего пользователя; native REST3 filled auditors limitation сохраняется для task:list, task:show использует заранее выбранную композицию чтений без error-triggered fallback/retry. Binding/policy/partial/outcomeUnknown/cancellation и 29 post-MVP исключений сохраняются. #19/#23/native packaging остаются критериями RC. [Полный список ограничений](../../../../docs/rc-0.1.0.md).
