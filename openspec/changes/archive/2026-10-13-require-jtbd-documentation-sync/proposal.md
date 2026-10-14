# Proposal

## Why

При реализации новых JTBD документ docs/task-jtbd.md может отставать от канонических требований и runtime. Требуется закрепить его сопровождение в скилле мейнтейнера и сделать ссылку доступной из шапки README.

## What Changes

- Добавить в bitrix24-cli-maintainer правило обновлять docs/task-jtbd.md в том же MR при добавлении/изменении JTBD или связанных CLI-команд.
- Сохранить ID, роли, навигацию, таблицы команд/файлов тестов, статусы и границы покрытия; проверять актуальность перед MR и merge.
- Добавить ссылку JTBD по ролям в шапку README и запись CHANGELOG Unreleased.

## Capabilities

### New Capabilities

Нет (`skip_specs: true`): только инструкции и навигация документации.

### Modified Capabilities

Нет: runtime и требования приложения не меняются.

## Impact

`.agents/skills/bitrix24-cli-maintainer/SKILL.md`, README.md, CHANGELOG.md и артефакты этого change. Документ JTBD остаётся текущим; новая обязанность применяется к будущим релевантным изменениям. Область #100 с runtime help не меняется.

## GitHub issues

- [#105](https://github.com/mesilov/bitrix24-cli/issues/105) — правило синхронизации JTBD и ссылка в шапке README, milestone 0.2.0, documentation.
