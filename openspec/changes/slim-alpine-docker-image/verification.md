# Verification

## Base and dependency audit

Implementation base: `a27d135` (`origin/dev`), checked 2026-10-08. Issue #4 is merged; all four dev-tools are included. Issue #2 is still a separate PR, so Make/Compose source files are unchanged here. Local verification uses a separate Compose project and an image override to avoid replacing another checkout's shared tag.

The required extensions from the actual lock, including dev packages, are `bcmath`, `ctype`, `curl`, `filter`, `hash`, `iconv`, `intl`, `json`, `tokenizer`. All except `bcmath` and `intl` are present in the pulled Alpine base. README contains the component/use/removal table. `excimer`, `pcntl`, `yaml`, `zip` are no longer additionally installed; OPcache is supplied by the official base and is not built again. No application dependencies or PHP minor version were changed.

Base identities from build logs (linux/arm64):

- Previous: `php:8.4-cli-bookworm@sha256:836ac6c672d1372a47c8fd61b625015bd07760f493fb2eaab2087636498a2b4b`.
- Replacement: `php:8.4-cli-alpine3.23@sha256:da7a7a945a493ac9c015f327231afd820bbe57fc6ae22f4f520a79fbd635ba39`.
- Composer in both builds: `composer:2.8@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c`.

Baseline build succeeded using the Dockerfile from `a27d135`. Its extension installer is an amd64 script-only stage and emitted a platform warning on arm64; the final image is arm64. That stage is removed from the replacement.

```sh
docker build --platform linux/arm64 -t bitrix24-cli-issue-6:baseline docker/php-cli
# After applying the Dockerfile change:
docker build --platform linux/arm64 -t bitrix24-cli-issue-6:alpine docker/php-cli
docker image inspect bitrix24-cli-issue-6:baseline bitrix24-cli-issue-6:alpine \
  --format '{{json .RepoTags}} {{.Architecture}} {{.Size}}'
docker run --rm bitrix24-cli-issue-6:alpine php -m
docker run --rm bitrix24-cli-issue-6:alpine apk info
```

Metric: Docker Engine `image inspect .Size` in bytes, not the Docker Desktop total storage display. Both images are built with the same Docker Engine for `linux/arm64`.

| Image | Bytes |
| --- | ---: |
| Debian baseline | 183536138 |
| Alpine replacement | 46596357 |

Reduction: 136939781 bytes (74.61%). Both contain PHP 8.4.26. Final image identity before publication: `sha256:452af242e017ae063b36f0c87daa48f2796f6fb38a5c981fc9d8dab3098a89ba` (local image manifest).

## Local scenario checks

All checks below succeeded on `linux/arm64` against the implementation Dockerfile with clean locked dependency installation (70 packages including the four dev-tools):

- `make docker-init`, `make check`, `make cli`, `make cli ARGS=--version`.
- `composer validate --strict`, PHP syntax check, `composer check-platform-reqs` without ignored requirements; CLI returns `Bitrix24 CLI 0.1.0`.
- `make lint-all`: license checker, PHP-CS-Fixer (zero fixes), PHPStan (no errors), Rector (dry run succeeds).
- Default image identity: UID/GID `10001:10001`; writes to `/var/www/html` and `/tmp/composer/cache` succeed.
- Compose host override: UID/GID `501:20`; cache writes and a bind-mounted file succeed; file ownership is `501:20`.
- `php -m`: all required extensions present; `excimer`, `pcntl`, `yaml`, `zip` absent. Inherited OPcache remains; no extra build is performed.
- `apk info`: runtime ICU and unzip present; `.build-deps`, `gcc`, `g++`, `icu-dev`, external extension installer and extracted PHP sources absent. No cached `.apk` files remain (the base's empty cache directory may exist).

Local logs are ephemeral under `/private/tmp/bitrix24-cli-issue-6-*.log`; the results and reproducible comparison commands are retained here. GitHub Actions evidence is recorded below once checks complete.
