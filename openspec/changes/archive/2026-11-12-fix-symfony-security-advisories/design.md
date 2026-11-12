## Context

`composer audit --format=json` на `dev` перечисляет: GHSA-6prc-h956-hxjq (filesystem); CVE-2026-121971, CVE-2026-123119 (http-client); CVE-2026-118514, GHSA-3h27-hjv7-fqjx, GHSA-xg2q-4rrq-657q, GHSA-5mx8-mwrp-5693 (http-foundation); CVE-2026-45070 (mime). Abandoned-пакетов нет. Все исправления есть в ветке 8.1.

## Goals / Non-Goals

Цель — `composer audit` без advisories при минимальном изменении lock-файла. Не цель — обновлять остальные зависимости, менять ограничения `composer.json` или код.

## Decisions

- `composer update symfony/filesystem symfony/http-client symfony/http-foundation symfony/mime --with-dependencies` в Docker-окружении проекта (`make composer`). Целевое обновление вместо полного `composer update` удерживает diff в пределах затронутых пакетов.
- Ограничения `^8.0` уже допускают исправленные версии, поэтому `composer.json` не меняется.

## Risks

Патч-релизы Symfony обратно совместимы; регрессию ловят unit-тесты, статический анализ и проверка старта CLI.

## Verification

`make composer ARGS="audit"` (exit 0), `make check`, `make lint-all`, `make unit`, strict OpenSpec validate. CI на MR в `dev`.
