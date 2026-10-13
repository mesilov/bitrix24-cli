# Verification: upload-local-task-files

Дата: 13.10.2026. Implementation source: `846fdfce6779afe823ceb80edad722bc6198e8ff`. Subsequent report/check-label updates do not change production PHP code.

## Completeness, correctness and coherence

| Dimension | Evidence |
|---|---|
| Completeness | 13 implementation/verification tasks; 8 new requirements + 3 modified requirements, 33 total scenarios including retained scenarios |
| Correctness | Mapping below; offline unit scenarios and real portal upload/attach/readback/collision/partial recovery; restricted-role acceptance unavailable |
| Coherence | Symfony Filesystem/Path and actual SDK Base64Encoder reuse; bounded local source and explicit Core transport adaptation recorded in design reuse table; existing 30-command catalog/--file-id preserved |

## Requirement mapping

| Requirement | Implementation | Checks |
|---|---|---|
| Explicit local upload attachment mode | AttachTaskFilesCommand, TaskInputMapper, AttachTaskFilesRequest | conflicting/missing modes/IDs before credentials; existing command contracts and real existing-ID attach |
| Bounded local binary file input | LocalFileSource::inspect/encode | 0/10 MiB/limit+1, binary NUL/0xff, special paths, unreadable/deleted/grown source, symlink; live binary file SIZE/PARENT_ID readback |
| Shared filesystem abstraction and SDK reuse | Filesystem/Path DI + SDK Base64Encoder::encodeString; Rest1DiskUploadAdapter | design source/reuse matrix, exact binary round-trip, lockfile unchanged packages, production PHAR offline preview |
| Explicit upload filename and collision behavior | local filename validation; exact NAME/fileContent[0]; generateUniqueName=true | invalid/255/256 Unicode names and controls; real portal collision returns distinct Disk IDs/NAME, first file remains |
| Safe preview of local attachment | PreparedOperation with safe descriptors and dynamic attach dependency | JSON/plain/human preview without API/credential reads, copied PHAR Unicode local file preview |
| Method specific upload and attachment confirmation | upload REST1 → attach REST3 with result.ID | exact payload/version contract; rejects malformed/overflow/FILE_ID, strips raw file/secret URLs; two confirmed live steps |
| Recoverable partial attachment and cancellation | upload/attach ledger, known ID, no automatic retry/delete | upload denial/unknown, attach denied/unknown, cancellation before/between/awaiting writes; real process SIGINT; live denied attach + explicit ID recovery |
| Safe output and bounded proof claims | explicit normalized upload/attach rows, existing presenter/envelope | secret/content non-disclosure JSON/plain/human/verbose; independent Disk readback separated from task ACK |
| Policy covers the complete route plan | request-dependent routes + scoped CommandCatalog entry | strict-rest3 rejects local mode before credentials/API (also preview); existing-ID mode remains REST3-only |
| API admission and future lifecycle acceptance remain explicit | same 30 commands, only local Disk upload route admitted | catalog regression; no generic Disk methods, discovery/default-folder or lifecycle mutation |
| Explicit file and structured input | local binary input distinct from text/JSON stdin | `--file -`/URL/wrappers rejected; existing text/JSON tests retained |

## Local receipt

- Docker PHP 8.4.26: `make unit` — **404 tests, 2329 assertions, passed**; contains real SIGINT handling. No portal used.
- `make lint-all` — license checker, PHP-CS-Fixer, PHPStan, Rector **passed** on the implementation commit.
- `make composer-validate` — passed; only root symfony/filesystem require and composer.lock content hash changed, SDK/package versions unchanged.
- `openspec validate upload-local-task-files --strict`, `git diff --check` — passed.
- `make package-phar` — passed; version `dev-846fdfce6779afe823ceb80edad722bc6198e8ff`.
- Copied PHAR smoke in isolated Docker runtime, without source/vendor/credentials and network access — passed, including Unicode local-file preview and strict-rest3 rejection. PHP/SDK/Filesystem autoload confirmed. This is PHAR/Linux evidence, not native macOS/Windows binary acceptance.

## Portal receipt

`B24CLI_ENV_FILE=<read-only existing credential file> make integration TEST_ARGS='--group command:task:file:attach'` on source SHA above: **6 cases, 72 assertions: 5 passed, 1 skipped**. Acting user ID **1**; exact role was not inferred from that ID. Production bootstrap and current profile resolution used; webhook/secret URLs/content not recorded.

| Case | Owned task IDs | Owned Disk file IDs | Owned child folder |
|---|---|---|---|
| Existing-ID attach | 5984 | 1494 | none |
| Binary Unicode local upload + attach + Disk metadata | 5990 | 1502 | 1500 |
| Collision with distinct file IDs/NAME | 5996 | 1510, 1516 | 1508 |
| Deleted task denies attach after successful upload; recovery to valid task | 6002, 6004 | 1522 | 1520 |
| Deleted folder stops upload before attach | 6014 | none | deleted before invocation |

All recorded task/file/folder cleanupFailures arrays are empty. Setup, readback and cleanup use SDK transport and only unique owned fixtures. Restricted-role test explicitly skipped: `B24CLI_TEST_RESTRICTED_WEBHOOK` not configured. Skip is not portal acceptance. The positive and denied-missing-task paths above do not prove a non-admin/insufficient-scope matrix.

## Findings and remaining evidence boundaries

- **O1 (WARNING):** restricted-role Disk upload denial remains unverified without the separate restricted webhook; dedicated integration case exists and explicitly skips when absent. This is an allowed fixture limitation in task 4.2, not a completed denied-role proof.
- Native macOS/Windows/Linux standalone binaries and remote CI are not established by the local PHAR receipt; the expanded packaging smoke checks them when native CI runs.
- Task attachment ACK is confirmed by the API method; independent membership readback is not claimed. Disk NAME/SIZE/PARENT_ID readback proves Disk persistence only.
- No critical implementation/spec mismatch found in checks that ran. Restricted-role/native evidence boundaries above remain explicit; release/publication/merge are separate.

## Revision receipt: 500 MB default and config priority — 13.10.2026

Production source: `352432ed037c624e70a60c8d359fa5636906446e`, synchronized with origin/dev `1a2ef638571d562a5006e5fdaf31a0b2a30cbb21`. Subsequent documentation fixes do not change production PHP. Earlier 10 MiB receipts above describe the earlier implementation and remain historical evidence.

| Dimension | Result |
|---|---|
| Completeness | 16 tasks tracked; 10 ADDED + 3 MODIFIED requirements, 42 scenarios including retained role/tag scenarios from dev; no removals/renames |
| Correctness | All 13 requirements mapped below/above; config boundaries, full-file transport, memory/framing/hash, read failures and portal stream exercised. Restricted-role matrix not verified: separate webhook absent |
| Coherence | Symfony Filesystem/Path + locked SDK Base64Encoder reused; large stream decorator remains inside SDK Core and existing ApiTransport, unchanged REST methods/fields/accounting. Code pattern consistency checked against diff origin/dev...HEAD: ports/immutable requests/provider DI/ledger/presenters match existing code |

Additional requirement mapping:

| Requirement | Implementation | Scenario evidence |
|---|---|---|
| Configured upload limit overrides the default | CommandRunner context → TaskInputMapper → immutable LocalFile.maxUploadBytes → LocalFileSource; preview metadata only | UploadLimitConfigurationTest accepts default/explicit below/above/default, strict invalid types/values, sparse exact/limit+1; no credentials/API on rejected inputs. PHAR/native smoke local-upload-config-limit |
| Large uploads preserve bounded memory and SDK transport | LocalFileSource 60000-byte SDK-encoded chunks, StreamingBase64, StreamingJsonHttpClient injected into SDK Core | large-upload-worker sends 500000000 and configured 500000001 bytes via production bootstrap/SDK and Symfony MockHttpClient at memory_limit=128M; hash/framing/Content-Length verified; cancellation/change abort chunks, source failure during send gives unknown/skipped and no attach/retry |

Retained role/tag policy scenarios were checked by the full regression after the dev merge; the route/spec deltas retain those admissions. Existing ID-only attachment and 34 task commands remain.

Local receipt:

- `make unit`: **516 tests, 3921 assertions**, passed after dev synchronization.
- `make lint-all`, Composer validate, strict OpenSpec validate, `git diff --check`: passed.
- `python build/tests/config-schema-check.py` with pinned jsonschema 4.25.1: **108 shared fixtures, 1 profile JSON example, 5 documented extra runtime checks**, passed. Runtime --config example is an executable shell heredoc; profile config.schema.json does not describe runtime settings.
- Full **500000000**-byte local input: wire JSON **666666759** bytes, decoded SHA256 matched source, **12582912 bytes (12 MiB)** peak memory, PHP memory_limit **128M**, exactly upload + attach. Separate **500000001**-byte input succeeds when runtime maxUploadBytes equals its size. These are local SDK/MockHttpClient transport receipts, not live 500 MB portal acceptance.
- `make package-phar`: passed for version `dev-352432ed037c624e70a60c8d359fa5636906446e`; copied artifact smoke passed without checkout/vendor/credentials/network, including local-upload-dry-run, local-upload-strict-policy, local-upload-config-limit, 34-task-commands.

Portal receipt after dev synchronization:

- `make integration TEST_ARGS='--group command:task:file:attach'`: **7 cases, 86 assertions — 6 passed, 1 skipped**. Acting user ID 1; no role inference. Real upload path includes a **10485762-byte** file, so it uses the streaming branch; Disk SIZE/PARENT_ID/NAME and attach ACK confirmed. Other cases cover existing-ID, binary/Unicode, collision, denied missing task with explicit recovery, deleted folder. All cleanupFailures arrays empty for owned fixtures.
- Restricted-role webhook unavailable; denied-role test remains explicitly skipped. Task membership readback and live 500 MB server quota/timeout acceptance remain unverified. No merge or release claim.

Native CI receipt: Head `d57738c46edbfca79cb26a1e64d78d1101cbc589`: **17/17 SUCCESS**. [Native package workflow](https://github.com/mesilov/bitrix24-cli/actions/runs/38391213068) passed Linux amd64, macOS arm64, Windows amd64 and final archive set. Each platform logs two passing smoke receipts (original/compressed) with exact `dev-d57738c...` version and local-upload-dry-run/local-upload-strict-policy/local-upload-config-limit. This is native CI artifact acceptance; no merge/release/publication proof.

Findings: O1 warning (restricted-role matrix) remains as above. No critical implementation/requirement mismatch found; restricted-role and independent membership/live 500 MB checks are explicitly bounded evidence rather than passing claims. Archive/worktree cleanup remains a follow-up after implementation merge and issue closure.

## Final dev synchronization receipt — 13.10.2026

Merged origin/dev `fdb9148` (users namespace + role-filter archive) into source `43d8ff60140fa4b28bcd17efedf89e38dc42c45a`. Resolved changelog by retaining both features; adapted owned upload tracking to LiveCommandTestCase.afterInvoke while keeping owned folder/file cleanup. The policy delta retains role/users/observer admissions from dev and adds only the scoped local Disk route. Current change: 13 requirements, **44 scenarios**, 16/16 tasks; 34 task commands preserved alongside the new users commands.

- `make unit`: **585 tests, 4301 assertions**, passed; `make lint-all`, strict OpenSpec/diff checks passed.
- Configuration schema: **125 fixtures, 2 profile JSON examples, 6 documented runtime differences**, passed with jsonschema 4.25.1. Runtime maxUploadBytes remains separate from the profile schema.
- PHAR `dev-43d8ff...` and copied-artifact smoke passed, including local-upload-config-limit and the 34-task catalogue.
- Full 500000000-byte source via production SDK/Symfony MockHttpClient: **666666759 wire bytes**, SHA256 match, PHP limit128M, **14680064-byte peak (14 MiB)** with the expanded bootstrap, exactly 2 calls. Earlier 12 MiB proof applies to the prior bootstrap.
- First portal rerun after merge: **7 cases/79 assertions, 2 failures, 1 skip**, both failures in the streamed fixture (transport-error/outcomeUnknown and missing happy-path confirmation). Its owned task6098/folder1632 were cleaned; no confirmed Disk ID/attach, no automatic retry. This failure receipt is retained.
- Separate rerun with a fresh fixture and explicit stream-test timeout120: **7 cases/86 assertions, 6 passed + 1 restricted-role skip**; full10485762-byte SIZE/PARENT_ID/NAME readback and attach ACK succeeded. Other cases passed; all owned cleanupFailures arrays empty. CLI default timeout remains unchanged.
- Native receipt for d57738c above is historical evidence before this dev merge. Required CI on the final merged PR head is checked separately before reporting readiness; this receipt does not replace it. No merge/release or live500MB claim.
