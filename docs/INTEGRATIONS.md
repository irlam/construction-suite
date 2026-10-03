# Construction Suite read-only module integrations

The Hub can read small dashboard summaries from production modules without sharing their user sessions or database credentials.

## Security model

The Hub sends a private header:

```text
X-Construction-Suite-Key: <secret>
```

Each module compares that value with its own private configuration using a timing-safe comparison.

The summary endpoints are read-only. They do not create, update or delete production records.

If a module has no integration key configured, its endpoint returns `503 suite_integration_not_configured`. An incorrect key returns `401 unauthorized`.

## Hub

Add a long random value to the private `.env` for `suite.defecttracker.uk`:

```ini
SUITE_INTEGRATION_KEY="replace-with-a-long-random-secret"
```

Use the same secret on each module that is to be connected.

## Defect Tracker

In the private Defect Tracker `.env`:

```ini
CONSTRUCTION_SUITE_API_KEY="same-secret-as-the-hub"
```

Summary endpoint:

```text
/api/suite-summary.php?project=<project-id>
```

The external project reference in Hub administration should be the numeric Defect Tracker project ID.

## Permits

In the private Permits `.env`:

```ini
CONSTRUCTION_SUITE_API_KEY="same-secret-as-the-hub"
```

Summary endpoint:

```text
/api/suite-summary.php?site=<site-block>
```

For the current permit system the external reference maps to `forms.site_block`.

## Safety Tours

In the private `includes/config.php`:

```php
const CONSTRUCTION_SUITE_API_KEY = 'same-secret-as-the-hub';
```

Summary endpoint:

```text
/api/suite-summary.php?site=<site-name>
```

The external reference maps to `safety_tours.site`.

## Project mapping

Open **Suite administration → Project modules** and set the external project reference for each connected module.

A module can remain enabled for launching even if it has no external reference; in that case its dashboard summary reports that mapping is required.

## Delivery integration

The Deliveries module remains launchable from the Hub. Its source repository is not currently part of the connected GitHub repository set, so the read-only summary endpoint will be added when that source is brought under the same integration workflow.
