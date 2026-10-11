# Design

## Context

См. `proposal.md`. `build/release-assets.py` уже создаёт Unix archives с `b24cli`, metadata, двумя notices и `runtime-licenses/`, а также `SHA256SUMS`. `build/tests/install-check.py` проверяет checksum/unpack/PATH программно, но пользовательского installer нет. `release.yml` публикует assets только после platform checks для version tag в истории main. На момент постановки публичных Releases нет. Изменение #24 готовит ручную macOS/zsh инструкцию.

## Goals / Non-Goals

**Goals:** автоматическая пользовательская установка существующих Unix packages, воспроизводимый выбор версии, сохранность обновления и корректная shell activation.

**Non-Goals:** Windows installer, новые targets, Homebrew formula, изменение PHP runtime, self-update CLI, автоматическая публикация Release при планировании, отключение Gatekeeper или снятие quarantine. #23 и #25 ведутся отдельно.

## Decisions

### POSIX shell and existing assets

Installer расположен в корне как `install.sh` и работает через системный `sh`. Он использует curl, tar, mkdir/install/mv и доступный SHA-256 utility (`shasum` на macOS, `sha256sum` на Linux); проверяет prerequisites до изменения установки. Bash-only синтаксис и Python/jq зависимости исключены, чтобы пользователь не готовил runtime для скачивания готового runtime. Архивный формат и release asset names сохраняются.

### Select one public release

По умолчанию resolve latest stable через GitHub release redirect, затем зафиксировать конкретный tag для archive и SHA256SUMS. `--version` задаёт опубликованный tag явно; tag проверяется как допустимый URL segment. Повторный latest lookup для второго asset исключён: иначе обновление latest может смешать версии. Установщик не требует GitHub credentials и не подменяет Release artifacts данными CI.

### Stage, validate, replace

Скачивание и распаковка идут во временный каталог, с cleanup по завершении/сигналу. Проверить единственную корректную checksum строку для выбранного archive до распаковки, archive layout/notices и результат `--version` по абсолютному пути candidate. Подготовить executable с mode 755 и notices, затем заменить binary через staging в файловой системе install directory. Ошибки download/checksum/unpack/candidate version не трогают старый binary. Installer управляет только собственными binary/notices/env entries; параметры подключения не читает и не перезаписывает. Test coverage проверяет сохранность реального предыдущего файла при каждом отказе.

### User paths and activation

Default binary directory — `$HOME/.local/bin`; notices и env-file — `$HOME/.local/share/b24cli/`. `--install-dir` меняет binary directory; env-file содержит фактический directory, с корректным quoting для пробелов. Env-file добавляет путь только если его нет в PATH.

Для zsh подключить env-file в `${ZDOTDIR:-$HOME}/.zshrc`; для bash — в `.bashrc` и выбранном login profile, чтобы interactive и login sessions проходили acceptance. Добавлять только отсутствующую собственную source-строку, сохранять имеющийся контент. `--no-modify-path` оставляет startup profiles без изменений, но env-file и инструкция по активации доступны.

`| sh` не меняет PATH родительского терминала: installer выводит `source "$HOME/.local/share/b24cli/env"` и альтернативу открыть новый терминал. Он проверяет binary по абсолютному пути, а acceptance новой shell начинается с PATH без install directory. Установка в системный bin ради обхода этой особенности потребовала бы дополнительных прав и не нужна.

### Script publication and readiness

Документируемый default URL — `https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh` после доставки installer в main. Для конкретной версии доступна tag-pinned ссылка на тот же файл. README показывает installer и inspect-before-execution вариант; ручная инструкция #24 остаётся доступной.

Публичный script URL и первый public Release — отдельные условия готовности доставки. Нельзя считать команду доступной лишь потому, что скрипт существует в feature branch. Без public Release installer выдаёт понятную ошибку. Публикация/merge выполняются по обычному release workflow, не во время постановки issue.

### Acceptance integration

Fixtures проверяют release selection, unavailable assets, неверный/отсутствующий checksum, неправильную версию, неподдерживаемый target, прежний binary/config, default/custom paths с пробелами, повторный setup и no-modify-path. Native acceptance в `package-platforms.yml` или отдельном связанном workflow использует собранные Unix archives через controlled release endpoint/transport fixture с их записанной version identity; production installer по-прежнему получает только публичные Releases. Существующие smoke/install checks сохраняются. Публичные script/assets проверяются без токена после доставки, отдельно от fixture CI.

## Risks / Trade-offs

- [Нет публичного Release] → implementation и fixture QA возможны, public quick start подтверждается после публикации; no-release поведение тестируется заранее.
- [Shell profiles имеют пользовательскую структуру] → минимальная собственная строка, ZDOTDIR, idempotency, opt-out и тестирование login/interactive режимов.
- [Частичная ошибка обновления] → candidate staging и проверки до замены binary; failure fixtures подтверждают старые bytes и config.
- [macOS блокирует executable] → явная ошибка candidate execution и ссылка на #23/#25; installer не меняет настройки защиты.

## Migration Plan

Добавить installer и fixture tests; связать native acceptance; после проверок доставить в dev через PR. Перенести installer в main в обычном release цикле, опубликовать проверенные platform assets и выполнить anonymous public smoke. Только после этого считать публичную однострочную установку рабочей. Основная спецификация синхронизируется и change архивируется после реализации и подтверждения scope, не при создании плана.
