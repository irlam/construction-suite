# Alpha/Beta rollout completion — 11 October 2026

Handover remains excluded. The approved synthetic Alpha/Beta rollout now has ongoing Suite sign-in and verified dashboard reporting for all seven tools (fourteen company/site connections). Existing standalone customer connections were not changed.

## Verified results

- 64 hosted summary checks: own metrics, anonymous and wrong-key denial, GET-only behaviour and forged project/company denial where applicable. Both dashboards show all seven live summaries and scoped tool links. Alpha and Beta Programme totals differ (87 and 8), demonstrating separate data sets.
- QA on both hosts: prepare, disconnected edit/save, disconnected reload with recovered draft, reconnect and server save. Beta unfinished-draft sign-out warning verified; synced drafts clear. Scrollable desktop sidebar added so short windows retain access to sign-out.
- Notices on both hosts: prepared cached notices, offline draft save/reload; Beta photo survived reopening. Protected Suite and native logout preserve unfinished own drafts. Disposable device drafts removed, then online tool logout succeeded. No test notice was submitted and no notification email sent.
- Ten offline queue/logout tests passed: attachment persistence, receipt replay after lost acknowledgement, account isolation, failed PDF retrieval without resubmission, validation preservation, multi-tab lease and guarded logout. Twenty-three legacy gateway checks and Suite project-scope tests passed.
- Suite dashboard reopened disconnected, reported offline summaries rather than fabricated counts, then recovered live data after reconnecting. Suite measured 390px content at 390px viewport with no horizontal overflow.
- Hosted restore verification: fifteen database/scoped-record backups, 166 tables, 778 rows; 5,909 test-host files (56,132,329 bytes) copied to private recovery storage and SHA-256 matched, including evidence, PDFs and application assets. Temporary MySQL clones were restored and row hashes compared; SQLite backups passed integrity and row checks. Live records were unchanged by the restore drill. Temporary backups/clones were removed.

## Operating boundaries

Offline functionality follows each tool: QA supports drafts/evidence; Notices supports drafts/photos and queued work. Approvals and the online-only tools require a connection. Remote session revocation does not instantly erase disconnected browser storage; use trusted devices and sign out online.

This completes the requested Alpha/Beta test rollout verification. The drill verifies database contents and file recovery; it does not simulate a complete hosting-server outage or provision recurring offsite backups. Before real customer production use, configure encrypted offsite retention and run an environment-level restore with production credentials rotated. MySQL restored temporary tables retain column/index definitions; foreign-key deployment and webserver startup belong to that environment-level drill.

Proof screenshots are kept in ignored release/: suite-test-summaries-complete.png, suite-mobile-complete.png, qa-unsynced-signout-warning.png, notices-unsynced-signout-protected.png. No runtime keys, passwords, databases or recovery archives are committed.
