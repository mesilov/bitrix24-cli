# Live acceptance EMP-09 — 16.10.2026

17/17 custom live-проверок PASS. Отчёт предварительный: выполняются четыре repository integration tests; собственный проект 238 ещё существует и будет удалён до merge. Все custom fixtures обеих попыток уже удалены.

База dev: `0caf9db62fce3f91852aafc77fd9b25cb3f9bde2`; обычный public CLI/ApplicationFactory, writable config-dir, реальный scope guard. Fixtures и независимые чтения выполняются SDK. Секреты не включены.

| Case | Check | Exit CLI | Actual status |
|---|---|---:|---|
| deferred-filter-and-ID-exclusions | PASS | 0 | deferred |
| source-filter-pending | PASS | 0 | pending |
| source-filter-in_progress | PASS | 0 | in_progress |
| source-filter-deferred | PASS | 0 | deferred |
| source-filter-supposedly_completed | PASS | 0 | supposedly_completed |
| target-pending | PASS | 0 | pending |
| target-in_progress | PASS | 0 | in_progress |
| target-deferred | PASS | 0 | deferred |
| target-completed | PASS | 0 | completed |
| target-supposedly_completed | PASS | 0 | supposedly_completed |
| controlled-review-to-completed | PASS | 0 | completed |
| controlled-completion-and-approval | PASS | 0 | completed |
| required-result-missing-partial | PASS | 3 | pending |
| required-result-present-completed | PASS | 0 | completed |
| review-without-control-rejected-before-assignment | PASS | 3 | None |
| creator-cannot-force-review-through-native-complete | PASS | 3 | completed |
| employee-self-mode | PASS | 0 | deferred |

PASS включает ожидаемые отказы и partial, не только успешные передачи. Проверены четыре source-status, deduplicated exclusions, пять целей, complete → approve, обязательный native result и self mode. Для положительных исходов статус/исполнитель независимо прочитаны пользователями 1 и 16. У исключённой и не подходящей по статусу fixtures карточки неизменны.

Первый scope-only повтор выявил reset source → pending, 6 из 15 проверок FAIL. После исправления выполнена вся матрица заново; эти FAIL не включены в финальные 17 PASS. Complete самим автором может сразу дать completed; CLI возвращает partial для запрошенного review и не подтверждает другую цель. Ограниченные non-admin права, потери ответов и побочные эффекты на подзадачи live не моделировались.

Локальные проверки: 893 unit tests / 16881 assertions, make check, make lint-all (4 проверки), CLI reference 63 команды, OpenSpec strict 27/27. Финальный receipt и verify добавляются перед ready/merge.
