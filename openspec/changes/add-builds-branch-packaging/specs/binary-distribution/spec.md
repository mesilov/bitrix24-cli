## MODIFIED Requirements

### Requirement: CI artifact verification
Development packaging CI SHALL run on manual dispatch or pushes to builds, build all supported platforms from the selected revision, smoke-test them outside the checkout and upload verified artifacts without releases. It MUST NOT run automatically on pull requests or other branch pushes. Source tests and linters SHALL continue on pull requests and pushes to dev. Release-tag packaging SHALL retain its full verification and publication gate.

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
- **WHEN** a production version tag is pushed
- **THEN** all supported platform packages are built and verified through the existing release gate
- **AND** publication requires main-history validation, successful smoke tests, complete archives and matching checksums

## ADDED Requirements

### Requirement: Intermediate artifact retention
Packaging from builds SHALL retain diagnostic platform artifacts for seven days and the verified archive set with SHA256SUMS for 30 days. Other packaging runs MUST retain their existing retention policy. Documentation SHALL explain downloading a specific run and distinguish intermediate acceptance from final release acceptance.

#### Scenario: Download an intermediate build
- **WHEN** a maintainer downloads the verified archive set from a successful builds run before expiration
- **THEN** the set contains all three platform archives and SHA256SUMS for that run's exact source SHA
- **AND** the documented checks cover checksum, metadata, version and execution outside the source checkout

#### Scenario: Preserve production artifact retention
- **WHEN** packaging runs from a production tag or a manually selected non-builds ref
- **THEN** intermediate retention overrides are not applied
