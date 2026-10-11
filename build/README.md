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
Composer 2.10.3, Python 3.13, Git, autoconf, automake, bison, re2c, pkgconf, cmake.
Добавьте Homebrew bison в PATH. Команда: `make package-macos`.

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

`Package standalone binaries`: PR в dev/main, push в dev, manual dispatch.
Матрица: Ubuntu 24.04 amd64 / macOS 15 arm64 / Windows 2022 amd64.
После smoke загружаются `b24cli-linux-amd64`, `b24cli-macos-arm64`,
`b24cli-windows-amd64`, включая диагностические PHAR/probe и build metadata.
Общий job готовит `b24cli-release-assets` с архивами и SHA256SUMS.

```sh
gh run download RUN_ID --name b24cli-release-assets --dir ./downloaded
```

Production-бинарники собираются в CI из конкретного version tag на коммите
ветки `main`. Workflow получает полную историю, разрешает тег в точный commit SHA
(включая annotated tag) и проверяет, что коммит входит в историю `origin/main`.
Тег на отдельной feature/dev истории отклоняется до запуска platform builders.
Можно собрать и более ранний коммит `main`; совпадение с текущим tip не требуется.
Production identity сохраняет точное имя тега, например `0.1.0` или `v0.1.0`.
PR/dev/manual workflow создаёт тестовые artifacts с `dev-<full SHA>`.

Version-tag workflow использует те же builders; publish зависит от всех checks.
Failure блокирует публикацию; на PR Release не создаётся. Публикация создаёт или
переиспользует draft, загружает все четыре проверенных assets и только затем
делает Release публичным. Повторный запуск после create/upload/publish failure
возобновляет draft; уже публичный Release оставляет без изменений. Assets:

- `b24cli-linux-amd64.tar.gz`
- `b24cli-macos-arm64.tar.gz`
- `b24cli-windows-amd64.zip`
- `SHA256SUMS`

В archive входят executable, build-info.json, runtime-sources.json и licenses.
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

Скачайте platform archive и `SHA256SUMS` из одного Release или одного CI run.
Для CI используйте artifact `b24cli-release-assets`: распакуйте внешний ZIP,
чтобы получить platform archives и `SHA256SUMS`. Перейдите в каталог с этими файлами.
Сначала checksum, затем распаковка и установка.

### Linux amd64

```sh
grep '  b24cli-linux-amd64.tar.gz$' SHA256SUMS | sha256sum --check -
tar -xzf b24cli-linux-amd64.tar.gz
mkdir -p "$HOME/.local/bin"
install -m 755 b24cli "$HOME/.local/bin/b24cli"
# Добавьте $HOME/.local/bin в PATH своего shell.
b24cli --version
```

Сохраняйте license notices рядом с установленным инструментом.

### macOS arm64 (zsh)

Проверьте archive и распакуйте его только при успешной проверке. Команды ниже
соединены через `&&`: при неверном checksum распаковка не выполняется.

```sh
grep '  b24cli-macos-arm64.tar.gz$' SHA256SUMS | shasum -a 256 --check - &&
tar -xzf b24cli-macos-arm64.tar.gz
```

Продолжайте, только если проверка вывела `b24cli-macos-arm64.tar.gz: OK`
и распаковка завершилась успешно. Первый запуск из этого каталога:

```sh
chmod +x ./b24cli
./b24cli --version
./b24cli --help
```

Пользователю не нужны PHP, Composer или Docker. Если macOS блокирует запуск,
см. [issue #23](https://github.com/mesilov/bitrix24-cli/issues/23): настройка PATH
решает поиск команды в shell, а блокировка запуска рассматривается отдельно.

После успешного первого запуска установите binary с правами на исполнение
в пользовательский каталог. `sudo` не требуется. Сохраните внешние license notices:

```sh
mkdir -p "$HOME/.local/bin" "$HOME/.local/share/b24cli"
install -m 755 b24cli "$HOME/.local/bin/b24cli"
install -m 644 THIRD-PARTY-NOTICES.md YIIPRESS-LICENSE.txt "$HOME/.local/share/b24cli/"
cp -R runtime-licenses "$HOME/.local/share/b24cli/"
```

Включите этот каталог в PATH текущей zsh-сессии:

```sh
export PATH="$HOME/.local/bin:$PATH"
rehash
```

Для новых терминалов добавьте ту же настройку в `.zshrc`. Команды учитывают
`ZDOTDIR`, если вы используете отдельный каталог конфигурации zsh,
и при повторном запуске не дублируют строку:

```sh
b24cli_zshrc="${ZDOTDIR:-$HOME}/.zshrc"
touch "$b24cli_zshrc"
grep -Fqx 'export PATH="$HOME/.local/bin:$PATH"' "$b24cli_zshrc" ||
  printf '\n%s\n' 'export PATH="$HOME/.local/bin:$PATH"' >> "$b24cli_zshrc"
```

Проверьте установку из другого каталога:

```sh
cd /tmp
command -v b24cli
b24cli --version
```

`command -v` должен показать путь к `$HOME/.local/bin/b24cli`, а команда версии —
версию скачанной сборки. Откройте новый терминал и повторите эти две проверки.
Новую interactive login zsh-сессию можно проверить и из текущего терминала:

```sh
zsh -lic 'command -v b24cli && b24cli --version'
```

После установки используйте `b24cli --help`, `b24cli help task:show` и другие
команды из любого каталога. Текущий каталог `.` добавлять в PATH не нужно.

### Windows amd64 (PowerShell)

```powershell
$expected = ((Get-Content SHA256SUMS | Where-Object { $_ -match '  b24cli-windows-amd64.zip$' }) -split '  ')[0]
if (-not $expected -or (Get-FileHash b24cli-windows-amd64.zip -Algorithm SHA256).Hash.ToLowerInvariant() -ne $expected) {
    throw 'Checksum mismatch'
}
Expand-Archive b24cli-windows-amd64.zip -DestinationPath b24cli
./b24cli/b24cli.exe --version
# Добавьте directory b24cli в пользовательский PATH.
```

Пользователю не нужны PHP/Composer/Docker. Linux использует static musl; clean
runtime проверяется на Debian 12, build job — Ubuntu 24.04. Minimum validated
matrix: macOS 15 arm64 и Windows Server 2022 x64. Старые OS требуют отдельного
acceptance. Windows требует доступный для записи TEMP для временного PHAR.
macOS/Windows используют standard OS libraries. Signing/notarization
в первой итерации не выполняются. HTTPS использует OpenSSL trust в `/etc/ssl` на Unix и Schannel certificate store
на Windows. Для отдельного PEM bundle на Unix задайте `SSL_CERT_FILE=/absolute/path/ca.pem`
(для каталога — `SSL_CERT_DIR`). На Windows добавьте корпоративный CA в Windows trust
store. Fixture harness задаёт собственный CA через cURL CAINFO и проверяет как
успешный запрос, так и отказ на чужом CA; certificate verification не отключается.

Local checks, actual CI, actual Release publication и live portal verification
фиксируются отдельно в OpenSpec verification. Packaging fixtures не обращаются
к живому порталу Bitrix24.
