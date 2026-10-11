# Tasks

## 1. Production payload и build identity

- [x] 1.1 Добавить staging и PHAR builder с явным manifest актуальной точки входа, исходников, production vendor/resources и license notices; проверить сборку по `composer.lock --no-dev` и inspection обязательных файлов, исключённых dev tools, `.env`, credentials и cache.
- [x] 1.2 Добавить generated staging version metadata для release tag и development full SHA; проверить `--version` PHAR для обеих identity и failure при неверной build identity без изменения исходников checkout.
- [x] 1.3 Добавить `package-phar` и checkout-local output/cache paths; документировать запуск и проверить autoload, resources, `list`, `--help` из отдельного каталога, а также отсутствие изменений в соседнем worktree.

## 2. Linux runtime и acceptance fixtures

- [x] 2.1 Адаптировать Docker/Buildx builder PHP micro и PHAR combine из YiiPress, закрепить PHP patch-version и `static-php-cli` commit; проверить Linux amd64 build, linkage и наличие требуемых production extensions без host modules.
- [x] 2.2 Добавить общий packaged-runtime smoke и fixture harness для version/help/list, UTF-8, usage errors/exit codes, HTTPS success и rejected certificate; проверить Linux binary в чистом runtime без PHP, Composer, Docker tooling и checkout/vendor access.
- [x] 2.3 Добавить documented `package` target, Linux prerequisites/CA policy/minimum requirements и атрибуцию адаптированных файлов; повторить документированную сборку из clean checkout и проверить состав output и корректность license notices.

## 3. macOS и Windows builders

- [x] 3.1 Адаптировать macOS shell wrapper для arm64 с общим PHAR manifest/version policy, отдельным staging/cache и pinned inputs; проверить нативную сборку и общий smoke/HTTPS harness вне checkout без внешнего PHP, документировать minimum macOS/system dependencies.
- [x] 3.2 Адаптировать Windows PowerShell/MSVC wrapper для amd64 с общими inputs и identity; проверить `b24cli.exe`, общий smoke/fixtures, отказ на unsupported target и отсутствие зависимости от build tools, документировать minimum Windows/system dependencies.
- [x] 3.3 Добавить Make/scripts entry points и per-platform unpack/checksum/PATH инструкции; проверить команды на соответствующих платформах и показать version check установленного binary.

## 4. Packaging CI

- [x] 4.1 Добавить GitHub Actions jobs Linux/macOS/Windows на PR в dev/main, push в dev и manual dispatch с pinned actions, минимальными permissions и cache keys по target/lock/toolchain; проверить event routing, изоляцию writable state и отсутствие release publication на PR.
- [x] 4.2 Запустить общий acceptance harness перед upload каждого target, сохранять только прошедшие artifacts; проверить реальные CI logs/artifacts и failure jobs на version mismatch, missing binary и failed smoke без публикации Release.
- [x] 4.3 Документировать имена CI artifacts, получение/проверку сборки и диагностику failures; сверить инструкцию с фактическим packaging CI run и записать source/toolchain identities.

## 5. Release assets

- [x] 5.1 Добавить version-tag workflow с общими builders, exact version checks и publish job, зависимым от всей platform matrix; проверить trigger/tag convention и блокировку publish при build/smoke failure.
- [x] 5.2 Добавить финальные tar.gz/zip archives с именами из spec и `SHA256SUMS`; проверить unpack, executable permissions, checksum для всех архивов и отказ checksum verification после повреждения fixture archive.
- [x] 5.3 Документировать tag/release workflow и проверку checksum перед установкой; проверить подготовку полного release asset set, отделяя dry-run/local validation от actual release publication в verification.

## 6. Интеграция и завершение реализации

- [x] 6.1 Выполнить existing quality gates и packaging regression checks; подтвердить, что source/Docker запуск и worktree isolation проходят, а принятые к тому времени команды задач проходят соответствующие offline packaged-runtime fixtures.
- [x] 6.2 Проверить implementation against `binary-distribution`, выполнить `openspec validate add-standalone-binary-build --strict`, двустороннюю issue trace и подготовить verification с отдельными фактами local/platform/CI/release/portal checks и явными unperformed boundaries.

## 7. Review follow-up — PR #17

- [x] 7.1 Влить актуальные main и dev в feature branch, разрешить конфликты точки входа/Makefile/README, сохранить новые task commands и packaged BuildVersion; проверить ancestry и startup/config registration.
- [x] 7.2 Отклонять dirty Git inputs и явный SHA, не совпадающий с HEAD, до staging/Docker build; проверить clean/dirty/staged/untracked/ignored fixtures и metadata identity на всех builders.
- [x] 7.3 Сделать публикацию возобновляемой: создать или переиспользовать draft, загрузить полный verified asset set и публиковать после успеха; проверить retries после create/upload/edit failure без настоящего Release.
- [x] 7.4 Ограничить production CI version-tag commit-ами в истории main, сохранив developer Make builds на любом clean checkout commit; проверить main/feature tags и tagless developer builds, обновить документацию.
- [x] 7.5 Проверить offline task commands в production PHAR/native package без portal credentials, Unix pcntl/SIGINT с сохранением подтверждённого шага, unit/quality/worktree gates и полную native CI matrix; обновить verification, issue trace и подготовить change к архивированию.

## Workflow follow-up

- После реализации, проверки соответствия и выполнения repository review workflow архивировать change через `openspec-archive-change`.
- Обновить OpenSpec путь в issue #14 на фактический archive path и проверить обе стороны связи.
