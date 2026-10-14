# Status contract check — 14.10.2026

Effective config and real official MCP search/details succeeded for REST1 history and REST3 get. Sources: https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-history-list.html and https://apidocs.bitrix24.ru/api-reference/tasks/tasks-task-get-rest-v3.html.

Disposable SDK fixture: acting user was creator and responsible; no control/result requirement. SDK readbacks confirmed pending → in_progress → completed → pending → in_progress → completed → pending → completed. Cleanup succeeded. Sanitized status-contract-fixture.json retains event IDs/timestamps/offsets, without user/portal/credentials.

- Observed field: STATUS only, numeric strings 2/3/5 corresponded to pending/in_progress/completed in REST3 readbacks. REAL_STATUS was absent: equivalence is unverified; STATUS alone cannot prove canonical exact totals.
- Equal timestamps occurred on completion/renew; event ID order is not a documented chronology guarantee.
- NEW from current creation and older NEW/DELETE from February appeared together. Multiple creation boundaries are ambiguous; do not pick the newest/oldest silently.
- next=null and total=null: source completeness remains history-navigation-unverified. A small consistent sequence does not prove absence of hidden cycles.
- No live exact-total acceptance. Offline arithmetic and partial CLI acceptance are separate checks.
