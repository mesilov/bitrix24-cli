# Tasks

## 1. Собственная лицензия

- [x] 1.1 Заменить root LICENSE на bitrix24-cli-license (BSD-3-Clause), Copyright 2026 Maksim Mesilov/mesilov.maxim@gmail.com и trademark ownership; обновить Composer metadata и проверить lock без изменения dependency entries, выполнить Composer strict validation и upstream notice byte identity.

## 2. License assets и документация

- [x] 2.1 Включать BITRIX24-CLI-LICENSE.txt в platform artifacts/архивы, сохранить upstream notices; добавить pack missing-notice regression и exact archive byte checks, выполнить compression/package tests.
- [x] 2.2 Обновить README/install/build notices/changelog с текущей application license; проверить local Markdown links и согласованность имён файлов в документах/pack.

## 3. Проверка изменения

- [x] 3.1 Выполнить OpenSpec strict, Composer/license checker, целевые Python package regressions и git diff --check; записать verification с фактическим evidence и границами.

## Workflow follow-up

- Verify/archive этого docs/assets change и обновить обратный путь в #47.
- Обновить PR #48 вокруг финального объёма, дождаться final native matrix/archive contents, merge в dev и проверить merge SHA.
