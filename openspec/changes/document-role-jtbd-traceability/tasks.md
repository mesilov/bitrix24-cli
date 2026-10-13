# Tasks

## 1. Таблица и навигация

- [x] 1.1 Добавить docs/task-jtbd.md с 19 исходными IDs и JTBD-текстами в трёх группах; проверить точное равенство canonical Scenario IDs и текстов.
- [x] 1.2 Добавить в каждую строку реализованные/планируемые команды и существующие Class::method интеграционных тестов, границы и gaps; проверить равенство jtbd-coverage/CommandCatalog, все ссылки и методы.
- [x] 1.3 Добавить ссылку README и CHANGELOG Unreleased с #80; проверить ссылки и содержательную запись.

## 2. Проверка

- [x] 2.1 Выполнить strict OpenSpec, git diff --check и статическую проверку таблицы; сохранить verification.md и проверить issue ↔ proposal, milestone/label.

## Workflow follow-up

- Опубликовать ветку и MR в dev с evidence.
- После подтверждённого merge и закрытия #80 архивировать change, обновить путь в issue, сохранить архив через MR и только затем удалить worktree.
