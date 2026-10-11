# Tasks

## 1. Compression implementation

- [x] 1.1 Add pinned packer configuration, scoped verified tool installation, licensing and compression reports; verify regression cases for wrong digest/version, failed tool, missing output, no reduction and altered report/hash.
- [ ] 1.2 Integrate Linux/Windows micro compression before PHAR combine and macOS packing after payload finalization with inner/outer signatures; verify original runtime/payload preservation and native size reduction.
- [x] 1.3 Document native prerequisites, packer identities and macOS cold/warm unpack behavior; verify documented commands match Make/build entry points and notices are shipped.

## 2. CI and archive acceptance

- [x] 2.1 Update common platform CI for compressed report checks, original/final smoke and macOS signing checks; verify actionlint and controlled compression tests pass and both CI routes reuse the updated workflow.
- [ ] 2.2 Include compression provenance and licenses in public archives while excluding diagnostic originals; verify archive/checksum/corruption/install checks.

## 3. Native integration and verification

- [ ] 3.1 Obtain successful Linux/macOS/Windows native CI acceptance for this source revision and record exact executable/archive sizes before/after, runtime contracts and source identities.
- [ ] 3.2 Verify all change scenarios, OpenSpec strict validation and issue/change/milestone trace; record local, actual CI and unperformed release/portal boundaries.

## Workflow follow-up

- Archive the change after implementation and native verification, update the issue path to archive, and publish the final PR to dev.
