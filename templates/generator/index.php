<?php
/** @var \NullAuth\Service\CsrfService $csrf */
/** @var array{password: string, entropy: float, mode: string}|null $result */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h1 class="h3 mb-1">Password generator</h1>
    <p class="text-muted mb-0">All generation uses PHP cryptographic randomness through <code>random_int()</code>.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card na-card">
      <div class="card-body">
        <form method="post" action="/generator">
          <?= $csrf->field() ?>
          <div class="mb-3">
            <label class="form-label">Mode</label>
            <select class="form-select" name="mode">
              <option value="characters">Character set</option>
              <option value="passphrase">Passphrase</option>
              <option value="pronounceable">Pronounceable</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Length</label>
            <input class="form-range" name="length" type="range" min="12" max="64" value="24" data-range-output="length-output">
            <output id="length-output">24</output>
          </div>
          <div class="row">
            <?php foreach (['uppercase' => 'Uppercase', 'lowercase' => 'Lowercase', 'numbers' => 'Numbers', 'symbols' => 'Symbols'] as $name => $label): ?>
              <div class="col-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="<?= e($name) ?>" value="1" id="<?= e($name) ?>" checked>
                  <label class="form-check-label" for="<?= e($name) ?>"><?= e($label) ?></label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="exclude_ambiguous" value="1" id="exclude_ambiguous">
            <label class="form-check-label" for="exclude_ambiguous">Exclude ambiguous characters</label>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="form-label">Words</label>
              <input class="form-control" name="words" type="number" min="4" max="12" value="6">
            </div>
            <div class="col-md-6">
              <label class="form-label">Separator</label>
              <input class="form-control" name="separator" value="-" maxlength="3">
            </div>
          </div>
          <button class="btn btn-primary mt-3" type="submit">Generate</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card na-card h-100">
      <div class="card-body">
        <h2 class="h5">Result</h2>
        <?php if ($result): ?>
          <div class="input-group">
            <input class="form-control font-monospace" id="generated-password" value="<?= e($result['password']) ?>" readonly>
            <button class="btn btn-outline-primary" type="button" data-copy-target="generated-password">Copy</button>
          </div>
          <div class="mt-3">
            <span class="badge text-bg-primary"><?= e($result['mode']) ?></span>
            <span class="badge text-bg-success"><?= e((string) $result['entropy']) ?> bits estimated entropy</span>
          </div>
        <?php else: ?>
          <p class="text-muted">Generated passwords appear here only after an explicit request.</p>
        <?php endif; ?>
        <hr>
        <p class="small text-muted mb-0">Entropy estimates use length × log2(pool size) for character mode and word count × log2(word-list size) for passphrases. Pronounceable mode is easier to type but lowers entropy density.</p>
      </div>
    </div>
  </div>
</div>
