<?php /** @var array<int, array<string, mixed>> $events */ ?>
<div class="mb-4">
  <h1 class="h3 mb-1">Audit log</h1>
  <p class="text-muted mb-0">Audit metadata is redacted by design and excludes plaintext secrets.</p>
</div>

<div class="card na-card">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Time</th>
          <th>Event</th>
          <th>Result</th>
          <th>Actor</th>
          <th>IP</th>
          <th>Object</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($events as $event): ?>
        <tr>
          <td><?= e((string) $event['occurred_at']) ?></td>
          <td><?= e((string) $event['event']) ?></td>
          <td><span class="badge text-bg-secondary"><?= e((string) $event['result']) ?></span></td>
          <td><?= e((string) ($event['actor_user_id'] ?? $event['username_attempted'] ?? 'unknown')) ?></td>
          <td><?= e((string) ($event['ip'] ?? '')) ?></td>
          <td><?= e(trim((string) ($event['object_type'] ?? '') . ' ' . (string) ($event['object_id'] ?? ''))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($events === []): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No audit events found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
