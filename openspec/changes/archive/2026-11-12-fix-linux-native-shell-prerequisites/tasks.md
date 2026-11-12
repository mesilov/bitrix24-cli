## 1. Linux CI prerequisites
- [x] 1.1 Добавить в Linux job `package-platforms.yml` шаг установки Bash и zsh с проверкой `command -v` перед приёмочными тестами; mandatory shell checks не менять.
- [x] 1.2 Добавить запись в CHANGELOG со ссылкой на #177.

## 2. Verification
- [x] 2.1 Проверить синтаксис workflow (YAML parse, actionlint при наличии) и strict OpenSpec validate.
- [x] 2.2 Проверить согласованность change, issue и CHANGELOG; выполнить OpenSpec verify перед MR в dev.
- [x] 2.3 После доставки в `builds` повторить трёхплатформенную intermediate-сборку и скачать артефакты успешного run перед standalone EMP-09.

## Evidence

- Исправление доставлено в `dev` через PR #188 (d09f7fd) и перенесено в `builds` 201e643c35e7f3733fc5afed3a0d82e53ccdb811.
- Intermediate run 43092552553 (https://github.com/mesilov/bitrix24-cli/actions/runs/43092552553): `Validate development or RC source`, macOS arm64, Linux amd64, Windows amd64 и `Verify release archive set` — success.
- Linux job: шаг `Prepare Bash and zsh acceptance` нашёл bash и zsh 5.9; `shell-installer-check.py --dist dist/linux-amd64` — `test_documented_shell_sequences ... ok`, 12 tests OK.
- Артефакт `b24cli-release-assets` скачан вне checkout; `shasum -a 256 -c SHA256SUMS` — OK для трёх архивов. macOS arm64 бинарник: `--version`/`--about` — `dev-201e643c35e7f3733fc5afed3a0d82e53ccdb811`, общий help выводится.
- Граница: standalone live-приёмка EMP-09 на портале — отдельная ручная проверка, этим change не выполнялась.
