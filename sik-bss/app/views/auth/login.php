<?php use App\Core\Flash; $cfg = config('app'); ?>
<div class="auth-card p-3">
  <div class="text-center mb-3">
    <i class="bi bi-wallet2 text-primary" style="font-size:2.6rem"></i>
    <h1 class="h5 fw-semibold mt-2 mb-0"><?= e($cfg['name']) ?></h1>
    <div class="text-muted"><?= e($cfg['company']) ?></div>
  </div>
  <div class="card"><div class="card-body p-4">
    <?php foreach (Flash::pull() as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> py-2"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <form method="post" action="<?= url('/login') ?>" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label fw-medium" for="username">Username</label>
        <input class="form-control" id="username" name="username" value="<?= e(old('username')) ?>" autocomplete="username" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label fw-medium" for="password">Password</label>
        <div class="input-group">
          <input type="password" class="form-control" id="password" name="password" autocomplete="current-password">
          <button type="button" class="btn btn-outline-secondary" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password'" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
        </div>
      </div>
      <button class="btn btn-primary w-100">Masuk</button>
    </form>
  </div></div>
</div>
