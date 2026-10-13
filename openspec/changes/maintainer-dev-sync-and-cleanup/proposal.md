# Proposal

## Why

В `bitrix24-cli-maintainer` обновление remote `dev` описано только перед созданием worktree. Перед открытием MR ветка может устареть, а после merge и закрытия issue отсутствует обязательный порядок архивирования change и удаления worktree.

## What Changes

- Непосредственно перед каждым MR в `dev` получать свежую remote `dev`, интегрировать её в ветку задачи, разрешать конфликты и повторять затронутые проверки.
- После подтверждённого merge в `dev` и закрытия issue архивировать связанный OpenSpec change и обновлять трассировку в issue.
- Сохранять результат архивирования и нужные данные до удаления связанного worktree, затем проверять удаление.

## Capabilities

### New Capabilities

Нет. Изменение касается инструкций сопровождения, а не runtime-поведения CLI.

### Modified Capabilities

Нет. Проверена спецификация `worktree-development`, включая требования безопасной очистки и изоляции окружений. Эти требования сохраняются; для документационной правки задано `skip_specs: true`.

## Impact

Изменяется `.agents/skills/bitrix24-cli-maintainer/SKILL.md`. Команды CLI, Bitrix24 API, конфигурация и зависимости приложения не затрагиваются. Основной dirty checkout и worktree других задач сохраняются.

## GitHub issues

- [#65](https://github.com/mesilov/bitrix24-cli/issues/65): правила синхронизации с `dev` перед MR и обязательного архивирования change с безопасным удалением worktree после завершения задачи. Milestone: `0.2.0`; label: `documentation`.
