<?php

declare(strict_types=1);

namespace NullAuth\Service;

use NullAuth\Http\Request;
use NullAuth\Repository\AuditRepository;

final readonly class AuditService
{
    public function __construct(private AuditRepository $repository, private Request $request)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function record(string $event, string $result, ?string $actorUserId = null, ?string $username = null, ?string $objectType = null, ?string $objectId = null, array $metadata = []): void
    {
        $this->repository->record(
            $actorUserId,
            $username,
            $this->request->ip(),
            $this->request->userAgent(),
            $event,
            $result,
            $objectType,
            $objectId,
            $metadata
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 100): array
    {
        return $this->repository->recent($limit);
    }
}

