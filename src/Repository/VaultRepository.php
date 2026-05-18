<?php

declare(strict_types=1);

namespace NullAuth\Repository;

final readonly class VaultRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function visibleToUser(string $userId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT ve.id, ve.title, ve.url, ve.tags, ve.favorite, ve.expires_at, ve.updated_at, ve.owner_user_id
            FROM vault_entries ve
            LEFT JOIN vault_entry_shares ves ON ves.vault_entry_id = ve.id AND ves.revoked_at IS NULL
            LEFT JOIN folder_shares fs ON fs.folder_id = ve.folder_id AND fs.revoked_at IS NULL
            WHERE ve.deleted_at IS NULL
              AND ve.status = 'active'
              AND (ve.owner_user_id = :user_id OR ves.grantee_user_id = :user_id OR fs.grantee_user_id = :user_id)
            ORDER BY ve.favorite DESC, ve.updated_at DESC
        SQL;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $entry */
    public function create(array $entry): string
    {
        $sql = <<<'SQL'
            INSERT INTO vault_entries
                (owner_user_id, title, username_envelope, password_envelope, url, notes_envelope, custom_fields_envelope, tags, favorite, expires_at)
            VALUES
                (:owner_user_id, :title, CAST(:username_envelope AS jsonb), CAST(:password_envelope AS jsonb), :url, CAST(:notes_envelope AS jsonb), CAST(:custom_fields_envelope AS jsonb), :tags, :favorite, :expires_at)
            RETURNING id
        SQL;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute([
            'owner_user_id' => $entry['owner_user_id'],
            'title' => $entry['title'],
            'username_envelope' => $entry['username_envelope'],
            'password_envelope' => $entry['password_envelope'],
            'url' => $entry['url'],
            'notes_envelope' => $entry['notes_envelope'],
            'custom_fields_envelope' => $entry['custom_fields_envelope'],
            'tags' => '{' . implode(',', array_map(static fn (string $tag): string => '"' . str_replace('"', '\"', $tag) . '"', $entry['tags'])) . '}',
            'favorite' => $entry['favorite'] ? 'true' : 'false',
            'expires_at' => $entry['expires_at'],
        ]);

        return (string) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findAuthorized(string $entryId, string $userId, string $permission = 'read'): ?array
    {
        $sql = <<<'SQL'
            SELECT ve.*
            FROM vault_entries ve
            LEFT JOIN vault_entry_shares ves ON ves.vault_entry_id = ve.id AND ves.revoked_at IS NULL AND ves.grantee_user_id = :user_id
            LEFT JOIN folder_shares fs ON fs.folder_id = ve.folder_id AND fs.revoked_at IS NULL AND fs.grantee_user_id = :user_id
            WHERE ve.id = :entry_id
              AND ve.deleted_at IS NULL
              AND ve.status = 'active'
              AND (
                    ve.owner_user_id = :user_id
                 OR ves.permission IN ('read', 'write', 'share', 'owner')
                 OR fs.permission IN ('read', 'write', 'share', 'owner')
              )
            LIMIT 1
        SQL;

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute(['entry_id' => $entryId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

