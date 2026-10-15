## ADDED Requirements

### Requirement: Bounded installer network retries
The Unix installer SHALL retry transient HTTP 408, 429, 500, 502, 503 and 504 responses, timeouts and refused connections during latest resolution and asset downloads. Each transfer MUST allow at most three retries with backoff and a 120-second retry-start budget, retain per-attempt timeouts and HTTPS verification, and report final failure without replacing the installation. Permanent HTTP errors and failed package validation MUST NOT be retried.

#### Scenario: Recover latest after a gateway timeout
- **WHEN** latest resolution receives HTTP 504 and a subsequent attempt succeeds within the retry limits
- **THEN** the installer resolves the release and installs its verified package successfully

#### Scenario: Recover an asset download
- **WHEN** an archive or SHA256SUMS download receives a transient HTTP error and then succeeds within the retry limits
- **THEN** installation continues using the complete successfully downloaded assets

#### Scenario: Recover a transfer timeout
- **WHEN** an asset transfer times out after receiving part of its body and the next attempt succeeds within the retry limits
- **THEN** the complete asset passes checksum validation without retaining bytes from the failed attempt

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
