# База знаний 2.0

[Обзор CLI](../README.md#cli-команды) · [Проверка разрешений](auth-scopes.md) · [JTBD: KB-01…KB-05](task-jtbd.md#knowledge-base)

Namespace `kb:*` читает и пишет статьи Базы знаний 2.0 через REST 3.0 (`note.*`). Нужен scope вебхука `note` и коммерческий тариф. Команды: `kb:list` (базы знаний), `kb:article:list` (дерево статей), `kb:article:search` (поиск), `kb:article:show` (статья), `kb:article:add` (создание), `kb:article:update` (замена заголовка и содержимого). Issue — [#234](https://github.com/mesilov/bitrix24-cli/issues/234).

Поддерживается только База знаний 2.0. Старая база знаний на лендингах (`landing.*`) в `kb:list` не попадает. Архивирование и удаление статей, создание и изменение самих баз знаний, вложения и файлы в CLI пока не входят.

```sh
b24cli kb:list --json
b24cli kb:article:list --collection 42
b24cli kb:article:search --query 'отпуск' --json
b24cli kb:article:show 77 --json
b24cli kb:article:show 77 --markdown > article.md
b24cli kb:article:add --collection 42 --title 'Регламент отпусков' --markdown-file vacation.md --dry-run --json
b24cli kb:article:add --collection 42 --parent 77 --title 'Заявление' --markdown-file - < statement.md
b24cli kb:article:update 77 --markdown-file article.md --json
b24cli kb:article:update 77 --title 'Регламент отпусков 2026'
```

## Найти статью

| Задача | Команда | Что вернёт |
| --- | --- | --- |
| Выбрать базу знаний | `kb:list` | Все доступные базы: `id`, `name`, `policyLevel`, `accessLevel`, `isArchived`; страницы обходятся до конца |
| Посмотреть структуру | `kb:article:list --collection ID` | Дерево статей без архивных: в JSON — `items[].children[]`, в human/plain — плоский список с `depth` |
| Найти по теме | `kb:article:search --query TEXT` | Статьи из всех доступных баз и статьи с прямым доступом: `documentId`, `collectionId`, `title`, `score`, `snippet`, `sharedAccess` |

Поиск ранжирован, курсора у метода нет: `meta.hasMore = true` означает, что за пределами страницы есть ещё результаты. Уточните `--query` или увеличьте `--limit` (до 200). `snippet` в JSON приходит как HTML портала с выделением `<mark>`; human-вывод показывает его без тегов.

Поля ответа передаются без переименования, включая не описанные в документации (`state`, `accessLevel`, `markdownDescription`). Значения `policyLevel` на портале (`portal`, `private`) отличаются от перечисленных в документации; CLI их не проверяет.

## Прочитать статью

`kb:article:show ID --json` возвращает статью в `data.article`: `title`, `markdown`, `collectionId`, `parentId`, авторов и даты. Портал отдаёт и статьи из корзины — смотрите `isTrashed`.

`--markdown` выводит только содержимое статьи байт в байт, без таблиц и добавленных символов. Флаг несовместим с `--json` и `--plain`.

## Создать статью

`kb:article:add --collection ID --title TITLE [--parent ID] [--markdown-file PATH|-]` создаёт одну статью. Заголовок обрезается по краям, длина — от 1 до 255 символов. Содержимое читается из файла или stdin без изменений, до 1 048 576 байт. Дочерняя страница должна быть в той же базе знаний, что и родитель.

До записи CLI читает дерево базы знаний и ничего не создаёт, если:

- у того же родителя уже есть статья с точно таким же заголовком — `article-exists` (exit 4), ID в `error.details.candidates`;
- дерево обрезано порталом — `search-incomplete` (exit 4).

`--allow-duplicate-title` пропускает проверку. Архивные статьи проверка не видит.

## Обновить статью

`kb:article:update ID [--title TITLE] [--markdown-file PATH|-]` меняет заголовок, содержимое или оба. Новое содержимое **заменяет текст статьи целиком**: частичной правки в API нет. Сценарий правки:

```sh
b24cli kb:article:show 77 --markdown > article.md
# изменить article.md
b24cli kb:article:update 77 --markdown-file article.md --json
```

API не защищает от одновременной правки: побеждает последняя запись. Перед записью сравните `updatedAt` из `kb:article:show` с тем, что видели при чтении.

Если статья открыта в совместном редакторе с несохранёнными изменениями, портал может отклонить запись. CLI сообщает об ошибке с подсказкой про `--overwrite` и сам `overwrite=true` не передаёт. Повторяйте с `--overwrite`, только если содержимое действительно нужно перезаписать.

## Исход записи

`--dry-run` показывает план без записи: базу знаний, ID статьи, заголовок, размер и SHA-256 содержимого (не сам текст). `kb:article:add --dry-run` выполняет проверку дубля.

Запись выполняется одним вызовом без повтора. Если ответ потерян, `meta.outcomeUnknown = true`: проверьте `kb:article:list` (после создания) или `kb:article:show` (после обновления) перед повтором.

Статья из ответа API сверяется с запросом. Другие `title`, `collectionId` или `parentId` — `readback: mismatch`, список полей в `operation.mismatch`, exit 3. Если отличается только `markdown`, запись подтверждена, а `operation.markdownNormalized = true`: сохранённый текст — в `data.article.markdown`.

## Ошибки

| Код | Причина |
| --- | --- |
| `usage-error` (exit 2) | Нет `--collection`, пустой или длинный заголовок, содержимое больше 1 048 576 байт, запрос поиска короче 3 или длиннее 200 символов, `update` без полей; проверяется до обращения к API |
| `collection-not-found` | Базы знаний нет или она недоступна |
| `article-not-found` | Статьи нет или она недоступна |
| `parent-not-found` | Родительской статьи нет в существующей базе знаний |
| `api-validation-error` | Портал отклонил поля; `error.details.validation[]` называет поле и опцию CLI, например родителя из другой базы знаний (`--parent`) |
| `article-exists`, `search-incomplete` (exit 4) | Проверка дубля перед созданием |
| `permission-denied` | Нет scope `note`, доступа к базе знаний или REST API на тарифе |
| `scope-missing` (exit 4) | Снимок прав вебхука подтвердил отсутствие `note`; остальные команды работают |
| `partial-result` (exit 3) | Неполный список (`pagination-no-progress`, `tree-truncated`) или несовпадение статьи после записи |
