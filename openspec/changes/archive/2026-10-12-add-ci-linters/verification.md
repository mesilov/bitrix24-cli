# Verification: add-ci-linters

Проверено 2026-10-08. Реализация: commit `93e887203ba147deebbdfe89db15603394cfabc9`, PR [#5](https://github.com/mesilov/bitrix24-cli/pull/5), issue [#4](https://github.com/mesilov/bitrix24-cli/issues/4).

## Completeness

Все четыре workflows, инструменты, конфигурации, Make-цели, lock и документация реализованы. В пересмотренной области 0.1.0 выполнены 10/10 retained tasks: 2.2 теперь учитывает только подтверждённые push и внутренние PR. Непроверенная внешняя fork-часть перенесена в #41/0.2.0; новый change имеет 0/4 выполненных verification tasks. Изменение знаменателя фиксирует перенос scope, а не выполнение внешнего fork-теста.

## Correctness

| Требование | Реализация и проверка |
| --- | --- |
| Automatic quality checks | Четыре `.github/workflows/*.yml`, `push`/`pull_request`, отдельные checks; восемь успешных реальных runs. Fork PR совместим по конфигурации, живой прогон не проверен. |
| Reproducible local commands | `Makefile:89`–`101`, единый `RUN`, locked dev-инструменты; `make docker-init lint-all check` проходит из свежего checkout без vendor/кешей. |
| Failures remain visible | Восемь отрицательных проверок: запрещённые лицензии, style/type/Rector нарушения в `bin/console` и `src/CiLintProbe.php`, отсутствие vendor binary. Все соответствующие Make-команды дали exit code 2; первый реальный CI также корректно отразил ошибку подготовки. |
| Checks do not rewrite source files | PHP-CS-Fixer `check` и Rector `--dry-run`; SHA-256 изменённых входных файлов одинаков до и после отрицательных проверок. Тестовые изменения удалены, оригиналы восстановлены. |
| CLI analysis scope | `.php-cs-fixer.php:8`–`12`, `phpstan.neon.dist:3`–`5`, `rector.php:16`–`17` явно покрывают `src/` и `bin/console`; положительный прогон работает при пустом `src/`, отрицательные пробы обнаруживают нарушения в обоих путях. |

Локально подтверждены: `make docker-build`, установка из `composer.lock` с dev-зависимостями, `make composer-validate`, четыре отдельных lint-цели, `make lint-all`, `make check`, игнорирование `var/cache/`, `git diff --check`, actionlint 1.7.12 и строгая валидация OpenSpec. Проверено сохранение bcmath, excimer, intl, pcntl, opcache, yaml и zip после сборки из закреплённых исходников. Версии production-зависимостей в lock не изменились.

## GitHub Actions evidence

| Workflow | Event | Result |
| --- | --- | --- |
| PhpCsFixer lint checks | pull_request | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749995246) |
| PHPStan lint checks | pull_request | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749995162) |
| Rector lint checks | pull_request | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749995180) |
| Allowed licenses checks | pull_request | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749995150) |
| PhpCsFixer lint checks | push | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749989240) |
| Rector lint checks | push | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749989116) |
| PHPStan lint checks | push | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749989027) |
| Allowed licenses checks | push | [success](https://github.com/mesilov/bitrix24-cli/actions/runs/37749989042) |

Все восемь runs выполнены на одном commit реализации `93e887203ba147deebbdfe89db15603394cfabc9`. Дополнительные коммиты отметок задач/отчёта не меняют исполняемые workflows и конфигурации инструментов; их CI проверяется отдельно перед передачей результата.

## Coherence

Дизайн соблюдён: отдельные workflows, общий Make/Compose механизм, `LOCAL_UID`/`LOCAL_GID`, сборка CLI-образа, минимальные разрешения `contents: read`, отсутствие registry login, пользовательских секретов и credentials Bitrix24. SDK-пути, bootstrap и подавления SDK-ошибок не перенесены. Существующие Make-команды сохранены; цели смогут использовать общий Compose-механизм будущей реализации #2.

Уточнение Dockerfile соответствует требованию сборки на runner: первые runs выявили недоступность PECL REST metadata, поэтому excimer 1.2.6 и yaml 2.3.0 из текущего development image закреплены по SHA официальных исходников. В локальной сборке и реальном CI все расширения сохранены; ошибки сборки не подавляются.

Milestone `0.1.0`, label `enhancement` и обе стороны issue ↔ change проверены после публикации. GitHub GraphQL возвращает `issueTypes: null` для репозитория; отдельный тип issue назначить невозможно.

## Перенесённая проверка

Решение пользователя от 12.10.2026: реальный внешний fork PR исключён из обязательной приёмки RC 0.1.0 и остаётся открытым в [#41](https://github.com/mesilov/bitrix24-cli/issues/41), `openspec/changes/verify-external-fork-ci/`. Его поддержка подтверждена только конфигурацией; owner approval/token policy требуется проверить фактически в 0.2.0. Предыдущее заключение о блокировке 0.1.0 этим case заменено решением о переносе.

Этот отчёт не объявляет fork acceptance выполненной. Архивирование исходного change в этом изменении не выполняется; оно требует проверки пересмотренной области. [Границы RC](../../../../docs/rc-0.1.0.md).
