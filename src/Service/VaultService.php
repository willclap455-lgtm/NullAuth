<?php

declare(strict_types=1);

namespace NullAuth\Service;

use NullAuth\Repository\VaultRepository;

final readonly class VaultService
{
    public function __construct(
        private VaultRepository $vault,
        private CryptoService $crypto,
        private AuditService $audit,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForUser(string $userId): array
    {
        return $this->vault->visibleToUser($userId);
    }

    /** @param array<string, string> $input */
    public function create(string $userId, array $input): string
    {
        $title = trim($input['title'] ?? '');
        $password = (string) ($input['password'] ?? '');
        if ($title === '' || $password === '') {
            throw new \InvalidArgumentException('Title and password are required.');
        }

        $entryIdContext = bin2hex(random_bytes(16));
        $tags = array_values(array_filter(array_map('trim', explode(',', $input['tags'] ?? ''))));
        $id = $this->vault->create([
            'owner_user_id' => $userId,
            'title' => mb_substr($title, 0, 255),
            'username_envelope' => $this->nullableEnvelope($input['username'] ?? '', 'vault:username:' . $entryIdContext),
            'password_envelope' => $this->crypto->encryptString($password, 'vault:password:' . $entryIdContext),
            'url' => $this->validUrl($input['url'] ?? ''),
            'notes_envelope' => $this->nullableEnvelope($input['notes'] ?? '', 'vault:notes:' . $entryIdContext),
            'custom_fields_envelope' => $this->nullableEnvelope($input['custom_fields'] ?? '', 'vault:custom:' . $entryIdContext),
            'tags' => array_slice($tags, 0, 20),
            'favorite' => ($input['favorite'] ?? '') === '1',
            'expires_at' => $this->dateOrNull($input['expires_at'] ?? ''),
        ]);

        $this->audit->record('vault.entry.created', 'success', $userId, null, 'vault_entry', $id);
        return $id;
    }

    public function revealPassword(string $userId, string $entryId): ?string
    {
        $entry = $this->vault->findAuthorized($entryId, $userId, 'read');
        if ($entry === null) {
            $this->audit->record('vault.entry.reveal.denied', 'denied', $userId, null, 'vault_entry', $entryId);
            return null;
        }

        $context = $this->contextFromEnvelope((string) $entry['password_envelope'], 'vault:password:' . $entryId);
        $password = $this->crypto->decryptString((string) $entry['password_envelope'], $context);
        $this->audit->record('vault.entry.password.revealed', 'success', $userId, null, 'vault_entry', $entryId);

        return $password;
    }

    private function nullableEnvelope(string $value, string $context): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $this->crypto->encryptString($value, $context);
    }

    private function validUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, 2048) : null;
    }

    private function dateOrNull(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $parsed === false ? null : $parsed->format(DATE_ATOM);
    }

    private function contextFromEnvelope(string $envelope, string $fallback): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($envelope, true, flags: JSON_THROW_ON_ERROR);
        return isset($data['context']) && is_string($data['context']) ? $data['context'] : $fallback;
    }
}

