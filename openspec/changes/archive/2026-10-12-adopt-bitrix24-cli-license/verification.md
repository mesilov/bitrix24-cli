# Verification: adopt-bitrix24-cli-license — 2026-10-12

Issue [#47](https://github.com/mesilov/bitrix24-cli/issues/47), PR [#48](https://github.com/mesilov/bitrix24-cli/pull/48), explicit additional user request during RC preparation.

| Dimension | Result |
| --- | --- |
| Completeness | 5/5 local implementation/verification tasks; canonical license, copyright/email/trademark statement, SPDX metadata, own package notice/docs and cross-platform LF checkout policy implemented. |
| Correctness | Spec-dependent checks not applicable (skip_specs: true). Existing binary-distribution license/notices contract implemented; common packaging copies own LICENSE and pack requires own + upstream notices. |
| Coherence | Same three BSD-3-Clause redistribution/non-endorsement conditions and disclaimer as requested template; title/copyright/email adapted for the application, trademark ownership stated separately. Upstream copyrights/licenses preserved. |

## Actual checks

- New archive-notice regression first failed against original pack because BITRIX24-CLI-LICENSE.txt was absent. Final implementation **20/20 compression/package regression tests PASS**, including all platform archive forms, exact own-notice bytes, missing-license rejection and actual Git checkout under core.autocrlf=true.
- Controlled three-archive fixture: final pack/archive-check PASS for checksums, permissions, exact own/upstream license bytes. Replacing own license and recomputing a valid archive checksum still fails with Application license differs from source.
- Source identity/gate suite **12/12 PASS**; draft publication/gate suite **7/7 PASS**. Python syntax checks PASS for all four edited modules.
- Composer update --lock --no-install, composer validate --strict and composer-license-checker PASS. Composer lock and all dependency entries remain **byte-identical**; license metadata is not part of this lock hash.
- build/YIIPRESS-LICENSE.txt remains **byte-identical** to pre-change Git blob, retaining Copyright © 2016 Alexander Makarov and Yii Software non-endorsement. Other runtime/compressor notices not edited.
- Local Markdown links/anchors and strict OpenSpec validation PASS; git diff --check PASS. The earlier RC local unit/quality suite (366 tests / 2104 assertions) remains source-bound evidence; final CI is checked again after this packaging edit.

No critical issue found in local docs/assets implementation. Native packaging and exact real platform archives on the final source SHA are workflow follow-up, not inferred from controlled fixtures. Actual run/source outcomes are recorded in #47/PR #48 once complete. No portal/fork test or public release was performed.

## Native archive correction

Run [38205231729](https://github.com/mesilov/bitrix24-cli/actions/runs/38205231729), cd72092: all three native builds/acceptance passed; final archive set **failed** the exact application license check for Windows and is not counted as successful RC packaging. Downloaded macOS notice matches source and strict codesign passes. Downloaded Windows own notice: 1784 bytes versus canonical 1749, 35 CRLF; YiiPress notice: 1525 versus 1496, 29 CRLF. Both match canonical bytes exactly after CRLF→LF, confirming checkout line-ending conversion.

.gitattributes now forces LF only for LICENSE and build/YIIPRESS-LICENSE.txt. The new Git regression reproduces differing bytes without this policy and exact canonical bytes after the policy under core.autocrlf=true. Texts/copyrights remain unchanged; archive byte comparison is not weakened. Final native CI is rerun after this fix and reported separately.
