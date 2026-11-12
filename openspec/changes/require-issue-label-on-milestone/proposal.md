# Proposal

## Why

Скилл мейнтейнера требует привязать issue к milestone и применять стандартные labels, но не связывает эти шаги. В milestone 0.3.0 восемь issues оказались без метки типа: #148, #152, #178, #179, #182, #184, #185, #186. Отчёт по milestone с группировкой «ошибки / новый функционал / документация» без метки неполон.

## What Changes

- При назначении issue в milestone мейнтейнер обязательно ставит метку типа по анализу заголовка и описания issue: `bug`, `enhancement` или `documentation`.
- Определены правила выбора основного типа, второй метки и действий, если тип неясен: milestone без метки не назначается.
- Проверка метки встроена в шаг 1 «Порядка работы над feature или bugfix» и в проверки перед MR.

## Capabilities

### New Capabilities

Нет. Изменяются инструкции сопровождения, а не поведение CLI.

### Modified Capabilities

Нет. Для документационной правки задано `skip_specs: true`.

## Impact

Изменяются `.agents/skills/bitrix24-cli-maintainer/SKILL.md`, `CHANGELOG.md` и артефакты этого change. Runtime CLI, API-вызовы и JTBD не меняются. Незакоммиченные изменения основного checkout сохраняются.

## GitHub issues

- [#205](https://github.com/mesilov/bitrix24-cli/issues/205): обязательная метка типа при назначении issue в milestone в скилле мейнтейнера. Покрывает всё issue. Milestone: 0.3.0; label: documentation.
