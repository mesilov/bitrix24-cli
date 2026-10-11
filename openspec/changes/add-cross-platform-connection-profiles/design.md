# Design

## Context

Мотивация — в [proposal.md](proposal.md); поведенческий контракт — в [spec](specs/connection-configuration/spec.md). Baseline: dev 56654f1310b75580362bb5ad58b00ad63d006363.

EnvConnectionResolver сейчас читает process webhook/SDK alias, B24CLI_ENV_FILE или project-root dotenv; B24ClientProvider подключается лениво. B24Application уже объявляет --profile и --config. CommandRunner::context принимает только default и non-secret runtime JSON с timeout/apiPolicy/profile. Packaging использует PHAR и Windows temporary payload; ни один payload root не подходит для mutable credentials.

## Goals / Non-Goals

**Goals:** единый пользовательский контракт для трёх OS, явный выбор одного портала, source/Docker совместимость, безопасные локальные операции и проверяемое происхождение подключения.

**Non-Goals:** OAuth/login browser flow, refresh tokens, remote webhook revocation, self-update, перенос секретов между компьютерами, credential helper execution и нативные keyring adapters в v1. Новая система не меняет REST маршруты task-команд.

## Decisions

### 1. Пользовательский каталог и отдельный runtime config

--config-dir > B24CLI_CONFIG_DIR > OS default. Linux/macOS: absolute XDG_CONFIG_HOME/b24cli или HOME/.config/b24cli. Windows: APPDATA/b24cli. Нет home/AppData — ошибка с запросом explicit path, без поиска cwd. Relative explicit paths нормализуются против cwd; XDG relative игнорируется.

Выбор Unix layout для macOS следует практикам gh/gcloud; Library/Application Support — допустимая альтернатива, но потребовала бы второго Unix convention без преимущества для текущего CLI. Данные не зависят от версии или пути бинарника.

--config остаётся явным runtime JSON, а не alias на user config.json; timeout/apiPolicy precedence сохраняется. profile в --config является явным selector, когда --profile/--env-file отсутствуют. --env-file конфликтует с config.profile; --profile перекрывает config.profile.

### 2. Файловый credential backend для v1

config.json version 1:

```json
{
  "version": 1,
  "defaultProfile": "work",
  "profiles": {
    "work": {
      "name": "portal.example",
      "connection": {
        "hostname": "portal.example",
        "credentialFile": "credentials/7d2d26f2.env"
      },
      "default": {
        "task": {
          "allowsTimeTracking": false
        }
      }
    }
  }
}
```

### 2.1. Структура окружения и домен описания

`profiles` — словарь окружений; его ключ (`work`, `pet`, `clientA`, `clientB`) является alias для `--profile` и `defaultProfile`. Каждый профиль описывает отдельный портал и содержит:

| Поле | Обязательность | Значение |
| --- | --- | --- |
| `name` | Обязательно | Непустой домен портала для описания; без scheme/path/query/userinfo. Не является сетевым адресом CLI. |
| `connection` | Обязательно | Объект параметров подключения. |
| `connection.hostname` | Обязательно | Безопасный hostname, к которому CLI обращается по HTTPS; без scheme/path/query/userinfo. |
| `connection.credentialFile` | Обязательно | Ссылка на отдельный credential dotenv относительно каталога конфигурации. |
| `default` / `default.task` | Опционально при чтении | Объекты non-secret defaults для task:add; connection:add сохраняет default.task с allowsTimeTracking=false даже без ID. |
| `default.task.groupId` | Опционально | Положительный JSON integer — дефолтная группа/проект создаваемой задачи; API fields.groupId. |
| `default.task.creatorId` | Опционально | Положительный JSON integer — дефолтный автор/постановщик; API fields.creatorId. |
| `default.task.responsibleId` | Опционально | Положительный JSON integer — дефолтный исполнитель; API fields.responsibleId. |
| `default.task.allowsTimeTracking` | Опционально при чтении; default false | JSON boolean — включён ли учёт времени по задаче; API fields.allowsTimeTracking. |

`name` может отличаться от `connection.hostname`; нельзя делать HTTP/DNS-запросы к `name`, использовать его для проверки доступности или fallback. `connection.hostname` задаёт transport authority, а credential file даёт полный webhook. Проверить, что webhook использует HTTPS и его hostname совпадает с `connection.hostname`: при несовпадении configuration-error до API, без переписывания webhook и без смешивания host/token. Отсутствующий/пустой обязательный параметр, неверные default/default.task или ID/boolean — configuration-error. Отсутствующие default/task/ID допустимы; null/0/строка для ID и null/0/1/строка для boolean не считаются отсутствием. Для allowsTimeTracking при отсутствии значения действует встроенный false. Прежний groupId на корне профиля больше не входит в схему: не поддерживать параллельный fallback, сообщить путь default.task.groupId.

Пример четырёх окружений (параметры вымышленные):

```json
{
  "version": 1,
  "defaultProfile": "work",
  "profiles": {
    "work": {
      "name": "work-portal.example",
      "connection": {
        "hostname": "portal.example",
        "credentialFile": "credentials/7d2d26f2.env"
      },
      "default": {
        "task": {
          "groupId": 42,
          "creatorId": 1,
          "responsibleId": 2,
          "allowsTimeTracking": false
        }
      }
    },
    "pet": {
      "name": "pet-portal.example",
      "connection": {
        "hostname": "pet-portal.example",
        "credentialFile": "credentials/94e65b10.env"
      },
      "default": {
        "task": {
          "allowsTimeTracking": false
        }
      }
    },
    "clientA": {
      "name": "client-a.example",
      "connection": {
        "hostname": "api-client-a.example",
        "credentialFile": "credentials/c1a8f603.env"
      },
      "default": {
        "task": {
          "groupId": 77,
          "creatorId": 3,
          "responsibleId": 4,
          "allowsTimeTracking": false
        }
      }
    },
    "clientB": {
      "name": "client-b.example",
      "connection": {
        "hostname": "client-b.example",
        "credentialFile": "credentials/b2d9e704.env"
      },
      "default": {
        "task": {
          "allowsTimeTracking": false
        }
      }
    }
  }
}
```

Плоский hostname/credentialFile из прежнего planning example заменён вложенным connection, а profile-root groupId перенесён в default.task.groupId. Version остаётся 1: профильное хранение ещё не реализовано/выпущено, это уточнение проектируемого v1, а не миграция существующих пользовательских файлов.

Credential file содержит BITRIX24_WEBHOOK, без хранения секрета в JSON. Имя файла генерируется, не строится из profile name. References ограничиваются управляемым credentials directory; исключить traversal и небезопасные symlink targets. JSON и dotenv — данные, без выполнения кода/команд. Webhook рассматривается как единый секрет: connection.hostname извлекается безопасным URL parser без path/query/userinfo и сверяется с HTTPS authority полного webhook; name используется только для описания.

CredentialStore изолирует read/write/delete, ProfileRepository — versioned metadata, ConfigDirectoryResolver — paths; файлы — единственный v1 adapter. Не создавать неиспользуемые native implementations.

Альтернативы рассмотрены в [research.md](research.md): system keyring защищает stored secret лучше, но Secret Service может отсутствовать в headless Linux, а PHP micro требует проверки API/FFI/helper packaging. Внешний helper добавляет executable/deployment contract. Один общий JSON с webhook проще, но смешивает shareable metadata и секрет. Base64 и encryption key рядом с файлом не решают задачу защиты.

Unix: 0700 dirs/0600 managed files с момента создания. Windows: OS ACL mechanism, проверенный native test, ограничивает обычный доступ текущим пользователем; SYSTEM/административный доступ описывается отдельно. Системный механизм не требует скачиваемого helper; если права установить/проверить нельзя, persistence fails closed. Не считать chmod эквивалентом Windows ACL. Explicit env-files остаются externally managed и не изменяются CLI.

Под блокировкой конфигурации: подготовить private temp credential, atomic replace, затем atomic metadata update. При сбое metadata новая запись не становится selectable; cleanup orphan/rollback не раскрывает secret. При remove сначала убрать reference/default, затем удалить credential. Остаточный защищённый orphan при ошибке удаления сообщается безопасным path. No overwrite существующего профиля в connection:add; для ротации v1 remove/add/use.

### 3. Явный selector и compatibility chain

Invocation selection передаётся в lazy resolver через отдельный context, вместо повторного чтения argv в infrastructure. B24ClientProvider/ConnectionResolver остаются границей получения одного полного webhook.

| Этап | Правило |
| --- | --- |
| CLI selector | --profile или --env-file; оба сразу — usage exit2 |
| Runtime config selector | config.profile, если CLI selector отсутствует; --env-file + config.profile — usage exit2 |
| Ambient conflict | Только без explicit selector: одновременно B24CLI_ENV_FILE/B24CLI_PROFILE — configuration-error exit1 до выбора process secret |
| Compatibility sources | process BITRIX24_WEBHOOK > process SDK alias > B24CLI_ENV_FILE > B24CLI_PROFILE > saved default > source project dotenv |
| Failure | Selected nonempty source failed — ошибка до API, без lower-priority fallback |
| Complete absence | connection-unavailable exit1 со способом настройки |

Имена и значения проверяются; пустой explicit selector — usage exit2, пустая environment variable означает отсутствие. --config-dir выбирает repository directory, а не connection, и не перекрывает process credential.

Full webhook выбирается атомарно. Не создавать host из профиля и token из env. Без explicit selectors сохраняется текущий process-env > B24CLI_ENV_FILE контракт. Явные selectors сознательно сильнее ambient credentials; это проектное решение, а не общий закон для AWS/gh.

**Совместимость:** --profile default и config.profile=default раньше лишь допускали legacy webhook. Теперь это явный сохранённый профиль, и без него команда выдаёт ошибку. Отметить это изменением поведения и описать миграцию: убрать selector для legacy env либо создать default profile. Новые selectors работают до/после команды согласно cli-experience.

### 4. Локальные команды и lazy bootstrap

connection:add NAME принимает hidden TTY input или --webhook-stdin; read stdin ограничен и разрешён только явно. При -n/non-TTY без stdin flag — exit2. В stdout никогда не печатается введённое значение; ошибки Dotenv/URL parser заменяются безопасной диагностикой.

connection:add не меняет default, connection:use NAME делает это явно. NAME здесь — alias профиля, например work; поле name — описательный домен. При add name по умолчанию равен hostname webhook; non-secret --portal-domain DOMAIN задаёт иной описательный домен. --group-id ID, --creator-id ID, --responsible-id ID сохраняют соответствующие optional ID в default.task; --allows-time-tracking true|false задаёт boolean с обязательным значением, по умолчанию false. Эти параметры не требуют запроса к порталу. connection:list/show используют общий human/plain/JSON presenter и показывают alias/name/connection.hostname/default.task/source/path. connection:remove удаляет локальное подключение и очищает default при совпадении; удалённый webhook не отзывается.

Global command registration через CommandCatalog не должна ломать coverage guards, привязанные сейчас к task-командам; добавить отдельное покрытие локального namespace. CommandRunner::context должен передавать selection до API provider, а OperationResult/meta — реальный effective profile/source. Не выдавать ambient env за профиль.

Help/list/version/completion не открывают secret files и не создают directories. Существующие offline dry-run проходят без credentials; разрешённые спецификацией remote preflights не превращаются в offline proof. Source vs packaged mode определяется launcher execution context, а не наличием cwd .env или basename executable.

### 5. default.task профиля для создания задачи

Структура уточнена 12.10.2026 по запросу пользователя. Все defaults создания задачи находятся в profiles.<alias>.default.task; прежний root groupId перенесён сюда. Это уточнение ещё не выпущенной schema v1, без сохранения второго расположения groupId.

Проверены [официальные поля REST 3.0](https://apidocs.bitrix24.com/api-reference/tasks/fields-rest-v3.html): tasks.task.add требует title, creatorId, responsibleId; группа — groupId, исполнитель — responsibleId, учёт времени — boolean allowsTimeTracking. Названия ключей default.task совпадают с именами полей REST 3.0, включая creatorId; отдельное переименование автора не вводится. REST 3.0 принимает true/false, а не Y/N. Это проверка документации и кода, без live создания задачи.

| Config default.task | Поле API | При отсутствии profile default и явного значения |
| --- | --- | --- |
| groupId | fields.groupId | Не отправлять группу. |
| creatorId | fields.creatorId | Сохранить required-field usage error, не определять автора по webhook user ID. |
| responsibleId | fields.responsibleId | Сохранить required-field usage error, не назначать пользователя автоматически. |
| allowsTimeTracking | fields.allowsTimeTracking | Передать false. |

Порядок подготовки:
1. Разобрать явные flags/advanced fields и сохранить их существующее взаимоисключение. Отдельно проверить присутствующие явные значения: ошибочное значение не заменять default.
2. Получить non-secret metadata именно фактически выбранного сохранённого профиля. По каждому отсутствующему API field подставить одноимённый ключ из default.task. Проверять наличие ключа, а не truthiness: явный false всегда выигрывает профильный true.
3. Если allowsTimeTracking всё ещё не задан, добавить встроенный false; это правило task:add действует также для env/file источника, без чтения defaults другого профиля.
4. После подстановки проверить required title/creatorId/responsibleId и типы итогового payload. При advanced input сохранить writable validation и существующие remote preflights.

Явный --group-id перекрывает default.task.groupId; --creator перекрывает creatorId; --responsible перекрывает responsibleId. Advanced --fields/--fields-file с groupId/creatorId/responsibleId/allowsTimeTracking перекрывают соответствующий default по полям. Самостоятельный task flag для учёта времени этим уточнением не вводится: явное boolean значение доступно через существующий advanced JSON. Boolean defaults также должны проходить local type validation в basic mode.

Все значения default.task применяются только к task:add. task:list/find/update/assign и другие операции не наследуют ни ID, ни allowsTimeTracking из профиля. Если effective source — process env, --env-file или B24CLI_ENV_FILE, нельзя брать default.task сохранённого defaultProfile: это мог бы быть другой портал. Profile-local defaults доступны только при выборе сохранённого профиля (explicit selector, runtime config, B24CLI_PROFILE или saved default). Invocation context передаёт non-secret profile metadata отдельно от webhook.

Dry-run показывает итоговые groupId/creatorId/responsibleId/allowsTimeTracking и источник каждого значения (explicit/profile default/builtin default/absent). Допустимо чтение non-secret metadata без credential file, записи config или нового API preflight; существующие remote preflight rules advanced mode сохраняются.

Canonical --group-id уже поставлен отдельно в [#27](https://github.com/mesilov/bitrix24-cli/issues/27), [PR #31](https://github.com/mesilov/bitrix24-cli/pull/31) в main; --project удалён без alias. Архив — openspec/changes/archive/2026-10-11-unify-group-id-option/. Реализация default.task остаётся в #19.

Примеры проектируемого поведения для work.default.task={groupId:42, creatorId:1, responsibleId:2, allowsTimeTracking:false}:

```sh
# Дефолты заполняют обязательных участников и группу
b24cli --profile work task:add --title "Договор"

# Явные значения перекрывают дефолты независимо
b24cli --profile work task:add --title "Договор" --group-id 77 --creator 3 --responsible 4

# Advanced JSON: явный учёт времени, остальные поля из профиля
b24cli --profile work task:add --fields '{"title":"Договор","allowsTimeTracking":true}'
```

Первый вызов подготовит title, groupId=42, creatorId=1, responsibleId=2, allowsTimeTracking=false. Без profile creatorId/responsibleId пользователь обязан передать соответствующие явные поля; default.task не отменяет требования API. Profile defaults ещё не реализованы.

### 6. Проверка и сборка

Unit/subprocess matrix покрывает precedence, --config profile, --profile default migration, selected failures, zero API calls, read-only offline commands и fixture secret redaction; четыре окружения с обязательными name/connection, различие name и transport hostname, HTTPS authority mismatch, default.task ID/boolean types, совпадение config/API key names, required validation после подстановки, явный false против profile true, builtin false, интеграцию с canonical --group-id из #27 и отсутствие переноса defaults между профилями/источниками. Native packaging smoke выполняет профиль, explicit env-file и process env против контролируемого API transport/HTTPS fixture на Linux amd64, macOS arm64, Windows amd64. Отдельно проверяются Unicode/space paths, CRLF dotenv, ACL, atomic replace/locking и отсутствие cwd dotenv.

Compose wrapper явно forward/mount только выбранный config/file read-only для task invocation; configuration writes выполнять в управляемом пользовательском каталоге с корректной UID/ACL политикой. Не считать env host автоматически доступным внутри контейнера. No actual secrets в fixture/archive/evidence.

### 7. Публикация схемы и проверка контракта

Схема v1 публикуется в docs/config.schema.json (Draft 2020-12), описание — docs/configuration.md. Обязательные name/connection, alias pattern, managed credential reference, API-имена default.task, запрет неизвестных полей и строгие типы проверяются на общих fixtures через actual ProfileRepository и стандартный schema validator. Никакие remote $ref или schema fetch при запуске CLI не нужны.

JSON Schema default является аннотацией: allowsTimeTracking=false подставляет CLI, валидатор не переписывает config. Проверки defaultProfile∈profiles, HTTPS hostname credential, файлов/ACL/symlinks и существования credentials требуют runtime state и явно описываются вне структурной схемы. Старый root groupId и authorId не поддерживаются.

ID в CLI — JSON integer literal 1..PHP_INT_MAX, без float coercion. Стандартный abstract integer в JSON Schema допускает 1.0/1e0; лексическое ограничение CLI относится к дополнительной runtime проверке. Fixtures для него имеют отдельные schemaValid/runtimeValid с явной причиной отличия; обычные структурные constraints обязаны совпадать.

## Пользовательский путь

Это проектируемое поведение, ещё не реализованные команды. Основной сценарий: пользователь скачал бинарник, не задавал process webhook/SDK alias/B24CLI_ENV_FILE/B24CLI_PROFILE/--config и использует стандартный каталог конфигурации.

| OS | Каталог конфигурации | Как запускаются команды |
| --- | --- | --- |
| Linux/macOS | `~/.config/b24cli/`; при absolute XDG_CONFIG_HOME — `$XDG_CONFIG_HOME/b24cli/` | `b24cli ...` после установки в PATH |
| Windows | `%APPDATA%\b24cli\` | `b24cli.exe ...` в PATH; PowerShell из текущего каталога: `.\b24cli.exe ...` |

Ниже показан Unix-путь; Windows выполняет те же действия в AppData.

### 1. Скачать, распаковать и проверить бинарник

Пользователь скачивает подходящий архив, проверяет checksum по инструкции установки и распаковывает исполняемый файл; установка в PATH выполняется отдельно.

```sh
b24cli --version
b24cli --help
```

Конфигурация не создаётся: нет `b24cli/`, `config.json` и `credentials/`. Если выполнить API-команду без источника подключения, она завершится с `connection-unavailable` и предложит настройку; эта ошибка также не создаёт файлов.

### 2. Добавить первое подключение

Webhook пользователь заранее создаёт в своём портале Битрикс24, для нужного пользователя и scopes. CLI не создаёт и не отзывает webhook.

```sh
b24cli connection:add work
```

CLI запрашивает полный URL скрытым вводом, проверяет локальный формат и сохраняет:

```text
~/.config/b24cli/
├── config.json
└── credentials/
    └── 7d2d26f2.env
```

Идентификатор файла здесь пример; его генерирует CLI. При первом успешном add создаются каталог конфигурации, `credentials/`, non-secret `config.json` и credential file с полным webhook. Права устанавливает сам CLI: Unix directories 0700/files 0600, Windows user-restricted ACL.

Сразу после add:

```json
{
  "version": 1,
  "defaultProfile": null,
  "profiles": {
    "work": {
      "name": "portal.example",
      "connection": {
        "hostname": "portal.example",
        "credentialFile": "credentials/7d2d26f2.env"
      },
      "default": {
        "task": {
          "allowsTimeTracking": false
        }
      }
    }
  }
}
```

`credentialFile` — путь относительно каталога, содержащего `config.json`, а не cwd. Здесь это `~/.config/b24cli/credentials/7d2d26f2.env`. Секрет находится только в credential file. Add не назначает профиль по умолчанию и не делает запросов в портал: сохранение не доказывает действительность webhook или scopes.

Без параметров defaults первый add сохраняет только default.task.allowsTimeTracking=false; ID не назначаются автоматически. Чтобы сразу задать defaults при создании профиля:

```sh
b24cli connection:add clientA --group-id 77 --creator-id 3 --responsible-id 4 --allows-time-tracking false
```

Создаются отдельные credentials и запись clientA.default.task в том же config.json; секрет вводится скрыто. Это проектируемые параметры локальной команды.

### 3. Посмотреть сохранённые подключения

```sh
b24cli connection:list
b24cli --profile work connection:show
```

Показываются alias окружения, описательный name, connection.hostname, default.task, источник и путь к файлу; webhook не выводится. Новых файлов нет, существующие не изменяются. Указать профиль можно до выбора default.

### 4. Выбрать подключение по умолчанию

```sh
b24cli connection:use work
```

Изменяется только `config.json`: `defaultProfile` становится `"work"`. Credential file остаётся прежним, новых постоянных файлов нет. Никаких API-запросов.

### 5. Выполнить команду портала

```sh
b24cli task:list --json
```

CLI читает `config.json`, берёт default profile `work`, отсчитывает его credential reference от конфигурационного каталога, читает webhook и выполняет API-запрос. Постоянные файлы конфигурации/credentials не создаются и не изменяются.

Ошибки авторизации или отсутствующих scopes теперь обнаруживаются ответом портала. При отсутствии default без env/explicit source команда объясняет, как вызвать `connection:use NAME` или `--profile NAME`; первый профиль не выбирается автоматически.

### 6. Добавить второй портал и выбрать его разово

```sh
b24cli connection:add personal
b24cli --profile personal task:list --json
```

Add создаёт второй credential file и добавляет запись `personal` в существующий `config.json`. Каталоги и первый credential file сохраняются. Default остаётся `work`.

Task-команда читает только явно выбранное подключение `personal`; файлы не изменяются, следующий обычный `task:list` по-прежнему использует `work`. Для постоянного переключения:

```sh
b24cli connection:use personal
```

Изменяется только поле `defaultProfile` в `config.json`.

### 7. Выполнить разовую команду без сохранения профиля

Когда dotenv file уже подготовлен пользователем или смонтирован secret manager:

```sh
b24cli --env-file "/run/secrets/bitrix24.env" task:list --json
```

```powershell
.\b24cli.exe --env-file "C:\Private Config\bitrix24.env" task:list --json
```

CLI только читает указанный файл. `config.json`, пользовательские каталоги и profile credentials создавать не требуется; default не изменяется. Process `BITRIX24_WEBHOOK`, предоставленный secret manager/CI без explicit selector, также используется без сохранения на диск. Ни один пример не передаёт сам секрет аргументом.

### 8. Удалить локальное подключение

```sh
b24cli connection:remove work
```

Из `config.json` удаляется запись `work`, соответствующий credential file удаляется. Если удалённый профиль был default, `defaultProfile` становится null; другой профиль не назначается автоматически. Каталоги и `config.json` сохраняются, другие credentials не затрагиваются. Webhook в самом Битрикс24 остаётся действующим; его отзыв — отдельное действие в портале.

Конфигурация живёт отдельно от бинарника: повторный запуск или замена бинарника сами по себе не создают и не пересоздают пользовательские файлы.

## Risks / Trade-offs

- Plaintext disk/backups доступны владельцу и администраторам → минимальные права, отдельный secret file, ясная документация; keyring может быть отдельным следующим change.
- Ошибка ACL или Windows atomic replace → fail closed и native regression; Unix simulation не заменяет Windows proof.
- Старые --profile default / config.profile=default → явная migration note; обычный legacy invocation сохраняется.
- Shared default меняется другим shell → scripts используют explicit profile/file; connection:use — единственная смена default.
- Упаковка случайно включит новый config directory → сохранить explicit package allowlist/exclusion и fixture-secret scan.
- Два файла нельзя заменить одной atomic операцией → порядок публикации и locking исключают selectable broken reference; сбой cleanup честно сообщается.

## Migration Plan

1. Реализовать новый capability без auto-import: dotenv/process credentials читаются как раньше при отсутствии explicit selectors.
2. Пользователь явно создаёт профиль через hidden input/stdin и выполняет connection:use либо задаёт --profile.
3. README/help описывают --config compatibility, precedence, file protection и source vs binary lookup.
4. Пройти local gates и native CI matrix; затем verify/archive и обновить issue path.
5. Откат binary не требует удаления пользовательских файлов: прежняя версия использует legacy env; сохранить credentials и не выполнять автоматическую обратную миграцию.

## Реализация Windows ACL (12.10.2026)

FileProtection вызывает только встроенный Windows PowerShell 5.1 по абсолютному
SystemRoot/System32/WindowsPowerShell/v1.0/powershell.exe. Статический EncodedCommand
передаётся без секрета; filesystem path поступает отдельной environment variable.
WindowsIdentity.GetCurrent().User даёт SID; FileSecurity/DirectorySecurity задают
owner SID, SetAccessRuleProtection(true,false) исключает inherited grants,
FileSystemAccessRule допускает FullControl только этому SID. Каталоги наследуют
ContainerInherit/ObjectInherit для приватного создания новых файлов. Get-Acl
повторно проверяет owner, protected DACL и отсутствие чужих grants; любые failures
останавливают persistence. Файл создаётся пустым, ACL устанавливается до записи webhook.

Metadata: private temporary file → flush/fsync → close → rename(config.json) под flock.
Native connection-smoke проверяет замену через use/remove и многопроцессные добавления.
Windows native CI остаётся обязательным подтверждением; локальные Linux unit tests
не доказывают Windows ACL. Файловый backend не является keyring или шифрованием.

Первичные API references: [Set-Acl](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.security/set-acl?view=powershell-5.1),
[FileSystemAccessRule](https://learn.microsoft.com/en-us/dotnet/api/system.security.accesscontrol.filesystemaccessrule),
[SetAccessRuleProtection](https://learn.microsoft.com/en-us/dotnet/api/system.security.accesscontrol.objectsecurity.setaccessruleprotection).
