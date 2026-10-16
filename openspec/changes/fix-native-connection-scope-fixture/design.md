## Context

GitHub native jobs 117124143552 (macOS) и 117124143498 (Windows), run 38945682487, падают в connection-smoke.py:169. Probe fixture отдаёт task-list payload на scope и отклоняет второй request. Проверка Python всё ещё ожидает единственный apiCall. Реальный product scope guard корректно требует свежий список grants.

## Goals / Non-Goals

Восстановить контролируемую native acceptance для актуального scope guard. Не менять runtime продукта, grants или fail-open/fail-closed политику; не пропускать проверки и не обращаться к реальному порталу из fixture.

## Decisions

- Fixture допускает только POST к точному ожидаемому HTTPS host и synthetic credential path. Разрешены лишь scope REST1 и tasks.task.list REST3.
- Последовательность ограничена scope → tasks.task.list для cold state либо единственным tasks.task.list для warm state. Повтор/неизвестный метод отклоняется.
- Scope возвращает JSON list `["task"]`; task-list сохраняет существующую карточку 123.
- Python acceptance проверяет точные version/effect/method и scopeValidation.status=fresh, а также отсутствие missing grants. Cold/warm проверяется для profile, explicit/env-file и process-env.
- Локально проверяется полный connection-smoke через собранные PHAR, затем CI. После доставки фикса в dev продолжается исходная операционная задача: intermediate builds и live EMP-09 на standalone.

## Evidence / API

Официальный MCP bitrix24_dev отвечает; `scope` возвращает string[] в result: https://apidocs.bitrix24.ru/api-reference/common/system/scope.html . Product unit contracts ScopeValidationCommandTest показывают cold refresh и warm reuse; EndpointUrlFormatter SDK формирует /rest/... для REST1 и /rest/api/... для REST3. Ни один API-контракт не изменяется этим fixture-only change.

## Risks

Излишне свободный mock мог бы скрыть неправильный routing. Поэтому сохраняются точные host/path/verb и bounded sequence; Python требует fresh status и ordered apiCalls. Unit/PHAR/native/portal evidence учитываются отдельно.
