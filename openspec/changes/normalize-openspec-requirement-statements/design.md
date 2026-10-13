# Design

## Context

См. proposal.md. Из dev f21e063 прочитаны четыре canonical specs полностью; validator подтвердил пять statements >500 characters. Объём пользовательского поручения явно разрешает разделение существующих длинных требований. Это структурная нормализация нормативного текста; source-code paths/CommandCatalog/ApiPolicyGuard/config schema служат проверяемым контекстом.

## Goals / Non-Goals

Goals: каждый изменённый/выделенный statement ≤500 characters; сохранить все прежние нормы, имена и scenarios; добиться strict 16/16.
Non-goals: новые API admissions/поля/CLI флаги, JTBD-сценарии, код или зависимости, изменение historical archives.

## Decisions

1. Сохранять MODIFIED headers и все прежние scenario blocks дословно. Каждую вынесенную норму размещать в именованном ADDED requirement с собственным scenario. Это сохраняет ссылки на старые требования и даёт проверяемый contract mapping.
2. Main specs редактируются в apply тем же набором блоков, что delta. Purpose и незатронутые requirements сохраняются; последующее archive будет идемпотентным sync уже применённых норм.
3. Сокращение только слов без выделения разных норм не выбрано: условие 500 characters не должно терять ограничения или прятать несколько поведений в плотной строке.
4. Проверка: baseline/current strict output, byte comparison прежних scenarios/Purpose/нетронутых blocks, mapping каждого исходного обязательства, concordance main ↔ delta и diff scope. Для документационного изменения local PHP suite повторно не требуется; native repository CI проверяет итоговый commit.

## Risks / Trade-offs

- Потеря скрытого ограничения в длинном statement → before/after mapping и дословные scenario blocks.
- Расширение REST1 scope при переносе → сохранить версии, TAGS-only, explicit local upload и отсутствие общего API/fallback.
- Изменение catalogue metrics → сохранить 34=30+4, четыре users, 59=30+0+29 и counts 4/8/2.
- Drift при параллельной dev работе → fresh fetch/merge перед MR и повтор strict validation.

## Migration Plan

Создать deltas → применить их к canonical specs → проверить сохранность/strict → CHANGELOG/evidence → MR в dev. Change остаётся active до подтверждённого merge; historical receipts не редактируются.

## Primary documentation boundary

Официальный MCP: search → details для [tasks.task.get REST3](https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html) и [disk.folder.uploadFile](https://apidocs.bitrix24.ru/api-reference/disk/folder/disk-folder-upload-file.html) ответили успешно. Это актуальная проверка названий/документации; допуски task-v3/strict-rest3 сверены с исходниками проекта, реальный портал не вызывался. Никакие новые field types/defaults не вводятся.

