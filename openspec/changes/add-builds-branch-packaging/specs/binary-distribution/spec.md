## MODIFIED Requirements

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
Production CI SHALL build only a specific non-RC version tag whose commit is reachable from main. A production version-tag workflow SHALL publish `b24cli-linux-amd64.tar.gz`, `b24cli-macos-arm64.tar.gz`, `b24cli-windows-amd64.zip` and `SHA256SUMS` only after all platform builds and smoke tests succeed. Checksums MUST cover the exact final archives. Publication retries MUST resume an existing draft, upload the complete verified asset set before publication and leave already public releases unchanged.

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

## ADDED Requirements

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
