# Defects pilot validation

Defects uses isolated instances 3 (Alpha, company/project 7) and 4 (Beta, company/project 8) at `https://alpha.defectnotice.site` and `https://beta.defectnotice.site`. Programme retains instances 1 and 2. Preserve both Programme inventory entries and append the two Defects entries to the existing private inventory. Set both verification flags to false on every pilot entry. Store distinct per-instance gateway keys in the private deployment environment; never commit them.

The inventory remains `private/programme-staging-instances.json` for compatibility with the existing CLI jail alias. Its directory must be 0700 and the file 0600. The file now contains four entries, while each validation policy admits only one module pair. Defects requires `SUITE_INSTANCE_KEY_3` and `SUITE_INSTANCE_KEY_4`, matching the private configuration on each isolated Defects host.

The six existing fixture accounts (IDs 7–12, company administrator/manager/viewer in each company) are reused. Do not assign them to production companies or projects. Existing fixtures must be active and have exactly the expected memberships. No new Suite accounts or readiness changes are made by the helper.

After deploying this reviewed Suite source and preparing both Defects hosts, run from the Suite application root:

```sh
/usr/local/php84/bin/php bin/staging-window.php --dry-run defects
/usr/local/php84/bin/php bin/staging-window.php --activate defects
```

A one-hour private policy enables only fixture launches to instances 3 and 4. It does not mark the tool ready. A Programme window cannot replace or remove a Defects policy, and vice versa. The dashboard can continue to display the ordinary unavailable notice while readiness remains false; the temporary fixture launcher is `/launch.php?instance_id=3` or `4`, reached through the existing Suite authenticated launch flow.

Test manager create/edit/refresh, viewer read/export with denied edits, private uploads/downloads, Alpha/Beta separation and logout/revocation. Close the window before its expiry to verify immediate denial:

```sh
/usr/local/php84/bin/php bin/staging-window.php --close defects
```

Closing first removes the matching private policy, then revokes outstanding grants and module sessions for that pair. No user memberships, inventory flags or production records change. The former top-level Programme helper pins the previous source and two-entry inventory and will refuse this updated deployment; use `bin/staging-window.php ... programme` for subsequent Programme windows.

Defects deployment details live in the Defect Tracker repository at `docs/SUITE-DEPLOYMENT.md`. The first package provides a core defect register for integration testing. Legacy administration, notifications, offline workflows and the rest of the original application require their own review before broader rollout. Leave all readiness flags false until live isolation and gateway checks have been completed and recorded.
