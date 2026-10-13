# Proposal

## Why

В `bitrix24-cli-maintainer` обновление remote `dev` описано только перед созданием worktree, а после merge и закрытия issue отсутствует обязательный порядок завершения. Обязательное использование официального MCP документации Битрикс24 и его подключение на уровне проекта также не зафиксированы.

## What Changes

- Непосредственно перед каждым MR в `dev` получать свежую remote `dev`, интегрировать её в ветку задачи, разрешать конфликты и повторять затронутые проверки.
- После подтверждённого merge в `dev` и закрытия issue архивировать связанный OpenSpec change и обновлять трассировку в issue.
- Сохранять результат архивирования и нужные данные до удаления связанного worktree, затем проверять удаление.
- Обязать использовать официальный MCP документации Битрикс24 при проверке методов, событий, параметров и API-контрактов; закрепить это в скилле и `AGENTS.md`.
- Подключить сервер `bitrix24_dev` по Streamable HTTP через проектный `.codex/config.toml` с `enabled = true` и `required = true`; не добавлять токены или настройки доступа к порталу.

## Capabilities

### New Capabilities

Нет. Изменение касается инструкций сопровождения и конфигурации инструмента разработки, а не runtime-поведения CLI.

### Modified Capabilities

Нет. Проверена спецификация `worktree-development`, включая требования безопасной очистки и изоляции окружений. Эти требования сохраняются; для документационной правки задано `skip_specs: true`.

## Impact

Изменяются `.agents/skills/bitrix24-cli-maintainer/SKILL.md` и `AGENTS.md`; добавляется `.codex/config.toml` с обязательным MCP документации. Команды CLI, Bitrix24 API, конфигурация и зависимости приложения не затрагиваются. Основной dirty checkout, глобальные настройки Codex и worktree других задач сохраняются.

## Sources

- [MCP-сервер Битрикс24](https://apidocs.bitrix24.ru/ai-tools/mcp.html): получен через официальный MCP (`bitrix-search` → `bitrix-article-details`); endpoint `https://mcp-dev.bitrix24.tech/mcp`, Streamable HTTP, без авторизации, только документация.
- [Конфигурация Codex](https://developers.openai.com/codex/config-reference) и [MCP в Codex](https://developers.openai.com/codex/mcp): проектный `.codex/config.toml` применяется только для доверенного проекта; `required = true` останавливает запуск/возобновление при ошибке инициализации включённого сервера. Имя `bitrix24_dev` — локальный идентификатор конфигурации, официальный пример использует `b24-dev-mcp`.

## GitHub issues

- [#65](https://github.com/mesilov/bitrix24-cli/issues/65): правила синхронизации с `dev`, завершения задачи и обязательного MCP документации Битрикс24 в скилле и проекте. Milestone: `0.2.0`; label: `documentation`.
