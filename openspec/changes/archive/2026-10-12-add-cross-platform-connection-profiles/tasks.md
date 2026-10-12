# Tasks

## 1. Исследование и контракт

- [x] 1.1 Сравнить шесть CLI по официальным источникам и проверить текущий код; результат — research.md с датой, baseline, ссылками и границами доказательств.
- [x] 1.2 Уточнить технический OS ACL механизм для Windows без скачиваемого helper, зафиксировать права/atomic replace и failure behavior; проверить маленьким native fixture с тестовым файлом и обновить design без ослабления контракта; использовать прямые .NET ACL API внутри inbox PowerShell без module-discovery cmdlets.
- [x] 1.3 Уточнить четыре окружения с name/connection/default.task; проверить REST 3.0 groupId/creatorId/responsibleId/allowsTimeTracking и текущий mapper, использовать одинаковые имена полей API в config без переименований; согласовать defaults, builtin false, explicit priority и поставленный отдельно #27 в issue/proposal/design/spec/tasks/research. Проверка по документации и коду 12.10.2026, без live создания задачи.

## 2. Каталог и хранение

- [x] 2.1 Реализовать OS paths и override resolver; проверить отсутствие cwd/PHAR fallback, relative XDG, AppData absence и space/Unicode paths unit/subprocess matrix; описать paths в README.
- [x] 2.2 Реализовать versioned metadata и isolated credential file repository с обязательными name/connection.hostname/connection.credentialFile и optional default.task с groupId/creatorId/responsibleId/allowsTimeTracking; проверить четыре окружения, отсутствие/пустые обязательные поля, name отличный от transport hostname без HTTP/DNS к name, HTTPS authority mismatch, invalid default/task objects, неизвестные/non-API default keys, strict positive IDs/boolean values, отсутствие default/task/optional IDs, root groupId rejection/unsupported JSON, redaction и traversal/symlink rejection; документировать format и plaintext boundary.
- [x] 2.3 Реализовать protected temp writes, locking и atomic metadata publication; проверить Unix modes, Windows ACL, concurrent writes и injected failure/cleanup без selectable broken reference.

- [x] 2.4 Передать non-secret default.task фактически выбранного профиля в подготовку task:add; подстановка одноимённых API fields groupId/creatorId/responsibleId/allowsTimeTracking, затем builtin false и required-field validation. Проверить basic/advanced field-wise priority, explicit false против profile true, строгие boolean types, optional ID absence, missing required participants/title и invalid explicit value без fallback, dry-run с источником каждого значения без secret read, отсутствие чужих defaults для env/file/другого профиля и остальных task-команд; обновить help и примеры.

- [x] 2.5 Добавить docs/configuration.md и docs/config.schema.json Draft 2020-12; проверить общие positive/negative structural fixtures через runtime ProfileRepository и JSON Schema, JSON-примеры документации и схему в CI; описать default annotations и дополнительные runtime/filesystem проверки без расширения API-полей.

## 3. Выбор подключения и совместимость

- [x] 3.1 Расширить --profile, добавить --env-file/--config-dir и передать invocation selection в lazy resolver; проверить selectors до/после команды, --config profile, conflicts и precedence tests; обновить global help.
- [x] 3.2 Реализовать compatibility chain, SDK alias и source-only root dotenv; проверить zero API calls при selected-source failure, missing default и ambient conflicts, fixture host/token не смешиваются; HTTP/virtual stream URI отклоняется до чтения и API, обычные filesystem paths и local file URI сохраняются; описать migration --profile default.
- [x] 3.3 Проверить bootstrap source/packaged mode и lazy offline behavior; subprocess tests для help/version/list/completion/offline dry-run в read-only environment не создают config и не читают secret store.
- [x] 3.4 Сохранить Compose/worktree сценарии process env и explicit file; добавить явное forwarding/mount пользовательского каталога и выбранного файла, проверить wrapper fixture и документированные Docker примеры.

- [x] 3.5 Проверить интеграцию с поставленным #27/PR #31 (archive 2026-10-11-unify-group-id-option): --group-id остаётся canonical без --project alias; повторить примеры default.task и explicit priority в basic/advanced/dry-run.

## 4. Локальное управление

- [x] 4.1 Добавить connection:add с hidden input/--webhook-stdin без literal secret flag, name из hostname либо --portal-domain, default.task с --group-id/--creator-id/--responsible-id и --allows-time-tracking true|false (default false); проверить strict ID/boolean input и сохранение default.task.allowsTimeTracking=false без ID flags, -n/non-TTY/competing stdin/duplicate profile и отсутствие secret в argv/stdout/stderr; добавить безопасные setup examples.
- [x] 4.2 Добавить connection:list/show/use/remove и корректный effective source/profile; проверить no remote calls, use/default/remove semantics и human/plain/JSON contracts, обновить help каждого command. Проверить пользовательский путь из design: first add/defaultProfile=null, relative credential reference, use меняет только metadata, чтения не пишут файлы, remove сохраняет чужие profiles и не выбирает новый default.
- [x] 4.3 Расширить command registration/coverage guards для локального namespace, сохранив guards всех task-команд; проверить catalog parity и redaction regression с узнаваемым fixture secret во всех modes/error paths.

## 5. Native packaging и завершение проверки

- [x] 5.1 Добавить native package smoke для profile/explicit file/process env вне checkout на Linux amd64/macOS arm64/Windows amd64 против controlled fixture; проверить CRLF, spaces/Unicode, read-only executable dir и отсутствие автоматического cwd-dotenv; README команды bash/zsh, PowerShell, cmd.exe повторены в соответствующих jobs; проверить concurrent Windows temporary payload без rename и cleanup на success/failure.
- [x] 5.2 Сохранить no-secret package invariant; fixture scan PHAR/архивов/evidence не находит credentials, config mutation не затрагивает binaries; проверить production packaging gates.
- [x] 5.3 Выполнить make check, make unit, make lint-all, strict OpenSpec validation и applicable packaging checks; записать результаты с точными source/CI refs, отделив live portal checks и keyring feasibility.
- [x] 5.4 Выполнить openspec-verify-change и проверить обе стороны issue #19 ↔ proposal, labels/milestone; устранить несоответствия, записать verification.md.

## Workflow follow-up

- Архивировать только после реализации и verification, синхронизировать capability и обновить фактический archive path в issue #19.
- Публикация ветки, PR в dev и merge — отдельные delivery действия; запрос 12.10.2026 доводит реализацию #19 до проверенного результата и закрытия; delivery выполняется через PR в dev.
