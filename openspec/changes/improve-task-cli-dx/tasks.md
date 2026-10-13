# Tasks

План для [issue #12](https://github.com/mesilov/bitrix24-cli/issues/12), milestone 0.2.0. База origin/dev `0aeb25b68dad641be57e6356222373d4fc9782d3` сверена 2026-10-13. PR #11/#13 уже merged. Отмечаются только фактически завершённые задачи подготовки; реализация O1–O3 остаётся открытой. Перед apply повторно сверить origin/dev и при изменении контрактов обновить planning.

## 1. Проверить принятую базу

- [x] 1.1 Сверить current dev runtime и canonical cli-experience/task-role-filtering, сохранить все сценарии пяти CLI MODIFIED blocks и уточнить role query block; проверить документальные API ограничения. Проверка: base SHA в proposal/design, PR #11/#13 merged, реальные MCP search/details и действующие официальные sources; strict OpenSpec, 21 исходный CLI сценарий сохранён дословно, все четыре role сценария сохранены с уточнением Role and title search; O1–O3 воспроизведены offline. Это не реализация или portal acceptance.
- [x] 1.2 Проверить issue #12 ↔ improve-task-cli-dx, labels enhancement/documentation, milestone 0.2.0 и assignee mesilov; обновить описание issue под текущие contracts и planning PR. Проверка: gh issue view, отдельный чистый worktree от current dev и planning diff только в этом change, основной dirty checkout сохранён.

## 2. O1 — Human карточки и полнота

- [ ] 2.1 Реализовать semantic order карточки и читаемые колонки из уже доступных responsible/group objects с names+ID/ID fallback и сохранением дополнительных selected fields. Проверка: offline fixtures, имена при explicit --select responsible.name/group.name, default ID fallback, сравнение call ledger/select до/после оформления; renderer не добавляет запросы/projections.
- [ ] 2.2 Реализовать wrapping длинного русского title/description, Unicode width и безопасное отображение markup/control sequences. Проверка: narrow-width fixtures, NO_COLOR/non-TTY/TERM=dumb/no-ansi и quiet/silent tests, значения не теряются.
- [ ] 2.3 Добавить scope/counters/complete/limitApplied/order и при наличии roleFilter summaries с конкретными partial reasons/next steps. Проверка: complete empty/limited, scan cap, следующая page failure, missing-id/missing-predicate-field, pagination-no-progress, role-read-error в human/plain/JSON; первая page error exit1 и Ctrl-C exit130 сохраняются; partial exit3, предупреждение про дубли и совет --max-scan сохраняются; TSV stdout прежний, summary plain в stderr, native quiet/silent.
- [ ] 2.4 Обновить README examples O1 и описания JSON/TSV. Проверка: examples соответствуют offline output, описывают scope/limit/max-scan/order и ID fallback; README описывает текущую первую сборку без сравнений с предварительными версиями и без полного project total.

## 3. O2 — Диагностика и справка

- [ ] 3.1 Различать missing/unknown/conflicting input в B24Application error boundary и действующей request validation, давать безопасные names/suggestions/examples. Проверка: CommandBehaviorTest/ConsoleHarness fixtures, usage-error/exit2 и ноль portal calls, parser --titel и --project с безопасной подсказкой, неизвестная/неоднозначная write suggestion не выполняется.
- [ ] 3.2 Проверить sanitization parser diagnostics на argv с webhook/token/arbitrary secret text, сохранив Failure codes/details и quiet/silent. Проверка: subprocess/ConsoleHarness human/JSON/stderr без raw argv, secret values или stack trace; global --profile/--env-file/--config/--config-dir и native options сохраняются.
- [ ] 3.3 Упорядочить help task commands: назначение и ordinary examples, затем options/defaults/advanced/ограничения; согласовать текущий README с runtime без повторного исправления уже удалённого текста об отсутствии task-команд. Проверка: оба entrypoints help/list/version/completion без credentials/API, полные Symfony имена, --group-id/--role/--order, defaults и combined search examples; профили/users/results/lifecycle/local upload из dev не пропадают из docs/catalog.

## 4. O3 — Title search AND filters

- [ ] 4.1 Добавить find flags --id (repeatable)/--responsible/--status/--due-before/--where/--where-file в FindTaskCommand и общий src/Console/TaskInputMapper.php, сохранив --group-id/--role/--order. Проверка: positive IDs, dates с timezone, current where allowlist/value rules, title validation, conflicts where/where-file и where с ordinary predicates; --title/--id/--role допустимы с where, --project отклоняется, invalid input exit2 без API. Явный --where-file=- и конфликт со stdin consumer --config=- проверены.
- [ ] 4.2 Использовать общий TaskQuery/TaskListScanner для title AND всех predicates/role на каждой странице, selecting обязательные predicate fields и отправляя только id server filter. Проверка: 0/1/many matches и вторая страница, --id+where+title, scalar/participant роли, identity selected profile, отсутствие implicit role/current-user/participant calls; текущий явный REST1 participant read сохраняется только для accomplice/auditor, strict-rest3 exit4 без API, без новых routes/fallback.
- [ ] 4.3 Сохранить find id/title array/TSV, default id DESC, повторяемый --order с allowlist и id ASC tie-breaker, limit50/max-scan10000/--all, scope/counters/meta.order/roleFilter и partial semantics. Проверка: RecentTaskScanTest/GroupIdOptionTest/TaskRoleFilterTest/ScanningAndBindingTest и новые combined fixtures, missing fields/role read error/page error/cap, limited complete, explicit ordering; predicate fields не появляются в output.
- [ ] 4.4 Добавить combined search examples в help/README и пояснить стоимость local scan и explicit participant-role reads. Проверка: title+group-id+responsible, title+where-file+role, повторяемый id+due-before и status+order воспроизводятся offline; --all не обещает unlimited scan, --group-id не обещает server optimization.

## 5. Проверить интеграцию и поставку

- [ ] 5.1 Выполнить make test, make check и make lint-all; проверить regression JSON/plain/native options/API policy/dry-run/exit status для текущего command catalog. Проверка: offline suite и четыре quality gates, логи без portal secrets; актуальные roles/profiles/results/lifecycle/users/local-file contracts сохраняются.
- [ ] 5.2 Проверить O1–O3 issue/spec и полный diff. Проверка: openspec validate improve-task-cli-dx --strict, git diff --check, сохранение всех current scenarios пяти CLI MODIFIED blocks и четырёх сценариев role query block, отсутствие нового O4–O8 scope; offline evidence отдельно от portal acceptance #42.
- [ ] 5.3 Опубликовать implementation PR в dev со ссылками issue/change и проверками; planning update не считается выполнением этой задачи. Проверка: remote commit, target dev, PR artifact, двусторонняя трассировка и актуальный milestone; feature readiness не объявляется по planning validation.

## Workflow follow-up

- После реализации выполнить openspec-verify-change; проверить merged implementation PR в dev и закрытие issue #12.
- Архивировать через openspec-archive-change, синхронизировать delta в canonical specs и доставить архив/синхронизацию в dev через MR, проверив merge.
- Обновить путь в issue #12 на фактический openspec/changes/archive/ путь и проверить ссылку из proposal.
- После сохранения результатов проверить worktree на dirty/untracked/needed ignored/unpublished commits, остановить его окружение, удалить безопасно из другого checkout без --force и проверить git worktree list.
