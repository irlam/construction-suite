<?php
declare(strict_types=1);

namespace Suite\Auth;

use PDO;
use Suite\Database\Connection;

final class Auth
{
    private const MAX_ATTEMPTS = 8;
    private const WINDOW_SECONDS = 900;

    public function user(): ?array
    {
        $id = (int) ($_SESSION['suite_user_id'] ?? 0);
        if ($id < 1 || !Connection::tableExists('users')) {
            return null;
        }

        $stmt = Connection::pdo()->prepare(
            'SELECT id, email, name, active, is_platform_admin, last_login_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user || !(bool) $user['active']) {
            $this->logout();
            return null;
        }

        return $user;
    }

    public function attempt(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Enter a valid email address.'];
        }

        if ($this->isRateLimited($email, $ip)) {
            return ['ok' => false, 'message' => 'Too many sign-in attempts. Try again in a few minutes.'];
        }

        $stmt = Connection::pdo()->prepare(
            'SELECT id, email, name, password_hash, active, is_platform_admin
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !(bool) $user['active'] || !password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailedAttempt($email, $ip);
            return ['ok' => false, 'message' => 'Email or password is incorrect.'];
        }

        $this->clearAttempts($email, $ip);
        session_regenerate_id(true);
        $_SESSION['suite_user_id'] = (int) $user['id'];
        $_SESSION['suite_signed_in_at'] = time();

        $update = Connection::pdo()->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?');
        $update->execute([(int) $user['id']]);

        return ['ok' => true, 'message' => 'Signed in.'];
    }

    public function requireUser(): array
    {
        $user = $this->user();
        if (!$user) {
            $next = urlencode($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: /login.php?next=' . $next);
            exit;
        }
        return $user;
    }

    public function logout(): void
    {
        unset(
            $_SESSION['suite_user_id'],
            $_SESSION['suite_signed_in_at'],
            $_SESSION['suite_project_id'],
            $_SESSION['suite_csrf']
        );
    }

    public function isPlatformAdmin(?array $user = null): bool
    {
        $user ??= $this->user();
        return $user !== null && (bool) ($user['is_platform_admin'] ?? false);
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['suite_csrf'])) {
            $_SESSION['suite_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['suite_csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['suite_csrf'])
            && hash_equals((string) $_SESSION['suite_csrf'], $token);
    }

    private function isRateLimited(string $email, string $ip): bool
    {
        if (!Connection::tableExists('login_attempts')) {
            return false;
        }

        $cutoff = time() - self::WINDOW_SECONDS;
        $stmt = Connection::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email_hash = ? AND ip_hash = ? AND attempted_at >= ?'
        );
        $stmt->execute([$this->hash($email), $this->hash($ip), $cutoff]);
        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    private function recordFailedAttempt(string $email, string $ip): void
    {
        if (!Connection::tableExists('login_attempts')) {
            return;
        }

        $pdo = Connection::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO login_attempts (email_hash, ip_hash, attempted_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$this->hash($email), $this->hash($ip), time()]);

        $cleanup = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $cleanup->execute([time() - 86400]);
    }

    private function clearAttempts(string $email, string $ip): void
    {
        if (!Connection::tableExists('login_attempts')) {
            return;
        }

        $stmt = Connection::pdo()->prepare(
            'DELETE FROM login_attempts WHERE email_hash = ? AND ip_hash = ?'
        );
        $stmt->execute([$this->hash($email), $this->hash($ip)]);
    }

    private function hash(string $value): string
    {
        return hash('sha256', strtolower(trim($value)));
    }
}
