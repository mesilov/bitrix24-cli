# Verification: document-platform-installation

Дата: 12.10.2026. Документация установки для 0.1.0 в PR #29; issue [#25](https://github.com/mesilov/bitrix24-cli/issues/25) сейчас имеет milestone `0.2.0`, эта привязка сохранена.

## Completeness и correctness

README содержит три закрытых по умолчанию details-блока Linux/macOS/Windows. Для каждого описаны checksum перед unpack, первый запуск, notices/metadata, пользовательский PATH, проверка версии и обновление. Показаны OS/arch границы и TEMP requirement; PHP/Composer/Docker пользователю не требуются. Отказ macOS #23 не объявлен исправленным; точечное разрешение Windows не обещает обход SAC/enterprise policy. Build guide ссылается на README и сохраняет старые платформенные заголовки и HTTPS/runtime prerequisites.

Задачи 1.1/1.2/2.1 подтверждены содержимым README/build guide, task 2.2 — проверками ниже. `skip_specs: true`; Spec Coverage, Requirement Implementation Mapping и Scenario Coverage — **Not applicable**: runtime поведение и требования binary-distribution не меняются.

## Выполненные проверки

- GitHub Markdown API (`mode: gfm`, context mesilov/bitrix24-cli) отрендерил README и build guide. HTMLParser подтвердил **3 details без open**, правильные platform summaries и **5 code blocks внутри каждого**. Markdown не остался неформатированным HTML-текстом.
- Проверены **7 относительных file/fragment links**: файлы существуют, fragments соответствуют заголовкам GFM. Markdown API не вставляет page anchor ids, поэтому slug сверялся с текстом отрендеренных headings. Сохранены macOS/Linux/Windows заголовки build guide.
- **11 sh-блоков** README прошли `bash -n` и `zsh -n`.
- Безопасные fixture-проверки использовали реальный release-assets.py и shell stub вместо native b24cli. На Linux/Bash и macOS/zsh sequences: корректный архив распакован; повреждённый архив и отсутствующая checksum-строка остановили выполнение до unpack. Выявленная при проверке проблема пустого checksum исправлена обязательным успешным grep до checksum pipeline.
- В этих fixtures проверены пути с пробелами, install permissions, сохранение всех metadata/notices, существующего rc содержимого, отсутствие дублирования PATH-строки при двух повторениях и lookup после чтения rc в отдельном shell process. Для безопасного исполнения копий команд `$HOME`/ZDOTDIR token заменён на отдельные fixture variables; настоящий HOME environment и пользовательские dotfiles не изменялись. Это проверка последовательности файловых операций, не native runtime acceptance.
- installation-evidence.json сохраняет результаты GFM, syntax, links и fixtures, без raw HTML/credentials.
- Остальной README начиная с `## CLI-команды` побайтно совпадает с актуальным origin/dev, включая --group-id migration. Отдельный worktree с исходными изменениями пользователя не редактировался.
- `git diff --check`: exit 0. `openspec validate document-platform-installation --strict`: exit 0, нулевые deltas допустимы при skip_specs.
- GitHub issue body прочитан обратно и совпал с опубликованным локальным текстом; содержит change ID/path. Proposal содержит полный URL issue. Milestone issue 0.2.0 сохранён.

## Coherence и границы

Design Adherence подтверждён: README — основной источник команд, три раскрывающихся блока, user-only install/PATH, точечные разрешения с ограничениями, notices и общий CI archive layout. CRITICAL: 0; WARNING для документальной области: 0; SUGGESTION: 0.

Code Pattern Consistency — **Not verified: runtime-код не изменялся**. PowerShell команды проверены чтением на соответствие archive layout и Microsoft cmdlet semantics; **native Windows запуск и PowerShell parser не выполнялись** (pwsh отсутствует). Новая проверка скачанного native бинарника на чистых Linux/macOS/Windows, исправление #23, signing/notarization, платёж, Release и портал не выполнялись. Existing packaging CI не представлен доказательством исполнения новых README-команд.

`openspec instructions apply --change document-platform-installation --json` подтвердил **4/4 tasks**, remaining 0, state all_done. `openspec archive document-platform-installation --yes --json` успешно создал `openspec/changes/archive/2026-10-12-document-platform-installation/`, specsUpdated false. Публикация в PR подтверждается отдельно по GitHub head SHA после push.
