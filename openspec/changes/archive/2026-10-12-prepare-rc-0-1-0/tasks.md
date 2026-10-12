# Tasks

## 1. Аудит и завершение OpenSpec

- [x] 1.1 Сверить все issues milestone 0.1.0 с dev/архивами/evidence; сохранить audit.md с состояниями и явно незавершённой будущей работой.
- [x] 1.2 Проверить retained requirements/scenarios/design завершённых add-ci-linters и design-task-console-architecture по коду, тестам и исходному evidence; записать verification и проверить strict validation.
- [x] 1.3 Синхронизировать specs и архивировать два завершённых changes; исправить текущие относительные ссылки и подготовить обратные archived paths для issues #4/#10; проверить main requirements и Markdown targets.

## 2. Документация первой сборки

- [x] 2.1 Создать CHANGELOG.md для RC 0.1.0 и переписать README без обратной совместимости/миграции; закрепить стиль до 1.0.0 в AGENTS.md, актуализировать docs/rc-0.1.0.md; проверить command inventory, текущие источники и отсутствие старых migration notes.

## 3. Проверка кандидата

- [x] 3.1 Выполнить make check, make unit, make lint-all, strict OpenSpec validation и проверку Markdown links/anchors, git diff --check; сохранить фактические результаты в verification.md.

## Workflow follow-up

- Архивировать prepare-rc-0-1-0 после завершения реализации и OpenSpec verify; обновить путь в issue #47.
- Опубликовать PR в dev; проверить CI/native packaging matrix на final PR SHA, затем merge и checks на dev SHA.
- Обновить issues #4/#10 archived paths, issue #47 критерии и source/run refs; закрыть #47 после поставки.
- Тег 0.1.0/GitHub Release не создавать в рамках этого запроса.
