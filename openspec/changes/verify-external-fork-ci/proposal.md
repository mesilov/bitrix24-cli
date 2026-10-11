# Proposal

## Why

Реальные push и PR внутри репозитория подтверждают работу четырёх линтеров, но внешний fork под другим владельцем ещё не проверен. По решению пользователя от 12.10.2026 этот оставшийся критерий #4 переносится из приёмки RC 0.1.0 в milestone 0.2.0.

## What Changes

- Проверить существующие license/PHP-CS-Fixer/PHPStan/Rector workflows через реальный внешний fork PR без пользовательских секретов и credentials портала.
- Зафиксировать owner approval, token permissions, source SHA и результаты всех четырёх checks; очистить собственные тестовые ресурсы.
- Сохранить границу доказательств RC 0.1.0: поддержка fork подтверждена конфигурацией, реальный прогон остаётся непроверенным.
- Перенос не отмечает внешний fork-тест выполненным и не меняет workflows, permissions или runtime CLI.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

Нет. `skip_specs: true`: проверяется существующий контракт `add-ci-linters/specs/ci-quality-checks/spec.md`; новое поведение не вводится.

## Impact

Тестовый fork/PR, GitHub Actions evidence и отчёт verification. Выполненный объём #4 остаётся в milestone 0.1.0; новая проверка относится к 0.2.0. Создание дополнительного репозитория и тестового PR выполняется в рамках будущего apply, с учётом согласованного владельца.

## GitHub issues

- [#41 — Проверить CI-линтеры через внешний fork PR](https://github.com/mesilov/bitrix24-cli/issues/41): весь change, milestone 0.2.0, стандартный label documentation. GitHub возвращает issueType=null; отдельный тип не подтверждён.
- [#4 — CI-линтеры](https://github.com/mesilov/bitrix24-cli/issues/4): источник перенесённой части task 2.2; реализация и внутренние push/PR проверки остаются в 0.1.0.

## RC 0.1.0

Перенесённый критерий не блокирует RC 0.1.0. Политика доступа GitHub и отсутствие пользовательских секретов сохраняются. Общие границы приёмки: [docs/rc-0.1.0.md](../../../docs/rc-0.1.0.md).
