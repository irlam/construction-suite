<?php
declare(strict_types=1);

use Suite\Auth\Auth;
use Suite\Database\Connection;
use Suite\Modules\ModuleRegistry;
use Suite\Modules\ModuleHealth;
use Suite\Projects\ProjectRepository;
use Suite\Support\Env;

define('SUITE_ROOT', dirname(__DIR__));

require_once SUITE_ROOT . '/app/Support/Env.php';
require_once SUITE_ROOT . '/app/Database/Connection.php';
require_once SUITE_ROOT . '/app/Auth/Auth.php';
require_once SUITE_ROOT . '/app/Projects/ProjectRepository.php';
require_once SUITE_ROOT . '/app/Modules/ModuleRegistry.php';
require_once SUITE_ROOT . '/app/Modules/ModuleHealth.php';
require_once SUITE_ROOT . '/app/Support/Audit.php';

Env::load(SUITE_ROOT . '/.env');

$config = require SUITE_ROOT . '/config/app.php';
date_default_timezone_set((string) ($config['timezone'] ?? 'Europe/London'));

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $https = Env::bool('SESSION_SECURE', true)
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    session_name((string) Env::get('SESSION_NAME', 'construction_suite'));
    session_set_cookie_params([
        'lifetime' => Env::int('SESSION_LIFETIME', 28800),
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}

function suite_config(?string $key = null, mixed $default = null): mixed
{
    global $config;
    return $key === null ? $config : ($config[$key] ?? $default);
}

function suite_db(): PDO
{
    return Connection::pdo();
}

function suite_auth(): Auth
{
    static $auth;
    return $auth ??= new Auth();
}

function suite_projects(): ProjectRepository
{
    static $projects;
    return $projects ??= new ProjectRepository();
}

function suite_modules(): ModuleRegistry
{
    static $modules;
    return $modules ??= new ModuleRegistry((array) suite_config('modules', []));
}

function suite_module_health(): ModuleHealth
{
    static $health;
    return $health ??= new ModuleHealth();
}

function suite_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
