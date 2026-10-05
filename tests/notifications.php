<?php
declare(strict_types=1);

$path = tempnam(sys_get_temp_dir(), 'suite-notifications-');
if ($path === false) throw new RuntimeException('Temp database unavailable');
putenv('DB_DRIVER=sqlite');
putenv('DB_DATABASE=' . $path);

require_once dirname(__DIR__) . '/app/Support/Env.php';
require_once dirname(__DIR__) . '/app/Database/Connection.php';
require_once dirname(__DIR__) . '/app/Support/Notifications.php';

use Suite\Database\Connection;
use Suite\Support\Notifications;

try {
    $db = Connection::pdo();
    $db->exec('CREATE TABLE notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        project_id INTEGER NULL,
        title TEXT NOT NULL,
        body TEXT NULL,
        read_at TEXT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $add = $db->prepare('INSERT INTO notifications (user_id, project_id, title) VALUES (?, ?, ?)');
    $add->execute([1, 10, 'My project']);
    $myId = (int) $db->lastInsertId();
    $add->execute([1, 11, 'Other project']);
    $otherProjectId = (int) $db->lastInsertId();
    $add->execute([2, 10, 'Other user']);
    $otherUserId = (int) $db->lastInsertId();
    $add->execute([1, null, 'My global']);

    $check = static function (bool $value, string $message): void {
        if (!$value) throw new RuntimeException('FAIL: ' . $message);
    };
    $check(Notifications::count(1, 10) === 2, 'Project and global count');
    $check(count(Notifications::recent(1, 10)) === 2, 'Scoped inbox');
    $check(!Notifications::acknowledge($otherProjectId, 1, 10), 'Other project must not be touched');
    $check(!Notifications::acknowledge($otherUserId, 1, 10), 'Other user must not be touched');
    $check(Notifications::acknowledge($myId, 1, 10), 'Owner can acknowledge own message');
    $check(Notifications::count(1, 10) === 1, 'Read count updates');
    $check(Notifications::count(1, 11) === 2, 'Other project remains intact');
    $check(!Notifications::acknowledge($myId, 1, 10), 'Acknowledgement is idempotent');

    echo "PASS: User/project-isolated notifications, CSRF-independent DB permissions and acknowledgements.\n";
} finally {
    @unlink($path);
}
