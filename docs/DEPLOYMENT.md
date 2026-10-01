# Construction Suite deployment

The Hub is designed for normal PHP hosting. The safest Plesk/cPanel layout is to keep the repository root private and expose only the `public/` directory.

## Plesk target

1. Create a new subdomain for the Suite.
2. Deploy this repository into the site's application directory.
3. Set the web/document root to the repository's **`public/`** directory.
4. Select PHP 8.2 or newer.
5. Do not enable Node.js for the Hub.
6. Create a MariaDB/MySQL database and a dedicated database user.
7. Copy `.env.example` to `.env` in the repository root and replace every deployment value.
8. Use HTTPS before signing in.

## Required environment values

At minimum set:

```ini
APP_ENV=production
APP_URL=https://your-suite-domain.example
SESSION_SECURE=true

DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SUITE_SETUP_KEY=<long random one-time value>
```

The `.env` file must not be committed to Git.

## First run

After the database and environment are configured, open:

```text
https://your-suite-domain.example/setup.php
```

Enter the one-time setup key, first organisation/site and administrator details.

After setup succeeds:

1. sign in and check the dashboard;
2. remove or blank `SUITE_SETUP_KEY` in the server environment;
3. confirm `/setup.php` reports that setup is locked;
4. check `/api/v1/health.php` returns `status: ok`;
5. install the PWA on a test Android/iOS device;
6. switch the device offline and verify the Suite offline shell opens.

## Existing modules

Hub V1 launches the existing production tools at their current domains. They keep their own authentication until shared identity is introduced in Phase 2.

## Important security boundary

Do **not** point the public document root at the repository root. Only `public/` should be directly web-accessible. This keeps application classes, schemas, documentation, `.env` and other private files outside the website document root.
