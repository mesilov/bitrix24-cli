## MODIFIED Requirements

### Requirement: CI artifact verification
Development packaging CI SHALL run only on manual dispatch, build all supported platform packages from the selected revision, smoke-test them independently of the checkout, and upload verified artifacts without publishing releases. It MUST NOT run automatically on pull requests targeting dev or main or branch pushes. Source tests and linters SHALL continue on pull requests and pushes to dev. Release-tag packaging SHALL retain its full verification and publication gate.

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
- **WHEN** a branch receives a push
- **THEN** development packaging is not automatically started
- **AND** source tests and linters continue to run on pushes to dev

#### Scenario: Preserve release-tag packaging
- **WHEN** a production version tag is pushed
- **THEN** all supported platform packages are built and verified through the existing release gate
- **AND** publication requires main-history validation, successful smoke tests, complete archives and matching checksums
