<?php /** @var string $displayName */ ?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 mb-1">Dashboard</h1>
    <p class="text-muted mb-0">Welcome, <?= e($displayName) ?>. Sensitive actions are audited.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-4">
    <div class="card na-card">
      <div class="card-body">
        <h2 class="h5">Vault posture</h2>
        <p class="text-muted">Secrets are encrypted per entry using authenticated encryption and unique nonces.</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card na-card">
      <div class="card-body">
        <h2 class="h5">Session security</h2>
        <p class="text-muted">Idle and absolute session timers are enforced with server-side tracking.</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card na-card">
      <div class="card-body">
        <h2 class="h5">Auditability</h2>
        <p class="text-muted">Login, reveal, copy, edit, sharing, and administrative actions are logged without secrets.</p>
      </div>
    </div>
  </div>
</div>
