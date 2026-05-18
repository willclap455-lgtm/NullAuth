<?php

declare(strict_types=1);

namespace NullAuth\Service;

use NullAuth\Repository\Database;

final readonly class SessionService
{
    public function __construct(private Database $db, private AuditService $audit)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) config('session.name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** @param array<string, mixed> $user */
    public function authenticate(array $user, string $ip, string $userAgent): void
    {
        $this->start();
        session_regenerate_id(true);

        $now = time();
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['display_name'] = (string) $user['display_name'];
        $_SESSION['created_at'] = $now;
        $_SESSION['last_seen_at'] = $now;

        $sessionHash = hash('sha256', session_id());
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO sessions (user_id, session_id_hash, ip_hash, user_agent_hash, idle_expires_at, absolute_expires_at) VALUES (:user_id, :sid, :ip_hash, :ua_hash, :idle, :absolute)'
        );
        $stmt->execute([
            'user_id' => (string) $user['id'],
            'sid' => $sessionHash,
            'ip_hash' => hash('sha256', $ip),
            'ua_hash' => hash('sha256', $userAgent),
            'idle' => gmdate(DATE_ATOM, $now + (int) config('session.idle_seconds')),
            'absolute' => gmdate(DATE_ATOM, $now + (int) config('session.absolute_seconds')),
        ]);
    }

    public function requireUser(): ?string
    {
        $this->start();
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $now = time();
        $idle = (int) config('session.idle_seconds');
        $absolute = (int) config('session.absolute_seconds');
        $createdAt = (int) ($_SESSION['created_at'] ?? $now);
        $lastSeen = (int) ($_SESSION['last_seen_at'] ?? $now);

        if ($now - $lastSeen > $idle || $now - $createdAt > $absolute) {
            $userId = (string) $_SESSION['user_id'];
            $this->destroy();
            $this->audit->record('session.expired', 'success', $userId);
            return null;
        }

        $_SESSION['last_seen_at'] = $now;
        $this->touch();
        return (string) $_SESSION['user_id'];
    }

    public function displayName(): string
    {
        $this->start();
        return (string) ($_SESSION['display_name'] ?? 'User');
    }

    public function destroy(): void
    {
        $this->start();
        if (session_id() !== '') {
            $stmt = $this->db->pdo()->prepare('UPDATE sessions SET revoked_at = now() WHERE session_id_hash = :hash');
            $stmt->execute(['hash' => hash('sha256', session_id())]);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    private function touch(): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE sessions SET last_seen_at = now(), idle_expires_at = :idle WHERE session_id_hash = :hash AND revoked_at IS NULL');
        $stmt->execute([
            'hash' => hash('sha256', session_id()),
            'idle' => gmdate(DATE_ATOM, time() + (int) config('session.idle_seconds')),
        ]);
    }

    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}

