<?php

declare(strict_types=1);

namespace NullAuth\Repository;

final readonly class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findForLogin(string $identifier): ?array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM users WHERE deleted_at IS NULL AND (lower(email::text) = lower(:identifier) OR lower(username::text) = lower(:identifier)) LIMIT 1'
        );
        $stmt->execute(['identifier' => $identifier]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function allActive(): array
    {
        $stmt = $this->db->pdo()->query('SELECT id, email, username, display_name, is_disabled, locked_until, last_login_at, created_at FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }

    public function updateLastLogin(string $userId): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE users SET last_login_at = now(), locked_until = NULL WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    public function lockUntil(string $userId, \DateTimeImmutable $until): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE users SET locked_until = :locked_until WHERE id = :id');
        $stmt->execute(['id' => $userId, 'locked_until' => $until->format(DATE_ATOM)]);
    }

    public function userHasPermission(string $userId, string $permission): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM user_roles ur
            JOIN role_permissions rp ON rp.role_id = ur.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :user_id AND p.name = :permission
            LIMIT 1
        SQL;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId, 'permission' => $permission]);
        return $stmt->fetchColumn() !== false;
    }

    public function countRecentFailures(string $normalizedUsername, string $ip): int
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT count(*) FROM login_attempts WHERE attempted_at > now() - interval '15 minutes' AND success = false AND (normalized_username = :username OR ip = CAST(:ip AS inet))"
        );
        $stmt->execute(['username' => $normalizedUsername, 'ip' => $ip]);
        return (int) $stmt->fetchColumn();
    }

    public function latestFailureTimestamp(string $normalizedUsername, string $ip): ?int
    {
        $stmt = $this->db->pdo()->prepare(
            "SELECT attempted_at FROM login_attempts WHERE success = false AND (normalized_username = :username OR ip = CAST(:ip AS inet)) ORDER BY attempted_at DESC LIMIT 1"
        );
        $stmt->execute(['username' => $normalizedUsername, 'ip' => $ip]);
        $value = $stmt->fetchColumn();
        $timestamp = $value === false ? false : strtotime((string) $value);
        return $timestamp === false ? null : $timestamp;
    }

    public function recordLoginAttempt(string $normalizedUsername, string $ip, string $userAgent, bool $success, ?string $reason): void
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO login_attempts (username_attempted, normalized_username, ip, user_agent, success, failure_reason) VALUES (:username, :normalized, CAST(:ip AS inet), :user_agent, :success, :reason)'
        );
        $stmt->execute([
            'username' => $normalizedUsername,
            'normalized' => $normalizedUsername,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 512),
            'success' => $success ? 'true' : 'false',
            'reason' => $reason,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function enabledTotp(string $userId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM mfa_totp WHERE user_id = :user_id AND enabled_at IS NOT NULL LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createAdmin(string $email, string $username, string $displayName, string $passwordHash): string
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, username, display_name, password_hash) VALUES (:email, :username, :display_name, :password_hash) RETURNING id'
            );
            $stmt->execute([
                'email' => $email,
                'username' => $username,
                'display_name' => $displayName,
                'password_hash' => $passwordHash,
            ]);
            $userId = (string) $stmt->fetchColumn();

            $role = $pdo->prepare("SELECT id FROM roles WHERE name = 'administrator'");
            $role->execute();
            $roleId = (string) $role->fetchColumn();

            $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            $assign->execute(['user_id' => $userId, 'role_id' => $roleId]);

            $pdo->commit();
            return $userId;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}

