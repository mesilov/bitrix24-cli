# Verification: compress-standalone-binaries

Verified on 2026-10-12. Source checkpoint: `ed7a54418bba956f4cac32dd4242449aab5ce256`.
Windows timeout calibration: `dd605c5feb3e0f3f517a56224d3393f8c41d20fd`.

## Scorecard

| Dimension | Result |
|---|---|
| Completeness | 4/7 tasks; both ADDED requirements implemented. Full Windows acceptance/archive set pending. |
| Correctness | All 5 scenarios mapped to code and checks; Windows final archive installation not yet verified. |
| Coherence | Shared builders/CI and pinned packer provenance follow design; application/API fields unchanged. |

## Measured executables

These are equivalent original/final executable bytes from one source checkpoint, not comparisons against an older release. MB uses 1,000,000 bytes.

| Target | Original bytes | Final bytes | Reduction |
|---|---:|---:|---:|
| Linux amd64 | 63,018,037 | 27,279,213 | 56.71% |
| macOS arm64 | 64,402,064 | 27,610,320 | 57.13% |
| Windows amd64 | 64,063,812 | 27,726,148 | 56.72% |

The compression report validates actual sizes/SHA256 of both application and probe against pinned provenance and build metadata. Reports from downloaded Linux/macOS artifacts were independently rechecked locally; Windows report verification and original/final runtime checks passed in native CI before the profile fixture timeout.

## Native evidence

- [Native run 38174251054](https://github.com/mesilov/bitrix24-cli/actions/runs/38174251054): Linux and macOS full acceptance passed, including final compressed runtime, profile fixtures and checksum/unpack/PATH installation. Windows original/final runtime smoke passed; profile acceptance reached its former 90-second limit. The all-platform archive job was skipped because Windows failed.
- [Uncompressed base dev run 38172429157](https://github.com/mesilov/bitrix24-cli/actions/runs/38172429157), source `b35aba5c63657a5ce37487face021ebd95b5f739`, reproduces the Linux export permission error and Windows profile timeout. Linux local export had mode 0700; making public artifacts readable allowed uid 65532 acceptance to pass.
- [Windows timing run 38175056319](https://github.com/mesilov/bitrix24-cli/actions/runs/38175056319): direct ACL helpers took 26.67, 17.79 and 17.61 seconds. Offline CLI calls took 0.31-0.34 seconds. At the first profile timeout, private profile directory, lock and credential file already existed. The measured Windows budget is now 60 seconds/helper and 600 seconds/native command or shell sequence, including four serialized writers. Unix budgets and all ACL assertions are retained.
- [Calibrated native run 38175654088](https://github.com/mesilov/bitrix24-cli/actions/runs/38175654088), source `dd605c5feb3e0f3f517a56224d3393f8c41d20fd`: Linux/macOS pass. Windows now passes profile creation (152.94 seconds), selection (152.54 seconds), defaults and source precedence; it fails the native PowerShell redirection assertion. That independent shell check now precedes the longer profile sequence and prints redacted diagnostics; on failure it also checks the equivalent original executable without weakening the final executable assertion.

Local macOS cold/warm execution of actual source `c69794bf2f87c2457256ea81f918e2320dcd915e` artifacts extracted both inner application and probe. Their SHA256 matched `original_sha256`, their signed PHAR bounds and `codesign --verify --strict` checks passed, and repeated stdout matched. Native original/final smoke also proves direct Unix SIGINT retains confirmed task output and exit 130.

## Archive measurements

From downloaded source `ed7a54418bba956f4cac32dd4242449aab5ce256` artifacts using the current release packager:

| Target | Equivalent raw archive benchmark bytes | Final archive bytes |
|---|---:|---:|
| Linux amd64 | 29,712,655 | 26,004,357 |
| macOS arm64 | 30,545,123 | 27,403,235 |
| Windows amd64 | Not verified | Not verified |

The raw archive benchmark uses identical archive members and replaces only the executable with the diagnostic original. It is a local size benchmark, not a publishable release archive. Final archive installation passed in native Linux/macOS CI. The complete three-archive checksum/corruption/allowlist gate is pending Windows acceptance.

## Requirement and scenario mapping

- **Compressed standalone executables**: `build/package.py` combines the same PHAR with an untouched original and a compressed micro copy on Linux/Windows, and packs the finalized signed executable on macOS. Exact Linux/Windows PHAR suffix equality is required. `build/compress.py` requires reduction, verifies tool identity/digest, tests UPX and restores the SPC Linux loader boundary. Missing/bad tool, bad digest/version, missing output and no reduction have regression checks.
- **Compression provenance and measured size**: `build/compression.json`, `build/compress.py` reports and `build/tests/archive-check.py` verify tool identity, original/final bytes and hashes, final archive checksums/notices and exclusion of original/probe/PHAR. `build/README.md` documents independent installation, writable temp, cold/warm unpack and cached inner execution.
- **Build every compressed platform package**: all three native jobs build and run original/final executables without PHP/Composer/build PATH. Full Windows profile acceptance remains pending.
- **Preserve signed macOS payload and process behavior**: original/outer signature checks, exact decoded inner SHA256/PHAR verification, repeated invocation and actual direct SIGINT proof above.
- **Reject unsuccessful compression**: 19 controlled regressions plus build failure gates before artifact upload/publication.
- **Verify final compression report**: native report verification and independent downloaded Linux/macOS report checks pass; complete final archive set pending.
- **Install a compressed executable**: Linux/macOS actual native archive/checksum/unpack/PATH acceptance passes; Windows native installation pending.

Implementation references: original/final payload integration `build/package.py:104-121`; digest/tool/reduction gates `build/compress.py:38-60,147-185`; macOS wrapper/signature handling `build/compress.py:188-206` and `build/macos.py:72-153`; exact report validation `build/compress.py:237-272`; archive allowlist/hash/notices `build/release-assets.py:15-37` and `build/tests/archive-check.py:24-46`; native/installation CI `.github/workflows/package-platforms.yml:45-75,139-155,204-225,245-248`.

## Local and application checks

19 compression regressions, 7 native macOS signing regressions, 12 source-provenance regressions and 7 publication regressions passed. Python syntax, actionlint, `git diff --check` and strict OpenSpec validation passed. PR application checks (unit tests, schema contract, PHPStan, Rector, PhpCsFixer, license checker and worktree isolation) passed at the executable checkpoint.

## Trace and boundaries

[#37](https://github.com/mesilov/bitrix24-cli/issues/37) has milestone 0.2.0 and enhancement label; proposal links the issue and issue links this change. [PR #39](https://github.com/mesilov/bitrix24-cli/pull/39) targets dev and remains Draft while native Windows/archive verification is incomplete.

No main/dev merge, version tag, public Release, live Bitrix24 portal operation, Developer ID/notarization or Windows Authenticode was performed. Upstream wrappe's outer packer has Cargo.lock; its nested runner resolves dependencies separately. The source patch/scoped Cargo registry/notices and exact cached tool hash are recorded, without claiming bit-for-bit reproducibility.

## Remaining checks

- Complete task 2.2: Windows native archive installation and the full archive/checksum/corruption gate.
- Complete task 3.1: all three native jobs passing for the same source and Windows archive measurements.
- Complete task 3.2: final scenario verification and strict validation after those results.

Windows installation/archive checks are not verified. This change is not ready for archival yet.
