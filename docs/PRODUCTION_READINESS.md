# Construction Suite v0.4.0 — production-readiness matrix

This is a truthful release gate. A green GitHub Action is necessary but does
**not** prove the live booking, safety or defect data can be edited or recovered.

## Module integration

| App | GitHub source | Suite feature | Status / restriction |
| --- | --- | --- | --- |
| Defects | irlam/defect-tracker | Live KPI, authenticated read-only API, real project discovery, active-record link | Project-scoped when mapped |
| Permits | irlam/permits | Live KPI, site discovery, mapped approval link | Source-level authorisation remains independent |
| Safety Tours | irlam/safety-tracker | Live action count, real site discovery, exact-site action link | Reconstructed DB still needs record create/edit/delete recovery test |
| Site Deliveries | irlam/site-deliveries | Today, arrivals, completions and no-shows, gateboard link | **One shared calendar, not project-aware**; all-data KPI disabled when Suite contains >1 active project |
| Site Notices | irlam/cleanup-notices | Live open counts, source site discovery and exact-site list link | Tracked secret and historical documents require privacy migration |
| Handover | irlam/handover | Launch + reachability | No shared Suite reporting/identity |
| Programme | irlam/programme | Launch + reachability | No shared Suite reporting/identity |
| Status | irlam/status | Launch + reachability | Availability does not establish workflow correctness |

**Scope guard:** once the Suite has more than one active project anywhere
(including separate organisations), unfiltered third-party summaries and
single-calendar Deliveries are withheld. Platform administrators must map
each project to an actual site/reference; duplicate source mappings across
projects are rejected. This protects KPI aggregation, but independent
applications still need their own authorisation audit.

## Automated checks

- Construction Suite checks: PHP syntax, bootstrap smoke, scoped links,
  notification isolation and idempotent acknowledgements
- Cross-product regression audit: every night and on Suite changes, tests
  Deliveries fixture counts, Safety Suite contracts, Notices offline queue,
  Defects offline queue/training/branding, and Permit entry point syntax
- Public live-site audit: HTTPS responses and rejection of anonymous API
  calls; **does not send a secret or verify authenticated data counts**
- Read-only PHP CLI preflight: `php bin/production-audit.php` from the
  Suite repository on the real Plesk server (does not print secrets)

## Plesk Git deployment (repeatable without storing credentials in Git)

1. Keep each private file once on Plesk, **ignored** by Git:
   - Suite: `httpdocs/.env`
   - Deliveries: `httpdocs/db.php`, `httpdocs/includes/suite.local.php`
   - Safety: `httpdocs/includes/config.php`
   - Defects / Permits: their private `.env` settings
   - Notices: `httpdocs/includes/suite.local.php` and, after migration,
     `httpdocs/includes/db.local.php`.
2. Use **Plesk → Git → Pull now → Deploy now** for changed repositories.
   Ordinary pulls are not a credential-reset process. The private files
   remain server-side and must not be cleaned or overwritten by a deployment
   action (particularly rsync with `--delete`).
3. If moving to a *new* host, supply credentials once through Plesk settings
   or a protected transfer. No Git source repo alone can reconstruct a
   password safely.
4. Verify a real authenticated dashboard refresh after deployment. Do not
   treat a HTTP 401 from an anonymous probe as proof of a working KPI.
5. Run the CLI production audit after a deploy; review any mapping warnings.

## REQUIRED privacy and credential remediation

These repositories are currently public and have historically tracked
credentials or site records, including generated PDFs/photos/signatures.
Make `irlam/cleanup-notices` and `irlam/safety-tracker` private **now**
using GitHub Repository Settings. Repo privacy does not revoke already
copied secrets or historic data.

- **Notices credentials:** Main includes a CLI-only
  `bin/prepare-private-db.php` helper. Deploy it and run it ONCE through
  Plesk PHP CLI; it copies existing configuration privately without
  retyping or printing keys. Then back up the database, review
  `irlam/cleanup-notices` draft PR #1, merge the private-config change,
  test the website and rotate the exposed database password immediately.
- **Historical attachments:** First export Plesk uploads/PDFs and validate
  recovery. Only then untrack old notice/safety files in Git and plan a
  careful history rewrite. Never delete production evidence to tidy Git.
- **Safety bootstrap login:** the known default has been removed from current
  source, but its history requires password rotation.
- Rotate any shared key or SMTP/database passwords shown in old screenshots,
  files or commits. Never store real values in issues or CI logs.

## Backups — explicit release gate

Configure **Plesk Backup Manager** for daily database + web file backups,
off-host storage, encryption, restricted access, and a retention policy.
Perform a nonproduction restore of each module and of the Suite. Check
that generated PDFs, signatures and uploads can be recovered as well as
tables. The Git repository is **not** the backup of any production database.

There is no connected Plesk backup administration in this conversation, so
backup scheduling, a restore drill and live credential rotation cannot be
claimed completed.

## Manual acceptance matrix before external customer production use

Test on an isolated test project/site (never modify real records just to
run acceptance checks):

- Login, role limits and audit trail for worker, manager, and admin
- Creating/editing/closing and re-opening each applicable record type
- Correct multi-project and multi-organisation isolation, including direct
  links and API filters
- Email notification delivery, suppression and failures
- Mobile Chrome/Safari navigation, PWA installation and offline cache
- Office Wi-Fi → offline capture → reconnect → automatic queue drain with
  attachments, conflict handling and duplicate submission replay
- Full database/file restore with retention and offsite backup verification
- Attempt unauthenticated and wrongly keyed API access; ensure no data leaks

## Honest release scope

v0.4.0 improves the Hub, scope checks, notified-item handling and source
links. It is **not** single-sign-on or a universal cross-domain offline
queue. Module authentication and offline queues are still individual.
Commercial multi-tenant signoff requires completing the privacy migration,
backups, acceptance tests, and site-aware Deliveries support.
