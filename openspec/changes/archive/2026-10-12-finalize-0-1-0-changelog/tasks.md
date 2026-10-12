# Tasks

## 1. Финальное оформление

- [x] 1.1 Оформить CHANGELOG.md 0.1.0 без RC/dev-кандидата, сохранив feature bullets/дату/ограничения; проверить минимальный diff и отсутствие RC/dev introduction.
- [x] 1.2 Исправить ссылки README.md и docs/rc-0.1.0.md на новый anchor; проверить текущие Markdown targets/anchors и неизменность исторических acceptance records.

## 2. Совместные проверки

- [x] 2.1 Подтвердить docs-only diff, неизменность production inputs, отсутствие conflict markers, git diff --check и strict OpenSpec; сохранить verification.

## Workflow follow-up

- Verify/archive change, обновить archived path в issue #52.
- Доставить task PR в dev, затем открыть dev→main MR с этим документационным diff для подготовки тега.
- Тег и GitHub Release не создавать этой документационной правкой.
