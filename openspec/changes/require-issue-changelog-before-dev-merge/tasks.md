# Tasks

## 1. Правило changelog

- [x] 1.1 Добавить в maintainer-скилл обязательную содержательную правку CHANGELOG для каждого MR в dev по issue, с проверкой перед MR и merge; сверить все категории работы с proposal/design и запустить штатный валидатор скилла.
- [x] 1.2 Добавить запись о текущей правке в Unreleased со ссылкой на issue #78; проверить её содержание в diff CHANGELOG.md относительно актуальной remote dev.

## 2. Проверка готовности

- [x] 2.1 Обновить ветку от remote dev перед MR, повторить проверки скилла и changelog, проверить локальные ссылки, выполнить openspec validate --strict и git diff --check, сверить issue ↔ change и сохранить результат verify.

## Workflow follow-up

- Открыть MR в dev со ссылками на issue #78, change и записью changelog.
- После согласованного merge и закрытия issue архивировать change, обновить трассировку и сохранить архив в dev через MR, также с записью changelog; удалить связанный worktree после сохранения данных.
