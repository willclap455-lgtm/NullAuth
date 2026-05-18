<?php
/** @var string $title */
/** @var string $content */
/** @var string $active */
/** @var \NullAuth\Service\CsrfService $csrf */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> - NullAuth</title>
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="na-app">
  <nav class="navbar navbar-expand-lg navbar-dark na-topbar">
    <div class="container-fluid">
      <a class="navbar-brand fw-semibold" href="/dashboard">NullAuth</a>
      <form method="post" action="/logout" class="ms-auto">
        <?= $csrf->field() ?>
        <button class="btn btn-outline-light btn-sm" type="submit">Sign out</button>
      </form>
    </div>
  </nav>
  <div class="container-fluid">
    <div class="row">
      <aside class="col-lg-2 na-sidebar p-0">
        <div class="list-group list-group-flush">
          <a class="list-group-item list-group-item-action <?= $active === 'dashboard' ? 'active' : '' ?>" href="/dashboard">Dashboard</a>
          <a class="list-group-item list-group-item-action <?= $active === 'vault' ? 'active' : '' ?>" href="/vault">Vault</a>
          <a class="list-group-item list-group-item-action <?= $active === 'generator' ? 'active' : '' ?>" href="/generator">Generator</a>
          <a class="list-group-item list-group-item-action <?= $active === 'admin-users' ? 'active' : '' ?>" href="/admin/users">Users</a>
          <a class="list-group-item list-group-item-action <?= $active === 'admin-audit' ? 'active' : '' ?>" href="/admin/audit">Audit</a>
        </div>
      </aside>
      <main class="col-lg-10 p-4">
        <?= $content ?>
      </main>
    </div>
  </div>
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>
  <script src="<?= e(asset('vendor/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(asset('js/vault.js')) ?>"></script>
</body>
</html>
