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
