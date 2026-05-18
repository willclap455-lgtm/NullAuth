<?php

declare(strict_types=1);

namespace NullAuth\Repository;

final readonly class AuditRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function record(?string $actorUserId, ?string $username, ?string $ip, string $userAgent, string $event, string $result, ?string $objectType = null, ?string $objectId = null, array $metadata = []): void
    {
        $sql = <<<'SQL'
            INSERT INTO audit_logs (actor_user_id, username_attempted, ip, user_agent, event, object_type, object_id, result, metadata)
            VALUES (:actor_user_id, :username_attempted, :ip, :user_agent, :event, :object_type, :object_id, :result, CAST(:metadata AS jsonb))
        SQL;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([
            'actor_user_id' => $actorUserId,
            'username_attempted' => $username,
            'ip' => $ip,
            'user_agent' => substr($userAgent, 0, 512),
            'event' => $event,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'result' => $result,
            'metadata' => json_encode($this->redact($metadata), JSON_THROW_ON_ERROR),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM audit_logs ORDER BY occurred_at DESC LIMIT :limit');
        $stmt->bindValue('limit', max(1, min($limit, 500)), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $metadata @return array<string, mixed> */
    private function redact(array $metadata): array
    {
        $blocked = ['password', 'secret', 'token', 'recovery_code', 'session_id', 'key', 'ciphertext'];
        foreach ($metadata as $key => $value) {
            foreach ($blocked as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $metadata[$key] = '[redacted]';
                }
            }
        }

        return $metadata;
    }
}

