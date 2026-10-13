# Spec Delta

## Purpose

Регистрировать подтверждённые пробелы публичной поверхности b24cli в обезличенных локальных карточках и давать переносимому скиллу периодический пакетный разбор с согласованной передачей в feedback.

## ADDED Requirements

### Requirement: Capability gap trigger
Скилл SHALL сверять версию/help и регистрировать сценарий, когда для задачи требуется собственная реализация отсутствующей CLI-возможности. Несколько штатных вызовов и обычный JSON parsing MUST NOT сами по себе считаться gap. Неподдерживаемый путь SHALL останавливаться без REST/SDK/MCP обхода и без выполнения обвязки ради регистрации.

#### Scenario: Normal multi-command workflow
- **WHEN** задача решается несколькими публичными командами установленного CLI
- **THEN** карточка не создаётся только из-за количества вызовов

#### Scenario: Missing CLI capability
- **WHEN** сверка help подтверждает нехватку операции/параметра
- **THEN** скилл описывает сценарий и необходимый алгоритм словами, вызывает doctor:problem-trajectory и сообщает пользователю результат сохранения
- **AND** immediate issue предлагается как возможность, а не обязательное действие после каждого случая

### Requirement: Public problem registration command
CLI SHALL предоставлять doctor:problem-trajectory с bounded structured JSON через file/stdin и обычными output/error contracts. Карточка MUST содержать sanitizedScenario, availableCommands, missingCapability, wrapperAlgorithm, expectedOutcome и версии. CLI SHALL назначать cardId/createdAt/state; регистрация MUST работать offline без portal credentials.

#### Scenario: Register a valid report
- **WHEN** включён сбор и передана корректная безопасная карточка
- **THEN** команда сохраняет её локально и возвращает идентификатор/состояние, без portal/network requests

#### Scenario: Invalid input and competing stdin consumers
- **WHEN** вход имеет неизвестные поля, превышает лимит, содержит узнаваемые секреты либо одновременно потребляет stdin несколькими опциями
- **THEN** регистрация отклоняется без сохранения/эхо приватного входа и без ожидания интерактивного ввода

### Requirement: Protected configuration adjacent storage
Карточки SHALL храниться отдельными JSON files в .problem-trajectories рядом с пользовательским config.json, по существующему config directory resolver. Atomic writes и защита доступа MUST исключать lost update, path traversal и symlink обход. Каталог проекта, cwd и executable directory MUST NOT выбираться как неявный fallback.

#### Scenario: Explicit config directory
- **WHEN** команда получает --config-dir с путём, содержащим пробелы/Unicode
- **THEN** файлы находятся в .problem-trajectories этого resolved каталога с защищёнными правами и валидными card IDs

#### Scenario: Unsafe storage or concurrent writes
- **WHEN** доступ небезопасен, найден symlink либо несколько invocations одновременно пишут карточки
- **THEN** unsafe storage отклоняется; допустимые concurrent writes не повреждают конфиг/файлы и не перезаписывают чужие карточки

### Requirement: Collection toggle and bounded retention
collectProblemTrajectories=false MUST выключать новые записи без удаления накопленных карточек. maxProblemTrajectoryFiles SHALL ограничивать число card files, default 100. Для освобождения места SHALL удаляться только старейшие terminal cards с подтверждённой передачей/связью или явным dismiss. Незавершённые и повреждённые карточки MUST NOT молча удаляться.

#### Scenario: Full pending storage
- **WHEN** все 100 файлов незавершённые и требуется новая запись
- **THEN** возвращается storage-full, существующие карточки сохраняются и пользователь получает предложение разбора

#### Scenario: Terminal cleanup and lowered limit
- **WHEN** можно освободить terminal cards или установлен лимит ниже накопленного
- **THEN** terminal cleanup соблюдает лимит; незавершённые сохраняются, новая запись без места отклоняется

#### Scenario: Collection disabled
- **WHEN** collectProblemTrajectories=false
- **THEN** record сообщает collection-disabled без нового файла; явно выбранные чтение/разбор/отправка существующих карточек остаются доступны

### Requirement: Public telemetry and card lifecycle access
Скилл SHALL получать effective telemetry, analysisDue, счётчики/карточки и изменять разрешённые настройки/processing state только через публичные действия doctor:problem-trajectory. Он MUST NOT самостоятельно обходить .problem-trajectories, редактировать config или вычислять due dates собственной Bash/Python-обвязкой. Status/list MUST оставаться read-only/offline.

#### Scenario: Read status without existing configuration
- **WHEN** скилл вызывает status без config.json и credentials
- **THEN** CLI возвращает effective defaults/нулевые счётчики без config/storage writes, credential access и сети

#### Scenario: Configure or record processing
- **WHEN** скилл передаёт валидированный settings/batch payload публичному действию
- **THEN** CLI изменяет только разрешённые поля и выбранные карточки, сохраняя остальные профили и состояния

### Requirement: Seven day offer at skill invocation
При запуске скилл SHALL проверять CLI status и предлагать один разбор, только если есть карточки для нового анализа и прошли семь суток с более позднего offered/processed timestamp. При обоих null первый запуск с накоплением SHALL предлагать разбор. Отсутствие запусков MUST NOT запускать фоновые jobs; пустая очередь MUST NOT давать предложение.

#### Scenario: Declined offer
- **WHEN** пользователь отказывается от показанного предложения
- **THEN** offered timestamp фиксируется, processed timestamp/карточки не меняются и следующие запуски до семи суток не повторяют предложение

#### Scenario: No skill launch or no pending analysis
- **WHEN** скилл не запускали либо новых карточек для анализа нет
- **THEN** daemon/cron/heartbeat не запускаются и уведомление отсутствует

### Requirement: Snapshot based processing state
Разбор SHALL использовать snapshot выбранных card IDs и сохранять durable результат/решение для каждой. lastProblemTrajectoryProcessingAt MUST обновляться только после успешного сохранения разбора, отдельно от отправки. Новые карточки, появившиеся во время разбора, MUST NOT помечаться обработанными. Сбой/повтор SHALL сохранять отслеживаемые состояния.

#### Scenario: New report during analysis
- **WHEN** во время разбора snapshot появляется новая карточка
- **THEN** она остаётся ожидающей следующего анализа; завершение текущего snapshot обновляет processing timestamp

#### Scenario: Analysis failed or submission unavailable
- **WHEN** разбор не завершён либо после успешного разбора endpoint недоступен
- **THEN** сбой разбора не обновляет processed timestamp, а сбой отправки не отменяет сохранённый анализ и не объявляет передачу успешной

### Requirement: Grouped and evidence limited candidates
Агент SHALL группировать похожие карточки, проверять версию/help и existing issues, различать CLI, skill/docs, environment и unclear. Кандидат MUST содержать expected/actual, synthetic reproduction, версии, число карточек и acceptance criteria. Синтетические шаги SHALL отличаться от наблюдений; карточки MUST NOT выдавать процент всех запусков или число пользователей.

#### Scenario: Repeated reports and existing command
- **WHEN** несколько карточек описывают один gap либо команда уже доступна
- **THEN** готовится одна группа; существующая возможность/старая версия/ошибка скилла учитываются, а не выдаются за новую CLI-функцию

#### Scenario: Single report or unavailable issue search
- **WHEN** есть одно наблюдение либо поиск existing issues недоступен
- **THEN** уверенность/проверка дубликатов отражаются явно; неподтверждённая дедупликация не допускает автоматической отправки

### Requirement: Sanitized reviewable publication text
Скилл MUST обезличивать карточку до record и подготовленный пакет до передачи. Payload MUST исключать credentials, персональные ID/контакты/домены/пути, raw argv/env/output, переписку и исполняемые скрипты. CLI SHALL проверять schema/размеры/узнаваемые секреты без эхо приватного входа. Перед submit пользователь SHALL видеть согласуемый публикационный текст.

#### Scenario: Sensitive text found
- **WHEN** проверка выявила приватные данные во входе/пакете либо пакет не прошёл обязательные проверки
- **THEN** запись/передача отклоняется или требует очистки; сырые данные не попадают в файл, логи, endpoint или issue
- **AND** механическая проверка не объявляется гарантией анонимности любого свободного текста

### Requirement: Consent based feedback batch handoff
CLI SHALL предоставлять submit с явным consent и feedback port. В согласованном MVP отправка SHALL работать только через явный --mock-feedback: mocked=true/delivered=false, тестовый receipt и mock-submitted, без сети/issues. Одна группа SHALL иметь один idempotency key. Без mock рабочий endpoint SHALL считаться недоступным; скилл MUST NOT делать HTTP обход CLI.

#### Scenario: Approved batch
- **WHEN** пользователь проверил текст и разрешил отправку выбранных групп
- **THEN** явный mock сохраняет тестовый результат отдельно по группе, не отправляет сеть и не объявляет GitHub publication

#### Scenario: No consent or endpoint
- **WHEN** согласия нет либо рабочий endpoint ещё не настроен/доступен
- **THEN** сеть/публикация без согласия отсутствуют; локальные анализ/карточки сохраняются и отсутствие передачи объясняется

#### Scenario: Mock receipt is not delivered feedback
- **WHEN** mock submit/reconcile подтверждает тестовый receipt
- **THEN** mocked=true/delivered=false отражаются явно, карточки получают mock-submitted и не удаляются как реально доставленные
- **AND** реальный HTTP-контракт, moderation и live issue link проверяются отдельно после готовности #32

### Requirement: Receipt and unknown outcome reconciliation
Local state MUST различать анализ, принятие обращения endpoint и создание issue. Durable receipt/idempotency key SHALL предотвращать повторы; потерянный ответ MUST давать submission-unknown до reconciliation. Private receipt access secrets MUST храниться отдельно с защитой и не попадать в карточки/telemetry/logs/issues.

#### Scenario: Lost response and partial batch
- **WHEN** endpoint принял часть пакета, а для другой группы ответ потерян
- **THEN** известные receipts сохраняются, unknown не повторяется слепо, reconcile устанавливает прежнее обращение или оставляет неизвестность

#### Scenario: Receipt without issue
- **WHEN** сервис подтвердил durable receipt, но issue ещё не создано
- **THEN** результат показывает принятое обращение, а не утверждает GitHub publication или выпуск исправления
