# Design

## Context

Мотивация и область описаны в [proposal.md](proposal.md); контракты — в [binary-distribution](specs/binary-distribution/spec.md). Осмотренная база `dev`: `5d236a084f67b9de2ef99fa99ad3fc586582c20b`.

`bin/console` загружает `../vendor/autoload.php` и создаёт Symfony Application с версией `0.1.0`. `composer.json` допускает PHP 8.4/8.5, production lock требует `curl`, `intl`, `json`, `ctype`, `bcmath`, `filter`, `tokenizer`, `iconv`. Docker development использует PHP 8.4 Alpine; Make обращается к Compose через checkout-specific `scripts/compose.sh`. Имеющиеся specs регулируют Docker development и worktree state; binary packaging — новая capability.

Обвязка YiiPress проверена на commit `e61459fc788a74b91188d5d41a5fca59a02a229e` (11.10.2026): [Dockerfile](https://github.com/yiipress/engine/blob/e61459fc788a74b91188d5d41a5fca59a02a229e/docker/Dockerfile), [PHAR builder](https://github.com/yiipress/engine/blob/e61459fc788a74b91188d5d41a5fca59a02a229e/build/package-phar.php), [macOS](https://github.com/yiipress/engine/blob/e61459fc788a74b91188d5d41a5fca59a02a229e/build/package-macos.sh), [Windows](https://github.com/yiipress/engine/blob/e61459fc788a74b91188d5d41a5fca59a02a229e/build/package-windows.ps1), [packaging CI](https://github.com/yiipress/engine/blob/e61459fc788a74b91188d5d41a5fca59a02a229e/.github/workflows/package-static.yml), [release](https://github.com/yiipress/engine/blob/e61459fc788a74b91188d5d41a5fca59a02a229e/.github/workflows/release.yml).

## Goals / Non-Goals

**Goals:** Один application payload и общие проверки для трёх нативных runtime-сборок; закреплённые сборочные входы; отделение PR artifact verification от tag release publication.

**Non-Goals:** Побитовая reproducibility, дополнительные архитектуры, внедрение Yii3, Rust extensions из YiiPress, nightly releases, self-update, installers, контейнерный distribution image. Сжатие UPX/wrappe из образца не обязательно для первой рабочей сборки.

## Decisions

### 1. Production PHAR плюс PHP micro

Сборка использует отдельный staging directory, `composer install --no-dev` по lock и явный manifest файлов. PHAR включает текущую точку входа, `src`, production `vendor`, ресурсы принятого runtime и license notices. Relative autoload должен разрешаться внутри PHAR. Version metadata генерируется в staging; исходники checkout не переписываются.

`static-php-cli` компилирует PHP micro с нужными extensions; `micro:combine` объединяет runtime и PHAR. Это повторяет полезную часть YiiPress и оставляет Symfony/b24phpsdk application code самостоятельным. PHAR без runtime потребовал бы PHP у пользователя; переписывание CLI на другой язык не решает задачу упаковки существующего приложения.

PHAR может оставаться диагностическим build artifact; public release первой итерации содержит три бинарных архива и checksum manifest.

Compatibility runs закрепили PHP 8.5.11 и PHP micro `fb6d497b6f4cf138ee3851a30c905d64b7b19aed`. Windows требует точного SPC fake-CLI quote patch и преобразования wide argv по PHP CLI; patch hashes входят в metadata. PHAR проходит preflight на build PHP отдельно от standalone acceptance. На Windows micro hooks неверно читают entries даже несжатого PHAR: host PHP читает тот же payload успешно, а embedded runtime выводит бинарные bytes и не загружает Symfony Application. Bootstrap копирует gzip PHAR через raw `micro_open_self` со смещением `micro_get_sfxsize` в уникальный TEMP файл, использует обычный PHAR wrapper и удаляет archive при завершении. Linux/macOS выполняют PHAR внутри binary. TEMP является явной системной предпосылкой Windows, external PHP/vendor/checkout остаются не нужны.

### 2. Минимальный проверенный runtime

Compatibility runs выбрали PHP 8.5.11 из допустимого диапазона и закрепили его в build configuration. `static-php-cli` pin образца — `5b5861c366a0d94bc84002db7b3f46144b388fbb`; поддержка нашего `intl`/ICU и `bcmath` проверяется общими native fixtures на каждой платформе. Обновление pin допускается после конкретной compatibility проверки и фиксируется вместе с причиной.

Extensions выводятся из актуального production lock; дополнительно проверяются PHAR/compression, HTTPS/cURL/OpenSSL, CA trust и Unicode runtime paths. Нельзя переносить `--ignore-platform-reqs` из wrappers YiiPress как замену проверке итогового runtime. Fixtures проверяют и действительный HTTPS certificate, и отказ на недоверенном. CA configuration и minimum OS/library dependencies документируются по фактическим сборкам.

Native Markdown/highlighter/YAML/inotify из YiiPress исключаются. Компрессия runtime остаётся оптимизацией после рабочего acceptance: дополнительная упаковка может изменить signing, extraction и runtime behavior.

### 3. Платформенные builders и общие inputs

| Target | Builder | Final archive |
| --- | --- | --- |
| Linux amd64 | Docker/Buildx, PHP micro с musl toolchain по образцу | `b24cli-linux-amd64.tar.gz` |
| macOS arm64 | Нативный macOS runner, адаптированный shell wrapper | `b24cli-macos-arm64.tar.gz` |
| Windows amd64 | Нативный Windows runner/MSVC, PowerShell wrapper | `b24cli-windows-amd64.zip` |

Wrappers используют общую version policy и PHAR manifest, явно проверяют host OS/architecture и завершаются ошибкой при неподдержанном target. Системные SDK и OS libraries на macOS/Windows допустимы с документированными minimum requirements; обещание Linux static linkage проверяется отдельным inspection и чистым runtime test.

Make exposes `package-phar`, `package`, `package-macos`, `package-windows` или эквивалентные documented targets. Staging/cache/output находятся внутри выбранного checkout, например `runtime/package-*` и `dist/`; они исключены из Git. Имеющийся Docker development image и wrapper сохраняют текущие contracts. Mutable packaging state другого worktree не используется; immutable compiler cache допустим только при корректном ключе toolchain/target/lock.

### 4. Verification перед upload и publish

Packaging workflow следует принятому event routing: PR в `dev`/`main`, push в `dev`, `workflow_dispatch`. Actions закрепляются по SHA, permissions минимальны. Каждая platform job собирает artifact и запускает его из отдельного каталога без доступа к staging/checkout или внешнему PHP. На Linux проверка выполняется в чистом окружении; на macOS/Windows убираются build tools из PATH и проверяется отсутствие зависимости от их файлов.

Smoke: `--version`, `list --no-ansi`, `--help`; дополнительно fixture checks extensions, autoload/resources, UTF-8, usage/exit codes и HTTPS trust. После принятия команд задач в `dev` проверяются их существующие offline fixtures с packaged runtime. Проверка packaging не обращается к живому порталу. Отсутствие готовой task implementation не блокирует упаковку нынешнего CLI skeleton.

Release workflow запускается по documented semver tag convention, повторно использует builders, проверяет version и имеет publish job с `needs` всей платформенной матрицы. Release uploads выполняются только после проверок. Checksums считаются от final archives. PR workflow получает только read/artifact capabilities и не публикует Release. Обязательное создание настоящего release/tag не входит в действия постановки или локальной реализации; соответствующий прогон отражается в verification только по факту.

### 5. Идентичность и документация

Release `--version` содержит tag version; development build — full source SHA. Build invocation передаёт эту identity явно и не наследует случайные локальные values. Документация показывает checksum → unpack → PATH → version check, prerequisites builders и ограничения платформ. Attribution адаптированных файлов сохраняет BSD-3-Clause notices YiiPress; package включает license notices production dependencies.

## Risks / Trade-offs

- [ICU/cURL/PHAR compatibility в PHP micro] → первоначальная сборка каждого target и runtime fixture audit до оптимизаций; pins меняются только с записанной проверкой.
- [CA files или dynamic libraries остаются на build host] → clean runtime/HTTPS tests, inspection зависимости от checkout и явный documented trust/system contract.
- [Reflection/ресурсы SDK несовместимы с фильтрацией PHAR] → conservative production manifest, autoload/resource tests и проверка offline application commands.
- [Дорогая матрица компиляции] → cache по lock/PHP/toolchain/target; диагностика build failures; публикация не обходит неуспешные targets.
- [Версия жёстко задана в текущей точке входа] → generated staging metadata и exact version check до upload.
- [Task runtime ещё изменяется отдельными changes] → строить из актуальной `dev`, включать принятые production resources и tests, не копировать чужую feature branch.

## Migration Plan

Добавить packaging как новый distribution path, выполнить quality gates и platform acceptance, подготовить документацию. Source/Docker запуск продолжает работать. После принятия изменений release создаётся существующим релизным процессом с явным version tag; при дефекте упаковки можно исправить packaging в следующей версии, сохраняя source/Docker installation. В verification записывать отдельно выполненные local checks, CI runs и actual release publication.

## Review follow-up — 2026-10-11

PR #17 reviews identify two violations of the existing provenance/publication contracts: dirty working-tree payloads can report the clean HEAD SHA, and a publication retry cannot reuse a draft left by a previous interrupted attempt. Build entry points must verify clean Git inputs and HEAD equality before staging or passing the SHA into the Docker context. Ignored runtime/dist/vendor state remains allowed. Draft creation, asset upload and final publication must support resuming a previously created draft, retain failure gating and avoid changing an already public release.

Update the branch from main as requested and also the current dev target: dev has merged the task runtime since initial acceptance. Resolve the launcher/version/config/resource manifest integration and extend offline package acceptance to task command registration and controlled usage/preflight behavior. Existing task unit tests stay separate from packaged standalone evidence; no live portal or actual release operation is authorized by this review follow-up.

Production source policy follows the user's review clarification: CI resolves the concrete version tag to its commit and verifies reachability from origin/main before invoking platform builders. Historical main tags may be rebuilt; feature-only tags are rejected. Developer Make builds use the checked-out commit on any branch, without requiring a tag, and retain dev-full-SHA identity and clean source validation. PR/dev/manual artifacts are development previews; public production assets originate only from the gated tag workflow.
