# Design

## Context

См. proposal.md. Root LICENSE — MIT; build/package.py копирует только THIRD-PARTY-NOTICES и исходный YIIPRESS-LICENSE, release-assets.py использует строгий список assets. Production PHAR уже включает root LICENSE. Все исходные dependencies/notices независимы от новой application license.

## Goals / Non-Goals

**Goals:** единая собственная лицензия в source, PHAR и внешних platform archives, проверяемая без изменения чужих notices.

**Non-Goals:** переписывать лицензии YiiPress, b24phpsdk, runtime/compressors или менять версии зависимостей/CLI/API. Товарные знаки не передаются пользователю лицензией исходников.

## Decisions

- Canonical file — LICENSE с заголовком bitrix24-cli-license и текстом BSD-3-Clause на основе указанного шаблона. Copyright 2026 Maksim Mesilov <mesilov.maxim@gmail.com>; non-endorsement относится к copyright holder/contributors. В конце отдельное информационное trademark ownership statement без нового запрета использования software.
- Composer использует стандартное BSD-3-Clause; custom display name не вводится как неподдержанный SPDX ID. composer.lock обновляется командой Composer --lock --no-install без изменения package entries.
- build/package.py копирует canonical LICENSE в BITRIX24-CLI-LICENSE.txt. pack требует оба own/upstream notices; archive-check сравнивает их байты с canonical source файлами. Compression fixture test проверяет присутствие собственного notice и отказ упаковки при его отсутствии.
- README/install копирует оба notice вместе с metadata. THIRD-PARTY-NOTICES явно различает собственную лицензию и upstream YiiPress.
- Новая проверка должна падать на текущих archives без собственного notice; после упаковки новых archives проходить вместе с native matrix. Не повторять прошедший весь runtime suite для одного docs asset до появления нового runtime diff.

## Risks / Trade-offs

- Перепутать собственное и upstream авторство → YIIPRESS-LICENSE сохраняется побайтно; два независимых notice файла в архиве.
- Стандартный SPDX metadata и собственное имя расходятся → указать BSD-3-Clause основу явно в LICENSE/README.
- Новый asset отсутствует на одной платформе → общий pack allowlist, fixture regression и проверка реальных архивов во всех трёх native jobs.

## Delivery

Дополнить PR #48, выполнить local Composer/license/package/Markdown/OpenSpec проверки, verify/archive, затем final native CI и dev merge. Обновить #47 второй change/архив и критерий лицензии.
