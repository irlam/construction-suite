# Company and project administration

This source checkpoint extends the existing company dashboard. It has not yet been deployed or verified against the production database.

## Roles and ownership

The platform owner creates companies, assigns company administrators, controls platform module access and owns source mappings and private instance inventory. Company administrators can manage only companies for which they hold a company-scope administrator membership. A project administrator cannot become a company administrator through a project role.

Company administrators can create and edit projects, create user accounts, assign existing company users to projects and remove their access. An account already belonging elsewhere is assigned by the platform owner; creating a person never silently adopts an existing email account. Company and project deactivation removes access on subsequent requests.

## Company branding

At `/company/`, Company & branding saves the company name, contact email, brand colour and optional PNG/JPEG logo. Names follow the 160-character database limit. Logos are limited to 256 KB and 2000 × 2000 pixels. SVG and other active formats are rejected. Logos are held in the database rather than executable upload directories.

The logo endpoint requires an active authenticated user with access to the company. Anonymous callers receive 401; other companies receive 404. Responses use the validated raster media type, no-store, nosniff and a restrictive CSP. The Suite project dashboard displays the selected company's logo and colour. Global login branding remains Suite branding because company selection happens after authentication. Connected tool branding and PDF branding require separate module-specific implementation and verification.

## Project tool choices

Company administrators select project tools at `/company/#project-tools`. Choices are stored separately from platform module configuration and can only narrow platform access. They cannot enable a platform-disabled tool, change external project references, set private keys or declare an instance verified.

A company-selected tool is still hidden from tenant users until its dedicated instance has verified isolation and gateway integration. The same preferences are used for dashboard cards, handoff authorization and validation of existing module sessions. Disabling a preference immediately causes later module-session validation to fail. This does not delete the project's data.

## Database update and rollout

Apply `004_company_settings` using the existing migration runner after a production database backup. Both MySQL and SQLite migrations create new tables; they do not copy or alter existing tenant records. Old deployments render branding defaults and show a migration notice until these tables exist.

Keep the existing private inventory readiness flags false until end-to-end hosting tests have passed. UI loading is not proof of PHP guards, database connectivity, shared login, company isolation or report/export isolation. Never expose configuration files or historical database exports to establish readiness.

## Validation

- `php tests/company-settings.php`: real repository operations, migration rerun, branding/file rejection, two-company boundaries and preservation of platform mappings.
- `python3 tests/company-http.py`: disposable server/database, actual login, multipart upload, CSRF, company/project forgery, authenticated logo access, dashboard branding and deactivation.
- `php tests/module-handoff.php`: existing opaque sessions and new launches respect company tool preferences, alongside audience, expiry, replay and revocation checks.
- Existing bootstrap, company-tenancy, project-scope and notifications regressions remain required.
- CI also runs company controls on an empty disposable MySQL 8.4 database and gateway tests with concurrent redemption, transaction rollback and fresh permission checks. Its fixture credentials are unrelated to production.

## Remaining complete-platform work

The shared project hub and administration are foundations, not certification that every module is tenant-safe. Complete module deployment, fresh permission enforcement on every operation, project-owned uploads, tenant-specific PDF/export paths, offline storage separation, notification ownership and recovery drills before admitting independent customers. Invitations/email delivery and client read-only roles are not yet implemented by this checkpoint. MySQL gateway concurrency is tested in disposable CI; production-host and complete two-instance user workflows still need verification.
