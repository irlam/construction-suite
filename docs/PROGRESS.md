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
