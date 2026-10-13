# Tasks

## 0. SDK file mechanisms and reuse decision

- [ ] 0.1 Исследовать файловую работу locked b24phpsdk: Infrastructure\Filesystem\Base64Encoder, Symfony Filesystem/Mime wiring, Disk Folder upload service/results и unit/integration fixtures. Зафиксировать в design таблицу кандидатов, выбранное reuse/adaptation и причины отклонения; проверка — pinned source paths/versions и контрактные пробы binary Base64, bounded read, positive ID, route/version/accounting/cancellation, без передачи secrets. Приоритет готовым SDK API/подходам, собственный код только для документированных gaps.

## 1. CLI modes and bounded local input

- [ ] 1.1 Добавить --file/--folder-id/--name в AttachTaskFilesCommand, явный local mode в request и mapping взаимоисключающих режимов. Проверка: CommandBehavior/Contracts tests охватывают все missing/conflicting inputs, positive IDs и existing repeatable --file-id; invalid input даёт usage-error/exit2 без credentials/API.
- [ ] 1.2 Добавить bounded binary local source через внедрённую Symfony Filesystem/Path абстракцию и совместимые SDK helpers по решению 0.1; объявить symfony/filesystem прямой зависимостью при прямом использовании, низкоуровневое bounded чтение изолировать внутри local source: regular/readable local file, reject URL/wrapper/stdin/directory/special files, UTF-8 filename <=255, basename/--name, size <=10485760, bounded read и проверки file handle до mutation. Проверка: unit cases 0/limit/limit+1, NUL bytes, Unicode/space/Windows drive paths, symlink/рост/нечитаемый файл; ни один invalid source не блокируется/не отправляется.
- [ ] 1.3 Обновить help/command catalog и README примерами двух режимов, требованиями disk/tasks, --name и лимитом CLI 10 MiB. Проверка: offline help без credentials содержит рабочую syntax, no-stdin/explicit-folder/filename/collision ограничения; документация до 1.0 описывает текущий интерфейс первой сборки.

## 2. API adapter, dynamic policy and dry-run

- [ ] 2.1 Добавить Disk upload application port/adapter и bootstrap binding, переиспользуя совместимые SDK upload service/results/helpers по решению 0.1; Core REST1 через существующий ApiTransport только для документированного gap, без обхода policy/accounting/cancellation. Проверка: contract test требует disk.folder.uploadFile/version1, точные id/data.NAME/fileContent/generateUniqueName types и binary bytes; ответ ID/NAME валидируется, FILE_ID не принимается за Disk ID, нет secret URL в normalized data.
- [ ] 2.2 Добавить request-dependent routes и scoped CommandCatalog::ROUTES admission. Проверка: strict-rest3 локального режима даёт policy-denied/exit4 до credentials/API (также dry-run), --file-id остаётся REST3-only и доступен; несовпадающая версия/произвольный Disk route отклоняются.
- [ ] 2.3 Подготовить безопасный двухшаговый preview без payload и credentials/API. Проверка: offline --dry-run с JSON/plain/human показывает task/folder/name/size/routes и зависимость attach от будущего Disk ID, не содержит Base64/bytes/URL; документированный пример preview проверен на fixture.

## 3. Orchestration, partial outcomes and output

- [ ] 3.1 Реализовать upload → attach в существующем handler с динамическим Disk ID и подтверждением каждого метода. Проверка: unit success фиксирует ровно два write calls, ID result.ID вместо FILE_ID, две confirmed rows/ledger, meta versions 1.0/3.0 и complete=true; existing --file-id regression проходит.
- [ ] 3.2 Сохранить известный Disk ID/NAME и upload row при attach denial/unknown; upload malformed/timeout не запускает attach, запретить automatic retry/rollback/delete. Проверка: unit failure matrix подтверждает exit1/3, complete=false, unknown/failed/skipped статусы, outcomeUnknown и инструкции проверки/повтора только attach; JSON/plain/human сохраняют schema/TSV, секреты/содержимое отсутствуют даже при verbose.
- [ ] 3.3 Реализовать cancellation до upload, между шагами и во время ожидания write. Проверка: RuntimeState/signal tests подтверждают exit130, прекращение новых запросов, сохранение confirmed upload и различие skipped/unknown attach; нет обещания отката.

## 4. Live integration and combined acceptance

- [ ] 4.1 Добавить собственный happy-path local upload в AttachTaskFilesCommandTest через production CLI с SDK fixtures/cleanup. Проверка: make integration TEST_ARGS='--group command:task:file:attach' подтверждает оба writes и Disk readback ID/NAME/SIZE/PARENT_ID; сохранить dated receipt с SHA/ролью/API versions, отдельно указать ACK без независимого task membership. Skip не считается выполненной live проверкой.
- [ ] 4.2 Добавить integration cases collision/Unicode filename и denied Disk folder/scope либо attach permission на доступных fixtures. Проверка: выполнить cases, сохранить actual results и partial Disk ID; после тестов убрать только собственные known-ID fixtures, недоступные role fixtures явно отметить непроверенными без success claim.
- [ ] 4.3 Выполнить make unit, make lint-all, openspec validate upload-local-task-files --strict и openspec-verify-change; связать requirements/scenarios с тестами/receipts. Проверка: полный local regression зелёный, Symfony Filesystem dependency попадает в production PHAR и проходит Composer/license checks, SDK reuse decision подтверждён тестами, scoped route/field contracts согласованы issue ↔ proposal/design/spec/tasks/runtime, 30 task commands сохранены; отдельные native/portal/CI границы перечислены в отчёте.

## Workflow follow-up

- Перед implementation PR: свежий git fetch origin, merge origin/dev в feature branch, разрешение конфликтов и повтор затронутых проверок; проверить enhancement/milestone 0.2.0 и двустороннюю issue trace; PR в dev.
- После подтверждённого implementation merge/закрытия #62: sync/archive change, обновить issue archive path, сохранить и доставить архив в dev через PR с проверкой merge; остановить окружение и удалить только worktree задачи из сохраняемого checkout.
- Planning-only PR не закрывает #62, незавершённые задачи не отмечаются выполненными и change не архивируется.
