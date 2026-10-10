<div class="card" style="max-width:480px"><div class="card-body">
<form method="post" action="<?= url('/profil/password') ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label req">Password lama</label><input type="password" name="password_lama" class="form-control" autocomplete="current-password"></div>
  <div class="mb-3"><label class="form-label req">Password baru</label><input type="password" name="password_baru" class="form-control" minlength="8" autocomplete="new-password"><div class="form-text">Minimal 8 karakter.</div></div>
  <div class="mb-3"><label class="form-label req">Konfirmasi password baru</label><input type="password" name="konfirmasi" class="form-control" autocomplete="new-password"></div>
  <div class="text-end"><a href="<?= url('/dashboard') ?>" class="btn btn-outline-secondary">Batal</a> <button class="btn btn-primary">Simpan</button></div>
</form></div></div>
