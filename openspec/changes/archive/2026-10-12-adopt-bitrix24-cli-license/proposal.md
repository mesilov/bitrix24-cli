# Proposal

## Why

В рамках PR #48 пользователь 12.10.2026 запросил собственную `bitrix24-cli-license` с условиями указанной лицензии YiiPress, своими копирайтами, почтой mesilov.maxim@gmail.com и указанием владельцев товарных знаков. Сейчас LICENSE/composer metadata используют MIT, а отдельный application license не включён рядом с executable.

## What Changes

- Заменить собственный LICENSE текстом BSD-3-Clause под именем bitrix24-cli-license, Copyright 2026 Maksim Mesilov с указанной почтой; добавить информационное уведомление о принадлежности упоминаемых товарных знаков их владельцам.
- Установить стандартный SPDX metadata BSD-3-Clause в Composer и проверить lock через Composer --lock без изменения версий зависимостей. При этой metadata правке Composer сохраняет lock побайтно.
- Включать точную копию application LICENSE как BITRIX24-CLI-LICENSE.txt в каждый standalone artifact/архив и проверять её наличие/содержание. Сохранить оригинальный YIIPRESS-LICENSE.txt, copyright и прочие dependency notices.
- Обновить notices, инструкции установки, changelog и PR/issue трассировку; проверить local package regressions и final native CI.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

Нет. skip_specs: true: лицензионные документы/metadata/assets реализуют существующий binary-distribution контракт Production package contents/Installation and evidence documentation; CLI/API поведение не меняется.

## Impact

LICENSE, composer.json/Makefile license metadata, build/package.py, release-assets.py, archive/compression tests, THIRD-PARTY-NOTICES, README/build README, CHANGELOG. Composer lock проверяется без изменения зависимостей. Исходные copyrights YiiPress и зависимости не заменяются. Поставка в том же PR #48/feature/47-prepare-rc-0-1-0 в dev; прежняя RC подготовка сохранена в архиве.

## Sources

- Указанный пользователем [YIIPRESS-LICENSE.txt](https://github.com/mesilov/bitrix24-cli/blob/main/build/YIIPRESS-LICENSE.txt), локальная версия прочитана целиком; upstream BSD-3-Clause notice сохраняется побайтно.
- [SPDX BSD-3-Clause](https://spdx.org/licenses/BSD-3-Clause.html), проверено 12.10.2026: три условия redistribution/non-endorsement и disclaimer. Уведомление о товарных знаках не вводит ограничение коммерческого использования.

## GitHub issues

- [#47 — Подготовить RC 0.1.0 в dev](https://github.com/mesilov/bitrix24-cli/issues/47), milestone 0.1.0, label documentation: дополнительное явное поручение лицензии в PR #48.
