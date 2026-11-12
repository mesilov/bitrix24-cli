# Verification

База: `origin/dev` `201e643c35e7f3733fc5afed3a0d82e53ccdb811`. Дата: 2026-11-12.

## Результаты

| Проверка | Результат |
|---|---|
| `quick_validate.py .agents/skills/bitrix24-cli-maintainer` | Skill is valid! |
| Локальные ссылки `../` в скилле | Все существуют |
| Заголовки скилла относительно `dev` | Все разделы `dev` сохранены; добавлены подраздел проверки сырыми запросами (4 подзаголовка) и «Только gh» |
| `openspec validate add-maintainer-raw-rest-scenario-check --strict` | valid (`skip_specs: true`) |
| `openspec validate --all --strict` | 28 passed, 1 failed: `define-task-jtbd`. Этот сбой есть и на чистой `dev` (27 passed, 1 failed) и не связан с change |
| `git diff --check` | pass |
| `CHANGELOG.md` | Собственная запись в `Unreleased → Изменено` со ссылкой на #192 |
| Трассировка | `proposal.md` → #192; #192 → `openspec/changes/add-maintainer-raw-rest-scenario-check/` |

## Границы проверки

- Изменены только инструкции сопровождения. Runtime CLI, API-вызовы, тесты и JTBD не менялись; PHP-проверки не нужны.
- Пример `curl --config -` не выполнялся против портала в рамках этой задачи: это правило работы, а не runtime-код.
- Шаг проверки сырыми запросами для самого #192 не релевантен: issue не добавляет и не меняет вызовы REST API.
