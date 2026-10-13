# Binary distribution delta

## ADDED Requirements

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
