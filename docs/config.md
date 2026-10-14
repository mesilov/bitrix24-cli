# Конфигурация подключений b24cli

[Подключение портала и выбор профиля](connection.md) · [Краткий обзор](../README.md#конфиг)

Пользовательский `config.json` хранит параметры нескольких порталов, defaults создания задач/рабочих адресатов и локальную telemetry карточек проблем. Полный webhook находится в отдельном credential file. Формат не зависит от расположения и версии бинарника.

Машиночитаемый контракт: [config.schema.json](config.schema.json), [JSON Schema Draft 2020-12](https://json-schema.org/draft/2020-12/schema). Эта схема описывает пользовательский `config.json`. Глобальный `--config PATH` читает отдельный runtime JSON с `timeout`, `apiPolicy`, `profile`, `maxUploadBytes`.

## Каталог и файлы

Каталог выбирается в порядке `--config-dir PATH` → `B24CLI_CONFIG_DIR` → OS default:

| ОС | По умолчанию |
| --- | --- |
| Linux | Абсолютный `$XDG_CONFIG_HOME/b24cli`; иначе `$HOME/.config/b24cli`. |
| macOS | Тот же Unix layout: `$XDG_CONFIG_HOME/b24cli` либо `$HOME/.config/b24cli`. |
| Windows | `%APPDATA%\b24cli`; без AppData нужен явный каталог. |

Относительный `XDG_CONFIG_HOME` игнорируется. Явные относительные пути CLI отсчитываются от текущего каталога; пути с пробелами нужно заключать в кавычки. Папка бинарника, PHAR payload и cwd не становятся автоматическим хранилищем конфигурации.

```text
b24cli/
├── config.json
├── .lock
└── credentials/
    ├── 7d2d26f2.env
    └── 94e65b10.env
```

`credentialFile` отсчитывается **от каталога конфигурации**, а не от cwd. Имя файла генерирует CLI: в schema v1 допустим `credentials/<8..64 lowercase hex characters>.env`, текущий writer использует 32 символа. В JSON используется `/` также на Windows. Абсолютные пути, `..`, произвольные имена и обратные слеши в этой ссылке не допускаются.

Credential file содержит `BITRIX24_WEBHOOK` с полным входящим HTTPS webhook. Managed storage — plaintext с ограниченным доступом: Unix directories 0700/files 0600; Windows — проверяемый ACL текущего пользователя. Права не являются шифрованием и не защищают от администратора. `--env-file` читает внешний файл; CLI не изменяет его права и содержимое. `--env-file` и `B24CLI_ENV_FILE` принимают путь к обычному файлу или локальный `file://` URI. HTTP URL и виртуальные потоки отклоняются до чтения, включая dry-run.

## Пример schema v1

Имена и ID ниже вымышленные; секретов в примере нет.

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
          "allowsTimeTracking": true
        }
      }
    },
    "clientB": {
      "name": "client-b.example",
      "connection": {
        "hostname": "client-b.example",
        "credentialFile": "credentials/b2d9e704.env"
      }
    }
  }
}
```

## Объекты и типы

Неизвестные ключи запрещены на каждом уровне. Объекты JSON записываются как `{}`, списки `[]` вместо объектов не принимаются.

| Поле | Обязательное | Значение |
| --- | --- | --- |
| `version` | Да | JSON integer literal `1`. Другие версии отклоняются. |
| `defaultProfile` | Да | Alias существующего профиля либо `null`. |
| `profiles` | Да | Словарь профилей; пустой `{}` допустим. |
| `profiles.<alias>.name` | Да | Описательный домен портала. Не используется для DNS/HTTP. |
| `profiles.<alias>.connection` | Да | Объект параметров подключения. |
| `connection.hostname` | Да | Bare ASCII hostname для HTTPS; должен совпадать с hostname секретного webhook. |
| `connection.credentialFile` | Да | Относительная ссылка на managed credential file. |
| `default` | Нет | Объект defaults выбранного профиля. |
| `default.task` | Нет | Объект defaults только для `task:add`. |
| `default.users` | Нет | Рабочие роли и флаг автодобавки наблюдателя. |

Alias чувствителен к регистру: от 1 до 64 символов, первая буква ASCII, далее буквы, цифры, `_` или `-`. `work` и `Work` — разные профили.

`name` и `hostname` содержат bare ASCII hostname: не больше 253 символов, labels от 1 до 63 символов, буквы/цифры/внутренние дефисы. Не допускаются схема URL, порт, trailing dot, userinfo, путь, query, fragment и пробелы. `name` может отличаться от `connection.hostname`. Для международного домена нужен ASCII punycode; Unicode в файловых путях допустим.

## default.task

Имена и типы совпадают с [полями задач REST 3.0](https://apidocs.bitrix24.com/api-reference/tasks/fields-rest-v3.html).

| Ключ | Тип | При отсутствии |
| --- | --- | --- |
| `groupId` | Положительный integer | Группа не подставляется. |
| `creatorId` | Положительный integer | Автор должен быть передан явно. |
| `responsibleId` | Положительный integer | Исполнитель должен быть передан явно. |
| `allowsTimeTracking` | Boolean `true` / `false` | Используется `false`. |

ID — integer literal от 1 до 9223372036854775807 на поддерживаемых 64-bit платформах. `null`, строки, boolean, ноль, отрицательные и дробные значения отклоняются. CLI не преобразует `1.0`/`1e0` в integer. Boolean не принимает `0`/`1`, `"false"`, `"Y"`/`"N"`.

У `task:add` явное поле всегда выше одноимённого default выбранного профиля. Для basic flags это `--group-id`, `--creator`, `--responsible`; advanced `--fields`/`--fields-file` использует `groupId`, `creatorId`, `responsibleId`, `allowsTimeTracking`. Basic flags и advanced JSON остаются взаимоисключающими.

Defaults заполняются до проверки обязательных `title`, `creatorId`, `responsibleId`. Если оба участника заданы в профиле, достаточно:

```sh
b24cli --profile work task:add --title "Договор"
b24cli --profile work task:add --title "Договор" --group-id 77 --creator 3 --responsible 4
b24cli --profile work task:add --fields '{"title":"Договор","allowsTimeTracking":true}'
```

Явный `false` перекрывает профильный `true`. Отсутствующий `allowsTimeTracking` берётся из выбранного профиля, затем из встроенного `false`. Ошибочное явное значение не заменяется дефолтом. CLI не определяет автора/исполнителя по user ID webhook.

Другие команды — включая `task:list/find/update/assign` — не наследуют `default.task`. При подключении через process env или env-file профильные defaults не читаются: это мог бы быть другой портал. Встроенный `allowsTimeTracking=false` для создания действует и без профиля.

`groupId` на корне профиля и `authorId` запрещены. Используются только `default.task.groupId` и `default.task.creatorId`; aliases и автоматического переименования нет.

## default.users

Служебные настройки CLI относятся только к фактически выбранному сохранённому профилю:

| Ключ | Тип | При отсутствии |
| --- | --- | --- |
| `observerId` | Положительный integer literal | Дефолтного наблюдателя нет. |
| `managerId` | Положительный integer literal | Настроенного рабочего руководителя нет. |
| `autoAddObserver` | Boolean | `false`; автоматическое назначение отключено. |

`autoAddObserver=true` требует `observerId`. `managerId` может отличаться от руководителя по оргструктуре; получить его можно явно через `users:manager --source config`. Обычная `users:manager` использует оргструктуру. ID не вычисляются из webhook и не заменяют `creatorId`/`responsibleId`.

```json
{
  "version": 1,
  "defaultProfile": "work",
  "profiles": {
    "work": {
      "name": "work.example",
      "connection": {
        "hostname": "work.example",
        "credentialFile": "credentials/7d2d26f2.env"
      },
      "default": {
        "users": {
          "observerId": 5,
          "managerId": 9,
          "autoAddObserver": true
        }
      }
    }
  }
}
```

```sh
b24cli connection:add work --observer-id 5 --manager-id 9 --auto-add-observer true
b24cli --profile work connection:show
b24cli --profile work users:manager --source config
```

Для существующего профиля эти optional поля можно добавить в его `config.json`; сохраняйте защищённые права файла и проверяйте его по JSON Schema. Сохранение настроек не обращается к порталу. `observerId`/`managerId` являются локальными role selectors и не передаются как поля REST.

При включённой автодобавке `task:add` создаёт задачу, читает существующих наблюдателей и добавляет `observerId`, сохраняя остальные ID. Назначение использует REST1 `AUDITORS`; read relation REST3 `auditors` не записывается. При ошибке после создания CLI возвращает partial/exit3 с ID задачи и ledger: проверяйте уже созданную задачу перед следующим действием. `false` или отсутствие флага не добавляет запросов. Env/file/process подключения не наследуют роли сохранённых профилей.

Basic `task:add --dry-run` показывает будущие шаги без credentials/API; Обычный advanced `--fields` проверяется локально без API; custom input выполняет явное чтение REST1 schema перед планом записи. Явно выбранная строгая API policy проверяется до создания задачи.

## Настройка и состояние файлов

`--help`, `--version`, `list`, completion и offline dry-run не создают конфигурацию и не читают secrets. JSON Schema также не создаёт и не изменяет файлы.

```sh
# Webhook вводится скрыто; токен не передаётся аргументом
b24cli connection:add work --group-id 42 --creator-id 1 --responsible-id 2 --allows-time-tracking false
b24cli connection:list
b24cli --profile work connection:show
b24cli connection:use work
b24cli task:list --json
b24cli --profile work task:add --title "Договор" --dry-run --json
```

| Команда | Что создаётся или меняется |
| --- | --- |
| Первый `connection:add` | Каталоги, lock file, config.json, один credential file; defaultProfile остаётся null. |
| `connection:add` с новым alias | Запись в config.json и новый credential file; defaultProfile сохраняется. |
| `connection:add` с существующим alias | configuration-error / exit 1 до ввода webhook; параметры не применяются, config и credentials сохраняются. |
| `connection:use NAME` | Только metadata defaultProfile, через управляемую атомарную запись. |
| `connection:list/show`, task reads | Постоянные config/credentials не меняются. |
| `connection:remove NAME` | Запись и credential file удаляются; default очищается при совпадении, другой профиль автоматически не выбирается. |

`connection:add` сохраняет минимум `default.task.allowsTimeTracking=false`, даже без ID flags. `--portal-domain DOMAIN` меняет только описательный `name`. Для CI настройка принимает `--webhook-stdin`; stdin должен подаваться из защищённого источника, без literal webhook в shell history. Для нового alias `-n`/non-TTY без этого флага возвращает ошибку. Дублирующий профиль не обновляется: ошибка возникает до запроса или чтения webhook.

Для изменения defaults существующего профиля сначала выполните `connection:show NAME`, сохраните нужные non-secret настройки и обеспечьте доступ к webhook для повторного ввода. `connection:show` не раскрывает webhook. Затем пересоздайте профиль с полным набором нужных параметров:

```sh
b24cli connection:show work
b24cli connection:remove work
# Защищённый secret-файл должен быть доступен до удаления профиля.
b24cli connection:add work --group-id 187 --creator-id 3 --responsible-id 2 --allows-time-tracking true --webhook-stdin -n < /secure/webhook.txt
# Только если work должен быть подключением по умолчанию:
b24cli connection:use work
b24cli --profile work task:add --title "Договор" --dry-run --json
```

Удаление стирает локальный credential file и очищает совпадающий `defaultProfile`, но не отзывает webhook на портале. Повторный add не восстанавливает выбор автоматически. Defaults удалённого профиля не переносятся: omitted ID flags оставляют соответствующие ID отсутствующими, а omitted `--allows-time-tracking` задаёт `false`. При необходимости передайте `--portal-domain` заново.

Локальные `connection:*` команды не создают, не проверяют на портале и не отзывают webhook. Отзыв — отдельное действие в Битрикс24.

PowerShell выполняет те же команды с `b24cli.exe` в PATH или `.\b24cli.exe`:

```powershell
.\b24cli.exe connection:add work --group-id 42 --creator-id 1 --responsible-id 2
.\b24cli.exe connection:use work
.\b24cli.exe --profile work task:add --title "Договор" --dry-run --json
.\b24cli.exe --env-file "C:\Private Config\bitrix24.env" task:list --json
```

В cmd.exe используйте `b24cli.exe`, `set "B24CLI_PROFILE=work"` и заключайте пути с пробелами в двойные кавычки.

## Выбор подключения

Явный `--profile NAME` или `--env-file PATH` выбирает только указанный источник; они взаимоисключающие и сильнее ambient credentials. `profile` внутри runtime `--config` является явным selector, если CLI selector отсутствует. `--profile` перекрывает `config.profile`; `--env-file` конфликтует с ним.

Без explicit selector:

1. Process `BITRIX24_WEBHOOK`.
2. Process `BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK`.
3. `B24CLI_ENV_FILE`.
4. `B24CLI_PROFILE`.
5. Saved `defaultProfile`.
6. Project-root `.env.local` / `.env` только при source-запуске.

Одновременные непустые `B24CLI_ENV_FILE` и `B24CLI_PROFILE` без explicit selector — configuration-error, даже если есть process webhook. Ошибка выбранного источника не вызывает fallback. Standalone binary не ищет dotenv автоматически в cwd, рядом с executable или внутри PHAR.

```sh
b24cli --profile clientA task:list --json
b24cli --config-dir "/home/me/Private Config" --profile work task:list --json
b24cli --env-file "/run/secrets/bitrix24.env" task:list --json
```

Разовый selector не меняет defaultProfile. Для CI/контейнера process credentials либо смонтированный explicit file не требуют сохранения профиля. Docker forwarding и volumes описаны в [development.md](development.md).

`--profile default` и runtime `config.profile=default` выбирают сохранённый профиль с alias `default`.

## Проверка схемы и дополнительные проверки CLI

`config.schema.json` можно подключить в редакторе через association с пользовательским config.json. Не добавляйте `$schema` в сам config.json: root schema v1 допускает version/defaultProfile/profiles и optional telemetry. CLI проверяет формат встроенным валидатором; для запуска не требуется Python, скачивание schema или remote `$ref`.

## Локальная telemetry проблемных сценариев

Корневой optional object действует на весь каталог конфигурации, независимо от
portal profile. Эффективные defaults: локальный сбор true, лимит 100 и две даты null.
Отсутствующий узел/поле не записывается при status/list/help. Явный false отключает
новые карточки без удаления накопленных. Это локальное хранение, без auto send.

```json
{
  "version": 1,
  "defaultProfile": null,
  "profiles": {},
  "telemetry": {
    "collectProblemTrajectories": true,
    "maxProblemTrajectoryFiles": 100,
    "lastProblemTrajectoryProcessingAt": null,
    "lastProblemTrajectoryAnalysisOfferedAt": null
  }
}
```

Collection — strict boolean; max — positive JSON integer literal до PHP_INT_MAX.
Даты — null или точный UTC timestamp `YYYY-MM-DDTHH:MM:SSZ` с корректной календарной
датой. Offered фиксирует показанное предложение, Processing — успешно сохранённый
разбор snapshot, отдельно от отправки. Неизвестные ключи, invalid objects/types
отклоняются. JSON Schema defaults являются annotations; runtime применяет их явно.

Меняйте настройки через публичное действие
`doctor:problem-trajectory --action configure --settings-file PATH --json`.
Read-only doctor actions работают без credentials; первая успешная запись может
создать config с profiles={}, не добавляя портал. Profile commands сохраняют
telemetry, а doctor updates не теряют profiles/defaultProfile/credential references.
Карточки лежат в `.problem-trajectories/` рядом с этим config, с такой же защитой
доступа. [Формат, лимит, weekly разбор и явный feedback mock](problem-trajectories.md).

Стандартный schema validator проверяет структуру и значения; дополнительно CLI проверяет:

- Ненулевой defaultProfile ссылается на существующий profiles key.
- Два профиля не ссылаются на один managed credential file: удаление одного подключения не должно удалять секрет другого.
- Версия и ID записаны integer literals: JSON Schema считает `1.0` и `1e0` математическими integer, CLI отвергает такую запись без coercion.
- Credential file существует, читается и содержит корректный полный HTTPS webhook с совпадающим hostname.
- Владение, permissions/ACL и отсутствие небезопасных symlinks у managed storage.

Ключ `default: false` в схеме — аннотация; стандартный валидатор не подставляет значение и не переписывает JSON. Подстановку выполняет CLI при создании задачи. Пустые optional default/task objects допустимы, `null` и arrays — нет.

Из checkout разработчик может проверить schema, общие fixtures и все JSON-примеры этого документа:

```sh
python3 -m venv /tmp/b24cli-schema-validation
/tmp/b24cli-schema-validation/bin/python -m pip install jsonschema==4.25.1
/tmp/b24cli-schema-validation/bin/python build/tests/config-schema-check.py
make unit TEST_ARGS="--filter ConfigSchemaContractTest"
```

Парные fixture expectations явно отделяют структурные ограничения от дополнительных runtime checks. Эта проверка не делает запросов к порталу.

## Лимит загрузки локального файла

В runtime JSON, который выбирается через `--config PATH` (или `--config -` для JSON из stdin), поле `maxUploadBytes` задаёт положительное целое число в байтах. При отсутствии применяется **500 MB = 500000000 байт**. Явный config имеет приоритет над default: может уменьшить или увеличить лимит. `null`, строки, boolean, дробные числа, ноль и отрицательные значения дают usage-error до credentials/API.

```sh
cat > runtime.json <<'JSON'
{
  "timeout": 120,
  "maxUploadBytes": 750000000
}
JSON
b24cli --config ./runtime.json task:file:attach 123 --file './Отчёт.pdf' --folder-id 456 --dry-run --json
```

Лимит применяется к локальному исходному файлу, включая preview. Runtime настройка не передаётся в Bitrix24 payload и не относится к `default.task` или schema пользовательского `config.json`. Портальные квоты/права и timeout проверяются независимо; локальный лимит не обещает серверное принятие файла такого размера.
