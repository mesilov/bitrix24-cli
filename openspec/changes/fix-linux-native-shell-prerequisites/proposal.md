## Why

Intermediate run 38949388094 из `builds` 6e452e3 прошёл native runtime, connection smoke и установку архива на Linux, но упал в `shell-installer-check.py::test_documented_shell_sequences`: на `ubuntu-24.04` нет zsh. Проверка требует Bash и zsh для приёмки документированных shell-последовательностей. Linux-архив не загружается, archive-set job не стартует, standalone-приёмка EMP-09 заблокирована. macOS и Windows jobs прошли.

## What Changes

- Linux job в `.github/workflows/package-platforms.yml` явно устанавливает Bash и zsh до приёмки и проверяет, что оба исполняемых файла доступны.
- Обязательные shell-проверки остаются без изменений: тесты не пропускаются и не ослабляются.

## Capabilities

### New Capabilities
Нет.

### Modified Capabilities
Нет: исправляется только окружение Linux CI. Требование к Bash/zsh-последовательностям в `binary-distribution` остаётся прежним (`skip_specs: true`).

## Impact

`.github/workflows/package-platforms.yml`, CHANGELOG. Команды продукта, API, зависимости и build/tests не изменяются.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/177 — milestone 0.3.0; блокер трёхплатформенной intermediate-сборки и standalone-приёмки EMP-09.
