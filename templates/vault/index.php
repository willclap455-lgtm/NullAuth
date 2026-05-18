<?php
/** @var array<int, array<string, mixed>> $entries */
/** @var \NullAuth\Service\CsrfService $csrf */
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="h3 mb-1">Password vault</h1>
    <p class="text-muted mb-0">Reveal and copy actions require explicit clicks and are audited.</p>
  </div>
  <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#new-entry">New entry</button>
</div>

<div class="collapse mb-4" id="new-entry">
  <div class="card na-card">
    <div class="card-body">
      <form method="post" action="/vault" autocomplete="off">
        <?= $csrf->field() ?>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Title</label>
            <input class="form-control" name="title" maxlength="255" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" maxlength="255" autocomplete="off">
          </div>
          <div class="col-md-6">
            <label class="form-label">Password</label>
            <input class="form-control" name="password" type="password" required autocomplete="new-password">
          </div>
          <div class="col-md-6">
            <label class="form-label">URL</label>
            <input class="form-control" name="url" type="url" maxlength="2048">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tags</label>
            <input class="form-control" name="tags" placeholder="server, production, postgres">
          </div>
          <div class="col-md-3">
            <label class="form-label">Expires</label>
            <input class="form-control" name="expires_at" type="date">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="favorite" value="1" id="favorite">
              <label class="form-check-label" for="favorite">Favorite</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Custom fields (JSON)</label>
            <textarea class="form-control" name="custom_fields" rows="3" placeholder='{"environment":"production"}'></textarea>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-primary" type="submit">Encrypt and save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card na-card">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Title</th>
          <th>URL</th>
          <th>Tags</th>
          <th>Updated</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($entries as $entry): ?>
        <tr>
          <td>
            <?= !empty($entry['favorite']) ? '<span class="text-warning">★</span> ' : '' ?>
            <?= e((string) $entry['title']) ?>
          </td>
          <td>
            <?php if (!empty($entry['url'])): ?>
              <a href="<?= e((string) $entry['url']) ?>" rel="noreferrer noopener" target="_blank"><?= e((string) $entry['url']) ?></a>
            <?php endif; ?>
          </td>
          <td><span class="badge text-bg-light"><?= e(is_array($entry['tags'] ?? null) ? implode(', ', $entry['tags']) : (string) ($entry['tags'] ?? '')) ?></span></td>
          <td><?= e((string) $entry['updated_at']) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-primary na-reveal" data-entry-id="<?= e((string) $entry['id']) ?>" data-csrf="<?= e($csrf->token()) ?>">Reveal</button>
            <button class="btn btn-sm btn-outline-secondary na-copy" data-entry-id="<?= e((string) $entry['id']) ?>" data-csrf="<?= e($csrf->token()) ?>">Copy</button>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($entries === []): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">No vault entries yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="secret-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h5">Password reveal</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input class="form-control font-monospace" id="secret-value" type="password" readonly>
        <p class="small text-muted mt-2 mb-0">This field auto-hides when the dialog closes. Avoid screen sharing while revealing secrets.</p>
      </div>
    </div>
  </div>
</div>
