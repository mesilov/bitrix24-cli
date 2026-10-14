# Design

## Context

См. proposal.md. Репозиторий содержит package-static workflow, `build/package-*`, acceptance harness `build/tests/smoke.py`, README с latest-ссылками и существующую матрицу Linux amd64/macOS arm64/Windows amd64. В #100 выполняется проверка исходников help/about и карты CLI.

## Goals / Non-Goals

**Goals:** Повторная проверка именно финальной 0.2.0 и сохранение воспроизводимых receipts.

**Non-Goals:** Автоматическая публикация по факту создания issue; расширение платформенной или portal приёмки без доказательств.

## Decisions

1. Зафиксировать согласованный release scope и source SHA до сборки. Сверить build metadata, tag identity, `--version` и `--about`.
2. Повторить `--help`, `-h`, обе формы help каждой публичной команды, list и примеры из #100. Сверить docs/README/JTBD и issue скилла с финальным каталогом; исправления доставляются через MR в dev.
3. Сохранить отдельные результаты source checks, native platform acceptance, terminal CI, merge/tag/Release/assets. Непроверенные этапы не считаются успешными.
4. RC1 по запросу пользователя: merge #132 подтверждён в dev `5330662`; исходники приложения/builders совпадают с уже принятым builds snapshot `9c268e5bbb6b3c3cea85cfbb3726cbd8b5efbb99`, отличаются только OpenSpec receipts. Тег `0.2.0-rc-1` зафиксирован на этом зелёном SHA, входит в dev/builds history. Отдельный промежуточный docs-only run 38537161240 отменён ради RC; существующий успешный run 38534772781 сохранён. RC run 38537920077 использует development route, publication отсутствует.
5. Добавить acceptance helper для готового archive set: checksum всех трёх archives, version/source/composer metadata, safe extraction, copied executable вне checkout, clean PATH/credentials, 56-command catalog, обе формы root/list/command help, about/version, JSON offline dry-run и usage/connection errors. Запустить helper на Linux amd64/macOS arm64/Windows amd64 runners с download-artifact конкретного RC run ID, без пересборки.
6. Для одноразового запуска acceptance из feature-ветки использовать branch-only wrapper существующего package-static workflow (workflow_dispatch уже зарегистрирован в default main). Wrapper содержит только download/verify jobs и read-only contents/actions; после dispatch восстановить оригинальный package-static перед PR в dev. Helper/receipts сохраняются в PR; RC source/tag не меняется.
7. Scope заморожен RC SHA: #121 включён, #134/#136 позже закрыты и merged в dev, но не входят в этот RC; синхронизация скилла #6 остаётся открыта. Stable-release задачи остаются открыты до отдельной приёмки/разрешённого выпуска.

## Risks / Trade-offs

- [Поверхность CLI меняется после #100] → пересверить список команд и документацию на финальном SHA.
- [Проверен только один runtime] → выполнить матрицу поддерживаемых платформ и показать оставшиеся ограничения.
- [Build/merge ошибочно принят за release] → подтвердить публикацию и latest-ссылки отдельно после разрешённого выпуска.
