# Tasks

Планирование для [issue #12](https://github.com/mesilov/bitrix24-cli/issues/12). Все checkbox — будущая реализация/проверка; наличие планирования не закрывает эти задачи. Apply зависит от принятия PR #11 в dev.

## 1. Проверить принятую базу

- [ ] 1.1 Подтвердить принятие task runtime PR #11 в dev и доступность canonical cli-experience; сравнить пять MODIFIED requirement blocks и renderer/query с актуальной базой. Проверка: merged commit/reference и записанный результат сверки; при конфликте обновить change до code edits.
- [ ] 1.2 Проверить issue #12 ↔ improve-task-cli-dx, labels enhancement/documentation и milestone 0.1.0; работать в отдельном worktree от актуальной dev. Проверка: gh issue view, git status/worktree и отсутствие чужих изменений в diff.

## 2. O1 — Human карточки и полнота

- [ ] 2.1 Реализовать semantic order карточки и читаемые колонки из уже доступных данных с names+ID/ID fallback и сохранением дополнительных selected fields. Проверка: offline fixtures и сравнение API call ledger до/после оформления, без новых запросов.
- [ ] 2.2 Реализовать wrapping длинного русского title/description, Unicode width и безопасное отображение markup/control sequences. Проверка: narrow-width fixtures, NO_COLOR/non-TTY/TERM=dumb/no-ansi и quiet/silent tests, значения не теряются.
- [ ] 2.3 Добавить scope/counters/complete/limitApplied summaries и actionable partial reasons. Проверка: complete empty, complete limited, capped partial, page failure, missing predicate data и pagination-no-progress в human/plain/JSON; коды 0/3 сохраняются, plain stdout прежний.
- [ ] 2.4 Обновить README examples O1 и сведения о machine compatibility. Проверка: примеры соответствуют offline fixture output и документируют scope/limit/max-scan без полного project total.

## 3. O2 — Диагностика и справка

- [ ] 3.1 Различать missing/unknown/conflicting input в error boundary и давать безопасные names/suggestions/examples. Проверка: invalidInput fixtures, usage-error/exit 2 и ноль portal calls; неизвестная/неоднозначная write suggestion не выполняется.
- [ ] 3.2 Проверить sanitization parser diagnostics на argv с webhook/token/arbitrary secret text, сохранив действующие Failure codes и quiet/silent. Проверка: subprocess/ConsoleHarness tests human/JSON/stderr без raw argv, secret values или stack trace.
- [ ] 3.3 Упорядочить help task commands: назначение и ordinary examples, затем options/defaults/advanced/ограничения; исправить устаревший README текст об отсутствии команд. Проверка: оба entrypoints help/list/version/completion без credentials/API, примеры полных Symfony имён корректны.

## 4. O3 — Title search AND filters

- [ ] 4.1 Добавить find flags id/responsible/project/status/due-before/where/where-file и общий query validation. Проверка: positive IDs, ISO8601 timezone, allowlists, conflicting where/convenient flags, empty title и multiple stdin consumers дают ожидаемый request либо exit 2 без API.
- [ ] 4.2 Применять title AND predicates на всех страницах, selecting необходимые predicate fields, с документированным server ID filter и bounded local predicates. Проверка: 0/1/many match fixtures, совпадение на второй странице и отсутствие unsupported server filters/REST1 fallback.
- [ ] 4.3 Сохранить find id/title array, TSV columns, id ASC, прежние defaults/limit/all/max-scan и partial semantics. Проверка: scanning tests для missing fields, errors/cap и limited complete, human/plain/JSON не раскрывают дополнительные predicate fields.
- [ ] 4.4 Добавить working examples combined search в help/README и пояснить стоимость локального scan. Проверка: примеры title+project+responsible, title+where-file, due filter и JSON воспроизводятся на offline fixtures; --all не обещает unlimited scan.

## 5. Проверить интеграцию и поставку

- [ ] 5.1 Выполнить актуальную offline suite и repo quality gates; проверить regression JSON/plain/native options/API policy/dry-run/exit status для всех task routes. Проверка: make test, make check/make lint-all согласно принятой базе, logs и отсутствие требуемых portal secrets.
- [ ] 5.2 Проверить соответствие O1–O3 issue и spec, полный diff и strict OpenSpec. Проверка: openspec validate improve-task-cli-dx --strict, git diff --check, неизменные MVP exclusions и отсутствие реализации O4–O8.
- [ ] 5.3 Опубликовать отдельный implementation PR в dev со ссылками issue/change, проверками и явно отдельной portal acceptance #10. Проверка: remote commit, target dev, review artifact и двусторонняя трассировка; не объявлять runtime готовность по planning или offline evidence.

## Workflow follow-up

- После реализации и проверки через openspec-verify-change архивировать change через openspec-archive-change.
- Обновить путь change в issue #12 на фактический openspec/changes/archive/ путь и проверить обе стороны связи.
