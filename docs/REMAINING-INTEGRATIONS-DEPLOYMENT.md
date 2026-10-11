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
| Safety | 51 checks passed using the exact origin with certificate verification enabled | Public DNS/proxy route verification |
| Notices (`documents`) | 51 checks passed using the exact origin with certificate verification enabled | Public DNS/proxy route verification |
| Permits | Storage/binding checks and local Viewer scope checks passed; HTTPS validation stopped before authentication | Certificate only covers the parent domain; issue certificates covering both child hosts |
| QA | Separate hosted SQLite/evidence bindings installed; nine local company/membership checks passed | Both child hosts have no public DNS records and need valid certificates before hosted authentication tests |

The Safety, Notices, Deliveries and Permits validation windows were closed after these runs. Defects and Programme windows remain closed. No persistent test access or production readiness was enabled. Origin-directed tests validate TLS and application behaviour, but do not establish public DNS/proxy correctness. No email notifications were sent by test fixtures or validators.

Main Notices mail settings were preserved in the ignored, mode-0600 `includes/mail.local.php` before deploying the credential-free mail code. The hosted preflight and apply receipts both passed, with zero messages sent. `bin/remaining-notices-mail.php` performs this migration without printing settings. The Suite sign-in copy also excludes Handover.

“8/8 available” on the Suite dashboard refers to existing app availability, not completed shared sign-in. Handover remains excluded. Public routing/certificate fixes, QA/Permits hosted checks and final onboarding/mobile/recovery acceptance are still required before claiming the rollout complete.
