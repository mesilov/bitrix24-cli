# Proposal

## Why

Поставляемые b24cli executable занимают около 63–64 MB, хотя upstream YiiPress применяет дополнительное сжатие исполняемых файлов. Уменьшение размера должно сохранить автономную установку и существующие runtime-контракты.

## What Changes

- Добавить обязательное сжатие PHP micro runtime через закреплённый UPX на Linux/Windows до добавления gzip PHAR; на macOS упаковать готовый подписанный executable закреплённым wrappe.
- Сохранить исходные runtime и PHAR, записывать метод/версию упаковки и размеры до/после в отдельный отчёт; проверять уменьшение размера до upload и release.
- Выполнять существующие native проверки на итоговых сжатых файлах, дополнительно проверяя macOS inner/outer подписи и неизменность PHAR.
- Документировать prerequisites, распаковку macOS при запуске и обязательные license notices.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `binary-distribution`: обязательное сжатие, зафиксированные упаковщики, evidence о размерах и native acceptance финального executable.

## Impact

`build/package.py`, новый compression helper/config, packaging CI, macOS acceptance, release archive allowlist и документация. Composer production lock, API-поля, CLI-команды и пользовательские настройки не изменяются. Не обещаем 6 MB: набор зависимостей отличается от YiiPress.

## GitHub issues

- [#37](https://github.com/mesilov/bitrix24-cli/issues/37) — сжатие всех трёх standalone targets и измеренная native проверка. Milestone `0.2.0`, label `enhancement`.

## Sources

- https://github.com/yiipress/engine/blob/0.1.13/docker/Dockerfile
- https://github.com/yiipress/engine/blob/0.1.13/build/package-windows.ps1
- https://github.com/yiipress/engine/blob/0.1.13/build/package-macos.sh
- https://docs.rs/crate/wrappe/1.0.4

Upstream release 0.1.13 и актуальный b24cli dev проверены 2026-10-12. Сжатие payload до micro:combine и упаковка всего macOS executable — разные стратегии.
