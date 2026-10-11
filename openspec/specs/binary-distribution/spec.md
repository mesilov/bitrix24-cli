# binary-distribution Specification

## Purpose

Предоставить самостоятельные исполняемые пакеты Bitrix24 CLI с проверяемой версией и целостностью, чтобы пользователи и CI могли запускать инструмент без подготовки PHP-окружения.

## Requirements

### Requirement: Standalone platform packages
The project SHALL provide CLI binaries for Linux amd64, macOS arm64 and Windows amd64 that run without installed PHP, Composer, Docker or an external application checkout. Minimum operating system versions and remaining system dependencies MUST be documented.

#### Scenario: Execute a downloaded binary outside the checkout
- **WHEN** a platform artifact is extracted into a clean directory on its supported operating system without PHP, Composer, Docker, vendor files or portal credentials
- **THEN** `b24cli` or `b24cli.exe` executes `--version`, `list --no-ansi` and `--help` successfully
- **AND** the process does not load files from the build checkout

### Requirement: Production package contents
The package SHALL include the application, its required resources, locked production dependencies and applicable license notices. Development-only dependencies, local credentials, environment files and build caches MUST be excluded.

#### Scenario: Inspect the packaged application
- **WHEN** package contents are compared with the production dependency set and application resource manifest
- **THEN** all required production files and license notices are present
- **AND** development tools, local environment files, credentials and build caches are absent

### Requirement: Runtime compatibility
The packaged application SHALL provide the runtime extensions needed by its locked production dependencies, preserve command output and exit-code contracts, and support UTF-8 and HTTPS with certificate verification.

#### Scenario: Offline command compatibility
- **WHEN** available application commands run against controlled offline fixtures in source and packaged form
- **THEN** equivalent invocations preserve documented output formats and exit codes, including Unicode input and usage errors
- **AND** no live portal credentials are required for packaging acceptance

#### Scenario: Verify HTTPS trust
- **WHEN** the packaged runtime connects to controlled HTTPS endpoints with valid and invalid certificates using the documented trust configuration
- **THEN** trusted HTTPS succeeds and an untrusted certificate is rejected
- **AND** required production extensions are available without loading modules from the build host

### Requirement: Repeatable build inputs and isolation
The project SHALL document commands to rebuild packages from a locked application dependency set and pinned PHP and toolchain identities. Mutable packaging state MUST remain local to the selected checkout, preserving existing development workflows.

#### Scenario: Rebuild from a clean checkout
- **WHEN** the documented packaging command runs from a clean checkout with the stated build prerequisites
- **THEN** it produces the requested platform package from recorded source, dependency and toolchain identities
- **AND** it does not modify another checkout's dependencies, configuration, cache or artifacts

### Requirement: Package version identity
Release binaries SHALL report a version matching their source version tag. Development binaries SHALL expose the full source commit SHA so each downloaded artifact can be traced to the revision built. Developer Make builds MUST support any clean checkout commit without requiring a main branch or tag and MUST reject uncommitted package inputs or a supplied SHA that differs from HEAD before staging.

#### Scenario: Verify release version
- **WHEN** a package is built from a release version tag
- **THEN** `--version` reports that tag's version according to the documented tag convention
- **AND** the workflow fails if the reported version differs

#### Scenario: Verify development revision
- **WHEN** a developer builds a clean checkout revision on any branch without requiring a version tag
- **THEN** `--version` identifies the full source commit SHA
- **AND** the workflow fails if it identifies a different revision

#### Scenario: Reject mismatched source identity
- **WHEN** a developer attempts packaging with dirty tracked/untracked application inputs or a supplied SHA that does not match the checkout HEAD
- **THEN** the build fails before staging or passing its source into Docker
- **AND** ignored build/cache output does not block a clean source build

### Requirement: CI artifact verification
Packaging CI SHALL run on pull requests targeting dev or main, pushes to dev and manual dispatch, build all supported platform packages, smoke-test them independently of the checkout, and upload verified artifacts. Pull-request runs MUST NOT publish releases.

#### Scenario: Package a pull request
- **WHEN** a pull request targets dev or main
- **THEN** each platform job builds and tests its package and uploads a successfully verified artifact
- **AND** a missing artifact or failed smoke test fails its job without release publication

### Requirement: Release assets and publication gate
Production CI SHALL build only a specific version tag whose commit is reachable from main. A version-tag workflow SHALL publish `b24cli-linux-amd64.tar.gz`, `b24cli-macos-arm64.tar.gz`, `b24cli-windows-amd64.zip` and `SHA256SUMS` only after all platform builds and smoke tests succeed. Checksums MUST cover the exact final archives. Publication retries MUST resume an existing draft, upload the complete verified asset set before publication and leave already public releases unchanged.

#### Scenario: Publish a verified release
- **WHEN** all supported platform packages for a version tag have passed their checks
- **THEN** the release contains the three named archives and SHA-256 checksums matching those archives

#### Scenario: Reject an incomplete release
- **WHEN** any platform build, version check, smoke test or required artifact fails
- **THEN** no public release is published for that workflow run

#### Scenario: Reject a version tag outside main
- **WHEN** a version tag points to a commit outside the main branch history
- **THEN** production CI fails before platform builds or publication

#### Scenario: Resume interrupted draft publication
- **WHEN** a previous publication attempt left a draft after creation, partial upload or a failed publish step
- **THEN** a rerun reuses the draft and uploads all verified assets before publishing
- **AND** lookup/upload failures stop publication and an already published release is not overwritten

### Requirement: Installation and evidence documentation
Documentation SHALL describe supported systems, package download, checksum verification before installation, PATH setup and build commands. Recorded acceptance MUST distinguish local checks, actual CI runs, actual release publication and live portal verification.

#### Scenario: Install a verified archive
- **WHEN** a user follows the platform installation instructions
- **THEN** the user verifies the downloaded archive against SHA256SUMS before installing the binary into PATH and can run `b24cli --version`
- **AND** unsupported systems and required operating system libraries are stated

#### Scenario: Report workflow readiness
- **WHEN** implementation verification records the completed checks
- **THEN** it identifies which local, CI, release and portal checks actually ran and which remain unperformed
