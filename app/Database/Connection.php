<?php
declare(strict_types=1);

namespace Suite\Database;

use PDO;
use RuntimeException;
use Suite\Support\Env;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $driver = strtolower((string) Env::get('DB_DRIVER', 'mysql'));
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $path = Env::get('DB_DATABASE', dirname(__DIR__, 2) . '/storage/suite.sqlite');
            if ($path === null || $path === '') {
                throw new RuntimeException('SQLite database path is not configured.');
            }
            $directory = dirname($path);
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create SQLite storage directory.');
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            return self::$pdo;
        }

        if ($driver !== 'mysql') {
            throw new RuntimeException('Unsupported DB_DRIVER: ' . $driver);
        }

        $host = Env::get('DB_HOST', 'localhost');
        $port = Env::get('DB_PORT', '3306');
        $database = Env::get('DB_DATABASE');
        $username = Env::get('DB_USERNAME');
        $password = Env::get('DB_PASSWORD', '');

        if (!$database || !$username) {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        self::$pdo = new PDO($dsn, $username, $password, $options);
        return self::$pdo;
    }

    public static function driver(): string
    {
        return (string) self::pdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function tableExists(string $table): bool
    {
        $pdo = self::pdo();
        if (self::driver() === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
