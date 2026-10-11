# Verification: streamline-readme

Дата: 11.10.2026. Issue: [#18](https://github.com/mesilov/bitrix24-cli/issues/18). MR: [#20](https://github.com/mesilov/bitrix24-cli/pull/20), база `dev`.

## Результат

| Измерение | Результат |
| --- | --- |
| Completeness | Все 6 задач реализованы и проверены; результаты ниже |
| Correctness | Spec Coverage, Requirement Implementation Mapping и Scenario Coverage не применимы: `skip_specs: true`, изменения только документации |
| Coherence | README соответствует решениям design; инструкции разработки сохранены; Code Pattern Consistency не применим к изменению без кода |

Критических замечаний, предупреждений и предложений по выполненным проверкам нет. Change готов к архивированию.

## Доказательства

- README: **228 → 64 строки**, четыре сохранённых бейджа, таблица трёх платформ и 33 строки каталога CLI-команд. Остались только разделы «Скачать» и «CLI-команды», краткое назначение и одна ссылка на руководство.
- `docs/development.md` сохраняет setup/check/lint, env precedence, обоснование дополнительных extensions и lifecycle worktree. Сверено с `worktree-development` и `docker-cli-environment`; проверены остановка окружения перед `git worktree remove`, инструкции сохранения локальных файлов и явного обращения с secrets. Исправлены относительные ссылки; machine-specific env path заменён примером `/absolute/path/to/checkout/.env.local`.
- Все четыре SVG-бейджа возвращают **HTTP 200**, содержат SVG; все четыре workflow pages возвращают **HTTP 200**. Имена workflow-файлов и фильтры `dev`/`push` совпадают с репозиторием.
- Последняя успешная матрица на момент проверки: [run 38148884407](https://github.com/mesilov/bitrix24-cli/actions/runs/38148884407), source `a7024b1f0228f833d185c0e4d6c2f5a1e1a57a17`, status `completed`, conclusion `success`. `git merge-base --is-ancestor <source> origin/dev` вернул 0; `git diff <source> HEAD -- src bin config` пуст. При отсутствии завершённой новой матрицы `dev` выбран уже принятый исходный коммит, как зафиксировано в design.
- Четыре artifact IDs проверены через авторизованный GitHub API: Linux `11706600824`, macOS `11706535808`, Windows `11706070836`, install archives `11706590395`. Все `expired=false`; `expires_at=2027-01-09T14:51:34Z`, хранение 90 дней. README содержит соответствующие прямые ссылки, срок хранения, требование входа в GitHub и переход к новым сборкам.
- Реально скачан `b24cli-release-assets` run 38148884407: `b24cli-linux-amd64.tar.gz`, `b24cli-macos-arm64.tar.gz`, `b24cli-windows-amd64.zip`, `SHA256SUMS`. **Все 3 SHA256 совпадают**. В каждом архиве проверены имя executable и build metadata: source SHA и версия `dev-<source SHA>` совпадают.
- Из этого установочного архива запущен macOS arm64 `b24cli` на macOS 15.5 из каталога вне checkout с ограниченным environment, без portal credentials. `--version` соответствует source; `list --raw --no-ansi` совпадает с 30 записями `src/Bootstrap/CommandCatalog.php` плюс `list`, `help`, `completion` и с **33 командами README**. Справка всех 33 команд и документированный `b24cli help task:show` завершились с exit 0; назначения сверены с runtime help и `AsCommand` descriptions.
- Проверены **3 относительные ссылки/anchors** README и руководства; в том числе перенесённый `integration-happy-path-coverage`. Дополнительные публичные страницы (run, workflow, 5 ссылок SDK/API) возвращают **HTTP 200**.
- Четыре прямые artifact URLs анонимно перенаправляются на GitHub suite download и возвращают 404; они требуют авторизованной GitHub-сессии. Доступность проверена авторизованным API и фактическим скачиванием набора архивов. Требование авторизации подтверждается [официальной документацией GitHub](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/download-workflow-artifacts).
- `openspec validate streamline-readme --strict` и `git diff --check` проходят; двусторонняя связь issue ↔ change, label `documentation` и milestone `0.1.0` проверены.

## Границы проверки

Изменены только README, документация и OpenSpec-артефакты. PHP, CI и сборочные scripts не изменены; полная runtime test suite для документационной правки не запускалась. Локально запускался только macOS executable; запуск Linux/Windows подтверждает указанный CI run, а локальная проверка их архивов подтверждает checksum и metadata. Новая CI-матрица MR, merge, публикация Release и live portal verification не являются результатом этих локальных проверок; их состояние фиксируется отдельно.
