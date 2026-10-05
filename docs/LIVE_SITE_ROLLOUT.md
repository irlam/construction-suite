# Construction Suite v0.3.5 — live-site rollout checklist

## Target domains

- Hub: https://suite.defecttracker.uk (irlam/construction-suite)
- Defects: https://defectnotice.site (irlam/defect-tracker)
- Permits: https://sitepermits.site (irlam/permits)
- Safety: https://sitesafety.site (irlam/safety-tracker)
- Deliveries: https://sitedeliveries.site (irlam/site-deliveries)
- Site Notices: https://sitenotices.site (irlam/cleanup-notices)

## Important order (private Plesk actions)

1. Export the production databases and site uploads. Do not import a new schema over existing data.
2. Ensure the Hub has SUITE_INTEGRATION_KEY in its untracked private .env file.
3. On Deliveries, create the untracked httpdocs/includes/suite.local.php using
   includes/suite.local.example.php. Set CONSTRUCTION_SUITE_API_KEY to the
   same long private key. This is separate from db.php, which must not be changed.
4. On Site Notices, create the untracked httpdocs/includes/suite.local.php
   using its example and the same secret. Do not put it in tracked files.
5. Pull/Deploy the main branches for irlam/site-deliveries and
   irlam/cleanup-notices in Plesk. Site Notices must not deploy the separate
   draft private-database PR until its server-only settings are ready.
6. Pull/Deploy irlam/safety-tracker: the HIGH_PRIORITY alias fix is committed,
   preventing a future deployment from reverting the working database query.
7. Pull/Deploy irlam/construction-suite and refresh the PWA/browser cache.

## Verification

- Opening /api/suite-summary.php on each product domain in a browser
  **without** the integration header must return HTTP 401 (not 200).
- HTTP 503 with suite_integration_not_configured means the private key
  is missing. HTTP 503 with summary_unavailable means a database/API error;
  consult that application's Plesk logs.
- In the Hub, Admin > Project modules: select real Defect, Safety, Permits,
  Notice sites when known; select All data in this module for single-site
  configurations. Deliveries currently has no per-site column.
- Confirm all five tiles show Live data, with fresh counts. A displayed 0
  must be supported by the API; do not confuse a failure placeholder with 0.
- Re-run the GitHub Actions Public live-site integration audit after deployment.

## Additional security work — do not skip

The public cleanup-notices repo previously tracked live database credentials.
Rotate the password and migrate the server to a private includes/db.local.php
using draft pull request #1 only after creating the file and verifying a backup.
The Safety Tours repository also previously contained a bootstrap administrator
password. Its default has been removed; existing user records were not touched.
Rotate any previously exposed Safety login and integration credentials.

## Safety recovery

The reconstructed schema restores table structures only, NOT historical
tour or action data. To keep the audit log compatible with tour deletion,
the non-destructive database/migrations/001_audit_nullable_action.sql
in irlam/safety-tracker may be needed on the reconstructed installation.
Back up the database and review the schema before applying it.

Production Plesk deployment and authenticated queries require the private
server. GitHub checks alone cannot assert that deployment has completed.
