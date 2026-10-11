# Исследование конфигурации и credentials в CLI

Дата проверки: **11.10.2026**. Issue: [#19](https://github.com/mesilov/bitrix24-cli/issues/19). Первичные источники — официальные manuals; чужие CLI и OS credential stores не устанавливались и не проверялись runtime-тестами.

## Наблюдаемые контракты

| CLI | Где хранится конфигурация/секрет | Выбор при запуске и автоматизация | Полезный паттерн |
| --- | --- | --- | --- |
| AWS CLI | `~/.aws/config` и отдельный plaintext `~/.aws/credentials`; Windows использует user profile directory. | `--profile` / `AWS_PROFILE`; `AWS_CONFIG_FILE` / `AWS_SHARED_CREDENTIALS_FILE`. Process credentials — отдельная часть provider chain. | Profiles + metadata/credentials separation. |
| GitHub CLI | System credential store; при проблеме возможен plaintext fallback. `GH_CONFIG_DIR` меняет путь; defaults учитывают XDG/AppData. | `GH_TOKEN` выше сохранённого token; `gh auth login --with-token` читает stdin. | Hidden/stdin setup, env для CI, явное объяснение fallback. |
| gcloud | Config directory обычно `~/.config/gcloud` на Linux/macOS и `%APPDATA%\\gcloud` на Windows. Named configuration содержит properties, включая выбранный account; это не утверждение о шифровании tokens. | `--configuration` выбирает на один запуск, `CLOUDSDK_ACTIVE_CONFIG_NAME` — в shell, `CLOUDSDK_CONFIG` — каталог. | Разовый выбор и persistent default — отдельные действия. |
| kubectl | `~/.kube/config`; contexts группируют cluster/user/namespace. | `--kubeconfig` выбирает один файл; `KUBECONFIG` объединяет список с first-wins правилами; Unix ':' и Windows ';'. | Explicit file без merge предсказуем; сложность merge не нужна для v1. |
| Docker CLI | `~/.docker/config.json`; helpers могут использовать macOS Keychain, Windows Credential Manager, Linux pass/Secret Service. Без helper возможен base64 в config. | `--config` выше `DOCKER_CONFIG`; `--context` выбирает подключение; `login --password-stdin` принимает secret. Helper executable нужен в PATH. | Store adapter boundary; secret через stdin; base64 не является шифрованием. |
| Terraform | Windows `%APPDATA%\\terraform.rc`, Unix `~/.terraformrc`; login сохраняет plaintext `credentials.tfrc.json`; optional credentials helper. | `TF_CLI_CONFIG_FILE`; service-host `TF_TOKEN_*` credentials выше config, затем helper. Приоритет изменился с 1.2. | OS paths и credentials precedence — публичный контракт, который нужно явно документировать. |

Источники для строк таблицы:

- AWS: [configuration/credential files](https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-files.html), [provider precedence](https://docs.aws.amazon.com/cli/latest/userguide/cli-chap-configure.html).
- GitHub: [auth login](https://cli.github.com/manual/gh_auth_login), [environment](https://cli.github.com/manual/gh_help_environment).
- Google: [managing configurations](https://docs.cloud.google.com/sdk/docs/configurations).
- Kubernetes: [kubeconfig organization/loading/merge](https://kubernetes.io/docs/concepts/configuration/organize-cluster-access-kubeconfig/).
- Docker: [global config flags](https://docs.docker.com/reference/cli/docker/), [credential stores/stdin](https://docs.docker.com/reference/cli/docker/login/), [persistent and invocation context](https://docs.docker.com/reference/cli/docker/context/use/).
- HashiCorp: [CLI configuration and credential priorities](https://developer.hashicorp.com/terraform/cli/config/config-file), [login storage](https://developer.hashicorp.com/terraform/cli/commands/login).
- Path standard: [XDG Base Directory 0.8](https://specifications.freedesktop.org/basedir/0.8/) defines absolute XDG variables and HOME/.config fallback.

## Что следует из сравнения

Это выводы для нашего продукта, а не требования vendors.

1. Единого «CLI-стандарта хранения секретов» нет. AWS/Terraform поддерживают plaintext files, gh/Docker — OS stores/helpers с fallback; выбор определяется окружением и зависимостями.
2. Каталог конфигурации, профиль, credential value и runtime options имеют разные приоритеты. Документировать одну строку «flags > env > config» недостаточно: --profile может выбрать provider, а process token — отдельно влиять на credentials.
3. Именованные профили решают сценарий нескольких порталов. Persistent default удобен человеку; explicit selector нужен scripts/parallel invocations.
4. Stdin убирает secret из argv и shell history самого вызова, но источник pipe/файл, process environment, backups и владельцы процесса остаются частью границ доступа. Не предлагать literal secret в shell command.
5. Linux headless и desktop нельзя считать одинаковыми для keyring. Наличие OS API в чужом Go/native CLI не доказывает совместимость с нашим PHP micro runtime.

## Рекомендуемый первый релиз

| Платформа | Default directory | Защита управляемых файлов |
| --- | --- | --- |
| Linux | absolute XDG_CONFIG_HOME/b24cli, иначе HOME/.config/b24cli | directory 0700 / files 0600 |
| macOS | тот же Unix layout | directory 0700 / files 0600 |
| Windows | APPDATA/b24cli | current-user restricted ACL; документировать SYSTEM/admin boundary |

Overrides: --config-dir > B24CLI_CONFIG_DIR. config.json содержит aliases окружений, обязательные name и connection.hostname/connection.credentialFile, nullable defaultProfile и optional profile-local default.task с groupId/creatorId/responsibleId/allowsTimeTracking; отдельный credential dotenv содержит полный webhook. Explicit --profile/--env-file выбирает один source; ambient credentials не смешиваются. Compatibility chain без explicit selectors сохраняет process env и существующий B24CLI_ENV_FILE. --config остаётся non-secret runtime JSON.

Защищённые файлы рекомендованы для v1 из-за одинакового standalone/headless контракта и отсутствия скачиваемых helpers. Это plaintext с access control. Keychain/Credential Manager/Secret Service и внешний helper сравниваются как следующий backend; нельзя обещать их поддержку без native прототипа. Самодельное шифрование с локальным ключом не рекомендовано.

## Проверенный код и совместимость

- Первичная проверка: dev `09c598e1eb085a125d689b1d35c1fe700d9b410a`.
- После refresh: dev `56654f1310b75580362bb5ad58b00ad63d006363` с merged standalone packaging; последнее состояние использовано для OpenSpec.
- `src/Infrastructure/Connection/EnvConnectionResolver.php`: process env > explicit env-file variable > root dotenv.
- `src/Console/B24Application.php`, `CommandRunner::context`: --profile уже существует, но допускается только default; --config содержит timeout/apiPolicy/profile.
- `build/phar-stub.php`, `build/package-phar.php`: mutable config внутри/рядом с payload не решает пользовательский storage; production package исключает local env.
- `cli-experience` оставляет auth/storage отдельной capability; `binary-distribution` сохраняет no-secret packages и offline launch.

Рекомендуемое расширение делает --profile default/config.profile=default явным сохранённым профилем. Это изменение поведения нужно пометить и объяснить migration; обычный invocation без selector продолжает принимать legacy env.

## groupId и создание задачи: проверка 11.10.2026

- [Официальные поля REST 3.0](https://apidocs.bitrix24.com/api-reference/tasks/fields-rest-v3.html) прямо указывают, что tasks.task.add принимает groupId — integer ID группы/проекта в fields.
- [Обзор API задач](https://apidocs.bitrix24.com/api-reference/tasks/index.html) описывает прежний GROUP_ID; это имя REST 1.0, а не новое поле нашей конфигурации.
- Текущий `src/Console/Command/Task/AddTaskCommand.php` объявляет --project; `src/Console/TaskInputMapper.php::patch` преобразует его в groupId; `src/Infrastructure/Bitrix24/Rest3TaskAdapter.php::add` вызывает tasks.task.add с apiVersion 3 и fields. Поэтому передача groupId уже поддержана кодом. Этот code snapshot сохраняет --project; переименование без alias поставлено отдельно в [#27](https://github.com/mesilov/bitrix24-cli/issues/27), [PR #31](https://github.com/mesilov/bitrix24-cli/pull/31) в main (merge проверен 12.10.2026). Архив: openspec/changes/archive/2026-10-11-unify-group-id-option/.
- Первоначальный profile-local groupId перенесён в default.task.groupId при уточнении контракта 12.10.2026; nested connection, описательный name и profile defaults остаются проектируемыми свойствами. Явные task fields выше profile defaults; defaults не переносятся на другой портал, env/file source или другие task-команды.
- Проверка ограничена чтением кода и текущей официальной документации. Live task:add, разрешения конкретной группы и фактическое создание задачи не проверялись.

## default.task: проверка полей 12.10.2026

По [официальной документации REST 3.0](https://apidocs.bitrix24.com/api-reference/tasks/fields-rest-v3.html):
- groupId — integer ID группы/проекта.
- creatorId — обязательный ID автора/постановщика создаваемой задачи. В default.task используется то же имя creatorId, без альтернативного ключа автора.
- responsibleId — обязательный ID исполнителя (assignee), соответствует пользовательскому «ответственный».
- allowsTimeTracking — boolean: включён ли учёт времени по задаче. REST 3.0 использует true/false; строки Y/N не поддерживаются.
- Встроенный false при отсутствии настройки — выбранный контракт b24cli по запросу пользователя; это не утверждение о серверном default.

В worktree #19 проверены TaskInputMapper::patch (creator/responsible → creatorId/responsibleId), FieldSchema::WRITABLE (включает allowsTimeTracking), FieldSchema::localPatch (обязательные поля сейчас проверяет до будущих defaults) и AddTaskHandler::prepare (advanced writable metadata check). Реализация должна вставить defaults до required validation, сохранить advanced preflight и добавить строгую local boolean validation для подготовленных basic payload.

default.task необязателен при чтении; connection:add пишет минимум allowsTimeTracking=false. groupId/creatorId/responsibleId необязательны, но supplied values — положительные JSON integers. Применять defaults по отсутствию API key, не по truthiness; явный false должен перекрывать profile true. Без явного/default автора или исполнителя API requirements остаются ошибкой до вызова. Webhook user ID не становится автоматическим автором.

Проверка ограничена официальной документацией и чтением кода. Создание задачи, конкретные права автора/исполнителя/группы и серверное поведение учёта времени не проверялись live.

## Что ещё не доказано

- File backend, global selectors и local commands пока только спроектированы.
- Windows ACL/atomic replace и native binary connection smoke должны пройти в реализации. Unit path simulation этого не доказывает.
- Не выполнялись запросы в реальные Bitrix24 порталы и native keyring прототипы.
- OAuth token lifecycle не относится к существующему webhook MVP и не включён в change.
