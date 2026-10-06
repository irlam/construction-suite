# Construction Suite company/project brief

Chris Irlam owns a master dashboard managing all independent companies and their projects. Each company has administrators who manage only that company's projects and people; project roles remain limited to their assigned projects.

Authorised scope: relevant GitHub repositories, Plesk hosting, domain setup, backups, deployment and testing. Use connected accounts and authorised sessions. Never commit credentials. Preserve live data and working tools.

## Delivered foundation (0.5.0)

- `/company/` company dashboards and scoped project creation/editing.
- Company admin role is an organization-wide `company_admin` membership; legacy organization-wide `admin` also grants company management. A project-scoped `admin` never does.
- Company admins can create new company accounts, assign project roles and revoke scoped memberships. Global account editing/deactivation remains platform-only because accounts can belong to several companies.
- Only the platform owner assigns or changes company administrators. Company admins cannot change their own access, adopt existing accounts from other companies or grant platform authority.
- Company/project ownership is checked server-side for every dashboard operation. Existing schema is reused; no migration needed.
- Connected legacy tools remain available to the platform owner. Other users do not inherit tools until a module's actual company isolation is implemented, tested and declared `tenant_isolated` in code. This flag is not an administrative checkbox.
- Users with no assigned project cannot inherit integrations or live summaries.

## Remaining work before independent-company rollout

1. Audit and upgrade each module's records, API, uploads, reports and offline caches for immutable company/project ownership.
2. Implement authenticated identity handoff and membership enforcement in each module. A URL filter is not an access boundary.
3. Upgrade Deliveries from a shared calendar to company/project-owned bookings.
4. Apply company branding to module screens and generated reports.
5. Add account invitation/password setup workflows, with delivery only when authorised.
6. Test complete multi-company workflows and migration/rollback on an isolated environment before onboarding real independent companies.

Do not advertise the connected toolset as fully isolated or production-ready for independent companies yet.
