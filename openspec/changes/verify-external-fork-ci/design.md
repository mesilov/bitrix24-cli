# Design

## Context

Мотивация и границы — в proposal.md. Существующие четыре workflow используют push/pull_request, contents: read и Docker/Make; их реальные внутренние runs записаны в `add-ci-linters/verification.md`. Данных реального fork под другим владельцем нет.

## Goals / Non-Goals

**Goals:** получить отдельное воспроизводимое доказательство существующего fork-контракта для 0.2.0.

**Non-Goals:** менять workflows, расширять token permissions, создавать публичный fork в рамках подготовки этого плана, внедрять новые проверки или объявлять RC 0.1.0 проверенным для всех GitHub policies.

## Decisions

1. Использовать реальный fork под согласованным другим владельцем и PR в dev; внутренний PR не заменяет этот case. Имитация контекста Actions также не доказывает owner approval и token policy.
2. Проверить все четыре checks на одном точном fork head; фиксировать owner/base, event, workflow/job URLs, conclusion и шаги одобрения. Статус skipped/cancelled не считается успешной проверкой.
3. Не предоставлять пользовательские секреты и portal credentials; сохранить текущие минимальные permissions. Если GitHub требует owner approval, записать это как условие запуска.
4. Сохранить evidence отдельно от RC 0.1.0. Результаты внутренних runs сохраняются; перенос milestone не создаёт нового runtime proof.

## Risks / Trade-offs

- [Настройки владельца ограничивают fork jobs] → записать фактическую policy и требуемое одобрение, не ослаблять permissions ради теста.
- [Создание тестового репозитория затрагивает отдельного владельца] → перед будущим apply определить согласованный fork; подготовка плана его не создаёт.

## Migration Plan

В 0.2.0 выполнить задачи ниже, закрыть только собственный тестовый PR, сохранить evidence и выполнить verify/archive. Runtime CLI не меняется; откат переноса приёмки требует нового явного решения о milestone.
