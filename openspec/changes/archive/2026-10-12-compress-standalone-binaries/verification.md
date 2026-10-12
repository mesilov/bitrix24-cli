# Verification: compress-standalone-binaries

Verified on 2026-10-12. Implementation source: `41ca8c2a98b6c60e5aa11854fb56463a88d4a2b7`.

## Scorecard

| Dimension | Result |
|---|---|
| Completeness | 7/7 tasks; both ADDED requirements implemented and verified. |
| Correctness | All 6 scenarios mapped to code, regressions and actual native acceptance. |
| Coherence | Shared builders/CI, pinned packer provenance, signatures and public archive allowlist follow design; API fields and command contracts unchanged. |

No unresolved critical, warning or suggestion findings in the changed requirement scope.

## Measured executables

Equivalent original/final executable bytes from the same source and production dependencies. MB uses 1,000,000 bytes.

| Target | Original bytes | Final bytes | Reduction |
|---|---:|---:|---:|
| linux-amd64 | 63,018,020 | 27,279,196 | 56.71% |
| macos-arm64 | 64,402,048 | 27,610,272 | 57.13% |
| windows-amd64 | 64,063,795 | 27,726,131 | 56.72% |

Both main and probe reports passed native verification against actual sizes/SHA256, pinned packer provenance and build metadata before upload. Downloaded Linux/Windows platform reports were independently rechecked locally. The macOS final executable/report came from the verified public archive; actual cold and warm execution unpacked an inner executable whose SHA256 exactly matched the reported original and whose strict signature verification passed. This independently checks the main macOS bytes without relying on the larger diagnostic platform download.

## Native and local evidence

- [Native run 38180366391](https://github.com/mesilov/bitrix24-cli/actions/runs/38180366391), source above: **Linux, macOS, Windows and the complete release archive gate all passed**. PR application/schema/license/static-analysis/worktree checks also passed at this source.
- Each native job executes equivalent original/final runtime smoke with clean PATH: version, help/list, 30 task commands, SDK/autoload/extensions, UTF-8, trusted HTTPS and rejection of an invalid certificate. Unix original/final smoke proves actual direct SIGINT keeps confirmed output and exit 130.
- macOS original payload bounds/bytes and signature, final outer strict signature, linkage, profiles and final archive/PATH installation pass in native CI. Independent local cold/warm execution of the current public executable confirms decoded inner SHA256 `e3d104d04bc53fd9e0ec122e4f6ad1e04981529871a3270ceed46242438bc6f4` and strict signature validity.
- Windows passes actual PowerShell and cmd.exe redirection, profile/default/source precedence, controlled full SDK transport, raw URL control rejection, Unicode/space paths, protected ACLs, read-only executable directory, four concurrent writers, no secret output and no binary mutation. Concurrent additions took 5.76/8.89/11.99/15.10 seconds and preserved all four profiles. Temporary PHAR cleanup and final archive/PATH installation pass.
- The shared builder runs 3 controlled real-PHAR regressions: payload execution/cleanup, exclusive collision refusal without overwriting/deleting an existing file, and unavailable temporary storage. They passed locally in Docker PHP 8.5.11 and on all native build hosts.
- 19 compression, 7 native macOS signing, 12 source-provenance and 7 publication regressions passed. Python/PHP syntax, actionlint, `git diff --check` and strict OpenSpec validation passed.
- Downloaded final release archives independently pass the existing three-archive SHA256, permissions, notice/allowlist and corruption-rejection check locally.

Windows acceptance environment corrections retain all assertions: OS essentials/inbox PowerShell modules, measured cold-start budgets, explicit UTF-8 without BOM for OutputEncoding and Console.InputEncoding, and correct cmd.exe quoting. The temporary Windows PHAR is created directly in exclusive mode with a random 128-bit filename, avoiding the rename failure observed during concurrent startup. Profile locks and application URL validation remain unchanged.

## Archive measurements

Actual final CI archives and equivalent original-executable benchmarks from the same source:

| Target | Equivalent raw archive benchmark bytes | Final archive bytes |
|---|---:|---:|
| linux-amd64 | 29,712,481 | 26,004,240 |
| macos-arm64 | 30,543,359 | 27,401,506 |
| windows-amd64 | 30,315,745 | 26,348,767 |

The raw benchmark reuses the final archive's exact members and metadata, replacing only the executable with the equivalent original. The macOS original is the decoded executable verified against the native report's original SHA256. These diagnostic benchmarks are not publishable release archives; their copied compression reports describe final bytes. Actual final archives and checksum/unpack/PATH installation passed for all three platforms.

- `linux-amd64` executable: `065b12d1a29d08d9e69b88c99f3d0a3cc4668b845a3dd69831b2273ecce9b84a`; archive: `a192b929e64701aabb0e9e8fd6d0f0c417f66fc4f04608694bb6d56264915a20`.
- `macos-arm64` executable: `c744532611c7f30ff3d30b9bec4cc6f3d754cf0a002f2481a2bb54a21f9acf26`; archive: `73d7ac211b57fb41a2c2bfe81dd195318bd1aba988ca3f27e3cf59e3e703b10b`.
- `windows-amd64` executable: `7f7689dfe11e904f4535ef806c4359f919c7d1006425d2817dc49ef3b0ffd0c2`; archive: `8cec024446ba2bbd995b25523aedcc22b8dd8bddc41411e12f67138a598307f0`.

## Requirement and scenario mapping

- **Compressed standalone executables** / **Build every compressed platform package**: `build/package.py` combines the same PHAR with an untouched original and compressed micro copy on Linux/Windows, requiring exact PHAR suffix equality. macOS packs the finalized signed original. Original/final report and native acceptance pass for all three targets at the same source.
- **Preserve signed macOS payload and process behavior**: `build/macos.py`/`build/compress.py` preserve finalized inner bytes, cover the outer overlay with a strict signature, and keep wrappe exec mode. Native original PHAR/signature, final runtime/SIGINT and local decoded-inner SHA/signature/cold-warm checks above cover the scenario.
- **Concurrent Windows startup**: `build/phar-stub.php` directly creates a unique PHAR using exclusive mode, retains the handle during payload copy and retains shutdown cleanup. The 3 loader regressions and actual native four-writer/cleanup fixture pass.
- **Reject unsuccessful compression**: pinned archive/version/tool hash, compressor failures, missing/empty/no-reduction output, Linux loader bounds and altered report/hash are checked by `build/compress.py` and 19 controlled regressions before upload/publication.
- **Compression provenance and measured size** / **Verify final compression report**: `build/compression.json`, `build/compress.py`, `build/release-assets.py` and `build/tests/archive-check.py` verify exact sizes/hashes/identity, reduction, final archive checksums and notices, and exclusion of original/probe/PHAR. All native reports/archive gate and local final archive checks pass.
- **Install a compressed executable**: `build/tests/install-check.py` performs checksum, extraction, executable permission, installation PATH and exact version checks on every native platform. `build/README.md` describes independent installation, writable temporary storage and macOS cold/warm unpack/cache behavior.

## Trace and delivery boundaries

[#37](https://github.com/mesilov/bitrix24-cli/issues/37) has milestone 0.2.0 and enhancement label; proposal links the issue and the issue tracks this change. [PR #39](https://github.com/mesilov/bitrix24-cli/pull/39) targets dev. Final follow-up changes only OpenSpec verification/task/spec/archive documentation.

No main/dev merge, version tag, public Release or live Bitrix24 portal operation was performed. Developer ID/notarization, Windows Authenticode and a browser-download/quarantine first-launch experiment were not part of this compression verification. Existing signature/distribution requirements remain intact. Wrappe's outer packer has Cargo.lock; its nested runner resolves dependencies separately. The verified source patch, scoped Cargo registry/notices and cached tool SHA256 are recorded without claiming bit-for-bit reproducibility.
