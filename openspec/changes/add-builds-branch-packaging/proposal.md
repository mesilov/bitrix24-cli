# Proposal

## Why

Для промежуточной приёмки 0.2.0 нужна постоянная ветка `builds`: мейнтейнер переносит в неё выбранный снимок `dev` и получает три проверенных standalone-пакета без тега и публикации. Это явное изменение прежней политики #77, запрещавшей автоматическую упаковку на любых branch pushes.

## What Changes

- Создать удалённую ветку `builds` для выбранных промежуточных снимков `dev`.
- Запускать существующую packaging matrix по push только в `builds`; сохранять ручной запуск для любого выбранного ref.
- Сохранять development identity `dev-<full source SHA>`, проверки трёх платформ и общий архив с SHA256SUMS; не создавать тег или GitHub Release.
- По дополнительному запросу пользователя закрепить два ручных режима в скилле мейнтейнера: промежуточная проверка и RC перед релизом. Теги формата `0.2.0-rc-1`, `0.2.0-rc-2` указывают на выбранный builds snapshot, запускают отдельную сборку с точной RC identity и не публикуют Release.
- Ограничить хранение диагностических platform artifacts сборок `builds` семью днями, итоговых архивов — 30 днями; не менять retention остальных сборок.
- Документировать перенос снимков, скачивание по run ID, контроль версии/checksum и пределы GitHub Actions.

## Capabilities

### New Capabilities

Нет.

### Modified Capabilities

- `binary-distribution`: CI artifact verification допускает упаковку при push в `builds` и numbered RC tags, закрепляет их identity/retention и сохраняет ручной и отдельный production-tag маршруты.

## Impact

`.github/workflows/package-static.yml`, `.github/workflows/package-platforms.yml`, `.github/workflows/release.yml` (исключение RC), `build/source.py` и его тесты, `build/README.md`, скилл мейнтейнера, CHANGELOG и OpenSpec. Application/REST контракты не меняются; Bitrix24 MCP-проверки не применимы к этому CI change. Финальная 0.2.0 по решению пользователя ожидает завершения #121; промежуточные сборки не считаются её релизной приёмкой. Bootstrap ветки допускает текущий `dev` плюс этот CI change; дальнейшие снимки переносятся из `dev` без force push.

## GitHub issues

- https://github.com/mesilov/bitrix24-cli/issues/109 — инфраструктура промежуточной сборки и проверки релиза 0.2.0.
