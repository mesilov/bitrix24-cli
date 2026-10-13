# Design

## Context

Мотивация — в proposal.md. Пользователь согласовал сужение #96 до локальных карточек и еженедельного предложения анализа при запуске скилла. Планирование ведётся в существующем worktree `feature/96-agent-trajectory-feedback`; актуальный `origin/dev` проверен 2026-10-13 (`79a4f9f`). Связанные config source/spec/schema не изменились относительно базы ветки.

Наблюдаемые точки расширения:
- `src/Console/B24Application.php` определяет `--config-dir`, `--json` и другие глобальные опции; команды находятся в `src/Console/Command/`.
- `ConfigDirectoryResolver` выбирает каталог через flag → env → OS default. `--config` относится к отдельному runtime JSON; telemetry принадлежит пользовательскому `config.json`.
- `ProfileRepository` и `docs/config.schema.json` сейчас разрешают только version/defaultProfile/profiles в корне. Telemetry нужно добавить согласованно в runtime/schema/fixtures, сохранив профили, defaultProfile и credential references.
- `skills/bitrix24-tasks/SKILL.md` в соседнем репозитории разрешает только публичный CLI и требует остановиться при отсутствии возможности. Он пока не поддерживает регистрацию карточек.
- `add-user-feedback-loop` (#15/#32) планирует локальную feedback-команду, приём обращений, receipt и moderation. Наличие плана не подтверждает готовность endpoint.

При apply реализуются CLI и repository package скилла; установленный пользовательский скилл этой задачей не заменяется. Доказательства source/offline проверок сохраняются в verification.md.

## Goals / Non-Goals

**Goals:** одна точка регистрации; предсказуемый config path; bounded local storage; переносимый скилл; одно предложение анализа за семь суток и один согласованный пакет.

**Non-Goals:** экспорт/парсинг историй, скрытые reasoning traces, клиентские Codex/Claude adapters, cron/heartbeat/daemon, отдельный LLM-сервис, автоматическое выполнение обвязки, изменения бизнес-команд и merge исправлений. Это следующий этап, не критерий завершения MVP.

## Decisions

### 1. Регистрация при подтверждённой нехватке CLI

Скилл сначала сверяет установленную версию и help. Несколько штатных команд и обычный разбор JSON не запускают регистрацию автоматически. Когда нужна собственная реализация отсутствующей функции, скилл останавливает этот путь, формирует безопасную карточку и вызывает `doctor:problem-trajectory`. Алгоритм описывает требуемые шаги словами; выполнять скрипт для доказательства не нужно.

Сценарии stale CLI, overlooked command и environment error при анализе классифицируются отдельно. После сохранения агент сообщает пользователю недостающую возможность и предлагает immediate issue либо следующий пакет. Если запись выключена/недоступна/хранилище заполнено, агент честно сообщает результат и сохраняет остановку неподдерживаемой операции; скрытого fallback через HTTP/SDK/MCP нет.

### 2. Один публичный command contract

Основная команда `doctor:problem-trajectory` получает structured report через `--report-file PATH|-` с поддержкой stdin; `--action record` — default. Скилл может сформировать JSON для stdin обычным shell-вызовом; он не реализует на shell/Python хранение, поиск файлов, даты или HTTP.

Планируемые действия той же команды:
| --action | Назначение |
| --- | --- |
| record | Проверка/локальное сохранение карточки; optional caller idempotency key для повторного вызова |
| status | Read-only effective telemetry, счётчики и analysisDue; без credential reads, сети или создания файлов |
| list | Read-only выдача карточек выбранного состояния/пакета в JSON; без доступа скилла к служебным путям |
| configure | Изменение collectProblemTrajectories/maxProblemTrajectoryFiles из валидированного JSON через --settings-file PATH или stdin |
| record-offer | Атомарная отметка показанного предложения анализа |
| record-analysis | Сохранение группировки/решений по выбранным card IDs через --batch-file PATH или stdin и даты успешного разбора |
| submit | Передача подтверждённого пакетного текста в фиксированный feedback endpoint через --batch-file и явный --confirm-publication |
| reconcile | Проверка ранее принятого/неопределённого обращения по receipt/idempotency key |
| recover | Завершение прерванной локальной journal transaction под общей config lock |

Одно действие на вызов; mutually exclusive file/stdin consumers, bounded input и обычные JSON/plain/error contracts. Все имена здесь — планируемый интерфейс, не доступные сегодня команды. Остальные команды не пишут карточки автоматически и не меняют stdout/exit codes ради telemetry.

### 3. Карточки и хранение

Caller payload: schemaVersion, sanitizedScenario, availableCommands, missingCapability, wrapperAlgorithm (массив описаний шагов), expectedOutcome, cliVersion, skillVersion (nullable если неизвестна). Тексты обезличивает скилл до вызова. CLI дополняет cardId/createdAt, собственную достоверную cliVersion и state; claimed version mismatch отражает явно. Caller idempotency key повторяет именно регистрацию эпизода, не объединяет все похожие сценарии в одну карточку.

`<resolved-config-dir>/.problem-trajectories/<cardId>.json` содержит payload и processing metadata: state, batch/group reference, safe issue links, feedback receipt/idempotency key. Персональные ID, portal domains, local paths, raw argv/env/outputs, исходная переписка, credentials и исполняемый скрипт исключены. Unknown fields/oversize/schema mismatch и узнаваемые секреты отклоняются без сохранения/эхо входа; regex не считается гарантией полного обезличивания свободного текста. Перед передачей агент и пользователь проверяют подготовленный текст.

Запись/изменение — atomic publish под согласованной блокировкой; namespace/card IDs проверяются, symlink/path traversal запрещены. Защита каталогов/файлов использует существующую FileProtection: Unix 0700/0600 и документированную защиту Windows. Это защищённое plaintext storage, не шифрование.

Состояния: pending → analyzed → submission-pending → submitted, с submission-unknown при потерянном ответе. linked-existing и dismissed — явные конечные решения. submitted означает durable receipt сервиса, не созданное GitHub issue. Ошибка передачи не возвращает результат анализа к неизвестному и не даёт автоматически отправить новый POST.

Лимит по умолчанию 100 охватывает все сохранённые card JSON files. Для новой записи первыми удаляются старейшие terminal cards (submitted с подтверждённым durable receipt, linked-existing или dismissed). Pending/analyzed/submission-pending/submission-unknown не удаляются автоматически. Если места нет, новая регистрация получает storage-full без повреждения старых данных. Снижение лимита ниже накопленного сохраняет незавершённые карточки и блокирует новые записи до освобождения места. Повреждённые card files учитываются в лимите, показываются как ошибки и не стираются автоматически.

### 4. Telemetry в пользовательской конфигурации

Корневой optional object, scope — весь resolved config directory, независимо от portal profile:
```json
{
  "telemetry": {
    "collectProblemTrajectories": true,
    "maxProblemTrajectoryFiles": 100,
    "lastProblemTrajectoryProcessingAt": null,
    "lastProblemTrajectoryAnalysisOfferedAt": null
  }
}
```

Отсутствующие telemetry/поля дают эти effective defaults без записи при read-only операции. Boolean строгий; max — integer literal 1..PHP_INT_MAX; даты — null или UTC ISO 8601 timestamp (например 2026-10-13T00:00:00Z). Unknown keys и invalid objects/types дают configuration-error без mutation/API. JSON Schema annotations не записывают defaults; runtime реализует их явно.

При первом успешном record без config CLI создаёт защищённый schema v1 config с version=1/defaultProfile=null/profiles={} и telemetry; credentials не нужны. Последующие локальные операции обновляют только telemetry под тем же config lock, не теряя изменения connection:add/use/remove. Help/version/list/status не создают config или storage. collectProblemTrajectories=false останавливает новые registrations; существующие карточки остаются доступны для явно выбранного анализа и передачи.

### 5. Семь суток и пакетный разбор из скилла

На запуске скилл вызывает status. analysisDue=true только при карточках, ожидающих нового анализа, и отсутствии обоих timestamps либо истечении семи суток с более позднего offered/processed timestamp. CLI считает время по UTC и возвращает готовый признак; скилл не вычисляет даты собственной обвязкой. Если карточек нет, предложения нет. Если скилл не запускают, фоновой работы нет.

После показа предложения скилл вызывает record-offer. Отказ пользователя не меняет state карточек и processing timestamp; новое предложение возможно через семь суток. После согласия агент читает карточки через list, анализирует их в текущем сеансе и сохраняет результат record-analysis. Этот timestamp обновляется только после успешного durable сохранения решений по выбранному пакету, не при простом чтении/ошибке/отказе. Карточки, созданные во время разбора, не помечаются обработанными вместе с предыдущим пакетом.

Обрабатывается snapshot выбранных card IDs, при необходимости порциями до 50. Один digest группирует повторения, проверяет installed CLI/help и existing issues при доступном публичном канале; при недоступном поиске статус dedup-unchecked указывается, автоматическая отправка блокируется до проверки. Карточка кандидата содержит goal, missing capability, expected/actual, synthetic reproduction, versions, cli/skill-docs/environment/unclear owner, count of reports и acceptance criteria. Частота выражается числом карточек, не процентом всех задач и не числом пользователей: успешные запуски в MVP не собираются.

### 6. Согласованная передача через feedback endpoint

Уточнение пользователя при apply: «мокай пока отправку». В этом MVP production feedback gateway возвращает endpoint-unavailable; явный --mock-feedback включает детерминированный offline gateway. Результат содержит mocked=true/delivered=false, тестовый receipt и состояние mock-submitted. Такие карточки не считаются submitted и не участвуют в automatic terminal cleanup. Перезапуск/reconcile в mock режиме воспроизводит тот же тестовый receipt; реальной сети и GitHub issues нет. Feedback port отделяет эту проверку от будущего HTTP-контракта #32; live handoff вынесен в workflow follow-up, не подменяется моками.

После анализа пользователь видит публикационный текст пакета и одним решением выбирает обращения для отправки. Одно одобренное обращение соответствует одной группе, с отдельным idempotency key; несколько карточек одной проблемы не порождают несколько issues. Скилл использует публичное submit CLI, не curl/SDK/MCP для доставки. В CLI нет общего GitHub token; endpoint/repository/consent/moderation берутся из согласованного канала #15/#32, не из текста карточки.

Клиентский handoff входит в этот MVP; deployment сервиса остаётся #32. Точный URL/HTTP schema определяется опубликованным endpoint-контрактом до включения отправки. До готовности канала сохраняются анализ/локальный пакет и честное endpoint-unavailable. При outage/timeout частичные результаты и receipts сохраняются отдельно по группе; submission-unknown проверяется через reconcile, не повторяется вслепую. Receipt/status secret если нужен хранится отдельно в защищённом локальном service state и не попадает в карточку, config telemetry, логи или issue.

## Risks / Trade-offs

- [Скилл не заметил gap] → MVP фиксирует только заявленные случаи; независимый анализ полной истории — следующий этап.
- [Свободный текст содержит приватное] → минимальный payload, safe examples, bounded validation, отсутствие raw logs и просмотр пакета; не обещать абсолютную анонимность.
- [Даты suppress prompt после отказа] → отдельные offered/processed timestamps, явные per-card states и проверяемые UTC rules.
- [100 незавершённых файлов] → отказ новой записи с понятной причиной; никакого silent discard.
- [Разные агенты меняют config/карточки одновременно] → общий config lock, atomic writes, snapshot IDs и idempotency/reconciliation tests.
- [Feedback endpoint пока не готов] → offline запись/разбор полноценны; сквозная отправка считается проверенной только по test deployment #32.
- [Уменьшенный объём не покрывает full trajectory metrics] → считать report occurrences; не заявлять статистику по всем чатам.

## Migration Plan

Порядок реализации: telemetry schema/runtime/fixtures → record/storage → публичные status/lifecycle действия → правка переносимого скилла → feedback port/явный mock → offline приёмка. Live HTTP handoff выполняется позже после готовности #32. Откат локального сбора — collectProblemTrajectories=false; существующие карточки не удаляются. Сбор JSONL/app-server/rollouts и автономный scheduler не задачи MVP. Change не архивируется до реализации/verify и подтверждённой поставки через review.
