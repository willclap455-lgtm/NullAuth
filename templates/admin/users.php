<?php /** @var array<int, array<string, mixed>> $users */ ?>
<div class="mb-4">
  <h1 class="h3 mb-1">Users</h1>
  <p class="text-muted mb-0">Administrative user management is permission-gated and audited.</p>
</div>

<div class="card na-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Display name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Status</th>
          <th>Last login</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= e((string) $user['display_name']) ?></td>
            <td><?= e((string) $user['username']) ?></td>
            <td><?= e((string) $user['email']) ?></td>
            <td><?= !empty($user['is_disabled']) ? '<span class="badge text-bg-danger">Disabled</span>' : '<span class="badge text-bg-success">Active</span>' ?></td>
            <td><?= e((string) ($user['last_login_at'] ?? 'Never')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
