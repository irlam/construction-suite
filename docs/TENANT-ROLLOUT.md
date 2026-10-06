# Connected-tool tenancy audit and rollout

Checkpoint: 6 October 2026. The Suite's company dashboard is deployed; external apps are not yet safe for independent-company onboarding.

## Source findings

| Tool | Existing boundary | Remaining isolation work |
| --- | --- | --- |
| Defects | Project-oriented records and local roles | Company-owned project membership, file/download/report scopes, offline cache ownership and session handoff |
| Permits | Role/holder/issuer access; site labels | Company/project ownership on permits, templates, public token flows, users, uploads, approvals and exports |
| Safety | Local users, tours/actions and site labels | Company/project access for tours, actions, attachments, reports and branding |
| Deliveries | A shared calendar | Independent company/project booking stores, uploads, notifications and admin access |
| Notices | Notice forms and site labels | Company/project ownership for notice submissions, private files, PDFs, outbox receipts and cached data |
| Handover | Separate application login/database | Confirm project membership and every record/file/report path |
| Programme | Projects/tasks and local session roles | Company/project membership checks on workspace, tasks, imports, exports and cached UI |

Read-only source filters and a common integration secret are not tenant isolation.

## Deployment decision under evaluation

Separate tool databases and upload stores per company/project deployment offer a safer compatibility path than retrofitting a tenant predicate into every legacy query at once. Each instance needs an immutable company/project binding, its own database credentials, its own private files, a host-bound session and an authenticated Suite handoff. Existing installations remain legacy owner-only instances.

This is not yet a verified provisioning system. Do not mark a module tenant_isolated merely because a URL or project filter exists. Before enabling an instance, verify its authenticated identity, project binding, database isolation, upload isolation, exports, public-token routes and offline cache/outbox behavior against another independent instance.

## Immediate configuration repairs

Programme's committed database configuration and Safety's committed private configuration have been removed from current public source and preserved in private hosting configuration. Preserve existing live values server-side before deployment. Removing current files does not remove historical Git copies; credentials need owner-controlled rotation. Never print or place actual values in project documentation.

## Checkpoint discipline

Work in independently tested commits. After each deployed stage, record the source commit, tests, observed live behavior, remaining blockers and next action in PROGRESS.md. Keep private hosting identifiers and restore references in the private handover. ChatGPT allowance is not visible to the agent; checkpoints reduce the cost of interruption but cannot guarantee uninterrupted work.

## Staged handoff contract

The Suite-side foundation provides a private deployment inventory, exact HTTPS instance origins, a browser-state-bound 60-second one-use code, server-authenticated redemption and fresh company/project/module permission checks. Code hashes also bind the exact instance origin and company/project identity, so inventory changes cannot redirect an outstanding grant. Summary requests use an instance-specific key and do not follow redirects with that key.

This branch is a tested foundation, not an enabled tenant gateway. Programme staging hosts have been prepared, but private instance keys and live Suite inventory have not been configured. App adapters must still generate browser state in a Secure HttpOnly host-only cookie, check it before redemption, verify every returned identity field against their immutable deployment binding, regenerate the app session, map only approved local roles, and revalidate current Suite access on protected requests. Do not treat redemption as a permanent authorisation. Logout, revocation, file/public-token routes and offline stores need cross-instance end-to-end tests before readiness flags can be set.

The gateway migrations and session contract have now also passed disposable MySQL 8.4 tests, including four concurrent one-use redemption races and rollback after session storage failure. Live hosting configuration and two-instance end-to-end verification remain outstanding.

### Session validation protocol

Handoff redemption now returns a secret `session_token` and absolute `session_expires_at`. The app must retain this token server-side, never in URLs, HTML, browser storage, logs or exports. Before protected requests, POST `action: validate`, `instance_id` and `session_token` to `/api/v1/module-session.php` with that instance's server key. Check all returned identity/binding fields and apply the current role. Validation rechecks company/project membership and module readiness; any error or Suite outage denies protected access. There is no cached permissive fallback. POST `action: revoke` at app logout. Suite logout also revokes all connected sessions belonging to that user and cancels pending codes; other users remain signed in. Programme has a tested staged app-side adapter and deployment package. Other adapters and a complete cross-module offline-session policy remain outstanding.

## Company administration and verification checkpoint

Company branding and project tool preferences are implemented in `work/company-admin-controls`, with migration 004, repository boundary tests and real login/upload/form HTTP tests. Preferences only narrow platform access and are rechecked by both new launches and existing module sessions. See COMPANY-ADMIN.md. This source is not deployed.

Read-only GitHub-runner probes on 6 October verified both Programme staging roots returned 200, protected workspace endpoints returned 503 while unconfigured, direct app PHP access returned 403 and deployment manifests returned 404. Existing Suite/eight module entry pages responded and five protected summary endpoints rejected anonymous callers with 401. These HTTP statuses establish availability and sampled denial only; they do not prove authenticated cross-company data/file/export isolation.
