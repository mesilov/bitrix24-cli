# Standalone binary packaging

Пайплайн: production Composer install по lock → PHAR → PHP micro → бинарник.
Обвязка адаптирована из [YiiPress](https://github.com/yiipress/engine/tree/e61459fc788a74b91188d5d41a5fca59a02a229e).
PHP, static-php-cli, PHP micro и extensions закреплены в [config.json](config.json).
PHP micro скачивается как архив точного commit; alternate-source fallback отключён,
а source lock проверяется до компиляции. Compatibility patches лежат в
`build/patches/`; их SHA входят в build-info.json и cache key. Windows fake-CLI
patch исправляет лишнюю кавычку в nmake wrapper закреплённого SPC.
Windows argv patch использует преобразование wide command line из PHP CLI,
чтобы кириллица не заменялась знаками вопроса. Original и patched SHA-1 micro
архива записываются в runtime-sources.json; patch SHA-256 — в build-info.json.
На Windows bootstrap копирует embedded PHAR во временный файл и читает его
обычным PHAR wrapper: прямые micro hooks повреждают чтение payload entries.
После завершения команды временный PHAR удаляется. Все platforms используют gzip
PHAR; payload strategy и compression записаны в build-info.json.

## Сборка

Работайте из отдельного checkout/worktree. Staging, Composer cache и toolchain
находятся в `runtime/package-<target>/`, результаты — в `dist/<target>/`.
Они не используют development `vendor/`. Production install выполняется с
`--no-dev --no-plugins --no-scripts`; при появлении runtime generation hooks
нужно явно добавить staging step и проверки.

Linux amd64: Docker, Buildx, Git, Make, Python 3. На ARM Docker использует amd64 emulation;
первую полную компиляцию лучше выполнять на native amd64 CI runner.

```sh
make package-phar
make package
```

Разработчик может собрать тестовый бинарник на любом коммите любой ветки:
переключите отдельный checkout/worktree на нужный коммит и выполните `make package`
(на соответствующей OS — `make package-macos` или `make package-windows`).
Тег и принадлежность `main` для этих Make-команд не требуются. Например:

```sh
git worktree add --detach /tmp/b24cli-test-build <commit-sha>
make -C /tmp/b24cli-test-build package
```

Checkout должен быть чистым: изменённые, staged и untracked файлы отклоняются
до staging и запуска Docker. Игнорируемые `runtime/`, `dist/`, `vendor/` не мешают
повторной сборке; игнорируемые файлы внутри application/resource roots отклоняются,
чтобы содержимое бинарника соответствовало заявленному коммиту.

macOS arm64: macOS 15, Xcode Command Line Tools, PHP 8.5.11 с production extensions,
Composer 2.10.3, Python 3.13, Git, rustup с Rust 1.90.0, autoconf, automake, bison, re2c, pkgconf, cmake.
Добавьте Homebrew bison в PATH, выполните `rustup toolchain install 1.90.0 --profile minimal`. Команда: `make package-macos`.

Windows amd64: Windows Server 2022 runner/совместимая build machine, VS 2022/MSVC
x64 developer environment, PHP 8.5.11 с production extensions, Composer 2.10.3
(`composer.phar` рядом с launcher), Python 3.13, Git, tar, PowerShell.
Команда: `./build/package-windows.ps1` или `make package-windows`.

PHP/Composer нужны только на машине сборки. Development identity:
`dev-<full commit SHA>`. Для локальной проверки release identity задайте
`B24CLI_RELEASE_VERSION=0.1.0`: это не создаёт tag или Release.
`B24CLI_SOURCE_SHA` позволяет передать текущий SHA явно; значение должно совпадать с HEAD и отклоняется
до создания staging. По умолчанию SHA получается через Git.

Пакет включает `bin/b24cli`, совместимый `bin/console`, src, DI config,
production vendor, LICENSE и build metadata.
Resource roots перечислены в config.json. Symlinks и локальные `.env`/credentials
в application staging отклоняются; поставляемые vendor `.env` и test directories
исключаются. Installed package set проверяется против production lock.

## Проверки

```sh
python3 build/tests/smoke.py dist/macos-arm64 --expected-version "dev-$(git rev-parse HEAD)"
```

На Windows используйте `python` и `dist/windows-amd64`. Linux harness выполняется
в чистом Python container с mounts только artifacts и smoke.py. Проверяются
exact version, help/list, регистрацию и help всех 30 команд задач, Unicode
`task:add --dry-run`, JSON usage/connection errors и exit codes, SDK autoload, production
extensions, BCMath/ICU, trusted HTTPS и отказ на чужой CA. Smoke копирует artifacts
в temp directory, убирает build tools из PATH и использует whitelist environment
без portal credentials и PHP/LD/DYLD конфигурации.

Linux/macOS также включают `pcntl` и проверяют реальный SIGINT на offline
`task:file:attach`: подтверждённый шаг остаётся в JSON, последующие шаги не
выполняются, exit code — 130. Windows не поддерживает PHP pcntl; POSIX signal
сценарий для этой платформы не заявляется. Actual target extensions записаны в metadata.

`runtime-probe` — отдельный тестовый executable с тем же micro runtime и production
payload. Он не входит в public archive; в основном CLI нет test/debug command.

## CI и release

`Package standalone binaries` запускается по push в `builds` или вручную
для выбранной ветки или ref.
В **Actions → Package standalone binaries → Run workflow** выберите `dev`
или нужную ветку изменений. Для запуска нужны права записи в репозиторий.
Через GitHub CLI:

```sh
gh workflow run package-static.yml --ref dev
```

На MR в `dev`/`main` и push в `dev` работают тесты и линтеры.
Автоматическая сборка бинарников выполняется по push в `builds` и по релизному тегу.
Обычные push в `dev` и PR сами по себе packaging не запускают.
Матрица: Ubuntu 24.04 amd64 / macOS 15 arm64 / Windows 2022 amd64.
После smoke загружаются `b24cli-linux-amd64`, `b24cli-macos-arm64`,
`b24cli-windows-amd64`, включая диагностические PHAR/probe и build metadata.
Общий job готовит `b24cli-release-assets` с архивами и SHA256SUMS.

```sh
gh run download RUN_ID --name b24cli-release-assets --dir ./downloaded
```

### Промежуточные снимки из dev

`builds` — постоянная ветка для осознанно выбранных промежуточных сборок.
Она начинается со снимка `dev` с настройкой packaging; последующие изменения
переносятся из актуальной `dev`. Обновление ветки запускает всю native matrix,
сохраняет точный SHA коммита `builds` и не создаёт тег или GitHub Release.
Не вливайте `builds` обратно в `dev`/`main`; настройка CI доставляется в `dev`
через отдельный PR. Для обновления используйте отдельный чистый checkout:

```sh
git fetch origin
git switch builds
git merge --ff-only origin/builds
git merge --no-edit origin/dev
git push origin builds
```

При расхождении с `origin/builds` сначала согласуйте историю обычным merge;
не используйте force push/reset для выбора нового снимка. Новый push в `builds`
отменяет устаревшую packaging-сборку этой ветки. Для повторной сборки того же
снимка без нового коммита доступен ручной запуск:

```sh
gh workflow run package-static.yml --ref builds
gh run list --workflow package-static.yml --branch builds
```

После успешного run скачайте `b24cli-release-assets` по конкретному `RUN_ID`
командой выше. Platform artifacts с диагностическими probe/PHAR хранятся
7 дней, итоговый набор из трёх архивов и `SHA256SUMS` — 30 дней. Другие ref
сохраняют repository retention; значение `retention-days: 0` означает его default.
Перед распаковкой проверьте SHA-256 нужного архива по `SHA256SUMS`, затем
сверьте `build-info.json` с run SHA и выполните бинарник вне checkout:

```sh
./b24cli --version
./b24cli --about
./b24cli --help
./b24cli list
```

На Windows используйте `./b24cli.exe`. Ожидаемая версия — `dev-<полный source SHA>`;
это приёмка промежуточного снимка, финальная 0.2.0 проверяется отдельно в #109.
Имена установочных архивов одинаковы у разных runs: не смешивайте архивы,
checksums и metadata из разных сборок.

Репозиторий public, используемые Ubuntu/macOS/Windows labels относятся к
[standard GitHub-hosted runners](https://docs.github.com/en/actions/how-tos/write-workflows/choose-where-workflows-run/choose-the-runner-for-a-job):
compute бесплатен для public repositories. Одна matrix использует три параллельных
jobs (один macOS), затем archive job. Проектный timeout платформ — 90 минут;
[GitHub job limit](https://docs.github.com/en/actions/reference/limits) — 6 часов.
Retained artifacts и cache расходуют storage; его billing/budgets проверяются
отдельно. По умолчанию cache ограничен 10 GB с eviction; ветка видит свои caches
и caches default `main`, поэтому cache sibling `dev` не гарантирован.
[Cache limits](https://docs.github.com/en/actions/reference/workflows-and-actions/dependency-caching#usage-limits-and-eviction-policy)
не увеличиваются этим workflow. Короткий retention уменьшает накопление
диагностики, accepted archives следует скачать до истечения срока.

Production-бинарники собираются в CI из конкретного version tag на коммите
ветки `main`. Workflow получает полную историю, разрешает тег в точный commit SHA
(включая annotated tag) и проверяет, что коммит входит в историю `origin/main`.
Тег на отдельной feature/dev истории отклоняется до запуска platform builders.
Можно собрать и более ранний коммит `main`; совпадение с текущим tip не требуется.
Production identity сохраняет точное имя тега, например `0.1.0` или `v0.1.0`.
Ручной workflow создаёт тестовые artifacts с `dev-<full SHA>` выбранного коммита
и не публикует Release.

Version-tag workflow использует те же builders; publish зависит от всех checks.
Failure блокирует публикацию. Публикация создаёт или
переиспользует draft, загружает все четыре проверенных assets и только затем
делает Release публичным. Повторный запуск после create/upload/publish failure
возобновляет draft; уже публичный Release оставляет без изменений. Assets:

- `b24cli-linux-amd64.tar.gz`
- `b24cli-macos-arm64.tar.gz`
- `b24cli-windows-amd64.zip`
- `SHA256SUMS`

В archive входят executable, build-info.json, runtime-sources.json и licenses:
`BITRIX24-CLI-LICENSE.txt` — точная копия собственной root LICENSE;
`YIIPRESS-LICENSE.txt` — неизменённая upstream лицензия с исходным авторством.
Оба текста проверяются при подготовке установочных архивов.
После smoke CI также проверяет linkage и отказы на неверную версию/отсутствующий
executable. Проверка установки выполняет checksum → unpack → запуск через чистый PATH.
Версии C libraries выбираются закреплённой конфигурацией static-php-cli, actual
sources записаны в runtime-sources.json. Побитовая reproducibility не заявляется.
Первый compile может быть долгим. Native cache key учитывает target/config/builder/
lock. Диагностика: CI logs и `runtime/package-*/static-php-cli-*/log/`.
Компилятор вынесен в `build/runtime.py`, поэтому изменения приложения и smoke
не требуют повторной компиляции C runtime. CI передаёт read-only GitHub token
для скачивания sources через API; token не сохраняется в Docker layers или logs.
При локальном API rate limit задайте свой `GITHUB_TOKEN` в окружении native builder.

## Установка

Основной Unix путь — корневой [install.sh](../install.sh); интерфейс, prerequisites,
public Release source и предварительный просмотр приведены в
[README → Установка](../README.md#установка). Публичный URL
`https://raw.githubusercontent.com/mesilov/bitrix24-cli/main/install.sh`.
Проверка опубликованного скрипта в main выполняется отдельно от fixture acceptance.
Установщик сохраняет все packaged licenses, включая runtime/compression notices,
в `$HOME/.local/share/b24cli/package/` и создаёт `$HOME/.local/share/b24cli/env`.
Download/checksum/unpack/candidate version failures сохраняют прежний binary.

```sh
sh -n install.sh
python3 build/tests/shell-installer-check.py
python3 build/tests/shell-installer-check.py --dist dist/macos-arm64
```

`--dist` упаковывает native artifacts через release-assets.py и передаёт их
installer через controlled curl fixture с version identity из build-info.json.
Unix package jobs сохраняют существующие smoke/install checks и добавляют этот
acceptance. Workflow `Shell installer` запускает fixtures на Linux/macOS,
проверяет настоящие archives публичного `0.1.0` через controlled transport и
отдельно выполняет anonymous latest/explicit install системным curl.
Fixture scripts имитируют transport только в тестовом PATH; production installer
использует фиксированный GitHub Release URL без CI fallback или token.
Shell проверки начинают с PATH без install directory и переходят в другой каталог.
Local, native CI, public script URL и signing evidence фиксируются раздельно.


Подробные команды скачивания, checksum, распаковки, первого запуска и пользовательского
PATH находятся в [README → Установка](../README.md#установка). Там же описаны предупреждения
macOS/Windows, ограничения текущей предварительной сборки, сохранение notices и обновление.
Для CI скачивайте `b24cli-release-assets` одного run и распакуйте внешний ZIP;
для Release используйте platform archive и `SHA256SUMS` из одного Release.

### Linux amd64

Откройте в README блок **Linux amd64 — установка и PATH (Bash)**.

### macOS arm64 (zsh)

Откройте в README блок **macOS arm64 — установка, первый запуск и PATH (zsh)**.
`./b24cli` запускает файл из текущего каталога; `b24cli` требует установки в PATH.
В этом блоке описаны ad-hoc подпись готового executable, её strict-проверка
и штатное разрешение конкретного файла. Диагностику отказа запуска собираем
в [#23](https://github.com/mesilov/bitrix24-cli/issues/23).

### Windows amd64 (PowerShell)

Откройте в README блок **Windows amd64 — установка и PATH (PowerShell)**.

Пользователю не нужны PHP/Composer/Docker. Linux использует static musl; clean
runtime проверяется на Debian 12, build job — Ubuntu 24.04. Minimum validated
matrix: macOS 15 arm64 и Windows Server 2022 x64. Старые OS требуют отдельного
acceptance. Windows требует доступный для записи TEMP для временного PHAR.
macOS/Windows используют standard OS libraries. macOS artifacts получают
ad-hoc подпись после объединения runtime/PHAR; CI проверяет `codesign --verify --strict`
и совпадение байтов payload для приложения и probe перед upload.
Проверка runner-local smoke отдельно от приёмки первого запуска browser download
с quarantine: результаты последней записываются в OpenSpec verification.
Production Developer ID/notarization и Windows Authenticode в первой итерации
не выполняются. HTTPS использует OpenSSL trust в `/etc/ssl` на Unix и Schannel certificate store
на Windows. Для отдельного PEM bundle на Unix задайте `SSL_CERT_FILE=/absolute/path/ca.pem`
(для каталога — `SSL_CERT_DIR`). На Windows добавьте корпоративный CA в Windows trust
store. Fixture harness задаёт собственный CA через cURL CAINFO и проверяет как
успешный запрос, так и отказ на чужом CA; certificate verification не отключается.

Local checks, actual CI, actual Release publication и live portal verification
фиксируются отдельно в OpenSpec verification. Packaging fixtures не обращаются
к живому порталу Bitrix24.

## Сжатие исполняемых файлов

Обычные `make package`, `make package-macos` и `make package-windows` автоматически
сжимают executable. Linux/Windows: UPX 5.2.1 (`--best`) сжимает копию PHP micro
до добавления gzip PHAR. Официальные UPX archives проверяются по SHA256 из
`build/compression.json`; ранее скомпилированный runtime остаётся без изменений.
На Linux удаляется служебный UPX trailer за последним PT_LOAD, как в SPC: PHAR
должен начинаться на границе, которую вычисляет micro loader.

macOS: проверенный wrappe 1.0.4 собирается через `cargo +1.90.0 install --locked --path` в
checkout-local prefix и упаковывает готовый executable с Zstandard level 16.
Исходный inner executable сначала проходит PHAR bounds check и ad-hoc signature;
Затем исправляются Mach-O bounds итогового launcher и выполняется его подпись.
Служебный вывод wrappe отключён для сохранения CLI/JSON stdout. Это техническая ad-hoc подпись:
Developer ID/notarization остаются отдельной задачей.

При первом запуске macOS launcher распаковывает программу в доступный для записи
системный временный каталог и сохраняет кэш для следующих запусков. Нужны место
для исходного executable и права на исполнение в этом каталоге. Default runner
заменяет себя программой через Unix exec, сохраняя PID, exit code и SIGINT.
Удаление временного кэша безопасно после завершения программы; следующий запуск
распакует её снова.

`dist/<target>/compression-info.json` записывает версию/стратегию упаковщика,
размеры и SHA256 исходных и финальных b24cli/probe. Проверка:

```sh
python3 build/tests/compression-check.py
python3 build/compress.py dist/macos-arm64
```

Для Linux/Windows укажите соответствующий target directory. CI проверяет
исходный и финальный runtime с чистым PATH, HTTPS/Unicode/SIGINT fixtures,
signatures/linkage и установкой из архива. Ошибка упаковщика, несовпадающий
SHA256/version или отсутствие уменьшения размера блокируют upload и release.

Исходные executable находятся в `dist/<target>/uncompressed/` только для
диагностики CI. Public archive содержит compressed executable, compression
report и `compression-licenses/`; исходный executable, probe и PHAR не входят.
Уведомления UPX и wrappe, включая license files Rust dependencies
встроенного launcher, поставляются вместе с архивом. У upstream wrappe внешний
packer использует Cargo.lock, а вложенный runner разрешает зависимости отдельно;
Локальный build.rs patch сохраняет CARGO_HOME у вложенного runner, чтобы обе
сборки использовали checkout-local registry. SHA256 patch записан в build-info;
notices сохраняются вместе с cached tool.
