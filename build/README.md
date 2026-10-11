# Standalone binary packaging

Пайплайн: production Composer install по lock → PHAR → PHP micro → бинарник.
Обвязка адаптирована из [YiiPress](https://github.com/yiipress/engine/tree/e61459fc788a74b91188d5d41a5fca59a02a229e).
PHP, static-php-cli, PHP micro и extensions закреплены в [config.json](config.json).

## Сборка

Работайте из отдельного checkout/worktree. Staging, Composer cache и toolchain
находятся в `runtime/package-<target>/`, результаты — в `dist/<target>/`.
Они не используют development `vendor/`. Production install выполняется с
`--no-dev --no-plugins --no-scripts`; при появлении runtime generation hooks
нужно явно добавить staging step и проверки.

Linux amd64: Docker, Buildx, Git, Make. На ARM Docker использует amd64 emulation;
первую полную компиляцию лучше выполнять на native amd64 CI runner.

```sh
make package-phar
make package
```

macOS arm64: macOS 15, Xcode Command Line Tools, PHP 8.5.11 с production extensions,
Composer 2.8, Python 3.13, Git, autoconf, automake, bison, re2c, pkgconf, cmake.
Добавьте Homebrew bison в PATH. Команда: `make package-macos`.

Windows amd64: Windows Server 2022 runner/совместимая build machine, VS 2022/MSVC
x64 developer environment, PHP 8.5.11 с production extensions, Composer 2.8
(`composer.phar` рядом с launcher), Python 3.13, Git, tar, PowerShell.
Команда: `./build/package-windows.ps1` или `make package-windows`.

PHP/Composer нужны только на машине сборки. Development identity:
`dev-<full commit SHA>`. Для локальной проверки release identity задайте
`B24CLI_RELEASE_VERSION=0.1.0`: это не создаёт tag или Release.
`B24CLI_SOURCE_SHA` позволяет передать SHA явно; неверная identity отклоняется
до создания staging. По умолчанию SHA получается через Git.

Пакет включает точку входа, src, production vendor, LICENSE и build metadata.
Resource roots перечислены в config.json. Symlinks и локальные `.env`/credentials
в application staging отклоняются; поставляемые vendor `.env` и test directories
исключаются. Installed package set проверяется против production lock.

## Проверки

```sh
python3 build/tests/smoke.py dist/macos-arm64 --expected-version "dev-$(git rev-parse HEAD)"
```

На Windows используйте `python` и `dist/windows-amd64`. Linux harness выполняется
в чистом Python container с mounts только artifacts и smoke.py. Проверяются
exact version, help/list, Unicode usage error/exit code, SDK autoload, production
extensions, BCMath/ICU, trusted HTTPS и отказ на чужой CA. Smoke копирует artifacts
в temp directory, убирает build tools из PATH и использует whitelist environment
без portal credentials и PHP/LD/DYLD конфигурации.

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

Version-tag workflow использует те же builders; publish зависит от всех checks.
Failure блокирует публикацию; на PR Release не создаётся. Tags: `0.1.0` или
`v0.1.0`; `--version` сохраняет точное tag имя. Assets:

- `b24cli-linux-amd64.tar.gz`
- `b24cli-macos-arm64.tar.gz`
- `b24cli-windows-amd64.zip`
- `SHA256SUMS`

В archive входят executable, build-info.json, runtime-sources.json и licenses.
Версии C libraries выбираются закреплённой конфигурацией static-php-cli, actual
sources записаны в runtime-sources.json. Побитовая reproducibility не заявляется.
Первый compile может быть долгим. Native cache key учитывает target/config/builder/
lock. Диагностика: CI logs и `runtime/package-*/static-php-cli-*/log/`.

## Установка

Скачайте platform archive и SHA256SUMS из одного Release. Сначала checksum,
затем распаковка и установка. Linux:

```sh
grep '  b24cli-linux-amd64.tar.gz$' SHA256SUMS | sha256sum --check -
tar -xzf b24cli-linux-amd64.tar.gz
mkdir -p "$HOME/.local/bin"
install -m 755 b24cli "$HOME/.local/bin/b24cli"
# Добавьте $HOME/.local/bin в PATH своего shell.
b24cli --version
```

macOS: `grep '  b24cli-macos-arm64.tar.gz$' SHA256SUMS | shasum -a 256 --check -`,
затем распакуйте этот archive и выполните те же install/PATH/version steps.
Сохраняйте license notices рядом с установленным инструментом.

Windows PowerShell:

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
acceptance. macOS/Windows используют standard OS libraries. Signing/notarization
в первой итерации не выполняются. HTTPS требует CA trust configuration системы/
HTTP backend; собственный CA нужно явно добавить в trust, certificate verification
не отключается.

Local checks, actual CI, actual Release publication и live portal verification
фиксируются отдельно в OpenSpec verification. Packaging fixtures не обращаются
к живому порталу Bitrix24.
