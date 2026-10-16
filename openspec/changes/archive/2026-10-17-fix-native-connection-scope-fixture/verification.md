# Verification: fix-native-connection-scope-fixture

| Dimension | Result |
|---|---|
| Completeness | 3/3 tasks complete |
| Correctness | Not applicable: skip_specs=true, no product requirement changes |
| Coherence | Readonly bounded MockHttpClient fixture; exact source-specific cold/warm assertions; product src/ unchanged |

Critical: 0. Warnings: 0. Suggestions: 0. No applicable check skipped. Local implementation is ready for MR; archive follows confirmed merge.

## Evidence

- Original native run [38945682487](https://github.com/mesilov/bitrix24-cli/actions/runs/38945682487) failed macOS/Windows/Linux connection acceptance with scope → tasks.task.list and the old single-request mock. All platform jobs are terminal; archive job skipped.
- Full `build/tests/connection-smoke.py --phar --php /usr/local/bin/php` PASS in isolated PHP 8.4.26 container, using locked production dependencies. Proves profile/default selection, fresh and cached scope per explicit-file/environment-file/process-env identity, managed modes, concurrent writers, no secret output and no binary mutation. PHAR mode does not execute native shell pipelines or Windows ACL checks; those remain mandatory in the repeated native build.
- PHP probe syntax, Python script execution/compilation, diff check and strict OpenSpec: PASS.
- Probe accepts only readonly POST scope REST1 followed by tasks.task.list REST3, or list alone for warm cache. Host, synthetic credential path and bounded order remain mandatory. Python requires fresh scope and exact apiCalls version/effect/method, preventing an unknown scope snapshot from passing unnoticed.
- src/, dependency locks and product specs unchanged. CHANGELOG and #174 link the change. The original builds/standalone live acceptance continues after this delivery; this report does not claim that future run passed.
