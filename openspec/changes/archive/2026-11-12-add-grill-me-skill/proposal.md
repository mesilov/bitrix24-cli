# Proposal

## Why

Перед подготовкой OpenSpec change полезно «прожарить» план: агент задаёт вопросы раундами и предлагает свой ответ на каждый, пока не останется ни одного неявного допущения. В репозитории такого скилла нет, а готовый `grill-me` из mattpocock/skills решает эту задачу.

## What Changes

- Добавить в `.agents/skills/` скилл `grill-me` и его обязательную зависимость `grilling` из mattpocock/skills без изменений.
- Зафиксировать происхождение (репозиторий, путь, commit) и приложить текст лицензии MIT.

## Capabilities

### New Capabilities

- `agent-skills`: набор скиллов агентов, поставляемых в репозитории, и правила подключения сторонних скиллов.

### Modified Capabilities

Нет.

## Impact

Только `.agents/skills/`. Код CLI, Docker-окружение и проверки не меняются.

## GitHub issues

- [#190 — Добавить скилл grill-me из mattpocock/skills](https://github.com/mesilov/bitrix24-cli/issues/190): change покрывает установку `grill-me` и `grilling`, фиксацию происхождения и лицензии. Milestone: `0.3.0`.
