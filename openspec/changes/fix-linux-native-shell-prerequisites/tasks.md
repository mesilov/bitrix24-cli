## 1. Linux CI prerequisites
- [x] 1.1 Добавить в Linux job `package-platforms.yml` шаг установки Bash и zsh с проверкой `command -v` перед приёмочными тестами; mandatory shell checks не менять.
- [x] 1.2 Добавить запись в CHANGELOG со ссылкой на #177.

## 2. Verification
- [x] 2.1 Проверить синтаксис workflow (YAML parse, actionlint при наличии) и strict OpenSpec validate.
- [ ] 2.2 Проверить согласованность change, issue и CHANGELOG; выполнить OpenSpec verify перед MR в dev.
- [ ] 2.3 После доставки в `builds` повторить трёхплатформенную intermediate-сборку и скачать артефакты успешного run перед standalone EMP-09.
