## 1. Dependencies
- [x] 1.1 Обновить symfony/filesystem, http-client, http-foundation, mime до исправленных версий 8.1.x целевым `composer update --with-dependencies`; `composer.json` не менять.
- [x] 1.2 Добавить запись в CHANGELOG со ссылкой на #196.

## 2. Verification
- [x] 2.1 `composer audit` без advisories; `make check`, `make lint-all`, `make unit` проходят.
- [x] 2.2 Strict OpenSpec validate; проверить согласованность change ↔ issue ↔ CHANGELOG перед MR в dev.
