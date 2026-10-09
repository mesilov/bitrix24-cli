# Adapted from bitrix24/b24phpsdk v3, commit 8ebd4c154d5557db949b0196825f347a3a6c5bf0.
# Copyright (c) Maksim Mesilov. Distributed under the MIT license; see LICENSE.

.DEFAULT_GOAL := help

export COMPOSE_HTTP_TIMEOUT=120
export DOCKER_CLIENT_TIMEOUT=120
export LOCAL_UID ?= $(shell id -u)
export LOCAL_GID ?= $(shell id -g)

COMPOSE := sh ./scripts/compose.sh
RUN := $(COMPOSE) run --rm -T php-cli
CLI_RUN := $(COMPOSE) run --rm -T --env BITRIX24_WEBHOOK --env BITRIX24_PHP_SDK_PLAYGROUND_WEBHOOK --env B24CLI_TIMEOUT --env B24CLI_API_POLICY --env B24CLI_TEST_DISK_FILE_ID --env B24CLI_TEST_RESTRICTED_WEBHOOK php-cli
ARGS ?=

.PHONY: help docker-init docker-build docker-up docker-down docker-restart \
        composer-install composer-update composer-dumpautoload composer \
        composer-validate php-cli-bash cli lint check worktree-info test-worktree \
        lint-allowed-licenses lint-cs-fixer lint-phpstan lint-rector lint-all test test-integration

help:
	@printf '%s\n' \
	  'Bitrix24 CLI' \
	  '' \
	  'docker-init          Build the PHP image and install dependencies' \
	  'docker-build         Build the PHP image' \
	  'docker-up            Start the development container' \
	  'docker-down          Stop the development container' \
	  'docker-restart       Restart the development container' \
	  'composer-install     Install locked dependencies' \
	  'composer-update      Update dependencies and composer.lock' \
	  'composer-dumpautoload Regenerate the Composer autoloader' \
	  'composer ARGS="..."  Run Composer with arguments' \
	  'composer-validate    Validate composer.json and composer.lock' \
	  'php-cli-bash         Open a shell in the PHP container' \
	  'cli ARGS="..."       Run bin/b24cli (default: command list)' \
	  'lint                 Check PHP syntax' \
	  'check                Validate Composer, PHP syntax and CLI startup' \
	  'worktree-info        Show this checkout and its Compose project' \
	  'test-worktree        Verify two concurrent worktrees from committed HEAD' \
	  'lint-allowed-licenses Check dependency licenses' \
	  'lint-cs-fixer        Check PHP code style (no source changes)' \
	  'lint-phpstan         Run PHPStan static analysis' \
	  'lint-rector          Check Rector rules (dry run)' \
	  'lint-all             Run all four quality checks'
	@printf '%s\n' 'test                 Run offline command/SDK contract tests' \
	  'test-integration     Run live portal tests using the root .env (skip without webhook)'

worktree-info:
	@$(COMPOSE) --info

test-worktree:
	bash ./tests/worktree-isolation.sh

docker-init:
	$(MAKE) docker-build
	$(MAKE) composer-install

docker-build:
	$(COMPOSE) build php-cli

docker-up:
	$(COMPOSE) up --build -d php-cli

docker-down:
	$(COMPOSE) down --remove-orphans

docker-restart:
	$(MAKE) docker-down
	$(MAKE) docker-up

composer-install:
	$(RUN) composer install --no-interaction

composer-update:
	$(RUN) composer update --no-interaction

composer-dumpautoload:
	$(RUN) composer dump-autoload

composer:
	$(RUN) composer $(ARGS)

composer-validate:
	$(RUN) composer validate --strict

php-cli-bash:
	$(COMPOSE) run --rm php-cli sh

cli:
	$(CLI_RUN) php bin/b24cli $(ARGS)

lint:
	$(RUN) sh -c 'find src config tests -name "*.php" -exec php -l {} + && php -l bin/console && php -l bin/b24cli'

check: composer-validate lint
	$(RUN) composer check-platform-reqs
	$(RUN) php bin/console list --no-ansi

lint-allowed-licenses:
	$(RUN) vendor/bin/composer-license-checker

lint-cs-fixer:
	$(RUN) vendor/bin/php-cs-fixer check --verbose --diff

lint-phpstan:
	$(RUN) vendor/bin/phpstan analyse --memory-limit=2G

lint-rector:
	$(RUN) vendor/bin/rector process --dry-run

lint-all: lint-allowed-licenses lint-cs-fixer lint-phpstan lint-rector

test:
	$(RUN) vendor/bin/phpunit --testsuite offline

test-integration:
	$(CLI_RUN) vendor/bin/phpunit --testsuite integration
