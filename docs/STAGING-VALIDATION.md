# Authenticated Programme validation

The two Programme fixtures have passed private configuration, separate database
access, protected HTTPS paths and anonymous web entry checks. Both companies and
projects are inactive, with no memberships. Full shared sign-in is still untested.
Readiness remains false. The owner reported and the next task verified preservation
of the two mistakenly extracted Alpha directories outside the document root.

## Source change

An optional private `SUITE_ROOT/private/staging-validation.json` permits named
fixture accounts to test Alpha and Beta for at most one hour. It is absent by
default. Installing source alone grants no access and changes no database rows,
credentials, inventory flags or deployment settings.

This pilot is deliberately limited to Programme instances 1 and 2, organizations
and projects 7 and 8, and the exact Alpha/Beta HTTPS origins. The organization
slugs must match the known disposable fixtures. A platform administrator or an
account assigned to any other company cannot enter validation.

`ModuleHandoff::authorize()` remains the gate for issue, exchange and every session
validation. Active company/project, current membership, supported role, enabled
tool and server authentication remain required. All ordinary unverified instances
still deny access. The validation policy does not set `ready` or advertise an
instance in normal project tool listings. Start tests through each fixture's
`/suite-login.php` URL. The Suite confirmation page identifies the validation
window and its end time.

The private directory must be canonical and mode 0700; the policy and inventory
must be canonical regular files with mode 0600. Symlinks, malformed policy,
duplicate instance entries, invalid IDs, oversized input, future/expired windows
and inventory changes deny access. The policy pins the entire reviewed inventory
by SHA256. It contains user IDs and a run ID, never passwords or shared keys.

Grants and sessions include the run ID and expiry in their audience hashes. They
cannot be reused in another window or converted to normal ready-instance access.
Token expiry is capped at the validation window. Disabling/removing the policy,
removing a user from its list, revoking membership, suspending a company or
disabling Programme stops the next validation request. The ordinary verified
instance audience format is preserved.

## Reviewed deployment sequence

1. Review the draft source and its passing checks. Install only the five changed
   runtime files into the existing Suite application, preserving `.env`, storage,
   private inventory, keys, databases and all existing deployments. No migrations
   are introduced. Confirm exact public source hashes before any fixture activation.
2. Prepare a separate concrete owner-run fixture batch. Its dry run must prove the
   original inactive state and record rollback values before it changes anything.
   The batch may activate only the two fixture companies/projects to allow owner
   account setup. Keep both verification flags false and the policy absent. This
   step is a future reviewed action, not authorized by a successful read-only check.
3. The owner creates six distinct, non-platform accounts through company account
   creation: company administrator, project manager and project viewer for each
   fixture. Enter unique passwords directly in the owner's browser. Use fixture
   email addresses and no invitations; no messages need be sent. Assign each only
   its fixture company/project. Never reuse production accounts or passwords.
4. Review actual account IDs, scoped memberships, private file protections and
   unchanged inventory. Only then create the exclusive mode-0600 policy for the
   chosen one-hour period and exact accounts. Record a fresh run ID. This runtime
   policy is deployment-owned and must not be committed or included in a package.
5. Run the matrix below on desktop and mobile. Retain sanitized results only.
   Do not copy passwords, cookies, states, authorization codes or session tokens
   into reports, screenshots, chat or Git.
6. Close the window; revoke fixture handoffs/sessions and remove its allowlist.
   Restore both fixture companies/projects to inactive and leave verification
   flags false. Keep test data isolated for review. Do not delete unrelated data.
   Restoration of the database and uploads is a separate acceptance check.

No fixture activation, user creation, runtime policy placement or authenticated
hosting test has been performed as part of this source change. The provider's
shared system-user boundary and historical broad database users remain limitations;
this pilot is not proof of adversarial filesystem isolation.

## Test matrix

| Account/action | Expected result |
| --- | --- |
| Alpha manager enters Alpha; Beta manager enters Beta | Correct company/project, separate local identity |
| Alpha account enters Beta or forges its project/record/file/export ID | Denied; no foreign data |
| Nonlisted account or platform owner enters validation | Denied |
| Viewer requests a permitted read/export | Only assigned fixture data |
| Viewer writes, uploads or imports | Denied without changing data |
| Manager writes/imports and exports fixture data | Correct fixture only; exported content checked |
| Change manager role to viewer with an existing session | Next validation refreshes role; writes denied |
| Remove membership, suspend fixture or disable Programme | Existing session and new launch denied |
| Replace/remove policy; expire window; change inventory | Pending grants and existing sessions denied |
| Set readiness after a validation grant/session was issued | Validation credentials cannot become normal credentials |
| Logout, switch account, return on mobile, replay callback | No stale identity or reusable handoff |
| Company admin creates/edits fixture projects | Company scope retained; other company denied |
| Restore a disposable fixture backup | Data/files reconciled; original production preserved |

Source tests run on disposable SQLite and MySQL fixtures. Hosted browser, import,
export, company administration, mobile and restore tests still require owner-run
staging verification; passing source tests must not be recorded as those outcomes.
