# Verification

Проверка 26.11.2026 в worktree `feature/234-knowledge-base-articles` от `dev` `68b9cb3`, Docker-окружение проекта (PHP 8.4.28, PHPUnit 12.5.38).

## Автоматические проверки

| Проверка | Результат |
| --- | --- |
| `make unit` | OK, 1431 тест, 22644 проверки; новый `tests/Unit/KnowledgeBaseCommandsTest.php` — 30 тестов |
| `make lint-cs-fixer` | 0 файлов к исправлению |
| `make lint-phpstan` | No errors |
| `make lint-rector` | без изменений |
| `make cli-reference-check` | карта соответствует 82 публичным командам |
| `make composer-validate` | valid |
| `openspec validate add-knowledge-base-articles --strict` | valid |

Unit-контракты покрывают: точные имена полей REST 3.0 в запросах (`collectionId`, `parentId`, `title`, `markdown`, `overwrite`, `pagination.limit`, `pagination.afterCursor`), обход курсором и `pagination-no-progress`, дерево и `tree-truncated`, поиск с `hasMore` и `snippet`, побайтный вывод `--markdown`, usage-error до credentials и API для каждого ограничения ввода, защиту от дубля (`article-exists`, `search-incomplete`, `--allow-duplicate-title`, тот же заголовок у другого родителя), один вызов записи и `outcomeUnknown`, сверку ответа (`mismatch`, `markdownNormalized`), перевод кодов ошибок портала, подсказку `--overwrite`, `strict-rest3`, офлайн-справку. Контрактные тесты каталога, справки, контейнера и scope обновлены под шесть команд и scope `note`.

## Тестовый портал

SDK playground, вебхук из `.env.local` основного checkout.

- **Integration:** `phpunit --testsuite integration-tests --group knowledge-base` — OK, 9 тестов, 119 проверок (два прогона). Каждый тест создаёт временную базу знаний `b24cli-kb <hex>` через SDK и переносит её в корзину; после прогонов на портале осталась только база «Моя компания».
- **Ручной smoke CLI** на временной базе (перенесена в корзину): `kb:list` (JSON и TSV), `kb:article:add` с `--dry-run`, создание, повтор → `article-exists`, дочерняя статья из stdin, `kb:article:list` (human и TSV), `kb:article:show --markdown` побайтно совпал с исходным файлом с таблицей Markdown, `kb:article:update` содержимого и заголовка, `kb:article:list` несуществующей базы → `collection-not-found`.
- **Сырые запросы REST 3.0** до реализации (таблица кодов ошибок — в design.md).

Наблюдения портала, учтённые в реализации: `policyLevel` = `portal`/`private`; незадокументированные `accessLevel`, `state`, `markdownDescription`; `snippet` с `<mark>`; `note.document.get` возвращает статью из корзины с `isTrashed: true`; Markdown с таблицей сохраняется без нормализации.

## Не проверено

- **Конфликт совместного редактора (задача 6.2).** Через REST не воспроизводится: две записи `note.document.update` подряд от разных пользователей проходят, побеждает последняя. Воспроизведение через редактор портала требует входа в браузере; мейнтейнер решил отложить проверку и 27.11.2026 закрыл задачу 6.2 как отложенную при архивировании. Код ошибки не снят; CLI подсказывает `--overwrite` для неизвестной ошибки записи содержимого.
- Вебхук без scope `note` и портал на тарифе без REST API — проверены только unit-контрактами.
- Дерево больше 5000 узлов (`truncated`) и многостраничный `note.collection.list` — только unit-контракты.
- Сквозной сценарий агента «нашёл регламент, дополнил, сохранил» и скилл `bitrix24-cli` (отдельный репозиторий) — не выполнялись.
