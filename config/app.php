<?php
declare(strict_types=1);

use Suite\Support\Env;

return [
    'name' => Env::get('APP_NAME', 'Construction Suite'),
    'environment' => Env::get('APP_ENV', 'production'),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/London'),
    'version' => '0.4.0',
    'modules' => require __DIR__ . '/modules.php',
];
