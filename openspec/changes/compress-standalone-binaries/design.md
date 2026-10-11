# Design

## Context

См. proposal.md — Why. Общий build/package.py используется Docker Linux, native macOS/Windows и обоими CI routes. Runtime cache зависит от build/runtime.py и build/config.json. micro:combine поддерживает `--with-micro`, поэтому упаковка не требует новой компиляции C. Текущий macOS builder исправляет bounds встроенного PHAR и подписывает thin arm64 executable в build/macos.py; эта проверка должна выполняться до внешней упаковки.

## Goals / Non-Goals

Goals: меньшие executable всех поддерживаемых targets, native сохранение CLI-контрактов, идентичный production payload и измеренный результат. Non-goals: pruning Composer/ICU, изменение CLI/API, коммерческая подпись/notarization, публикация или merge релиза.

## Decisions

1. Отдельные build/compress.py и build/compression.json. UPX 5.2.1 Linux/Windows получают из официальных release archives с закреплёнными SHA256. Копия micro.sfx сжимается `--best`, проходит `--test`; Linux затем удаляет UPX trailer за последним PT_LOAD по контракту SPC processUpxedMicroSfx, после чего micro подаётся в micro:combine; compiler cache и исходный runtime не меняются. Сжатие уже combined PHAR было отвергнуто: изменяет семантику overlay/PHAR offset.
2. macOS использует wrappe 1.0.4, установленный из verified source crate через `cargo install --locked --path` в checkout-local prefix (Rust 1.90.0). Исходные b24cli/probe предварительно проходят существующий finalize/verify, затем `wrappe --compression 16 --show-information none` для сохранения чистого CLI/JSON stdout, расширение __LINKEDIT outer runner до конца packed data, удаление прежней LC_CODE_SIGNATURE с сохранением offsets, ad-hoc codesign и strict verify. Unix runner при default cleanup=false использует exec: PID, SIGINT и exit codes сохраняются. `--cleanup` не включаем, поскольку этот режим создаёт отдельний child. Writable temp и кэш распаковки документируются. Cargo --locked фиксирует packer dependencies; upstream runner собирается вложенным Cargo без lock. Проверенный source crate получает build.rs patch, сохраняющий CARGO_HOME у вложенного runner; обе сборки используют checkout-local registry. Patch SHA256 включён в provenance, notices сохраняются рядом с cached tool.
3. В dist/uncompressed остаются diagnostic b24cli/probe с исходными версиями. Финальные файлы находятся на прежних путях. build-info включает packer identity до PHAR generation; отдельный compression-info.json содержит размеры и SHA256 исходных/финальных файлов. Report и notices включаются в public archive; uncompressed/probe/PHAR исключаются существующим allowlist.
4. Native acceptance выполняется и для исходных diagnostic executable, и для final output. Для macOS payload bounds проверяются у uncompressed файла, outer signature — у packed wrapper. Проверка меньшего размера и exact final hashes обязательна перед upload. Publication gates и имена assets не меняются.

## Risks / Trade-offs

- UPX overlay semantics → compress only micro, сохранить exact appended PHAR, выполнить native smoke включая runtime-probe.
- macOS outer wrapper / extracted inner signatures → проверять оба уровня и не редактировать finalized inner bytes.
- Temp cache может занимать место и первый старт требует распаковки → документировать, test cold/warm invocation и сохранить direct SIGINT acceptance.
- Упаковщик отсутствует, подменён или завершился с ошибкой → fail closed; version/SHA256 pinned; final output не принимается без reduction/hash report.
- Упаковка требует дополнительных notices → поставлять UPX license/exception и wrappe BSD license, указать происхождение Rust runner dependencies.

## Migration Plan

Обычные Make/CI команды автоматически создают compressed outputs; пользовательские имена и PATH остаются прежними. Rollback — revert packaging change без изменения source/Composer/runtime API. Проверить все native PR jobs, архивировать OpenSpec после verify и отправить PR в dev.
