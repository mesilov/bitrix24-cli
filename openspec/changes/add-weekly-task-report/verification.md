# Проверка реализации add-weekly-task-report — 17.10.2026

## Результат

Реализация сохранена отдельно от постановки: worktree `/private/tmp/bitrix24-cli-issue-168`, ветка `feature/168-weekly-task-report`, исходный baseline dev `0caf9db62fce3f91852aafc77fd9b25cb3f9bde2`. Issue [#168](https://github.com/mesilov/bitrix24-cli/issues/168); MR постановки [#169](https://github.com/mesilov/bitrix24-cli/pull/169) влит в dev commit `c6295051a61d48b8821a00d6766c4585bf8ccb08` после 13 SUCCESS checks на head `1badbd4942d966964b0d8411a968b5cf225d17a2`, без открытых review threads. Все шесть post-merge workflows этого commit также SUCCESS. Runtime-ветка объединена с этой dev и опубликована отдельно в [MR #173](https://github.com/mesilov/bitrix24-cli/pull/173); runtime-код в MR #169 не входит.

| Измерение | Проверенный результат |
| --- | --- |
| Completeness | 15/16 задач после фиксации этих проверок; 13 требований сопоставлены коду, 31 сценарий прочитан; остаётся live-приёмка |
| Correctness | Offline календарь, фильтры и четыре показателя, partial, native payload, policy/scope, форматы и регистрация проверены; portal-поведение не проверено |
| Coherence | Существующие CommandRunner, Clock, API policy, scope manifest и классический list adapter сохранены; отдельный report port и общий read primitive |

Это промежуточный результат реализации, не готовность к архивированию или portal acceptance.

## Команды и evidence

Compose image собран для конкретного worktree; `make docker-init` не смог создать новую сеть из-за исчерпания Docker address pools. Чужие сети не удалялись. Locked зависимости установлены в vendor этого checkout через существующую bridge-сеть. Все offline проверки затем выполнены тем же собранным PHP 8.4.26 image с bind mount только этого worktree и `--network none`, user 501:20.

| Проверка в контейнере worktree | Результат |
| --- | --- |
| vendor/bin/phpunit --testsuite unit-tests | exit 0 после исправления P2 review; 926 tests / 17328 assertions |
| ReportWeekTest + WeeklyTaskReportTest + TaskDeadlineFilterTest | exit 0; 46 tests / 402 assertions |
| ReportTasksCommandTest + WeeklyTaskReportTest + TaskDeadlineFilterTest | exit 0 после P2 fix; 46 tests / 575 assertions, включая восемь pagination cases |
| vendor/bin/phpstan analyse --memory-limit=2G | exit 0; 390 файлов, 0 ошибок |
| vendor/bin/php-cs-fixer check | exit 0; 0/390 файлов требуют исправления |
| vendor/bin/rector process --dry-run | exit 0 |
| vendor/bin/composer-license-checker | exit 0; allowed licenses |
| composer validate --strict | exit 0 |
| composer check-platform-reqs | exit 0 |
| PHP syntax src/config/tests и два CLI entrypoints | exit 0; 388 PHP файлов плюс bin/console и bin/b24cli |
| php scripts/cli-reference.php --check | exit 0; 64 публичных команды |
| openspec validate add-weekly-task-report --strict | exit 0 |
| git diff --check | exit 0 |
| Изменённые локальные ссылки/JTBD | 0 отсутствующих файлов; 25 уникальных ID (9 PM / 7 AM / 9 EMP) |
| build/tests/smoke.py syntax | Python AST parse: OK; native packaged smoke не запускался |
| Integration: --group command:task:report | 1 test, 0 assertions, 1 skipped без webhook; это не live-приёмка |

Первая полная проверка выявила два расхождения (старое ожидаемое число Command-классов и англоязычное описание timezone). Они устранены; исходная suite прошла 914 tests / 17170 assertions, после sync dev с исправлениями #170 — 918 / 17280 на `3bbffbd`. Последующее объединение постановки #169 (`e699cbf`) изменило только документацию; `openspec validate` и CLI map check повторно успешны. Первые style/Rector замечания применены только к новым файлам, обязательные проверки повторены.

## Сопоставление требований

| Контракт | Реализация | Evidence |
| --- | --- | --- |
| Проект обязателен, optional responsible, без default.task | TaskInputMapper::reportTasks, ReportTasksRequest | ReportTasksCommandTest::testNativePayloadWholeProjectAndOptionalResponsible / testUsageAndStrictPolicyBeforeCredentialsOrApi |
| Календарная неделя, IANA/DST, один reportAt, effectiveTo | ReportWeekResolver / ReportWeek | ReportWeekTest (границы, несуществующие/будущие даты, DST, новый год, microseconds) |
| Закрытые за период и повторное открытие | WeeklyTaskReport::collect / invalid | WeeklyTaskReportTest: четыре показателя, creation boundary, reopened; server filter REAL_STATUS=5 + CLOSED_DATE |
| Текущий контроль и текущая работа | WeeklyTaskReport: REAL_STATUS=4/3 | Старый awaiting-control fixture и исключение pending; current snapshot metadata |
| Новые независимо от текущего статуса | WeeklyTaskReport: CREATED_DATE | Одна задача участвует в new и closed; границы и отсутствующие/неверные даты |
| Текущие наблюдения отделены от прошлого периода | reportAt / observedUntil / snapshotAtomic=false | Unit metadata assertions, human/plain подписи и docs/task-weekly-report.md |
| REST1 native fields, read policy и scope | Rest1TaskListAdapter::reportPage / read, ReportTasksHandler, CommandCatalog | Command unit payload, strict-rest3 до credentials/API, manifest task/tasks; общая scope validation suite |
| Pagination/budget/dedup/partial | WeeklyTaskReport::collect | 51 rows, точная граница, общий budget, page error, missing fields, conflicts, unverified next, cancellation |
| Human/plain/JSON | WeeklyReportRenderer / ResultPresenter | Согласованные counts, literal null, TSV escape, текущие подписи; RussianHelpTest |
| PM-09 и post-MVP admission | docs/task-jtbd.md и delta specs | Статусы runtime/acceptance разделены; linked issue/change и 25 anchors |
| Точный runtime-каталог | CommandCatalog + task-console-runtime delta | ContainerRegistrationTest, IntegrationCommandCoverageTest, CLI map: 50 task / 64 public |

Все 13 ADDED/MODIFIED requirements имеют соответствующий код или документацию. Сценарии прочитаны и сопоставлены с проверками; это не утверждение о выполнении всех бизнес-сценариев на портале.

## CI и review MR #173

Исходный head `cb8f78967332402123505fda30528819cbf94874` прошёл 15/15 CI checks. Reviews, inline threads и discussion проверены спустя пять минут после открытия. Найдено одно P2: общий REST1 adapter терял total и признак неполноты страницы, из-за чего отсутствие next при total больше количества строк превращалось в точный count.

Добавлены восемь command-level cases: отсутствующий next на первой/следующей странице, total меньше количества строк, пустая страница с положительным total, лишнее продолжение, корректные несколько страниц, необязательный total и точная пустая выборка. До исправления пять cases FAIL, после — все PASS. Adapter сверяет offset + rows с optional total/next и передаёт Page.complete через нормализацию. Целевая suite с deadline-регрессией: 46 tests / 575 assertions, exit 0; PHPStan, CS-Fixer и Rector повторно успешны.

Исправление опубликовано в `34be4e6ce016722df6e62e7586ca12c5e465cfae`: полная suite 926 tests / 17328 assertions и 15/15 CI checks SUCCESS на этом head. P2 thread `PRRT_kwDOVACKOM6tU3NU` закрыт после исправления и проверок. Повторный Codex review этого commit завершён 16.10.2026 18:29:55 UTC без findings; открытых threads и активных CHANGES_REQUESTED нет. Это evidence проверенного runtime; последующие правки proposal/tasks/этого отчёта меняют только документацию, их финальный head также требует свежего зелёного CI.

Задача 4.5 выполнена: спецификация сверена, актуальная dev объединена, issue ↔ change ↔ MR #173 связаны, changelog/JTBD/API fields проверены; CI/review runtime подтверждены. MR реализации оставлен открытым согласно поручению; merge, закрытие issue и архивирование change ещё не выполнены.

## Незавершённое и ограничения

1. **CRITICAL для завершения change: задача 4.4.** Live-приёмка не выполнена: нет предоставленного для этой сессии тестового подключения в worktree. Не проверены реальные права/пагинация REST1, создание/закрытие/повторное открытие fixtures и их cleanup. Отдельный integration happy-path подготовлен и явно skipped; запустить на разрешённом портале с B24CLI_TEST_GROUP_ID и сохранить sanitized evidence.
2. **WARNING / Not verified:** native binary smoke для нового runtime не запускался; изменённый harness проверен только по синтаксису и source CLI-контрактам. Installer checks Linux/macOS проверяют установщик, не доказывают native smoke нового runtime. Проверить реальные artifacts в рамках последующей поставки.

Остальных spec/design расхождений в выполненных offline проверках не найдено. Не архивировать change и не закрывать issue по этим результатам; задача 4.4 остаётся незавершённой. Основной checkout и несвязанные worktrees сохранены.
