# Design

## Context

См. [proposal](proposal.md) и [delta](specs/connection-configuration/spec.md). Baseline `dev`: `6d9fe39b4f6d431c70680adefdab970cb0ad2db4`, проверена 13.10.2026.

`ConnectionCommandRunner::add` проверяет task options, затем читает stdin или запрашивает webhook. Только после этого `ProfileRepository::add` под `.lock` обнаруживает дубликат и выдаёт `configuration-error` с требованием remove/add. Success presenter вызывается только после успешного выполнения. По чтению актуального кода молчаливый success из сообщения о 0.1.0 не подтверждён; запуск опубликованного бинарника не выполнялся.

`AddConnectionCommand` не объясняет повторное создание; README тоже не описывает этот путь. `docs/configuration.md` кратко запрещает перезапись, но таблица «Следующий connection:add» не различает новый alias и дубликат. Архив #19 явно предусматривает remove/add/use, поэтому выбран допускаемый #59 вариант с уточнением диагностики и документации.

## Goals / Non-Goals

**Goals:** сделать существующий запрет видимым до ввода секрета, сохранить защиту от гонок и дать полный пример настройки defaults.

**Non-Goals:** upsert, новая команда update, редактирование secrets, автоматическое восстановление defaultProfile, изменение config schema, API-полей или task:add.

## Decisions

### 1. Проверка alias в двух местах

После обычной проверки alias и runtime context, но до разбора add-specific task defaults и webhook input, выполнить read-only проверку существования alias через `ProfileRepository`. Общий метод проверки SHALL использовать одну диагностику и в раннем пути, и внутри существующей блокировки `add`, где конфигурация перечитывается. Нельзя удерживать lock во время интерактивного ввода. Существующие правила для конфликтующих stdin consumers и некорректной конфигурации сохраняются.

Альтернатива — только поздняя проверка: защищает persistence, но не устраняет ненужный ввод секрета. Upsert меняет принятую семантику и требует дополнительных решений об отсутствии флагов, boolean default false, credential replacement и атомарности; в данном плане не применяется.

### 2. Понятная диагностика без мутации

Ошибка сообщает: профиль уже существует, переданные параметры не применены. Указаны `connection:show NAME`, затем явные remove/add с нужными параметрами и повторный use для восстановления выбранного default. Alias уже проверен; не включать webhook или полученные defaults в текст ошибки. Использовать действующие failure/presenter contracts, сохраняя exit 1 и JSON error metadata.

Ранняя проверка читает только metadata. Проверка под lock остаётся окончательной для concurrent writers и происходит до credential write. Не создавать fallback на другой профиль или credential source.

### 3. Пример описывает полное повторное создание

Help и обе страницы документации объясняют: сохранить необходимые non-secret defaults из `connection:show`, обеспечить повторный ввод webhook, затем удалить локальный профиль, создать с полным набором желаемых флагов и явно выбрать его при необходимости. Перед удалением пользователь должен иметь доступ к webhook. `connection:show` не раскрывает secret; remove не отзывает webhook на портале. Оmitted IDs исчезают, `allowsTimeTracking` без флага снова false. В README описывается текущее поведение без миграционной истории предварительных версий.

### 4. Проверки наблюдаемого контракта

Расширить `ConnectionProfilesTest` и существующий `ConsoleHarness`/`FakeApiTransport`: duplicate с/без stdin, human/plain/JSON, сохранность bytes config/credentials/default/другого профиля, новый alias по прежнему пути, remove/add/use и offline task:add. Проверку нечитанного stdin строить на позиции/содержимом предоставленного тестового потока; не добавлять production API только ради тестов. Repository-тест проверяет повторный add без лишнего credential file. Для гонки сохраняется проверка в критической секции; не объявлять concurrency proof только по двум последовательным вызовам.

## API sources and verification boundary

13.10.2026 из worktree проверен `codex mcp get bitrix24_dev --json`: enabled, Streamable HTTP, официальный endpoint. Реальные ответы MCP получены последовательностью search → details:

- Search `tasks.task.add` → method details: [REST 3.0 add](https://apidocs.bitrix24.ru/api-reference/rest-v3/tasks/tasks-task-add.html).
- Search «Поля задачи REST 3.0» → article details точного результата «Поля задачи в REST 3.0»: [поля REST 3.0](https://apidocs.bitrix24.ru/api-reference/tasks/fields-rest-v3.html).

Статья подтверждает writable ID-поля `creatorId`, `responsibleId`, `groupId` (integer), обязательные для add `title`, `creatorId`, `responsibleId`, и `allowsTimeTracking` (boolean). Профильные ID defaults optional, но supplied IDs строго положительные по действующему локальному контракту. В method details присутствуют примеры REST 1.0 с uppercase полями; они не используются для определения REST 3.0 payload. Это проверка документации; портал, release binary и runtime acceptance ещё не проверены.

## Risks / Trade-offs

- [Remove/add может потерять defaults и выбранный default] → явно показать полный набор флагов, повторный webhook input и необязательный use; не выполнять замену автоматически.
- [Race после ранней проверки] → повторная проверка под существующим lock до записи credential.
- [Невалидные flags или runtime context могут иметь свою ошибку раньше duplicate] → сохранять существующие общие validation rules; гарантировать отсутствие webhook input для корректного repeated add.
- [Пользователь хочет удобное редактирование defaults] → вариант upsert не считается согласованным этим планом; при выборе пересмотреть proposal/design/spec/tasks до реализации.

## Delivery

План публикуется на review в `dev`; runtime-код изменяется только в последующем apply. После реализации — unit/quality checks, OpenSpec verify, PR в актуальную `dev`. После подтверждённого merge и закрытия issue — archive/sync и доставка архива, затем безопасное удаление task worktree по maintainer workflow.
