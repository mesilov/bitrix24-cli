# docker-cli-environment Specification

## Purpose

Provide a compact Docker environment for installing Bitrix24 CLI dependencies and running the CLI without a host PHP installation.

## Requirements

### Requirement: Compact Alpine image
The CLI Docker environment SHALL use Alpine and PHP 8.4 CLI. Its image size MUST be lower than the previous Debian image when measured on the same architecture and with the same image-size metric.

#### Scenario: Comparable size measurement
- **WHEN** the previous and replacement images are built for the same architecture
- **THEN** Alpine is identifiable in the replacement image and its measured size is lower
- **AND** both sizes, architecture, base-image identities and comparison commands are recorded

### Requirement: Necessary dependencies only
The environment SHALL provide all PHP extensions and system packages required by the current locked dependencies, CLI and adopted project tools. Additional extensions and packages MUST have a documented use; build-only dependencies MUST be absent from the final image.

#### Scenario: Extension audit
- **WHEN** the image's extensions and packages are reviewed against the current project
- **THEN** every additionally installed extension and package has a documented use and unneeded additions are absent
- **AND** composer check-platform-reqs succeeds without ignoring requirements

### Requirement: Compatible development workflow
The environment SHALL support locked Composer installation, existing Make/Compose commands and CLI startup without requiring PHP or Composer on the host.

#### Scenario: Clean dependency installation
- **WHEN** dependencies are installed from composer.lock in an isolated clean checkout using the new image
- **THEN** make docker-init, make check, make cli and make cli ARGS="--version" succeed
- **AND** adopted lint targets succeed if present in the implementation base

### Requirement: Unprivileged writable execution
The environment SHALL run development commands as a non-root user and support the existing LOCAL_UID/LOCAL_GID mapping for writable project files and Composer cache.

#### Scenario: Host identity mapping
- **WHEN** Make runs dependency installation with the host user's non-root UID/GID
- **THEN** the container runs with that identity, writes its cache successfully and creates project files owned by that host UID/GID

#### Scenario: Default container identity
- **WHEN** the image is run without a UID/GID override
- **THEN** its configured user is non-root and can write the working directory and Composer cache
