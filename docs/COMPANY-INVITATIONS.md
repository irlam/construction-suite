# Company staff invitations

Company administrators can create a one-use invitation link from Company dashboard → Invitations. They select the recipient email, company/project scope and permitted role. The recipient chooses a password for a new account, or proves ownership of an existing Suite account before explicitly accepting the additional company/project membership. Existing memberships are preserved. No email service is invoked; the administrator copies the link and shares it themselves.

## Boundaries

- Seven-day expiry and a cryptographically random 256-bit code; only its SHA-256 hash is stored in the database.
- Company/project/role/email are fixed in the stored invitation. Recipient form fields cannot change them.
- Project-only invitations never leave an all-company bootstrap membership behind.
- Only the platform owner can invite company administrators. Company administrators cannot grant platform administration or company administration.
- Company admins can list/revoke only their company invitations. No token/hash is returned in pending lists.
- The inviter's current authority, company status and project status are checked again at acceptance. Inactive accounts and platform-admin accounts cannot be adopted through an invitation.
- Existing email accounts are never silently attached. An active session for the addressed account or its real password is required. Password sign-in uses the existing login throttling.
- Atomic consumption, account creation and membership assignment use one database transaction. Failed acceptance rolls back consumption and writes; simultaneous acceptance must yield one winner.

## Privacy

Generated links use `/invite.php#token=…`. Browser fragments are not sent in request URLs. The invitation script clears the fragment from history before posting it in a CSRF-protected form. Invitation pages use no-store and no-referrer. The company dashboard shows the new link once, in an owner-bound session flash lasting at most ten minutes; logout clears it. Actual invitation codes must never be put into Git, audit details, notes or logs. Treat a copied link as a credential intended for its recipient.

## Deployment and validation

Apply migration 005 after backup through the Suite migration runner. Until it exists the company dashboard shows a migration notice. This branch is source work, not a verified hosting deployment.

`tests/company-invitations.php` runs with disposable SQLite and MySQL fixtures: cross-company/project/role rejection, account ownership, exact expiry, one-use consumption, preserved existing memberships, project-only scope, revocation and disabled inviter/company/project. On MySQL it also runs three races between separate processes; each must create exactly one account and grant.

`tests/company-http.py` exercises actual admin creation, one-time link display, CSRF, preview, new account acceptance, forged role/email/project fields, replay denial, rejected existing-account password and successful acceptance with the existing password. No real invitations are sent by these tests.

The invitation UI/JS still requires a real mobile/desktop browser check after hosting deployment. Tenant module readiness is unchanged. Read-only client roles, automatic email delivery and full module isolation remain separate work.
