<?php
/** @var ?array $row @var array $kasBank, $akunBeban @var string $action */
$v = fn($k, $d = '') => old($k, $row[$k] ?? $d);
?>
<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="card" style="max-width:760px"><?= csrf_field() ?>
<div class="card-body"><div class="row g-3">
  <div class="col-md-6"><label class="form-label req">Kas / Bank sumber</label>
    <select name="id_kas_bank" class="form-select" required>
      <?php foreach ($kasBank as $k): ?><option value="<?= $k['id_kas_bank'] ?>" <?= (int)$v('id_kas_bank') === (int)$k['id_kas_bank'] ? 'selected' : '' ?>><?= e($k['kode_kas_bank'] . ' - ' . $k['nama_kas_bank'] . ' (saldo ' . angka($k['saldo']) . ')') ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-6"><label class="form-label req">Akun beban</label>
    <select name="id_akun" class="form-select" required>
      <?php foreach ($akunBeban as $a): ?><option value="<?= $a['id_akun'] ?>" <?= (int)$v('id_akun') === (int)$a['id_akun'] ? 'selected' : '' ?>><?= e($a['kode_akun'] . ' - ' . $a['nama_akun']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="col-md-4"><label class="form-label req">Tanggal</label><input type="date" name="tanggal" class="form-control" required value="<?= e($v('tanggal_pengeluaran', date('Y-m-d'))) ?>"></div>
  <div class="col-md-4"><label class="form-label req">Nominal (Rp)</label><input type="number" step="0.01" min="0.01" name="nominal" class="form-control" required value="<?= e($v('nominal_pengeluaran')) ?>"></div>
  <div class="col-md-4"><label class="form-label">No. bukti</label><input class="form-control" disabled value="<?= e($row['no_bukti_pengeluaran'] ?? 'Otomatis (BKK-YYYYMM-0001)') ?>"></div>
  <div class="col-12"><label class="form-label">Keterangan</label><input name="keterangan" class="form-control" maxlength="100" value="<?= e($v('keterangan_pengeluaran')) ?>"></div>
  <div class="col-12"><label class="form-label">Bukti transaksi (opsional)</label><input type="file" name="file_bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
    <div class="form-text">JPG, PNG, atau PDF, maksimal 2 MB.<?= !empty($row['file_bukti']) ? ' Bukti saat ini tersimpan; unggah baru untuk menggantinya.' : '' ?></div></div>
</div></div>
<div class="card-footer bg-transparent text-end"><a href="<?= url('/pengeluaran') ?>" class="btn btn-outline-secondary">Batal</a> <button class="btn btn-primary">Simpan</button></div>
</form>
