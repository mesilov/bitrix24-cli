## Why

Intermediate run 38945682487 из builds a0e2ee4 завершил macOS и Windows connection acceptance с ошибкой: fixture допускает один запрос, тогда как свежий scope guard добавляет scope перед tasks.task.list. Это блокирует получение проверенных standalone-архивов и повтор EMP-09.

## What Changes

- Обслуживать scope и tasks.task.list разными readonly ответами fixture, сохраняя точные host/credential проверки и ограниченный порядок.
- Проверять fresh scope и точные cold/warm последовательности apiCalls в native connection acceptance.
- Сохранить проверки credential precedence, private storage, native shells и отсутствие реального HTTP.

## Capabilities

### New Capabilities
Нет.

### Modified Capabilities
Нет: исправляются только packaging fixtures. Продуктовые scope/connection требования остаются прежними (`skip_specs: true`).

## Impact
build/tests/runtime-probe.php, build/tests/connection-smoke.py, CHANGELOG. Runtime CLI, зависимости и API contracts не изменяются.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/174 — milestone 0.3.0; блокер standalone-приёмки EMP-09.
