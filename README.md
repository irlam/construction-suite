# Construction Suite

A mobile-first, offline-capable construction management platform for site teams.

## Vision

Construction Suite is the central hub for a growing set of site-management tools. Existing applications remain operational while the Suite gradually provides a shared identity, project/site context, navigation, notifications, reporting and offline-sync foundation.

The long-term model is:

**Organisation → Projects / Sites → Users & Roles → Modules → Reporting / Notifications / Sync**

## Product principles

1. **Phone first.** Every field workflow must be comfortable on a site manager's phone before desktop enhancements are considered.
2. **Offline is a core feature.** Users must be able to prepare while connected, work without signal, and synchronise safely on reconnect.
3. **Never lose site data.** Offline submissions use durable local storage, retryable queues and idempotency keys.
4. **One Suite, multiple modules.** Defects, Documents, Safety, Permits, Handover, Programme and future tools should feel like one product.
5. **Shared hosting friendly.** The first production architecture targets PHP 8.x, MariaDB/MySQL, HTTPS and standard Plesk/cPanel hosting.
6. **Commercial-ready architecture.** Organisation, tenant, project and role boundaries are designed in from the start.
7. **Do not break working apps.** Existing repositories are integrated progressively rather than copied wholesale into this repository.

## Initial modules

- Defects
- Documents
- Safety
- Permits
- Handover / QA
- Programme
- System status
- Deliveries when its current source is identified

## Foundation sources

The Suite will selectively reuse proven patterns from the existing repositories:

- `irlam/defect-tracker` — durable field outbox, reconnect sync, duplicate protection, RBAC concepts and PWA field workflow.
- `irlam/docs` — prepare-for-offline workflow, IndexedDB queueing, cached documents, foreground/reconnect retries and offline browser tests.
- `irlam/permits` — cleaner reusable PHP authentication, role and database classes.
- `irlam/safety-tracker` — PWA/site safety module patterns.
- `irlam/handover`, `irlam/programme`, `irlam/status` — future module integration.

## Repository strategy

This repository is the **Suite shell and shared platform**, not a dump of the existing applications.

Existing apps remain separately deployable during the transition. The Suite will first launch them as registered modules, then progressively share:

- authentication and user identity
- organisation/project/site context
- role-based access
- common navigation and branding
- notification centre
- offline/sync status
- audit logging
- reporting APIs

## Current phase

**Phase 0 — Foundation**

See:

- [Architecture](docs/ARCHITECTURE.md)
- [Reuse map](docs/REUSE-MAP.md)
- [Offline-first standard](docs/OFFLINE-FIRST.md)
- [Roadmap](docs/ROADMAP.md)

## Status

Early foundation work. Do not deploy this repository over any existing production application yet.
