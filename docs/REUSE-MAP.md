# Existing Repository Reuse Map

This document records what should be reused conceptually or extracted from existing projects. It does **not** mean copying entire repositories into Construction Suite.

## Defect Tracker

Strong candidates to reuse/refactor:

- durable offline defect outbox
- IndexedDB-backed field queue
- reconnect/foreground sync
- Background Sync where browser support exists
- client submission IDs / idempotency
- duplicate-submission protection
- sync conflict concepts
- service-worker/PWA field workflow
- role/permission concepts
- project selector concepts
- offline regression tests

Important improvement before reuse:
- centralise auth/permission checks rather than allowing page-by-page variations
- keep private uploads outside the public document root
- reduce historical/development clutter in deployable production code

## Docs

Strong candidates:

- explicit **Prepare for offline use** workflow
- caching recent records and associated files
- offline drafts
- local PDF capability where appropriate
- queued close-out/action requests
- owner/account isolation for local IndexedDB data
- retry on reconnect, app foreground and periodic foreground interval
- browser-level offline tests

This is currently the clearest reference implementation for the user experience we want across the Suite.

## Permits

Strong candidates:

- `src/Auth.php`
- `src/Roles.php`
- `src/Db.php`
- PDO database abstraction
- MySQL/SQLite support
- production health-check concepts
- login rate-limit/security migration patterns

Use these as design references for shared Suite services rather than duplicating old procedural authentication.

## Safety Tracker

Strong candidates:

- safety workflow concepts
- existing PWA registration/components
- module-specific dashboard/content

Offline behavior should eventually be upgraded to the shared Suite offline standard rather than independently evolved.

## Handover

Treat as a future business module. Keep domain workflow separate from Suite shell code.

## Programme

Treat as a future business module. Existing application appears capable of remaining separately deployed while the Suite initially links to it.

## Status

Useful concept for:
- module availability
- lightweight health endpoints
- displaying service health in the Suite

Do not couple the Suite dashboard directly to the current implementation until its authentication/exposure model is reviewed.

## Deliveries

The known live Deliveries product should be included as a Suite module, but a repository named `deliveries` was not found in the currently accessible GitHub repository list during the initial review. Identify its source repository before code-level integration.

## Rule

When two apps solve the same platform problem differently, Construction Suite gets **one shared implementation**.

Platform concerns include:

- authentication
- tenancy
- roles/permissions
- navigation
- PWA install
- IndexedDB helpers
- sync/outbox protocol
- notifications
- audit logging
- private file delivery
- health/status contracts
