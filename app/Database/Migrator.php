<?php
declare(strict_types=1);

namespace Suite\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(private readonly PDO $pdo, private readonly string $root)
    {
    }

    public function pending(): array
    {
        $this->ensureTable();
        $applied = $this->appliedVersions();
        $pending = [];

        foreach ($this->migrationFiles() as $version => $path) {
            if (!isset($applied[$version])) {
                $pending[$version] = $path;
            }
        }

        return $pending;
    }

    public function appliedVersions(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query('SELECT version, applied_at FROM schema_migrations ORDER BY version')->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['version']] = (string) $row['applied_at'];
        }
        return $result;
    }

    public function migrateAll(): array
    {
        $appliedNow = [];
        foreach ($this->pending() as $version => $path) {
            $this->apply($version, $path);
            $appliedNow[] = $version;
        }
        return $appliedNow;
    }

    private function apply(string $version, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Unable to read migration: ' . $version);
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $transactional = $driver === 'sqlite';

        if ($transactional) {
            $this->pdo->beginTransaction();
        }

        try {
            foreach ($this->splitStatements($sql) as $statement) {
                $this->pdo->exec($statement);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO schema_migrations (version, applied_at) VALUES (?, CURRENT_TIMESTAMP)'
            );
            $stmt->execute([$version]);

            if ($transactional && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($transactional && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Migration ' . $version . ' failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function migrationFiles(): array
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $suffix = $driver === 'sqlite' ? '.sqlite.sql' : '.mysql.sql';
        $paths = glob($this->root . '/database/migrations/*' . $suffix) ?: [];
        sort($paths, SORT_STRING);

        $result = [];
        foreach ($paths as $path) {
            $name = basename($path, $suffix);
            $result[$name] = $path;
        }
        return $result;
    }

    private function ensureTable(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (
                    version TEXT PRIMARY KEY,
                    applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
            );
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function splitStatements(string $sql): array
    {
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        return array_values(array_filter(
            array_map('trim', $statements),
            static fn(string $statement): bool => $statement !== ''
        ));
    }
}
