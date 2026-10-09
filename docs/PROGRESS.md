# Progress checkpoint — 6 October 2026

## Verified

- GitHub connection has admin/push access to irlam/construction-suite, defect-tracker, cleanup-notices (the former docs URL redirects), and programme.
- Plesk and Suite authenticated sessions established; session expiry can still require secure reauthentication.
- Suite live version before changes: 0.4.0, MySQL, one active project, eight registered modules, no pending migrations.
- Live Suite exposes only the public/ directory; PHP 8.4.24.
- Plesk Git last deployed commit before this change: e84c06789846fb3a81ab2c88edc51c0dc5833c1d, matching the main branch cloned for this work.
- A fresh pre-change configuration/database backup completed on 6 October 2026. Private restore identifiers are recorded separately. User files/mail excluded; existing backups remain intact.

## Implemented 0.5.0 foundation

Company management dashboard, scoped project CRUD, company account creation, scoped memberships/revocation, owner-only company-admin assignment, company-aware project switch labels, disabled legacy module access for non-platform users until adapters are verified, and no-project integration fail-closed behavior.

Live aggregate isolation now counts all registered projects, including inactive ones, so suspending another project cannot re-enable a shared all-data summary.

## Validation

Local PHP 8.3 syntax checks and bootstrap, project-scope, notifications and company-tenancy tests pass. Company tests use two independent company fixtures and cover forged company/project IDs, unauthorised role escalation, new-user password hashing, rollback of invalid cross-company account creation, revoked/inactive access, project selection and legacy tool isolation. No real company/user accounts were created or permissions expanded during testing.

## Next work

Audit source modules for tenant boundaries and introduce identity handoff in a test environment. Do not enable tenant_isolated on legacy modules before all access paths are verified. Verify application credentials only through secure sign-in flows as required. Keep this record current after each deployment.

## Deployment verification

Release commit 94fe456712f1bc029bc78589b98ca6b0fbc2f7c4 deployed through the existing automatic Plesk Git configuration. The live /company/ page renders the current company's projects and access controls. Original dashboard verified with all eight modules available and live summary values present. GitHub Suite checks, cross-product regression audit and public live-site integration audit all completed successfully.

Public project documentation excludes private hosting identifiers; private recovery notes are kept separately. No production accounts, memberships or projects were created or modified during live verification.

## Staged next phase (not deployed)

Branch `work/tenant-handoff-foundation` contains the connected-tool source audit and Suite-side isolated-instance/handoff foundation. PHP syntax and all five local regression suites pass, including new tests for foreign project access, shared/invalid origins, unverified gateways, bad server keys, mismatched browser state/audience, replay, expiry, inventory rebinding, membership revocation, inactive users and disabled modules. Existing dashboard regressions pass.

Plesk Git controls currently return 502 Bad Gateway / connection refused in the working browser, including a later recovery check. This prevents preservation of legacy live configuration and deployment verification. Configuration-loader replacement is deliberately held until the live values have been copied privately. Tested CLI migration helpers are published in Programme and Safety; server execution is not confirmed. Historical committed credentials still need owner-controlled rotation.

Next: recover Plesk access; preserve private configuration with the CLI helpers; verify the file and application before publishing replacement loaders. Then implement one app-side gateway in an isolated staging instance and prove two-instance database/file/session/offline isolation before onboarding companies. Keep the Suite-side branch unmerged until those adapters and MySQL migration are reviewed and tested.

## Hosting recovery and configuration repair

Plesk access recovered and was securely reauthenticated. Programme's live configuration was preserved outside its web root, then migrated to an ignored owner-only runtime PHP file because the host's default open_basedir excludes outside-root private directories. Its direct-request guard returns 404; no broader file-access setting was selected. Programme's loader/runtime compatibility commit 57e405b3a6bb1c4e1e92c7501fe01d1d360f180d is deployed and the live workspace again loads activities. An initial loader deployment was blocked by open_basedir and corrected during this work.

Safety configuration was independently preserved for the active installation and legacy installation, including original upload/bootstrap path binding. Protected runtime copies were installed before the active site's loader deployment. Safety release 6fbee8c01f5edd16defe3994f868af1a6df93b7c is deployed on the active site; its login page renders, and GitHub integration plus live-deployment audits pass. The legacy Git repository has no deployment target; its application was not upgraded. Historical credentials remain in Git history and require coordinated owner rotation.

## Staged revocable sessions

The isolated-instance branch now adds opaque, hashed eight-hour tool sessions, server-authenticated validation/revocation, current role/access checks on each validation, and global Suite logout revoking connected sessions plus pending handoffs. Code consumption and session creation are transactional. Local tests prove existing-session revocation, inactive companies, foreign audiences, role changes, expiry, inventory rebinding, storage-failure rollback and isolation between users during logout. These changes remain staging-only; no app adapter or instance has been enabled, and MySQL/concurrent-request verification is still required.


## 6 October 2026 — Company administration source checkpoint

The agreed product remains the central multi-company Construction Suite: platform administration → company dashboard → project dashboard → verified modules. Programme Alpha/Beta are integration fixtures for one module, not the platform itself.

Added company branding and protected raster logos, company project tool preferences that cannot widen platform permissions, and real HTTP regression coverage. Preference changes participate in module handoff/session authorization. New tables use migration 004. Production has not received this checkpoint. Connected tool/PDF branding, live instance/database setup, full eight-module isolation and recovery verification remain pending.


Company administration, MySQL gateway concurrency, cross-product regressions and sampled public HTTPS guards now pass. See MODULE-VERIFICATION.md for exact checkpoint/run references and remaining gaps. Draft PR #2 contains the staged changes; they are not merged or deployed.


## 7 October — Owner merged central administration; invitation workflow

Chris merged PR2 into main at41e71f74616783fdd8d4e89c0da4ece76956399f (6Oct23:44UTC). Source, cross-product and public checks passed on the merged commit. A merge does not establish hosting deployment or that migration004 has been applied.

Continued independently from that exact main checkpoint, adding explicit recipient-accepted company/project invitations with seven-day one-use tokens, owner-controlled roles, existing-account proof and revocation. No invitations sent and no private hosting settings changed. See COMPANY-INVITATIONS.md.

Added the viewer membership role for company or assigned-project clients. Invitations and administration forms support it; company management remains restricted. The matching Programme adapter enforces read-only requests against a fresh Suite identity. Other module adapters and hosted verification remain pending.

## 8 October — controlled authenticated staging validation source

Owner task receipts verify both Programme restricted-hosting configurations,
anonymous HTTPS entries, inactive fixture state, module role eligibility and Alpha
misplaced-directory cleanup. Actual authenticated handoff remains unverified.

Added an optional, private, named-account validation window for the exact Alpha/Beta
fixtures. All normal authorization checks remain; readiness flags stay false.
Grants/sessions are bound to the run and capped at one hour. Without the private
policy, source installation permits no validation access. See STAGING-VALIDATION.md
for deployment sequence, fixture activation review, test-account setup and rollback.

Source is staged for review; no live policy, account, company/project activation,
credential change or new production-readiness claim accompanies this checkpoint.

## 9 October — CLI inventory path and web dashboard repair

The owner reports a fatal inventory path error on the web dashboard after the
CLI checks passed. The provisioning helper writes a path from the CLI filesystem;
Plesk may expose that filesystem as a jail while web PHP sees the full hosting
path. The old loader cannot resolve the CLI alias in that web context.

The loader now recognizes only the exact alias for this application's standard
private inventory and resolves it to that same canonical application-owned file.
An unrelated missing path never falls back. Public files, ambiguous aliases,
symlinks and unsafe standard-file/directory permissions are rejected. Existing
explicit private paths still load. The validation policy checks the same resolved
file rather than comparing incompatible path strings.

Added an owner-only web diagnostic returning sanitized booleans/counts. Source
regressions reproduce the jail alias in a real authenticated web request and
verify both dashboards, owner-only diagnostics and private-file denial. Hosted
repair confirmation remains pending the owner's upload and web check. No inventory
values, flags, credentials, fixture records or test accounts are changed by repair.
