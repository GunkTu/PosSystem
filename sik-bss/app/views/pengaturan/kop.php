<?php /** @var array $k, $kop */ $v = fn($f, $d = '') => old($f, $k[$f] ?? $d); ?>
<div class="row g-3">
<div class="col-lg-6"><form method="post" enctype="multipart/form-data" class="card"><?= csrf_field() ?>
  <div class="card-body"><div class="row g-3">
    <div class="col-12"><label class="form-label">Mode kop</label>
      <div class="form-check"><input class="form-check-input" type="radio" name="mode_kop" id="mG" value="gambar" <?= $v('mode_kop') === 'gambar' ? 'checked' : '' ?>>
        <label class="form-check-label" for="mG"><b>Kop gambar penuh</b> (memakai kop perusahaan yang sudah ada, JPG/PNG)</label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="mode_kop" id="mS" value="sistem" <?= $v('mode_kop') !== 'gambar' ? 'checked' : '' ?>>
        <label class="form-check-label" for="mS"><b>Kop disusun sistem</b> (logo + teks di bawah)</label></div></div>
    <div class="col-12"><label class="form-label">File kop (JPG/PNG)</label><input type="file" name="header_image" class="form-control" accept=".jpg,.jpeg,.png">
      <div class="form-text">Maks 2 MB, disarankan lebar ≥ 1600 px. Kop berupa PDF? Konversi dulu ke JPG/PNG.</div>
      <?php if ($k['header_image_path']): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="hapus_header" value="1" id="hh"><label class="form-check-label" for="hh">Hapus file kop yang tersimpan</label></div><?php endif; ?></div>
    <hr class="my-1">
    <div class="col-12"><label class="form-label">Logo</label><input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png">
      <?php if ($k['logo_path']): ?><div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="hapus_logo" value="1" id="hl"><label class="form-check-label" for="hl">Hapus logo yang tersimpan</label></div><?php endif; ?></div>
    <div class="col-12"><label class="form-label req">Nama perusahaan</label><input name="nama_perusahaan" class="form-control" maxlength="100" required value="<?= e($v('nama_perusahaan')) ?>"></div>
    <div class="col-12"><label class="form-label">Alamat</label><input name="alamat" class="form-control" maxlength="255" value="<?= e($v('alamat')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Telepon</label><input name="telepon" class="form-control" maxlength="30" value="<?= e($v('telepon')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="50" value="<?= e($v('email')) ?>"></div>
    <div class="col-md-4"><label class="form-label">NPWP</label><input name="npwp" class="form-control" maxlength="30" value="<?= e($v('npwp')) ?>"></div>
    <hr class="my-1">
    <div class="col-md-4"><label class="form-label">Bank</label><input name="bank_nama" class="form-control" maxlength="50" value="<?= e($v('bank_nama')) ?>"></div>
    <div class="col-md-4"><label class="form-label">No. rekening</label><input name="bank_no_rekening" class="form-control" maxlength="30" value="<?= e($v('bank_no_rekening')) ?>"></div>
    <div class="col-md-4"><label class="form-label">Atas nama</label><input name="bank_atas_nama" class="form-control" maxlength="100" value="<?= e($v('bank_atas_nama')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Nama penandatangan</label><input name="ttd_nama" class="form-control" maxlength="100" value="<?= e($v('ttd_nama')) ?>"></div>
    <div class="col-md-6"><label class="form-label">Jabatan</label><input name="ttd_jabatan" class="form-control" maxlength="50" value="<?= e($v('ttd_jabatan')) ?>"></div>
    <div class="col-12"><label class="form-label">Catatan kaki invoice</label><input name="catatan_invoice" class="form-control" maxlength="255" value="<?= e($v('catatan_invoice')) ?>"></div>
    <div class="col-md-4"><label class="form-label req">Tarif PPN default (%)</label><input type="number" step="0.01" min="0" max="100" name="ppn_default" class="form-control" required value="<?= e($v('ppn_default', 11)) ?>"></div>
  </div></div>
  <div class="card-footer bg-transparent text-end"><button class="btn btn-primary">Simpan</button></div>
</form></div>
<div class="col-lg-6"><div class="card"><div class="card-body">
  <div class="card-title mb-3">Pratinjau kop (tersimpan)</div>
  <div style="background:#fff;border:1px solid #e2e7ef;padding:14px"><?php partial('kop', ['kop' => $kop]); ?></div>
  <?php if (!$k['logo_path'] && !$k['header_image_path'] && $k['nama_perusahaan'] === 'Nama Perusahaan'): ?>
    <div class="form-text mt-2">Kop masih berupa placeholder. Isi data di samping atau unggah file kop Anda.</div><?php endif; ?>
</div></div></div>
</div>
