## ADDED Requirements

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
