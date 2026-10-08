# Design

## Context

См. proposal.md — Why. bin/console пока создаёт только Symfony Application, task commands отсутствуют. Исследование #10 содержит предложения 79 команд; они не были приняты как MVP. Пользователь согласовал 16 JTBD и добавил переписку в чате задачи для всех участников.

## Goals / Non-Goals

Фиксация пользовательского поведения в task-workflows и перенос его в актуальную спецификацию. Этот этап не пишет код и не выбирает auth, модель клиентских обязательств, уведомления или окончательный набор команд.

## Decisions

- Стабильные ID PM-01..06, AM-01..06, EMP-01..07 связывают ситуацию, желаемое действие, рабочую цель и признак успеха. Альтернатива — свести JTBD к перечню методов — потеряла бы цели пользователей.
- Переписка объединена одним требованием с тремя ролевыми сценариями. Все остальные участники тоже включены, если у подключения есть права. Внешний клиент не получает доступ из названия роли аккаунт менеджера.
- История и отправка — обязательная часть переписки. Чат не заменяет result или решение о приёмке; legacy comment tool не означает полное покрытие чата.
- Главная спецификация фиксирует принятые продуктовые цели; implementation tasks остаются открытыми. Архивирование этого change до реализации и проверки будущего поведения не выполняется.
- Каталог MCP сохраняется с commit SHA и проверяется по исходникам, а не только README. Он характеризует templates-mcp, не hosted mcp.bitrix24.tech/com. Live tools/list не проверяется.
- Для текущего CLI-предложения область — новая карточка. Card/result/chat.send используют REST3; history сообщений — официальный IM companion. task.commentitem.* и result-from-comment не входят в новый каталог. Strict-rest3 оценивается отдельно: сам факт новой карточки не переводит все методы в v3.
- Native list v3 filter только id; tasks my/plan используют полную выборку доступных задач и явные локальные predicates. Scan cap/недостающий доступ/сбой отражаются в partial, не маскируются под полную сводку проекта. Альтернатива legacy list не выбрана как скрытый fallback.
- Семь lifecycle commands кандидаты через update.status, но соответствие специальным действиям при needsControl/requireResult не доказано; до acceptance они disabled. Наличие поля status и rights.approve не доказывает эквивалентность операции.
- Для blocker/change/client promise/delivery/feedback нужны явные данные. В cli-candidates.md названы необходимые поля, альтернативы provider и границы полноты; схема хранения не принята. Альтернатива выводить согласованные факты из любого текста чата отвергнута как недоказуемая.
- Каталог и coverage являются дополнительными planning artifacts, не изменяют принятые JTBD. Предыдущее предложение 79 имён остаётся историческим исследованием; проектирование продолжается по cli-candidates.md/json (55 имён, 42+13), со статической матрицей 19 сценариев.

## Risks / Trade-offs

- [Название роли ошибочно примут за права доступа] → права определяет текущая авторизация, не JTBD.
- [Согласованный JTBD примут за реализованную команду] → purpose, tasks и verification явно разделяют продуктовый контракт и runtime.
- [Сводки рисков/обязательств требуют неоднозначных данных] → при проектировании реализации определить источники и полноту; не угадывать blocker status или CRM-связь по одному полю задачи.
- [Интерфейс MCP comment расходится с task chat] → фиксировать фактический legacy вызов и отсутствие чтения истории в проверенном tool set.

## Migration Plan

Публикуется спецификация и planning artifacts в существующем PR #11 для issue #10. Runtime/dependencies не меняются. При последующей реализации нужно проверить каждый JTBD на тестовом портале с правами соответствующего участника. Откат этого документационного commit убирает новую спецификацию, не меняя поведение приложения.

## CLI Guidelines

Референс пользователя: [cli-guidelines/cli-guidelines](https://github.com/cli-guidelines/cli-guidelines), сайт [clig.dev](https://clig.dev/), источник content/_index.md на commit 697d6a29fc8c93d3981a755c0c7683507ad39c3e, проверен 2026-10-09. Attribution и лицензия приведены в cli-candidates.md. Гайд направляет UX, но не определяет Bitrix24 API, права или полноту JTBD.

| Раздел первичного источника | Решение проекта | Проверяемый контракт |
| --- | --- | --- |
| [Human-first design](https://clig.dev/#human-first-design), [Subcommands](https://clig.dev/#subcommands) | Установленная команда bitrix24; tasks create/show/list/update, вложенные chat/result/time группы; один positional ID и именованные связанные сущности | Direct and consistent command structure |
| [Help](https://clig.dev/#help), [Documentation](https://clig.dev/#documentation) | Help/version offline, примеры в начале, одинаковая справка command --help и help command, documented defaults/limitations | Discoverable help without remote operations |
| [Arguments and flags](https://clig.dev/#arguments-and-flags) | Обычные title/responsible/description флаги; advanced fields отдельно, explicit PATH/- для files, полные названия; no arbitrary abbreviations | Convenient and explicit task field updates; Explicit file and structured input |
| [Output](https://clig.dev/#output), [The Basics](https://clig.dev/#the-basics) | Human default, явные --json/--plain, stdout data / stderr messages, минимальное сообщение об успешном изменении | Human output and composable structured output |
| [Errors](https://clig.dev/#errors), [Robustness](https://clig.dev/#robustness-guidelines) | Внятные ошибки, finite timeout, validation до write, отдельные partial/outcomeUnknown, bounded scans | Explicit filtering and completeness; Actionable errors and stable exit status; Bounded requests and honest recovery |
| [Interactivity](https://clig.dev/#interactivity), [Arguments and flags](https://clig.dev/#arguments-and-flags) | --no-input не ждёт диалога; --force только подтверждение delete, --dry-run без write; обычный update без лишнего подтверждения | Controlled remote deletion and dry run; Predictable noninteractive and terminal behavior |
| [Configuration](https://clig.dev/#configuration), [Signals](https://clig.dev/#signals) | Нечувствительные flags/config/env/defaults, безопасные credentials, одинаковое место общих options, Ctrl-C с описанием результата | Consistent global options and protected credentials; Bounded requests and honest recovery |
| [Future-proofing](https://clig.dev/#future-proofing) | Явный subcommand без catch-all/autocorrect; versioned JSON, стабильные error codes; previous_proposal_name только история | Direct and consistent command structure; Human output and composable structured output |

Новая capability cli-experience дополняет task-workflows общими наблюдаемыми правилами. Основную spec синхронизируем по прямому запросу пользователя; implementation tests и packaging ещё не существуют. Shell examples представлены как будущие вызовы установленного CLI.

В каталоге сохраняются 55 операций, 42 кандидата и 13 extensions: смена command path не добавляет API методов или реализации. --fields-file заменяет прежнюю @JSON нотацию; два источника одного поля отклоняются. В update raw JSON является отдельным режимом; create разрешает дополнительные fields без дубликатов и проверяет обязательные поля итогового payload. Схема прочих параметров --where/--params и допустимые operators должны быть определены отдельным implementation change, неподдержанный произвольный payload не обещается.

Отличия, выбранные проектом: один positional task ID ради краткости; creator/responsible остаются явными; limit50/max-scan10000/timeout30 и exit codes 0/1/2/3/4/130 являются нашими defaults, не требованиями CLIG. Plain TSV columns определяются отдельно по командам до реализации. Pager, interactive missing-field wizard, man pages, completion, distribution и analytics не включены в этот этап; auth/storage также отдельное решение. --no-input — публичное имя; Symfony --no-interaction может стать документированным alias. Short -n не назначается dry-run из-за существующей Symfony convention. CLI Guidelines не требуют отказаться от Symfony: будущая реализация должна обеспечить внешний space-separated интерфейс и full help через выбранный parser.

Предыдущие colon names, --yes и exit-code предложение остаются в неизменённом архиве исследования. В текущем каталоге они заменены до выпуска команд; compatibility aliases не нужны для несуществующего runtime. Даже --fields status и --force не обходят lifecycle/data/API gates. Новые UX сценарии не закрывают 3 открытые задачи по provider, lifecycle и API exceptions и не повышают documented JTBD score.
