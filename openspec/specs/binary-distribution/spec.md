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
Development packaging CI SHALL run on manual dispatch, pushes to builds or RC tags, build all supported platforms from the selected revision, smoke-test them outside the checkout and upload verified artifacts without releases. It MUST NOT run automatically on pull requests or other branch pushes. Source tests and linters SHALL continue on pull requests and pushes to dev. Production-tag packaging SHALL retain its full verification and publication gate.

#### Scenario: Manually package a development revision
- **WHEN** a maintainer manually dispatches packaging for a selected branch or ref
- **THEN** each platform job builds and tests its package from that revision and uploads a successfully verified artifact
- **AND** a missing artifact or failed smoke test fails its job without release publication
- **AND** development binaries identify the full selected source commit SHA

#### Scenario: Package a pull request
- **WHEN** a pull request targets dev or main
- **THEN** development packaging is not automatically started
- **AND** source tests and linters continue to run on the pull request

#### Scenario: Skip automatic branch packaging
- **WHEN** a branch other than builds receives a push
- **THEN** development packaging is not automatically started
- **AND** source tests and linters continue to run on pushes to dev

#### Scenario: Package a selected intermediate snapshot
- **WHEN** a maintainer updates builds with a selected snapshot from dev
- **THEN** all supported platform packages are built and verified from the exact builds commit SHA
- **AND** verified archives and SHA256SUMS are uploaded as workflow artifacts without creating a version tag or GitHub Release
- **AND** binaries identify dev-<full source SHA> and a newer builds push cancels an obsolete packaging run for that branch

#### Scenario: Preserve release-tag packaging
- **WHEN** a production version tag other than an RC tag is pushed
- **THEN** all supported platform packages are built and verified through the existing release gate
- **AND** publication requires main-history validation, successful smoke tests, complete archives and matching checksums

### Requirement: Release assets and publication gate
Production CI SHALL build only a non-RC version tag reachable from main. A production version-tag workflow SHALL publish `b24cli-linux-amd64.tar.gz`, `b24cli-macos-arm64.tar.gz`, `b24cli-windows-amd64.zip` and `SHA256SUMS` only after all platform builds and smoke tests succeed. Checksums MUST cover the exact final archives. Publication retries MUST resume an existing draft, upload the complete verified asset set before publication and leave already public releases unchanged.

#### Scenario: Publish a verified release
- **WHEN** all supported platform packages for a version tag have passed their checks
- **THEN** the release contains the three named archives and SHA-256 checksums matching those archives

#### Scenario: Reject an incomplete release
- **WHEN** any platform build, version check, smoke test or required artifact fails
- **THEN** no public release is published for that workflow run

#### Scenario: Reject a version tag outside main
- **WHEN** a production non-RC version tag points to a commit outside the main branch history
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

### Requirement: Final macOS executable integrity
macOS arm64 artifacts SHALL carry a valid ad-hoc or Developer ID signature covering the final embedded application. Packaging MUST reject invalid signatures or changed payload bytes before uploading artifacts. The embedded application MUST retain its existing command and exit-code contracts after signing.

#### Scenario: Verify the completed executable
- **WHEN** the macOS runtime and application have been combined and finalized
- **THEN** `codesign --verify --strict` succeeds on both the application and runtime probe
- **AND** copied binaries execute version, help and list with the expected source identity
- **AND** the embedded PHAR matches the original payload byte for byte

#### Scenario: Reject signed payload tampering
- **WHEN** bytes inside a signed macOS application's embedded payload are modified
- **THEN** strict signature verification fails
- **AND** the altered executable is not accepted as a distributable artifact

### Requirement: Downloaded macOS development artifact first launch
Development-artifact documentation SHALL distinguish valid ad-hoc integrity from Developer ID/notarization trust and describe macOS approval of a specific trusted download. Acceptance MUST record an actual browser download with quarantine metadata and its first-launch/approval result on supported macOS separately from CI smoke. Global disabling of Gatekeeper MUST NOT be required.

#### Scenario: Run a quarantined download
- **WHEN** a supported macOS user downloads and extracts a verified development artifact through a browser with normal security settings
- **THEN** version, help and list work either immediately or after the documented Privacy & Security → Open Anyway approval for that file
- **AND** evidence records OS/build, source SHA, checksum, quarantine and approval result

#### Scenario: State production trust limits
- **WHEN** a user reads development-artifact installation instructions
- **THEN** the instructions explain that valid ad-hoc signing does not provide Developer ID/notarization trust
- **AND** runner-local success is not represented as downloaded-artifact acceptance

### Requirement: Compressed standalone executables
All supported standalone packages SHALL contain a compressed executable smaller than its equivalent uncompressed build. Packaging MUST preserve production payload bytes, runtime features, version identity, command output, exit codes, UTF-8, verified HTTPS and Unix interruption behavior. Missing tools or failed compression MUST fail the build.

#### Scenario: Build every compressed platform package
- **WHEN** a developer or CI builds Linux amd64, macOS arm64 or Windows amd64
- **THEN** the normal executable path contains the smaller compressed build with the same source/version and production dependencies
- **AND** native acceptance runs on that final executable without PHP, Composer or checkout dependencies

#### Scenario: Preserve signed macOS payload and process behavior
- **WHEN** a compressed macOS package runs with a writable temporary directory
- **THEN** both the embedded executable and its launcher have valid signatures, the original PHAR bytes remain intact, and repeated invocation succeeds
- **AND** direct SIGINT preserves confirmed task output and exit 130

#### Scenario: Concurrent Windows startup
- **WHEN** multiple compressed Windows executables start concurrently in one writable temporary directory
- **THEN** each loads the unchanged application PHAR from its own exclusively created file without a temporary-file rename
- **AND** concurrent profile updates preserve all independent profiles and normal shutdown removes the temporary PHAR files

#### Scenario: Reject unsuccessful compression
- **WHEN** the selected compressor is unavailable, has an unexpected identity, fails, or produces no size reduction
- **THEN** packaging fails before artifact upload or release publication

### Requirement: Compression provenance and measured size
Platform packages SHALL record the compressor identity and exact sizes and SHA256 of the equivalent uncompressed and final executable. Final archive checksums MUST cover the compressed bytes. Packages SHALL include applicable compressor notices and document any launch-time writable storage requirement.

#### Scenario: Verify final compression report
- **WHEN** CI prepares a platform artifact and release archive
- **THEN** its report matches the actual source and final executable bytes, records the selected compressor, and proves the final file is smaller
- **AND** the public archive contains the final executable, report and notices without diagnostic uncompressed executable or probe

#### Scenario: Install a compressed executable
- **WHEN** the user follows the documented checksum, unpack and PATH instructions
- **THEN** the installed final executable runs independently and reports the expected version
- **AND** any required temporary storage and first-start unpacking behavior is documented

### Requirement: Unix shell installer
The project SHALL provide a POSIX shell installer for the supported macOS arm64 and Linux amd64 packages. It MUST detect the target, require no sudo or external PHP, Composer, Docker, Homebrew, Python or jq, and reject unsupported targets before changing an installation.

#### Scenario: Select the supported platform
- **WHEN** a user runs the installer on supported macOS arm64 or Linux amd64
- **THEN** it selects the corresponding standalone archive and checks required system utilities

#### Scenario: Reject an unsupported target
- **WHEN** the installer runs on an unsupported OS or architecture
- **THEN** it reports that target, exits nonzero and leaves an existing installation unchanged

### Requirement: Public release selection
The installer SHALL download its platform archive and SHA256SUMS from the same public GitHub Release without authentication. It MUST select latest stable by default, support an explicit published tag through `--version <tag>` including a prerelease, and report missing releases or assets without falling back to authenticated CI artifacts.

#### Scenario: Install the default stable release
- **WHEN** the user runs the installer without a version argument
- **THEN** it resolves one stable release and downloads both assets from that exact tag

#### Scenario: Install an explicit tag
- **WHEN** the user supplies a published tag with `--version`
- **THEN** it selects that tag even when it is a prerelease and does not substitute another version

#### Scenario: Report an unavailable release
- **WHEN** no suitable public release exists or an asset is unavailable
- **THEN** the installer reports the missing release or asset and exits nonzero without changing the installed binary

### Requirement: Verified user installation
The installer SHALL verify the archive SHA-256 before extraction and install an executable binary in `$HOME/.local/bin` by default. It MUST preserve external license notices under `$HOME/.local/share/b24cli`, check the candidate binary version against the selected version and report the final installation path and version.

#### Scenario: Install a verified package
- **WHEN** both assets are downloaded and checksum and candidate version checks pass
- **THEN** the user receives an executable b24cli and all packaged external license notices at the documented locations

#### Scenario: Reject invalid checksum evidence
- **WHEN** the checksum is missing, malformed, ambiguous or does not match the archive
- **THEN** the installer exits nonzero before extraction or replacement of the installed binary

### Requirement: Preserve existing installations during updates
Repeated installation and updates SHALL preserve connection configuration and other user data. Download, checksum, extraction or candidate version failures MUST leave the previous working binary unchanged. Successful replacement MUST occur only after the new candidate has passed its checks.

#### Scenario: Update an existing installation
- **WHEN** a newer selected package passes its checks
- **THEN** it replaces the installed binary and notices while retaining user configuration and data

#### Scenario: Keep the old version on failure
- **WHEN** download, checksum, extraction or candidate execution/version checking fails
- **THEN** the old binary and user data remain usable and the installer reports a nonzero result

### Requirement: Persistent shell activation
The installer SHALL create an env-file and configure supported zsh and bash startup profiles idempotently, respecting ZDOTDIR for zsh. It MUST avoid duplicate PATH entries or startup lines and MUST NOT add the current directory to PATH. It MUST explain that current-shell activation requires sourcing the env-file or opening a new terminal.

#### Scenario: Activate the current and a new session
- **WHEN** the user sources the emitted env-file and then opens another supported shell session
- **THEN** both sessions can find b24cli from another directory and report the installed version

#### Scenario: Repeat shell setup
- **WHEN** installation or env-file activation is repeated
- **THEN** startup configuration and PATH do not accumulate duplicate installer entries

### Requirement: Installer customization
The installer SHALL support `--install-dir <path>` for a custom binary directory and `--no-modify-path` for installation without modifying shell profiles. It MUST handle paths containing spaces and print activation instructions for the actual selected binary directory.

#### Scenario: Install to a custom directory
- **WHEN** the user selects a custom path containing spaces
- **THEN** the binary is installed there and the emitted env-file activates that exact path

#### Scenario: Preserve managed shell profiles
- **WHEN** the user selects `--no-modify-path`
- **THEN** shell profiles remain unchanged and the installer still reports how to execute or activate the installed binary

### Requirement: Installer distribution documentation
Documentation SHALL expose an anonymously accessible installer URL, the public release asset source, current-shell activation and manual installation. It MUST provide an inspect-before-execution alternative, identify supported systems and keep macOS launch blocking and signing/notarization separate from PATH setup.

#### Scenario: Follow the installer quick start
- **WHEN** an unauthenticated user follows the published instructions
- **THEN** the script and selected public release assets are downloadable and the user can complete installation and activation

### Requirement: Installer acceptance evidence
Acceptance SHALL cover fixtures and native macOS/Linux packages, repeated installation, updates, custom paths, opt-out and failure behavior. It MUST distinguish local checks, platform CI, actual public URL/release availability and macOS launch/signing checks. Installer execution MUST NOT disable Gatekeeper or remove quarantine automatically.

#### Scenario: Record installation readiness
- **WHEN** installer verification is recorded
- **THEN** it lists the checks actually run and any pending public distribution or signing evidence without presenting fixtures as public delivery

### Requirement: Intermediate artifact retention
Packaging from builds or an RC tag SHALL retain diagnostic platform artifacts for seven days and the verified archive set with SHA256SUMS for 30 days. Other packaging runs MUST retain their existing retention policy. Documentation SHALL explain downloading a specific run and distinguish intermediate acceptance from final release acceptance.

#### Scenario: Download an intermediate build
- **WHEN** a maintainer downloads the verified archive set from a successful builds run before expiration
- **THEN** the set contains all three platform archives and SHA256SUMS for that run's exact source SHA
- **AND** the documented checks cover checksum, metadata, version and execution outside the source checkout

#### Scenario: Preserve production artifact retention
- **WHEN** packaging runs from a production tag or a manually selected ref outside builds and RC tags
- **THEN** intermediate retention overrides are not applied

### Requirement: Release candidate identity and manual promotion
RC tags SHALL use MAJOR.MINOR.PATCH-rc-N with a positive integer N and point to a commit reachable from builds. RC binaries MUST report the exact tag and source SHA. Maintainers SHALL choose builds updates and RC tagging manually for intermediate binary checks or pre-release acceptance. RC packaging MUST NOT enter production publication or change latest release links.

#### Scenario: Test a numbered release candidate
- **WHEN** a maintainer tags a selected builds snapshot as 0.2.0-rc-1 or the next candidate 0.2.0-rc-2
- **THEN** development CI validates the tag identity and builds ancestry before platform jobs
- **AND** all three platform binaries report the exact candidate version and are uploaded for acceptance without a GitHub Release

#### Scenario: Reject an invalid or unrelated candidate
- **WHEN** an RC-shaped tag has an invalid version/counter, points outside builds history or does not match the selected source SHA
- **THEN** CI fails before platform builds and does not publish a Release

#### Scenario: Preserve the meaning of an accepted candidate
- **WHEN** a candidate fails acceptance or needs source changes
- **THEN** changes return through the ordinary feature/bugfix workflow into dev, a new snapshot is selected and the next RC tag is created
- **AND** the existing RC tag and accepted artifacts are not overwritten or relabeled as a stable version

### Requirement: Bounded installer network retries
The Unix installer SHALL retry transient HTTP 408, 429, 500, 502, 503 and 504 responses, timeouts and refused connections during latest resolution and asset downloads. Each transfer MUST allow at most three retries with backoff, a retry-start budget of 360 seconds for latest or 900 for assets, and per-attempt timeouts of 120 or 300 seconds, respectively. HTTPS verification MUST remain enabled. Permanent HTTP errors and failed package validation MUST NOT be retried.

#### Scenario: Recover latest after a gateway timeout
- **WHEN** latest resolution receives HTTP 504 and a subsequent attempt succeeds within the retry limits
- **THEN** the installer resolves the release and installs its verified package successfully

#### Scenario: Recover an asset download
- **WHEN** an archive or SHA256SUMS download receives a transient HTTP error and then succeeds within the retry limits
- **THEN** installation continues using the complete successfully downloaded assets

#### Scenario: Recover a transfer timeout
- **WHEN** latest resolution or an asset transfer reaches its full per-attempt timeout after receiving part of its body and the next attempt succeeds within the retry limits
- **THEN** latest resolves successfully or the complete asset passes checksum validation without retaining bytes from the failed attempt

#### Scenario: Recover a refused connection
- **WHEN** a connection is refused and the endpoint becomes reachable within the retry limits
- **THEN** the installer retries the transfer and completes installation

#### Scenario: Exhaust transient retries
- **WHEN** a release transfer continues returning HTTP 504
- **THEN** the installer makes no more than four attempts for that transfer and exits nonzero
- **AND** the previous binary, env, shell profiles, notices and user data remain unchanged

#### Scenario: Reject a permanent failure
- **WHEN** a release transfer returns HTTP 404 or downloaded package validation fails
- **THEN** the installer exits nonzero without retrying the failed request or downloading the package again
- **AND** the previous installation remains unchanged

### Requirement: Conditional shell installation examples
The README and installation guide SHALL provide Bash/zsh command sequences that activate the env and invoke b24cli only after script download and installation succeed. A failure on either side of a pipeline MUST stop the sequence even when a previous env and binary exist. Env activation MUST remain in the current shell, and activation failure MUST prevent the version command.

#### Scenario: Stop after script download failure
- **WHEN** the user executes a documented installation block and downloading install.sh fails
- **THEN** the block returns nonzero without sourcing env or invoking b24cli

#### Scenario: Stop after installer failure
- **WHEN** the script downloads successfully but the installer returns nonzero
- **THEN** the block returns nonzero without sourcing env or invoking b24cli

#### Scenario: Stop after activation failure
- **WHEN** installation succeeds but sourcing env fails
- **THEN** the block returns nonzero without invoking b24cli

#### Scenario: Activate a successful installation
- **WHEN** script download, installation and env activation succeed
- **THEN** the block reports the installed version and leaves b24cli available in the current shell
