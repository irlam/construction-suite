# Architecture

## 1. Purpose

Construction Suite is the shared platform around the existing construction applications. The first release is a hub/PWA that knows the signed-in user, organisation, selected project/site and available modules.

Existing production apps are not merged into this repository during the first phase.

## 2. Initial technical direction

The first production target remains compatible with normal Plesk/cPanel hosting:

- PHP 8.2+
- MariaDB/MySQL in production
- PDO for database access
- SQLite allowed for local/tests where useful
- server-rendered PHP for resilient navigation
- vanilla JavaScript modules for interactive/offline features
- Progressive Web App shell
- IndexedDB for local durable data
- service worker for application-shell caching
- HTTPS required in production

Avoid requiring a Node server, container platform or cloud-specific service for the core product.

## 3. Domain model

Every business record should ultimately resolve through these boundaries:

```text
Organisation
  └── Project / Site
       ├── Users / Memberships
       ├── Modules
       ├── Records
       ├── Files
       ├── Notifications
       └── Audit Events
```

A user may belong to more than one organisation or project. Permissions are granted through memberships/roles, not inferred only from UI visibility.

## 4. Suite shell

The hub owns:

- sign-in/session
- organisation selection
- project/site selection
- module registry
- dashboard summaries
- global navigation
- notification centre
- connectivity/sync indicator
- account/profile
- administration
- audit trail

A module registration should include at least:

```text
key
name
description
icon
launch_url
health_url (optional)
required_permission
offline_capability
enabled
sort_order
```

During transition, `launch_url` may point to an existing application domain. Later, modules can use Suite APIs and shared authentication.

## 5. Authentication direction

Do not immediately replace working application logins.

Phase 1:
- Suite has its own secure login/session.
- Existing modules remain independently authenticated where necessary.

Phase 2:
- introduce signed hand-off / shared identity so a Suite session can launch trusted modules without another password.

Phase 3:
- central identity and membership model becomes authoritative.

Authentication requirements:
- secure, HttpOnly, SameSite cookies
- HTTPS-only cookies in production
- password_hash/password_verify
- CSRF protection on state-changing browser requests
- server-side permission checks on every protected endpoint
- login throttling
- session expiry and rotation
- audit events for privileged actions

## 6. Multi-tenancy

Commercial-readiness requires tenant boundaries from the start.

Every tenant-owned record should resolve to an organisation ID and, when relevant, project ID.

Never rely on a URL parameter alone to establish tenancy. Resolve access through the signed-in user's memberships.

## 7. Files and uploads

Private project files should not be directly public-addressable.

Preferred production pattern:

```text
private storage outside document root
        ↓
authorised PHP download/stream endpoint
        ↓
permission + tenant/project checks
```

The database stores metadata and ownership; files remain in controlled storage.

## 8. API direction

Use versioned JSON endpoints for new shared services:

```text
/api/v1/me
/api/v1/projects
/api/v1/modules
/api/v1/dashboard
/api/v1/sync/...
```

Responses should have predictable error codes so offline clients can distinguish:

- retryable network/server failure
- authentication required
- validation failure
- conflict requiring attention
- duplicate/already accepted submission

## 9. Deployment

Initial target:

```text
GitHub
  ↓
Plesk Git deployment
  ↓
public document root
  + private config/storage outside web root
  ↓
MariaDB/MySQL
```

Production secrets must never be committed. Use environment/private host configuration.

## 10. First application structure

Expected direction once implementation begins:

```text
/
├── app/
│   ├── Auth/
│   ├── Database/
│   ├── Modules/
│   ├── Projects/
│   ├── Tenancy/
│   └── Support/
├── public/
│   ├── assets/
│   ├── api/
│   ├── index.php
│   ├── login.php
│   ├── manifest.webmanifest
│   ├── service-worker.js
│   └── offline.html
├── storage/
│   └── .gitkeep
├── database/
│   └── migrations/
├── tests/
├── docs/
├── .env.example
└── README.md
```

The exact structure may evolve after the first working hub, but tenant, auth and offline boundaries should remain explicit.
