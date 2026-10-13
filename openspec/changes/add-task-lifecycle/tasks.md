# Tasks

Все задачи ниже относятся к будущей реализации; planning сам по себе их не закрывает. Проверки выполняются в worktree задачи через проектные Docker/Make entrypoints.

## 1. Команды, registration и policy

- [ ] 1.1 Добавить семь lifecycle Command-классов, input mapping и закрытый request/action contract. Проверка: offline help/list, положительный TASK_ID и negative/overflow/unknown-option cases через ConsoleHarness дают ожидаемый ввод либо exit2 до credentials/API.
- [ ] 1.2 Зарегистрировать команды и DI, объявить точные REST1 lifecycle и REST3 preflight/post-read маршруты в CommandCatalog. Проверка: catalog/AsCommand uniqueness, 37 task-команд, прежние 5 connection-команд, 22 исключения и raw status denial; strict-rest3 всех семи действий, включая dry-run, даёт exit4 без provider/API.
- [ ] 1.3 Добавить семь команд в README/development catalogue и help с TASK_ID, REST1 ограничением и примерами. Проверка: названия/количество совпадают с CommandCatalog, текущие команды описаны без обещания portal acceptance и release publication.

## 2. Gateway и preflight

- [ ] 2.1 Реализовать узкий lifecycle gateway/REST1 adapter через общий ApiTransport. Проверка: parameterized unit cases всех семи action требуют ровно правильный method/version, effect=write и единственный payload taskId; object task.id ACK принимается только для выбранной задачи, malformed/null/boolean/foreign-ID отклоняются с outcomeUnknown после отправки.
- [ ] 2.2 Реализовать lifecycle service preflight REST3 карточки и action-specific access. Проверка: нужные поля и true boolean право допускают действие; edit без action права, missing/false/invalid values, foreign task и неправильные условия approve/disapprove не вызывают mutation; error/exit codes соответствуют specs.
- [ ] 2.3 Добавить внутренний bounded native result read для complete при requireResult=true. Проверка: точный tasks.task.result.list/version3/filter.taskId/select/order/pagination, valid bound row, empty/unreadable/malformed/foreign row; requireResult=false не вызывает result read, containsResults=true не снимает проверку, ни один result/chat/file write не выполняется.
- [ ] 2.4 Документировать внутренние маршруты, gates и native-result boundary в development docs. Проверка: имена id/taskId/status/needsControl/requireResult/containsResults/autocompleteSubTasks одинаковы в specs, JSON fixtures и API payload; отсутствие неподдержанных aliases и implicit result creation.

## 3. Execute, actual state и preview

- [ ] 3.1 После ACK выполнить один REST3 post-read и собрать lifecycle OperationResult с data.operation/data.task. Проверка: completed и supposedly_completed различаются, неожиданный корректный concurrent status сохраняется, preflight status не подставляется; request order и task binding проверены unit cases.
- [ ] 3.2 Сохранить confirmed mutation при failed/malformed/incomplete post-read. Проверка: partial/exit3, complete=false, confirmed=true, outcomeUnknown=false, неизвестные поля null и причина; для lost/malformed ACK — exit1/outcomeUnknown=true, нет повтора записи/fallback, безопасная рекомендация task:show.
- [ ] 3.3 Интегрировать dry-run и cancellation в PreparedOperation. Проверка: dry-run проходит safe preflight и показывает plan/current conditions без mutation/post-read; denied gates сохраняются; Ctrl-C до/после отправки даёт exit130, корректные confirmed/outcomeUnknown и ни одного последующего запроса.
- [ ] 3.4 Реализовать human/plain/JSON lifecycle profile и справку форматов. Проверка: один JSON envelope, status и review outcome во всех форматах, TSV taskId/action/confirmed/status/needsControl/requireResult, корректное экранирование и stderr; meta.complete не трактуется как task completed, preview показывает autocompleteSubTasks.

## 4. Собственные live cases и семантика портала

- [ ] 4.1 Добавить отдельные StartTaskCommandTest, PauseTaskCommandTest, DeferTaskCommandTest и RenewTaskCommandTest с command/happy-path groups через production provider. Проверка: собственный happy-path каждой команды, независимый SDK readback, SDK fixtures/cleanup и coverage guard; документировать фактически полученные статусы и права.
- [ ] 4.2 Добавить отдельные CompleteTaskCommandTest, ApproveTaskCommandTest и DisapproveTaskCommandTest. Проверка: complete без контроля, complete с needsControl=true и supposedly_completed, approve принятие и disapprove возврат на доработку; каждый case вызывает только свою CLI-команду, setup/readback/cleanup через SDK.
- [ ] 4.3 Добавить complete cases с requireResult=true без result и с native result; также renewed task с существующим result и dry-run. Проверка: no-result не выполняет переход, bound result допускает серверную проверку, result не создаётся CLI неявно; фактические result status/права/ограничения зафиксированы.
- [ ] 4.4 Выполнить denied-role и wrong-state cases для lifecycle. Проверка: доступный участник без права действия/наблюдатель не может перейти, серверный race отказ не повторяется; отсутствующие роли/credentials/skip явно остаются непроверенными случаями, не выдаются за успех.
- [ ] 4.5 Проверить side effects на собственных fixtures с allowsTimeTracking и autocompleteSubTasks, контролем/result. Проверка: SDK snapshots до/после показывают фактические изменения основной задачи, подзадач и времени; CLI не добавляет скрытых записей/отката. Зафиксировать portal evidence и ограничения, обновить документацию согласно результатам.

## 5. Интеграционные проверки и verify

- [ ] 5.1 Выполнить make unit и проектные обязательные static/lint/check targets. Проверка: все команды/contract/policy/output/registration cases проходят, нет регрессии raw status denial, connection resolution, partial и cancellation; git diff --check чистый.
- [ ] 5.2 Запустить отдельные happy-path и lifecycle portal cases через make integration с явным разрешённым тестовым подключением. Проверка: записаны команды, результаты, роль и ограничения; skips и локальные mock успехи не заменяют live acceptance, чужие fixtures не меняются.
- [ ] 5.3 Проверить соответствие реализации всем scenarios трёх delta specs через openspec-verify-change и openspec validate add-task-lifecycle --strict. Проверка: verification artifact перечисляет evidence, все задачи завершены по факту, GitHub issue/milestone/category и двусторонняя trace согласованы; никаких необоснованно принятых exceptions.

## Workflow follow-up

- После реализации и verify архивировать change через openspec-archive-change; обновить фактический archive path в issue #60 и проверить обе стороны trace.
- Обновить этот draft PR до результата реализации либо открыть соответствующий PR из ветки задачи в dev; указать проверки и оставшиеся ограничения. Merge и release выполняются отдельным согласованным процессом.
