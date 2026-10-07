# Module verification — 7 October 2026

The product is the central multi-company Construction Suite. Existing modules are preserved and integrated behind company/project administration. Passing a regression or returning HTTP 200 is not certification that an application is ready for independent companies.

## Tested checkpoints

Company controls, protected branding and project tool choices were merged through PR #2. Staff invitations and viewer memberships are staged in PR #3. Gateway SQL and real concurrent code exchanges are exercised on disposable MySQL 8.4. Programme adapter checks use the immutable Programme source commit `96c1daf81b22e127a5e3869238cea5ed426f8cc8`.

| Module | Source checks in cross-product CI | Public check | Remaining tenant integration |
| --- | --- | --- | --- |
| Defects | Offline field, training and branding regressions | Entry 200; summary anonymous 401 | Shared identity, company-owned project/file/report/offline authorization |
| Deliveries | Disposable summary/reference API contracts | Entry 200; summary anonymous 401 | Split shared calendar into project-bound stores and enforce booking/admin/notification ownership |
| Safety | Recovery/bootstrap source contracts and endpoint syntax | Entry 200; summary anonymous 401 | Shared identity; company/project ownership on tours, actions, files and PDF branding |
| Permits | Approval and summary endpoint syntax | Entry 200; summary anonymous 401 | Project ownership on permits/templates/approval/public tokens, uploads and reports; complete workflow regressions |
| Notices | API authentication checks and durable offline sync regression | Entry 200; summary anonymous 401 | Company/project binding for notice records/files/PDFs and browser outbox/cache ownership |
| Handover | Application/config/template/script PHP syntax | Entry 200 | Shared identity and verification of project, file, portal, certificate and export access |
| Programme | Gateway identity, mapping, server sessions, HTTP gate, sanitized package, maintenance denial and scheduling regressions | Entry 200; staging workspace anonymous 503, app PHP 403, deployment manifest 404 | Configure private staging DB/binding/keys and perform authenticated Alpha/Beta import/task/export/file tests |
| Status | API/config PHP syntax | Entry 200 | Retain as platform operations tool pending a decision on company-visible service information |

For the Suite itself, bootstrap, notifications, company tenancy, project scoping, branding/preferences, real HTTP forms and handoff/session regressions pass. Company and gateway MySQL tests also pass, including exactly one winner in each of four concurrent code redemption races and rollback when session persistence fails.

## Validation references

- Company/MySQL source checkpoint `d628e03ce2571ee1fe2a85659fc0d1cb4bb5945a`, checks run 37535708624: success.
- Gateway concurrency checkpoint `b06eb895057ef679ce9f774025e7a229f6a31a5b`, checks run 37535969722: success.
- Expanded audit checkpoint `e2cf79cb70c7dd67071ec2ce41073d4cccb4649b`, checks run 37536134380, cross-product run 37536134417 and public-site run 37536134444: success.

PR #2 is merged; invitations and viewer access remain draft PRs, not a deployed release. Existing customer-independent production sites continue on their earlier versions. No staging instance has been declared tenant-ready. Public probes use no login, private integration key or customer record. They verify sampled HTTP responses rather than content-level authorization.

## Next release gates

Deploy Suite code and apply migration 004 after backup; configure private gateway inventory and instance keys securely; initialize dedicated module fixtures; verify two independent companies through real sign-in, permission changes, imports, exports, downloads, reports and offline reconnect. Then extend the same contract to the remaining modules. Verify staged invitations and Programme read-only access, extend client restrictions into other modules, and complete per-module PDF branding and production backup restoration before claiming the entire Suite finished.
