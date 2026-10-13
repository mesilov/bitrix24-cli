# Proposal

## Why

Скилл мейнтейнера проверяет трассировку, API-контракты и результаты проверок перед MR в `dev`, но не требует изменения changelog. Работа над issue может попасть в `dev` без записи о поставленном результате.

## What Changes

- Требовать содержательную правку `CHANGELOG.md` в `Unreleased` со ссылкой на issue для каждого MR в `dev` в рамках работы над issue, включая документацию, tooling и архивирование OpenSpec.
- Перед открытием MR и непосредственно перед merge проверять наличие правки в актуальном diff MR и соответствие записи итоговому объёму работы. Без записи merge запрещён, даже при зелёном CI.
- Отразить текущую правку скилла в changelog.

## Capabilities

### New Capabilities

Нет. Изменяется инструкция сопровождения, а не runtime-поведение CLI.

### Modified Capabilities

Нет. Проверена существующая `ci-quality-checks`: эта работа не добавляет автоматический CI gate. Для документационной правки задано `skip_specs: true`.

## Impact

Изменяются `.agents/skills/bitrix24-cli-maintainer/SKILL.md` и `CHANGELOG.md`. Проверка становится обязательной частью workflow мейнтейнера; изменения CI и runtime CLI в эту работу не входят.

## GitHub issues

- [#78](https://github.com/mesilov/bitrix24-cli/issues/78): обязательная запись в changelog и её проверка для любых MR в `dev` по issue. Milestone: `0.2.0`; label: `documentation`.
