# Remaining integration deployment — 11 October 2026

Handover is disabled globally and excluded from Suite configuration and availability counts.

## Dedicated test instances

Only the existing synthetic Alpha and Beta organisations/projects (7 and 8) and six test accounts (7–12) are used. The live standalone apps are retained.

| Module | Alpha host | Beta host | Storage |
| --- | --- | --- | --- |
| QA | alpha.qa.defecttracker.uk | beta.qa.defecttracker.uk | Separate private SQLite database, evidence and sessions |
| Safety | alpha.sitesafety.site | beta.sitesafety.site | k87747_safety_alpha_suite / k87747_safety_beta_suite |
| Permits | alpha.sitepermits.site | beta.sitepermits.site | k87747_permits_alpha_suite / k87747_permits_beta_suite |
| Notices | alpha.sitenotices.site | beta.sitenotices.site | k87747_notices_alpha_suite / k87747_notices_beta_suite |

Each host uses HTTPS, private deployment binding, private session directory and fresh Suite access validation. No credentials are committed. Separate legacy database schemas prevent the existing global list/report queries from returning another company’s records. This does not assert operating-system isolation or database-account isolation when existing subscription-wide database accounts are used.

The source for the legacy front gate is under `integrations/legacy/`. It blocks local login and identity/hosting administration, limits Viewers to explicit read pages and denies writes, validates database binding before serving requests, maps new Suite identities to separate native users, and fails closed when Suite validation fails. Dedicated instances must route uploaded files through the authenticated `suite-file.php` and configure the front gate before exposing any app routes.

Deliveries retains its shared physical site calendar and reservation locks. Its separate Suite connections select an explicit local company and site. It must not use separate booking databases for companies on the same site.

## Activation order

1. Create eight named HTTPS hosts and six empty schemas above.
2. Install application code, native dependencies and private connection settings; initialise synthetic records without email notifications.
3. Verify anonymous denial, own-company reads/files, foreign-company denial, Viewer write denial, fresh role/membership changes, logout and Suite revocation for every module.
4. Enable the verified project tools and finish onboarding/mobile/recovery checks.

## Hosted progress

All eight dedicated hosts and six schemas are installed, with separate private bindings, session directories and synthetic records. The retired, unassigned `k87747_qa` MySQL database was deleted with explicit user approval to free the final database slot; the current QA SQLite app is unaffected. The read-only eight-instance storage/binding check passed.

| Module | Hosted validation | Remaining gate |
| --- | --- | --- |
| Defects | 12 authenticated isolation/revocation checks passed | Final workflow and activation acceptance |
| Programme | 22 authenticated checks passed | Final activation acceptance |
| Deliveries | 53 checks passed over the public HTTPS address, including shared capacity, private exports, Viewer denial and fresh role changes | Final activation acceptance |
| Safety | 51 checks passed over public HTTPS after explicit test-host DNS records replaced the registrar wildcard route | Final activation acceptance |
| Notices (`documents`) | 51 checks passed over public HTTPS after explicit test-host DNS records replaced the registrar wildcard route | Final activation acceptance |
| Permits | 51 checks passed over public HTTPS; both child hosts now have individual trusted certificates | Final activation acceptance |
| QA | 49 checks passed over public HTTPS; both child hosts have DNS, individual certificates and private per-app PHP runtime boundaries | Final activation acceptance |

The Safety, Notices, Deliveries and Permits validation windows were closed after these runs. Defects and Programme windows remain closed. No persistent test access or production readiness was enabled. Origin-directed tests validate TLS and application behaviour, but do not establish public DNS/proxy correctness. No email notifications were sent by test fixtures or validators.

Main Notices mail settings were preserved in the ignored, mode-0600 `includes/mail.local.php` before deploying the credential-free mail code. The hosted preflight and apply receipts both passed, with zero messages sent. `bin/remaining-notices-mail.php` performs this migration without printing settings. The Suite sign-in copy also excludes Handover.

“8/8 available” on the Suite dashboard refers to existing app availability, not completed shared sign-in. Handover remains excluded. All seven tools have hosted authentication/isolation receipts (289 checks across the seven runs). These checks establish the tested access boundaries; they do not certify every business workflow, offline queue, summary endpoint or production recovery procedure.

## Final test-company preparation

Alpha Construction Ltd (Test) / Alpha Test Site and Beta Building Services Ltd (Test) / Beta Test Site are configured with the existing administrator, manager and Viewer accounts. IDs, slugs and membership boundaries remain unchanged. The company page fits a verified 390px mobile viewport without horizontal overflow.

The platform module page now shows each deployment-owned company/site connection, including shared Deliveries company/site scope. It does not offer unrelated standalone source sites for a prepared connection. A public form cannot declare isolation or readiness.

QA required the previously authorised Plesk PHP path correction on both children. `bin/remaining-qa-runtime.php` installs a per-app `.user.ini` and an automatic runtime guard that restricts PHP to that QA application and `/tmp`; displayed errors are disabled. The initial QA validator used the wrong idempotency header, causing validation to stop before the Viewer permission check. The corrected validator uses the app's actual `Idempotency-Key` header; its 49-check rerun passed. Each failed and successful window was closed; final QA closure confirms the policy is absent and no remaining fixture sessions/grants needed revocation.

`bin/complete-test-connections.php --check` prepares activation of the exact fourteen test connections. It requires only the six existing test accounts, unchanged roles, closed validation windows, no active fixture grants/sessions, exact hosts, private keys and Deliveries company/site bindings. `--enable` is the final persistent access change and requires action-time owner acceptance. It affects only organisations/projects 7 and 8; existing customer standalone connections are retained. Summary reporting is intentionally not declared verified by this activation helper.

The hosted preflight passed with `test_connections_enabled:false`, fourteen connections, seven modules and Handover excluded. Twelve local activation checks passed, including open-window rejection, changed-role rejection, foreign-company membership rejection, active-session rejection, incorrect delivery-company rejection, private atomic publication and no change to an unrelated customer project. Company tenancy and company settings tests also passed.
