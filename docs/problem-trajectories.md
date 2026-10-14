# Карточки проблемных сценариев

`doctor:problem-trajectory` сохраняет обезличенные сообщения о недостающей
публичной возможности CLI. Несколько штатных вызовов и обычный разбор JSON
не являются сами по себе проблемой. Скилл проверяет help, останавливает
неподдерживаемый путь и описывает необходимый алгоритм словами; выполнять
обвязку для регистрации не нужно.

Все локальные действия работают без credentials и портала. Файлы находятся
в `<resolved-config-dir>/.problem-trajectories/<cardId>.json`; выбор каталога:
`--config-dir` → `B24CLI_CONFIG_DIR` → [OS default](config.md).
Это каталог пользовательского `config.json`, независимо от выбранного profile.
Скилл обращается к нему только через CLI, без прямого чтения/редактирования.

## Регистрация

Схема JSON: [problem-trajectories.schema.json](problem-trajectories.schema.json).
Пример `report.json`, полностью на синтетических данных:

```json
{
  "schemaVersion": 1,
  "sanitizedScenario": "Получить задачи, сгруппированные по бизнес-критерию",
  "availableCommands": ["task:list", "--json"],
  "missingCapability": "Нативная агрегация списка",
  "wrapperAlgorithm": ["Получить список", "Сгруппировать по заданному критерию"],
  "expectedOutcome": "Полная сводка по группам",
  "cliVersion": "0.1.0",
  "skillVersion": null
}
```

```sh
b24cli doctor:problem-trajectory --report-file report.json --json
b24cli doctor:problem-trajectory --report-file - --json < report.json
```

`record` — действие по умолчанию. CLI назначает `cardId`, UTC `createdAt`,
`state=pending` и собственную `cliVersion`. Заявленная версия сохраняется в
`reportedCliVersion`, несовпадение показывает `versionMismatch`. Это не
подтверждает выпуск новой команды в релизе; возможности уточняются help.
`--idempotency-key` принимает opaque retry key до 64 символов
`[A-Za-z0-9_-]`, без персональных ID. Повтор с тем же ключом и payload в
пределах хранения возвращает прежний ID; другой payload отклоняется.

Максимум report — 64 KiB; тексты — до 4096 UTF-8 bytes, алгоритм — 1..20
описаний, availableCommands — до 50 технических имён без значений. Batch
input — до 512 KiB и до 100 snapshot card IDs. JSON integer literals обязательны.
Неизвестные поля, неверные контейнеры/типы, превышение размеров и узнаваемые
секреты/контакты/домены/пути/исполняемые фрагменты отклоняются без эхо входа.
Bare domains проверяются без фиксированного списка TLD, включая IDN;
валидные IPv4/IPv6 также отклоняются. Семантические версии CLI остаются допустимы.
Один stdin нельзя одновременно использовать для payload и runtime `--config`.

Приватные значения и исходные данные портала удаляет агент **до вызова**.
Механическая проверка CLI не гарантирует анонимность произвольного текста.
ФИО, переписка, webhook, токены, персональные ID, raw argv/env/output и код
обвязки в карточки не передаются. Каталоги/файлы используют защищённые права
0700/0600 на Unix и user-restricted ACL на Windows; это plaintext, не шифрование.

## Публичные действия

| `--action` | Ввод | Результат в `data` |
| --- | --- | --- |
| `record` | `--report-file PATH|-`, optional retry key | cardId/state/duplicate/версия |
| `status` | Нет | effective telemetry, counts, fileCount, analysisDue |
| `list` | `--state`, `--limit 1..100`, `--offset` | items, total, returned, nextOffset, complete |
| `configure` | `--settings-file PATH|-` | telemetry |
| `record-offer` | Нет | дата предложения |
| `record-analysis` | `--batch-file PATH|-` | groups и готовый publication preview |
| `submit` | selection через `--batch-file`, `--confirm-publication` | результаты отдельно по группе |
| `reconcile` | selection через `--batch-file` | прежний receipt либо неизвестный результат |
| `recover` | Нет | завершение прерванного journal |

`--json` даёт стандартный `{data,meta,error}`, `meta.apiCalls=[]`. Статус
0 означает подтверждённый результат заявленного локального действия;
1 — ошибка хранения/collection-disabled/storage-full/endpoint-unavailable;
2 — неверный ввод или отсутствующее подтверждение; 3 — неполный список,
повреждённые карточки или частичный handoff. `--plain` — headerless TSV.
Status/list/help/version/inventory не создают файлов и не читают secrets.

```sh
b24cli doctor:problem-trajectory --action status --json
b24cli doctor:problem-trajectory --action list --state pending --json
b24cli doctor:problem-trajectory --action record-offer --json
```

Default state filter — pending. `list --state all` включает накопленные
результаты анализа и безопасный publication preview. Сохраняйте IDs выбранного
snapshot; новые карточки не должны попадать в уже согласованный batch.
При `nextOffset` список продолжается отдельным CLI-вызовом; последующий разбор
всегда задаёт конкретные cardIds, не «все файлы».

## Telemetry и еженедельное предложение

Корневой `telemetry` в пользовательском config описан в
[config.md](config.md): default collection=true, max files=100,
даты Processing/Offered=null. `configure` разрешает менять только
`collectProblemTrajectories` и `maxProblemTrajectoryFiles`. Например, файл
`settings.json`: `{"collectProblemTrajectories":false}`.

```sh
b24cli doctor:problem-trajectory --action configure --settings-file settings.json --json
```

Выключение запрещает новые записи, сохраняя доступ к накопленным. На запуске
скилл вызывает status. `analysisDue` требует pending cards и семи суток с
более поздней даты Offered/Processing; при обоих null и непустой очереди
предложение доступно сразу. После показа предложения вызывается record-offer.
Отказ не меняет Processing/карточки и подавляет повтор до семи суток.
Без запуска скилла фоновой работы, cron, heartbeat и отправки нет.

## Анализ snapshot

Агент группирует выбранные карточки, сверяет CLI/help и существующие issues.
CLI сохраняет уже подготовленные решения, не выполняет LLM-анализ сам.
Каждая карточка принадлежит одной группе snapshot. `batchId/groupId` повторяют
тот же разбор идемпотентно; изменять содержание сохранённой пары нельзя.
Для нового разбора агент выбирает новый уникальный opaque batchId;
weekly-review в примере ниже — значение одного синтетического разбора.

```json
{
  "schemaVersion": 1,
  "batchId": "weekly-review",
  "groups": [{
    "groupId": "aggregation",
    "cardIds": ["aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"],
    "owner": "cli",
    "title": "Добавить агрегацию задач",
    "expected": "Сводка через публичную команду",
    "actual": "Доступен только список",
    "syntheticReproduction": ["Создать синтетические категории", "Запросить сводку"],
    "acceptanceCriteria": ["Группировка выполняется средствами CLI"],
    "confidence": "hypothesis",
    "deduplication": "checked",
    "decision": "candidate",
    "issueLinks": []
  }]
}
```

Подставляйте реальные локальные cardIds из list, не персональные ID портала.
Owners: cli/skill-docs (`skill/docs`)/environment/unclear. Confidence:
hypothesis/confirmed/unclear. Decisions: candidate/linked-existing/dismissed.
Для linked-existing обязателен canonical issue URL одного из двух репозиториев;
deduplication=unchecked блокирует submit. Число карточек — число локальных
сообщений о проблеме, не процент всех запусков или количество пользователей.
Примеры воспроизведения синтетические и помечены соответственно.

```sh
b24cli doctor:problem-trajectory --action record-analysis --batch-file analysis.json --json
```

Успешный durable результат обновляет Processing и только выбранные cardIds.
Invalid batch не меняет карточки/дату; повтор завершённого batch не сдвигает
Processing. Если карточка уже включена в новый анализ, retry старой группы
отклоняется как superseded snapshot. Новые карточки, поступившие во время
анализа, остаются pending. Publication содержит версии CLI и скилла из
выбранных карточек, неизвестная skillVersion отображается как unknown.
Согласование отправки происходит после просмотра publication из результата.

## Feedback: явная репетиция с моками

По согласованному объёму MVP реальный endpoint пока недоступен. Без
`--mock-feedback` submit/reconcile возвращают endpoint-unavailable и сохраняют
локальный результат. Передача не заменяется curl/SDK/MCP или прямым GitHub API.

Файл selection.json содержит `{"groupRefs":["<groupRef из record-analysis>"]}`.
Один пакет выбирает 1..100 сохранённых групп; публикационный текст берётся
из неизменяемого анализа, не из нового непроверенного body.

```sh
b24cli doctor:problem-trajectory --action submit --batch-file selection.json \
  --confirm-publication --mock-feedback --json
b24cli doctor:problem-trajectory --action reconcile --batch-file selection.json \
  --mock-feedback --json
```

Результат **mocked=true, delivered=false**, receipt с префиксом mock,
state=mock-submitted. Сети, moderation и GitHub issue нет. Повтор и reconcile
возвращают тот же тестовый receipt. Такие карточки не удаляются как отправленные;
их можно явно dismiss новым analysis batch. Существующие реальные/неизвестные
попытки нельзя превращать в mock через смену режима.

Feedback port имеет offline tests принятия, частичного пакета и потерянного
ответа. Перед вызовом transport сохраняются group idempotency key и неизвестный
outcome. Неизвестная попытка не повторяется вслепую; reconcile восстанавливает
receipt. Private access secret находится только в защищённом private state,
не в карточке/config/output. Future durable receipt означает приём обращения,
не публичное issue или release. Реальный HTTP-контракт и deployment относятся
к #15/#32 и требуют отдельной live проверки.

## Лимит и восстановление

100 — число card JSON files, включая повреждённые. Автоматически освобождаются
лишь старейшие dismissed/linked-existing либо submitted с реальным durable
receipt. Pending/analyzed/unknown/mock-submitted и corrupt не удаляются молча.
При заполнении — storage-full; снижение лимита не стирает незавершённые.

Изменения config и карточек используют один configuration lock, atomic file
publish и защищённый recovery journal. Прерванная операция даёт
storage-recovery-required на read-only поверхности. Для её завершения:

```sh
b24cli doctor:problem-trajectory --action recover --json
```

Этот MVP не собирает истории чатов, файлы сессий или скрытые рассуждения.
