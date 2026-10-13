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

## Risks / Trade-offs

- [Поверхность CLI меняется после #100] → пересверить список команд и документацию на финальном SHA.
- [Проверен только один runtime] → выполнить матрицу поддерживаемых платформ и показать оставшиеся ограничения.
- [Build/merge ошибочно принят за release] → подтвердить публикацию и latest-ссылки отдельно после разрешённого выпуска.
